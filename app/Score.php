<?php

namespace App;


use App\Helpers\Helper;
use App\Http\Requests\UpdateScoresRequest;
use App\Jobs\ProcessPassBackByUserIdAndAssignment;
use Exception;
use Faker\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Traits\Statistics;

use Carbon\Carbon;
use Throwable;

class Score extends Model
{
    use Statistics;

    protected $fillable = ['user_id', 'assignment_id', 'score'];

    /**
     * @param UpdateScoresRequest $request
     * @param Assignment $assignment
     * @param Question $question
     * @param $model
     * @return array
     * @throws Exception|Throwable
     */
    public function handleUpdateScores(UpdateScoresRequest $request,
                                       Assignment          $assignment,
                                       Question            $question,
                                                           $model): array
    {

        $response['type'] = 'error';
        $submission = new Submission();
        $authorized = Gate::inspect('updateScores', [$submission, $assignment, $question, $request->user_ids]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $data = $request->validated();
        try {
            $apply_to = $request->apply_to;
            $new_score = $data['new_score'];
            $submissions = $apply_to
                ? $model->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->whereIn('user_id', $request->user_ids)
                    ->get()
                : $model->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->whereNotIn('user_id', $request->user_ids)
                    ->get();

            $lti_launches_by_user_id = $assignment->ltiLaunchesByUserId();
            $assignment_scores = Score::where('assignment_id', $assignment->id)->get();
            $assignment_scores_by_user_id = [];
            foreach ($assignment_scores as $assignment_score) {
                $assignment_scores_by_user_id[$assignment_score->user_id] = $assignment_score;
            }
            DB::beginTransaction();
            foreach ($submissions as $submission) {
                $adjustment = $new_score - $submission->score;
                $submission->score = $new_score;
                $submission->save();
                if (isset($assignment_scores_by_user_id[$submission->user_id])) {
                    $assignment_score = $assignment_scores_by_user_id[$submission->user_id];
                } else {
                    $assignment_score = new Score();
                    $assignment_score->user_id = $submission->user_id;
                    $assignment_score->assignment_id = $assignment->id;
                }
                $assignment_score->score += $adjustment;
                $assignment_score->save();
            }
            if ($assignment->course->lms) {
                DB::table('passback_by_assignments')->insert(
                    ['assignment_id' => $assignment->id,
                        'status' => 'manual_pending',
                        'created_at' => now(),
                        'updated_at' => now()]);
            }
            DB::commit();
            $response['type'] = 'success';
            $response['message'] = 'The scores have been updated.';
            if ($assignment->course->lms) {
                $response['message'] .= '  Please allow 5-10 minutes for the new scores to appear in your LMS.';
            }

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the scores.  Please refresh the page and try again or contact us for assistance.";
        }
        return $response;
    }


    public function updateAssignmentScore(int  $student_user_id,
                                          int  $assignment_id,
                                          bool $passback_grade)
    {
        if (User::find($student_user_id)->role === 2) {
            return;
        }
        $assignment = Assignment::find($assignment_id);
        if ($assignment->mastery_retake_enabled) {
            $best_completed_score = MasteryAssignmentAttempt::where('assignment_id', $assignment_id)
                ->where('user_id', $student_user_id)
                ->whereIn('status', [
                    MasteryAssignmentAttempt::STATUS_COMPLETED,
                    MasteryAssignmentAttempt::STATUS_MASTERED
                ])
                ->max('score');

            // Incomplete whole-assignment attempts retain feedback without changing the saved grade.
            // without changing the assignment or LMS grade.
            if ($best_completed_score === null) {
                return;
            }

            $assignment_score = self::firstOrNew([
                'user_id' => $student_user_id,
                'assignment_id' => $assignment_id
            ]);
            $assignment_score->score = $best_completed_score;
            $assignment_score->save();

            if ($passback_grade) {
                $lti_launch = DB::table('lti_launches')
                    ->where('assignment_id', $assignment->id)
                    ->where('user_id', $student_user_id)
                    ->first();
                if ($lti_launch) {
                    $ltiGradePassBack = new LtiGradePassback();
                    $ltiGradePassBack->initPassBackByUserIdAndAssignmentId($best_completed_score, $lti_launch);
                }
            }
            return;
        }
        //initialize
        $assignment_score = 0;
        $submission_scores_by_question_id = [];
        $submission_file_scores_by_question_id = [];
        $submission_score_overrides_by_question_id = [];
        $question_ids = $assignment->questions->pluck('id')->toArray();
        $submissions = DB::table('submissions')
            ->where('assignment_id', $assignment_id)
            ->where('user_id', $student_user_id)
            ->get();
        foreach ($submissions as $submission) {
            $submission_scores_by_question_id[$submission->question_id] = $submission->score ?? 0;
        }
        $submission_files = DB::table('submission_files')
            ->where('assignment_id', $assignment_id)
            ->whereIn('type', ['q', 'text', 'audio', 'discuss_it', 'no upload', 'forge']) //'q', 'a', or 0
            ->whereIn('question_id', $question_ids)
            ->where('user_id', $student_user_id)
            ->get();
        foreach ($submission_files as $submission_file) {
            $submission_file_scores_by_question_id[$submission_file->question_id] = $submission_file->score ?? 0;
        }
        $submission_score_overrides = DB::table('submission_score_overrides')
            ->where('user_id', $student_user_id)
            ->where('assignment_id', $assignment_id)
            ->get();
        foreach ($submission_score_overrides as $submission_score_override) {
            $submission_score_overrides_by_question_id[$submission_score_override->question_id] = $submission_score_override->score;
        }
        foreach ($question_ids as $question_id) {
            if (isset($submission_score_overrides_by_question_id[$question_id])) {
                $assignment_score += $submission_score_overrides_by_question_id[$question_id];
            } else {
                $assignment_score += $submission_scores_by_question_id[$question_id] ?? 0;
                $assignment_score += $submission_file_scores_by_question_id[$question_id] ?? 0;

            }
        }
        DB::table('scores')
            ->updateOrInsert(
                ['user_id' => $student_user_id, 'assignment_id' => $assignment_id],
                ['score' => $assignment_score, 'updated_at' => Carbon::now()]);

        if ($passback_grade) {
            $lti_launch = DB::table('lti_launches')
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $student_user_id)
                ->first();
            if ($lti_launch) {
                $ltiGradePassBack = new LtiGradePassback();
                $ltiGradePassBack->initPassBackByUserIdAndAssignmentId($assignment_score, $lti_launch);
            }
        }
    }

    /**
     * @param Course $course
     * @param User $user
     * @return array[]
     */
    public function getUserScoresByAssignment(Course $course, User $user): array
    {

        $assignments = $course->assignments;
        $assignment_ids = [];
        $scores_released = [];
        $scores_by_assignment = [];
        $z_scores_by_assignment = [];

//initialize
        foreach ($assignments as $assignment) {
            $assignment_ids[] = $assignment->id;
            $scores_released[$assignment->id] = $assignment->show_scores;
            $z_scores_by_assignment[$assignment->id] = 'N/A';
            $scores_by_assignment[$assignment->id] = ($assignment->show_scores) ? 0 : 'Not yet released';
        }
        $scores = DB::table('scores')
            ->whereIn('assignment_id', $assignment_ids)
            ->where('user_id', $user->id)
            ->get();


        $mean_and_std_dev_by_assignment = $this->getMeanAndStdDevByColumn('scores', 'assignment_id', $assignment_ids, 'assignment_id');


//show the score for points only if the scores have been released
//otherwise show the score

        foreach ($scores as $key => $value) {
            $assignment_id = $value->assignment_id;
            $score = $value->score;
            if ($scores_released[$assignment_id]) {
                $scores_by_assignment[$assignment_id] = $score;
                $z_scores_by_assignment[$assignment_id] = $this->computeZScore($score, $mean_and_std_dev_by_assignment[$assignment_id]);
            }
        }


        return [$scores_by_assignment, $z_scores_by_assignment];

    }

    /**
     * @param array $assignment_question_scores_info
     * @return int|mixed
     */
    public function getAssignmentScoreFromQuestions(array $assignment_question_scores_info)
    {

        $assignment_score_from_questions = 0;
        //get the assignment points for the questions
        foreach ($assignment_question_scores_info as $score) {
            $question_points = $score['question'] ?? 0;
            $assignment_score_from_questions = $assignment_score_from_questions + $question_points;
        }

        return $assignment_score_from_questions;
    }

    /**
     * @param Course $course
     * @param int $sectionId
     * @return array
     */
    public function getCourseScores(Course $course, int $sectionId): array
    {
        $enrollment = new Enrollment();
        $assignmentGroup = new AssignmentGroup();
        $assignment = new Assignment();
//get all user_ids for the user enrolled in the course
        $enrolled_users_by_id = [];


        $role = Auth::user() ? Auth::user()->role : 2;
        $viewable_users = $enrollment->getEnrolledUsersByRoleCourseSection($role, $course, $sectionId);
        $viewable_users_by_id = [];

        foreach ($viewable_users as $viewable_user) {
            $viewable_users_by_id[] = $viewable_user->id;
        }
        $enrolled_users = $course->enrolledUsers;
        $course_section_enrollments_by_user = $course->sectionEnrollmentsByUser();

        $ferpa_mode = (int)request()->cookie('ferpa_mode') === 1 && Auth::user()->id === 5;
        $faker = Factory::create();
        foreach ($enrolled_users as $user) {
            $first_name = $ferpa_mode ? $faker->firstName : $user->first_name;
            $last_name = $ferpa_mode ? $faker->lastName : $user->last_name;
            $student_id = $ferpa_mode ? rand(pow(10, 4), pow(10, 4) - 1) : $user->student_id;
            $email = $ferpa_mode ? $faker->email : $user->email;
            $enrolled_users_by_id[$user->id] = [
                'name' => "$last_name, $first_name",
                'email' => $email,
                'crn' => $course_section_enrollments_by_user[$user->id]['crn'],
                'first_name' => $first_name,
                'last_name' => $last_name,
                'student_id' => $student_id,
                'course_section' => $course_section_enrollments_by_user[$user->id]['course_section']];
        }

//get all assignments in the course
        $assignments = $course->assignments->sortBy('due');
        if ($assignments->isEmpty()) {
            return ['hasAssignments' => false];
        }
        $assignments = $assignments->sortBy(function ($assignment) {
            return [
                $assignment->assignment_group_id,
                $assignment->order
            ];
        });


        $sections_info = ($role === 2) ? $course->sections : $course->graderSections();
        $sections = [];
        foreach ($sections_info as $key => $section) {
            $sections[] = ['name' => $section->name, 'id' => $section->id];
        }
        $assignment_ids = $assignment->getAssignmentIds($assignments);
        $total_points_by_assignment_id = $assignment->getTotalPointsByAssignmentId($assignments, $assignment_ids);


        $assignment_groups = $assignmentGroup->summaryFromAssignments($role, $assignments, $total_points_by_assignment_id);


        $scores = $course->scores;

        $extensions = [];
        foreach ($course->extensions as $value) {
            $extensions[$value->user_id][$value->assignment_id] = 'Extension';
        }

        [$rows, $fields, $download_rows, $download_fields, $extra_credit_assignment_id, $weighted_score_assignment_id, $z_score_assignment_id, $letter_grade_assignment_id, $sum_of_scores_by_user_and_assignment_group] = $this->processAllScoreInfo($course, $assignments, $assignment_ids, $scores, $extensions, $enrolled_users, $enrolled_users_by_id, $total_points_by_assignment_id);

        $viewable_rows = [];

        foreach ($rows as $key => $row) {
            if (in_array($row['userId'], $viewable_users_by_id)) {
                $viewable_rows[] = $row;
            }
        }
        $score_info_by_assignment_group = [];
        foreach ($enrolled_users_by_id as $user_id => $user) {
            $score_info_by_assignment_group[$user_id] = ['name' => $user['name'],
                'user_id' => $user_id,
                'email' => $user['email']
            ];
            foreach ($assignment_groups as $assignment_group_id => $assignment_group) {
                $score_info_by_assignment_group[$user_id][$assignment_group['assignment_group']] = $sum_of_scores_by_user_and_assignment_group[$user_id][$assignment_group_id] ?? '-';
            }
        }
        $score_info_by_assignment_group = array_values($score_info_by_assignment_group);
        if ($score_info_by_assignment_group) {
            usort($score_info_by_assignment_group, function ($a, $b) {
                return $a['name'] <=> $b['name'];
            });
        }
        $score_info_by_assignment_group_fields = ['name', 'email'];
        foreach ($assignment_groups as $assignment_group) {
            $score_info_by_assignment_group_fields[] = $assignment_group['assignment_group'];
        }

        return ['hasAssignments' => true,
            'viewable_rows' => $viewable_rows,
            'fields' => $fields,
            'download_rows' => $download_rows,
            'download_fields' => $download_fields,
            'sections' => $sections,
            'table' => ['rows' => $viewable_rows,
                'fields' => $fields,
                'hasAssignments' => true],
            'extra_credit_assignment_id' => $extra_credit_assignment_id,
            'weighted_score_assignment_id' => $weighted_score_assignment_id,//needed for testing...
            'z_score_assignment_id' => $z_score_assignment_id,
            'letter_grade_assignment_id' => $letter_grade_assignment_id,
            'assignment_groups' => array_values($assignment_groups),
            'score_info_by_assignment_group' => $score_info_by_assignment_group,
            'score_info_by_assignment_group_fields' => $score_info_by_assignment_group_fields];

    }

    /**
     *
     * The final average is computed using assignments that have "include_in_weighted_average" set to true.
     * Two items are updated with this: the counts (getAssignmentGroupWeights)
     * and whether to contribute the score: processAllScoreInfo
     * @param $course
     * @param $assignments
     * @param $assignment_ids
     * @param $scores
     * @param $extensions
     * @param $enrolled_users
     * @param $enrolled_users_by_id
     * @param $total_points_by_assignment_id
     * @return array
     */
    function processAllScoreInfo($course,
                                 $assignments,
                                 $assignment_ids,
                                 $scores, $extensions,
                                 $enrolled_users,
                                 $enrolled_users_by_id,
                                 $total_points_by_assignment_id): array
    {

        $include_in_weighted_average_by_assignment_id_and_user_id = [];
        $assign_tos = $course->assignTosByAssignmentAndUser();
        $enrolled_user_ids = array_keys($enrolled_users_by_id);

        foreach ($assignments as $assignment) {
            foreach ($enrolled_user_ids as $user_id) {
                $include_in_weighted_average_by_assignment_id_and_user_id[$assignment->id][$user_id] = $assignment->include_in_weighted_average * isset($assign_tos[$assignment->id]) && in_array($user_id, $assign_tos[$assignment->id]);
                if (auth()->user()->role === 3) {
                    $include_in_weighted_average_by_assignment_id_and_user_id[$assignment->id][$user_id] = $include_in_weighted_average_by_assignment_id_and_user_id[$assignment->id][$user_id] * $assignment->show_scores;
                }
            }
        }

        [$assignment_group_weights_info, $assignment_groups_by_assignment_id] = $this->getAssignmentGroupWeights($enrolled_user_ids, $course->id, $include_in_weighted_average_by_assignment_id_and_user_id);
        [$scores_by_user_and_assignment, $proportion_scores_by_user_and_assignment_group, $sum_of_scores_by_user_and_assignment_group] = $this->getScoresByUserIdAndAssignment($course, $scores, $assignment_groups_by_assignment_id, $total_points_by_assignment_id, $include_in_weighted_average_by_assignment_id_and_user_id);
        $final_weighted_scores_and_letter_grades = $this->getFinalWeightedScoresAndLetterGrades($course, $enrolled_users, $proportion_scores_by_user_and_assignment_group, $assignment_group_weights_info);

        [$rows, $fields, $download_rows, $download_fields, $extra_credit_assignment_id, $weighted_score_assignment_id, $z_score_assignment_id, $letter_grade_assignment_id] = $this->getFinalTableInfo(
            $course,
            $assignment_ids,
            $enrolled_users_by_id,
            $assignments,
            $extensions,
            $final_weighted_scores_and_letter_grades['extra_credit'],
            $final_weighted_scores_and_letter_grades['final_weighted_scores'],
            $final_weighted_scores_and_letter_grades['letter_grades'],
            $final_weighted_scores_and_letter_grades['final_weighted_scores_without_extra_credit'],
            $scores_by_user_and_assignment,
            $total_points_by_assignment_id);

        return [$rows, $fields, $download_rows, $download_fields, $extra_credit_assignment_id, $weighted_score_assignment_id, $z_score_assignment_id, $letter_grade_assignment_id, $sum_of_scores_by_user_and_assignment_group];


    }

    /**
     * @param $enrolled_user_ids
     * @param int $course_id
     * @param $include_in_weighted_average_by_assignment_id_and_user_id
     * @return array[]
     */
    public function getAssignmentGroupWeights($enrolled_user_ids, int $course_id, $include_in_weighted_average_by_assignment_id_and_user_id)
    {
        $assignment_groups_by_assignment_id = [];
        $assignment_group_weights_info = [];

        $assignment_group_weights = DB::table('assignments')
            ->join('assignment_group_weights', 'assignments.assignment_group_id', '=', 'assignment_group_weights.assignment_group_id')
            ->where('assignment_group_weights.course_id', $course_id)
            ->where('assignments.course_id', $course_id)
            ->select('assignments.id', 'assignments.assignment_group_id', 'assignment_group_weights.assignment_group_weight')
            ->get();
//create arrays for assignment_group_ids, weights, and counts


        foreach ($assignment_group_weights as $key => $value) {
            foreach ($enrolled_user_ids as $user_id) {
                $assignment_groups_by_assignment_id[$value->id] = $value->assignment_group_id;
                if (!isset($assignment_group_weights_info[$value->assignment_group_id])) {
                    $assignment_group_weights_info[$value->assignment_group_id] = [];
                }
                if (isset($assignment_group_weights_info[$value->assignment_group_id][$user_id])) {
                    $assignment_group_weights_info[$value->assignment_group_id][$user_id]['count'] = $include_in_weighted_average_by_assignment_id_and_user_id[$value->id][$user_id]
                        ? $assignment_group_weights_info[$value->assignment_group_id][$user_id]['count'] + 1
                        : $assignment_group_weights_info[$value->assignment_group_id][$user_id]['count'];
                } else {
                    $assignment_group_weights_info[$value->assignment_group_id][$user_id] = [];
                    $assignment_group_weights_info[$value->assignment_group_id][$user_id]['weight'] = $value->assignment_group_weight;
                    $assignment_group_weights_info[$value->assignment_group_id][$user_id]['count'] = $include_in_weighted_average_by_assignment_id_and_user_id[$value->id][$user_id] ? 1 : 0;
                }
            }
        }
        return [$assignment_group_weights_info, $assignment_groups_by_assignment_id];
    }

    /**
     * @param Course $course
     * @param $scores
     * @param array $assignment_groups_by_assignment_id
     * @param array $total_points_by_assignment_id
     * @param array $include_in_weighted_average_by_assignment_id_and_user_id
     * @return array[]
     */
    public function getScoresByUserIdAndAssignment(Course $course,
                                                          $scores,
                                                   array  $assignment_groups_by_assignment_id,
                                                   array  $total_points_by_assignment_id,
                                                   array  $include_in_weighted_average_by_assignment_id_and_user_id): array

    {
        //organize the scores by user_id and assignment
        $scores_by_user_and_assignment = [];
        $proportion_scores_by_user_and_assignment_group = [];
        $sum_of_scores_by_user_and_assignment_group = [];
        $fake_student_ids = $course->fakeStudentIds();
        $instructor_id = $course->user_id;
        foreach ($scores as $score) {
            $user_id = $score->user_id;
            if (in_array($user_id, $fake_student_ids) || $user_id === $instructor_id) {
                continue;
            }
            $assignment_id = $score->assignment_id;
            $scores_by_user_and_assignment[$user_id][$assignment_id] = Helper::removeZerosAfterDecimal(Round($score->score, 2));
            $group_id = $assignment_groups_by_assignment_id[$assignment_id];
            //init if needed
            $proportion_scores_by_user_and_assignment_group[$user_id][$group_id] = $proportion_scores_by_user_and_assignment_group[$user_id][$group_id] ?? 0;
            $sum_of_scores_by_user_and_assignment_group[$user_id][$group_id] = $sum_of_scores_by_user_and_assignment_group[$user_id][$group_id] ?? 0;

            if (!isset($total_points_by_assignment_id[$assignment_id])) {
                $total_points_by_assignment_id[$assignment_id] = 0; //if they have an ADAPT assignment without questions
            }
            $score_as_proportion = (($total_points_by_assignment_id[$assignment_id]) <= 0)//total_points_by_assignment can be 0.00
                ? 0
                : $score->score / $total_points_by_assignment_id[$assignment_id];

            $include_in_weighted_average = $include_in_weighted_average_by_assignment_id_and_user_id[$assignment_id][$user_id] ?? false;
            $proportion_scores_by_user_and_assignment_group[$user_id][$group_id] += $include_in_weighted_average
                ? $score_as_proportion
                : 0;

            $sum_of_scores_by_user_and_assignment_group[$user_id][$group_id] += (($total_points_by_assignment_id[$assignment_id]) <= 0)//total_points_by_assignment can be 0.00
                ? 0
                : $score->score * $include_in_weighted_average;
            $sum_of_scores_by_user_and_assignment_group[$user_id][$group_id] = Helper::removeZerosAfterDecimal(Round($sum_of_scores_by_user_and_assignment_group[$user_id][$group_id], 2));
        }
        return [$scores_by_user_and_assignment, $proportion_scores_by_user_and_assignment_group, $sum_of_scores_by_user_and_assignment_group];
    }

    public function getFinalWeightedScoresAndLetterGrades(Course     $course,
                                                          Collection $enrolled_users,
                                                          array      $proportion_scores_by_user_and_assignment_group,
                                                          array      $assignment_group_weights_info)
    {
        $letter_grades = explode(',', $course->finalGrades->letter_grades);
        $letter_grades_array = [];

        for ($i = 0; $i < count($letter_grades) / 2; $i++) {
            $letter_grades_array[] = ['min_score' => $letter_grades[2 * $i], 'letter_grade' => $letter_grades[2 * $i + 1]];
        }

        $final_weighted_scores = [];
        $letter_grades = [];
        $extra_credit = [];
        foreach ($course->extraCredits as $key => $value) {
            $extra_credit[$value->user_id] = $value->extra_credit;
        }


        $extra_credit_group_id = DB::table('assignment_groups')->where('assignment_group', 'Extra Credit')
            ->pluck('id')
            ->first();
        $final_weighted_scores_without_extra_credit = [];

        foreach ($enrolled_users as $key => $user) {
            $final_weighted_scores[$user->id] = 0;
            $final_weighted_scores_without_extra_credit[$user->id] = 0;
            if (isset($proportion_scores_by_user_and_assignment_group[$user->id])) {

                foreach ($proportion_scores_by_user_and_assignment_group[$user->id] as $group_id => $group_score) {
                    $final_weighted_scores[$user->id] += $assignment_group_weights_info[$group_id][$user->id]['count']
                        ? $assignment_group_weights_info[$group_id][$user->id]['weight'] * $group_score / $assignment_group_weights_info[$group_id][$user->id]['count']
                        : 0;

                    $final_weighted_scores_without_extra_credit[$user->id] += $assignment_group_weights_info[$group_id][$user->id]['count'] && ($group_id !== $extra_credit_group_id)
                        ? $assignment_group_weights_info[$group_id][$user->id]['weight'] * $group_score / $assignment_group_weights_info[$group_id][$user->id]['count']
                        : 0;

                }
            }
            if (!isset($extra_credit[$user->id])) {
                $extra_credit[$user->id] = 0;
            }

            $final_weighted_scores[$user->id] += $extra_credit[$user->id];

        }

        foreach ($enrolled_users as $key => $user) {
            $score = Round($final_weighted_scores[$user->id], 2);
            $final_weighted_scores_without_extra_credit[$user->id] = Round($final_weighted_scores_without_extra_credit[$user->id], 2);
            $final_weighted_scores[$user->id] = $score . '%';
            $letter_grades[$user->id] = $this->getLetterGradeBasedOnScore($score, $letter_grades_array, $course->finalGrades->round_scores);
        }

        return ['final_weighted_scores' => $final_weighted_scores,
            'final_weighted_scores_without_extra_credit' => $final_weighted_scores_without_extra_credit,
            'letter_grades' => $letter_grades,
            'extra_credit' => $extra_credit];
    }

    public function getLetterGradeBasedOnScore($score, $letter_grades_array, $round_scores)
    {
        foreach ($letter_grades_array as $letter_grade_key => $letter_grade_value) {
            $score = $round_scores ? Round($score, 0) : $score;
            if ($score >= $letter_grade_value['min_score']) {
                return $letter_grade_value['letter_grade'];
            }
        }
        return 'Letter grade error';
    }

    /**
     * @param Course $course
     * @param array $assignment_ids
     * @param array $enrolled_users
     * @param $assignments
     * @param array $extensions
     * @param array $extra_credit
     * @param array $final_weighted_scores
     * @param array $letter_grades
     * @param array $final_weighted_scores_without_extra_credit
     * @param array $scores_by_user_and_assignment
     * @param array $total_points_by_assignment_id
     * @return array
     */
    public function getFinalTableInfo(Course $course,
                                      array  $assignment_ids,
                                      array  $enrolled_users,
                                             $assignments,
                                      array  $extensions,
                                      array  $extra_credit,
                                      array  $final_weighted_scores,
                                      array  $letter_grades,
                                      array  $final_weighted_scores_without_extra_credit,
                                      array  $scores_by_user_and_assignment,
                                      array  $total_points_by_assignment_id): array
    {
        {
            $extra_credit_assignment_id = max($assignment_ids) + 1;
            $weighted_score_assignment_id = $extra_credit_assignment_id + 1;
            $z_score_assignment_id = $weighted_score_assignment_id + 1;
            $letter_grade_assignment_id = $z_score_assignment_id++;

            $course_average = count($final_weighted_scores_without_extra_credit) ? array_sum($final_weighted_scores_without_extra_credit) / count($final_weighted_scores_without_extra_credit) : 0;
            $course_std_dev = $this->stats_standard_deviation($final_weighted_scores_without_extra_credit);
            $mean_and_std_dev_info = ['average' => $course_average, 'std_dev' => $course_std_dev];


            //now fill in the actual scores
            $rows = [];
            $download_rows = [];
            $download_fields = [];
            $assignment_scores = [];
            $is_student = Auth::user() && Auth::user()->role === 3;
            foreach ($assignments as $assignment) {
                $assignment_scores[$assignment->id] = [];
            }

            foreach ($enrolled_users as $user_id => $user_info) {
                $columns = [];
                if (!$is_student) {
                    $download_row_data = [
                        $user_info['first_name'],
                        $user_info['last_name'],
                        $user_info['course_section'],
                        $user_info['crn'],
                        $course->term,
                        $user_info['email'],
                        $user_info['student_id']
                    ];
                }

                foreach ($assignments as $assignment) {
                    if (isset($scores_by_user_and_assignment[$user_id][$assignment->id])) {
                        $assignment_scores[$assignment->id][] = $scores_by_user_and_assignment[$user_id][$assignment->id];
                    }
                    $default_score = '-';
                    $score = $scores_by_user_and_assignment[$user_id][$assignment->id] ?? $default_score;
                    if (isset($extensions[$user_id][$assignment->id])) {
                        $score .= ' (E)';
                    }
                    $columns[$assignment->id] = $score;
                    if (!$is_student) {
                        $download_row_data[] = str_replace(' (E)', '', $score);//get rid of the extension info
                    }
                }


                $columns[$extra_credit_assignment_id] = $extra_credit[$user_id];
                $columns[$weighted_score_assignment_id] = $final_weighted_scores[$user_id];
                $columns[$z_score_assignment_id] = $this->computeZScore($final_weighted_scores_without_extra_credit[$user_id], $mean_and_std_dev_info);

                $columns[$letter_grade_assignment_id] = $letter_grades[$user_id];
                if (!$is_student) {
                    $download_row_data[] = $extra_credit[$user_id];
                    $download_row_data[] = $final_weighted_scores[$user_id];
                    $download_row_data[] = $columns[$z_score_assignment_id];
                    $download_row_data[] = $letter_grades[$user_id];
                }

                $columns['name'] = $user_info['name'];
                $columns['email'] = $user_info['email'];
                $columns['userId'] = $user_id;
                if (!$is_student) {
                    $download_rows[] = $download_row_data;
                }
                $rows[] = $columns;
            }

            $fields = [['key' => 'name',
                'label' => 'Name',
                'sortable' => true,
                'stickyColumn' => true,
                'isRowHeader' => true],
                ['key' => 'email',
                    'label' => 'Email',
                    'sortable' => true,
                    'stickyColumn' => false]];
            foreach (['First Name', 'Last Name', 'Course - Section', 'CRN', 'Term', 'Email', 'Student Id'] as $value) {
                $download_fields[] = $value;
            }
            $reserved_names = ['Term', 'CRN', 'First Name', 'Last Name', 'Email', 'Student Id', 'Course - Section',
                'Extra Credit', 'Weighted Score', 'Z-Score', 'Letter Grade'];

            foreach ($assignments as $assignment) {
                $mean = count($assignment_scores[$assignment->id]) ? Round(array_sum($assignment_scores[$assignment->id]) / count($assignment_scores[$assignment->id]), 2) : 'N/A';
                $points = Helper::removeZerosAfterDecimal(Round(0 + ($total_points_by_assignment_id[$assignment->id] ?? 0), 2));
                $field = ['key' => "$assignment->id",
                    'name_only' => $assignment->name,
                    'assignment_id' => $assignment->id,
                    'points' => $points,
                    'mean' => $mean,
                    'not_included' => !$assignment->include_in_weighted_average,
                    'sortable' => true,
                    'tdClass' => 'text-center'];
                if (in_array($assignment->name, $reserved_names)) {
                    $assignment->name .= ' ';
                }
                $download_fields[] = $assignment->name;

                $fields[] = $field;
            }
            $fields[] = ['key' => "$extra_credit_assignment_id",
                'label' => 'Extra Credit',
                'name_only' => 'Extra Credit',
                'sortable' => true,
                'tdClass' => 'text-center',
                'thClass' => 'text-center'];
            $fields[] = ['key' => "$weighted_score_assignment_id",
                'label' => 'Weighted Score',
                'name_only' => 'Weighted Score',
                'sortable' => true,
                'tdClass' => 'text-center',
                'thClass' => 'text-center'];
            if ($course->show_z_scores) {
                $fields[] = ['key' => "$z_score_assignment_id",
                    'label' => 'Z-Score',
                    'name_only' => 'Z-Score',
                    'sortable' => true,
                    'tdClass' => 'text-center',
                    'thClass' => 'text-center'];
            }
            $fields[] = ['key' => "$letter_grade_assignment_id",
                'label' => 'Letter Grade',
                'name_only' => 'Letter Grade',
                'sortable' => true,
                'tdClass' => 'text-center',
                'thClass' => 'text-center'];

            $download_fields[] = "Extra Credit";
            $download_fields[] = "Weighted Score";
            $download_fields[] = "Z-Score";
            $download_fields[] = "Letter Grade";


            return [$rows, $fields, $download_rows, $download_fields, $extra_credit_assignment_id, $weighted_score_assignment_id, $z_score_assignment_id, $letter_grade_assignment_id];

        }
    }


}
