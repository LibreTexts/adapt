<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\Exceptions\Handler;
use App\Helpers\DynamicRubric;
use App\Helpers\RubricHelper;
use App\Question;
use App\RubricPointsBreakdown;
use App\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RubricPointsBreakdownController extends Controller
{
    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param RubricPointsBreakdown $rubricPointsBreakdown
     * @return array
     * @throws Exception
     */
    public function existsByAssignmentQuestion(Assignment            $assignment,
                                               Question              $question,
                                               RubricPointsBreakdown $rubricPointsBreakdown): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('existsByAssignmentQuestion', [$rubricPointsBreakdown, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $rubric_points_breakdown_exists = $rubricPointsBreakdown->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->exists();
            $response['rubric_points_breakdown_exists'] = $rubric_points_breakdown_exists;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error checking whether the rubric points breakdown exists.  Please try again or contact us for assistance.";

        }
        return $response;

    }


    /**
     * The student's total from the applied criteria, as the score was calculated: the cap (question points less the
     * auto-graded score) and the late penalty, so the student view's total matches their score.
     *
     * @param Assignment $assignment
     * @param Question $question
     * @param User $user
     * @param object $assignment_question
     * @param string $rubric
     * @param array $dynamic_rubric_state
     * @return array
     */
    private function _dynamicRubricScore(Assignment $assignment, Question $question, User $user, $assignment_question,
                                         string     $rubric, array $dynamic_rubric_state): array
    {
        $auto_graded_score = DB::table('submissions')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->where('user_id', $user->id)
            ->value('score');
        $late_penalty_percent = DB::table('submission_files')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->where('user_id', $user->id)
            ->where('type', '<>', 'a')
            ->value('applied_late_penalty');
        $cap = DynamicRubric::cap($assignment_question->points, $auto_graded_score);
        $score = DynamicRubric::score(DynamicRubric::decode($rubric),
            $dynamic_rubric_state['applied_item_ids'],
            $dynamic_rubric_state['adjustment'],
            $cap,
            $late_penalty_percent);
        return ['raw' => $score['raw'],
            'final' => $score['final'],
            'out_of' => round($cap, 2),
            'capped' => $score['raw'] > $cap + 0.005,
            'late_penalty_percent' => (float)($late_penalty_percent ?? 0),
            'has_override' => DB::table('submission_score_overrides')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->where('user_id', $user->id)
                ->exists()];
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param User $user
     * @param RubricPointsBreakdown $rubricPointsBreakdown
     * @return array
     * @throws Exception
     */
    public function getByAssignmentQuestionUser(Assignment            $assignment,
                                                Question              $question,
                                                User                  $user,
                                                RubricPointsBreakdown $rubricPointsBreakdown)
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('getByAssignmentQuestionUser', [$rubricPointsBreakdown, $assignment, $user]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $assignment_question = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();
            $rubric = RubricHelper::assignmentQuestionRubric($assignment_question, $question->rubric);
            $is_dynamic = DynamicRubric::isDynamic($rubric);
            $dynamic_rubric_state = ['exists' => false, 'applied_item_ids' => [], 'adjustment' => 0, 'needs_review' => false];
            if (!$rubric) {
                $rubric_points_breakdown = [];
                $rubric_points_breakdown_exists = false;
                $rubric_shown = false;
            } else {
                try {
                    $rubric_shown = json_decode($rubric, 1)['rubric_shown'] ?: false;
                } catch (Exception $e){
                    $rubric_shown = false;
                }
                //a student whose score was set directly by an override sees that score, not the rubric
                if ((int)Auth::user()->role === 3
                    && DB::table('submission_score_overrides')
                        ->where('assignment_id', $assignment->id)
                        ->where('question_id', $question->id)
                        ->where('user_id', $user->id)
                        ->exists()) {
                    $rubric_shown = false;
                }
                $rubric_points_breakdown = $rubricPointsBreakdown->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->where('user_id', $user->id)
                    ->first();
                //students only see their own rubric results once scores are released and the rubric is shown
                $results_visible = (int)Auth::user()->role !== 3 || ($assignment->show_scores && $rubric_shown);
                if (!$results_visible) {
                    $rubric_points_breakdown = null;
                }
                if ($rubric_points_breakdown && $is_dynamic) {
                    $dynamic_rubric_state = ['exists' => true,
                        'applied_item_ids' => $rubric_points_breakdown->applied_item_ids !== null
                            ? array_map('strval', json_decode($rubric_points_breakdown->applied_item_ids, true) ?: [])
                            : [],
                        'adjustment' => (float)$rubric_points_breakdown->adjustment,
                        'needs_review' => (bool)$rubric_points_breakdown->needs_review];
                    $dynamic_rubric_state['score'] = $this->_dynamicRubricScore($assignment, $question, $user,
                        $assignment_question, $rubric, $dynamic_rubric_state);
                }
                $rubric_points_breakdown_exists = true;
                if (!$rubric_points_breakdown) {
                    $rubric_points_breakdown_exists = false;
                    $rubric_points_breakdown = json_decode($rubric, 1);
                    foreach ($rubric_points_breakdown['rubric_items'] as &$value) {
                        $value['points'] = '';
                        $value['percentage'] = '';
                    }
                    $rubric_points_breakdown = json_encode($rubric_points_breakdown);
                } else {
                    $rubric_points_breakdown = $rubric_points_breakdown->points_breakdown;
                }

            }
            $response['rubric_points_breakdown'] = $rubric_points_breakdown;
            $response['rubric_points_breakdown_exists'] = $rubric_points_breakdown_exists;
            $response['rubric_shown'] = $rubric_shown;
            $response['is_dynamic'] = $is_dynamic;
            $response['dynamic_rubric_state'] = $dynamic_rubric_state;
            $response['scores_released'] = (bool)$assignment->show_scores;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the rubric points breakdown.  Please try again or contact us for assistance.";

        }
        return $response;

    }
}
