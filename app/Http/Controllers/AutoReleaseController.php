<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AutoRelease;
use App\Course;
use App\Exceptions\Handler;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AutoReleaseController extends Controller
{

    public function getGlobalAutoReleaseUpdateOptions(Course $course, AutoRelease $autoRelease)
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getGlobalAutoReleaseUpdateOptions', [$autoRelease, $course]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $global_auto_release_update_options = [];
            foreach ($course->assignments as $assignment) {
                $global_auto_release_update_options[] = ['text' => $assignment->name, 'value' => $assignment->id];
            }

            $global_auto_release_update_options[] = [
                'text' => '-----------------------',
                'value' => 'divider-1',
                'disabled' => true];
            $global_auto_release_update_options[] = ['text' => 'Entire Course', 'value' => -1];
            $response['global_auto_release_update_options'] = $global_auto_release_update_options;
            $response['type'] = 'success';
            return $response;

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to get the global auto-release options for this course. Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Course $course
     * @param AutoRelease $autoRelease
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public function globalUpdate(Request     $request,
                                 Course      $course,
                                 AutoRelease $autoRelease,
                                 Assignment  $assignment): array
    {
        $response['type'] = 'error';
        $update_item = $request->update_item;
        $update_name = $request->update_name;

        $authorized = Gate::inspect('globalUpdate', [$autoRelease, $course, $update_item]);


        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $setting = $request->setting;
            $value = $request->value;
            $auto_release_keys = $autoRelease->keys();
            $assignment_ids_to_update = $update_item === -1
                ? $course->assignments->pluck('id')->toArray()
                : [$update_item];
            foreach ($auto_release_keys as $auto_release) {
                switch ($setting) {
                    case('manual'):
                        $query = $assignment->whereIn('id', $assignment_ids_to_update);
                        if ($auto_release === 'show_scores' && !$value) {
                            $query->whereNotIn('assessment_type', Assignment::ALWAYS_SHOW_SCORES_ASSESSMENT_TYPES);
                        }
                        $query->update([$auto_release => $value]);
                        break;
                    case('auto'):
                        $assignment_ids = DB::table('assignments')
                            ->where('assignments.assessment_type', '<>', 'clicker')
                            ->whereIn('id', $assignment_ids_to_update)
                            ->get('id')
                            ->pluck('id')
                            ->toArray();
                        $autoRelease->where('type', 'assignment')
                            ->whereNotNull($auto_release)
                            ->whereIn('type_id', $assignment_ids)
                            ->update(["{$auto_release}_activated" => $value]);
                        break;
                }
            }
            $response['type'] = $value ? 'success' : 'info';
            $new_state = $value ? 'on' : 'off';
            $updated_text = $update_item === -1 ? 'all assignments in your course' : "the assignment $update_name";
            $response['message'] = "The '$setting' setting has been turned $new_state for $updated_text.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to globally update the auto-releases for this course. Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @param string $property
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function autoReleaseTimingMessage(Assignment  $assignment,
                                      string      $property,
                                      AutoRelease $autoRelease): array
    {

        $response['type'] = 'error';
        try {
            $auto_releases = DB::table('auto_releases')
                ->join('assign_to_timings', 'auto_releases.type_id', '=', 'assign_to_timings.assignment_id')
                ->where('auto_releases.type', 'assignment')
                ->where('auto_releases.type_id', $assignment->id)
                ->select('assign_to_timings.available_from',
                    'assign_to_timings.due',
                    'assign_to_timings.final_submission_deadline',
                    'auto_releases.*'
                )
                ->get();
            $timing_message = 'However, you have this assignment set to auto-release ';
            if ($property === 'shown') {
                $first_available_from = Carbon::parse($auto_releases[0]->available_from)->toImmutable();
                foreach ($auto_releases as $auto_release) {
                    $new_first_available_from = Carbon::parse($auto_release->available_from)->toImmutable();
                    $first_available_from = $first_available_from->min($new_first_available_from);
                }
                $timing = $auto_releases[0]->{$property};
                $formatted_date_time = $autoRelease->formattedDateTime($first_available_from);
                $timing_message .= "$timing before it opens for submission on $formatted_date_time.";
            } else {
                $last_due = $autoRelease->lastDue($auto_releases[0], $property);
                $last_due = Carbon::parse($last_due)->toImmutable();
                foreach ($auto_releases as $auto_release) {
                    $new_last_due = $autoRelease->lastDue($auto_release, $property);
                    $new_last_due = Carbon::parse($new_last_due)->toImmutable();
                    $last_due = $last_due->max($new_last_due);
                }

                $timing = $auto_releases[0]->{$property};
                $formatted_date_time = $autoRelease->formattedDateTime($last_due);
                $property_after = "{$property}_after";
                $due_text = $auto_releases[0]->{$property_after} === 'due date' ? 'it becomes due' : 'the final submission deadline';
                $timing_message .= "$timing after $due_text on $formatted_date_time.";
            }

            $response['timing_message'] = $timing_message;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the auto-release text.  Please try again or contact us for assistance.";
        }

        return $response;

    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function updateActivated(Request $request, Assignment $assignment, AutoRelease $autoRelease): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('updateActivated', [$autoRelease, $assignment]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            if (!in_array($request->property, ['shown', 'show_scores', 'solutions_released', 'students_can_view_assignment_statistics'])) {
                $response['message'] = "$request->property is not a valid auto-release activation property.";
                return $response;
            }
            if ($request->property === 'show_scores' && Assignment::scoresAlwaysReleased($assignment->assessment_type)) {
                $response['message'] = "Scores are always released for $assignment->assessment_type assignments.";
                return $response;
            }
            $property = $request->property . "_activated";
            $auto_release = $autoRelease->where('type', 'assignment')->where('type_id', $assignment->id)->first();
            $auto_release->{$property} = 1 - $auto_release->{$property};
            $auto_release->save();

            $response['type'] = $auto_release->{$property} ? 'success' : 'info';
            $activated_message = $auto_release->{$property} ? 'activated' : 'deactivated';
            $response['message'] = "The auto-release has been $activated_message.";


        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to update the auto-release activated status. Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function getStatuses(Assignment $assignment, AutoRelease $autoRelease): array
    {
        $response['type'] = 'error';
        try {
            $auto_release_statuses = [];
            $auto_release_keys = $autoRelease->keys();
            foreach ($auto_release_keys as $key) {
                $auto_release_statuses[$key] = $assignment->{$key};
            }
            $response['type'] = 'success';
            $response['auto_release_statuses'] = $auto_release_statuses;
            return $response;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to get the statuses.  Please contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @param Course $course
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function compareAssignmentToCourseDefault(Assignment  $assignment,
                                              Course      $course,
                                              AutoRelease $autoRelease): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('compareAssignmentToCourseDefault', $autoRelease);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $non_matching_auto_releases = [];
            if (!$assignment->formative && $assignment->assessment_type !== 'clicker') {
                $assignment_auto_release = $autoRelease->where('type', 'assignment')->where('type_id', $assignment->id)->first();

                $assignment_auto_releases = ['shown' => null,
                    'show_scores' => null,
                    'solutions_released' => null,
                    'students_can_view_assignment_statistics' => null];


                if ($assignment_auto_release) {
                    $assignment_auto_releases = [
                        'shown' => $assignment_auto_release->shown,
                        'show_scores' => $assignment_auto_release->show_scores,
                        'show_scores_after' => $assignment_auto_release->show_scores_after,
                        'solutions_released' => $assignment_auto_release->solutions_released,
                        'solutions_released_after' => $assignment_auto_release->solutions_released_after,
                        'students_can_view_assignment_statistics' => $assignment_auto_release->students_can_view_assignment_statistics,
                        'students_can_view_assignment_statistics_after' => $assignment_auto_release->students_can_view_assignment_statistics_after];
                }

                $course_default_auto_releases = [
                    'shown' => $course->auto_release_shown,
                    'show_scores' => $course->auto_release_show_scores,
                    'show_scores_after' => $assignment->assessment_type !== 'real time' ? $course->auto_release_show_scores_after : null,
                    'solutions_released' => $course->auto_release_solutions_released,
                    'solutions_released_after' => $course->auto_release_solutions_released_after,
                    'students_can_view_assignment_statistics' => $course->auto_release_students_can_view_assignment_statistics,
                    'students_can_view_assignment_statistics_after' => $course->auto_release_students_can_view_assignment_statistics_after
                ];

                $auto_release_keys = $autoRelease->keys();
                $labels['shown'] = 'Assignment';
                $labels['show_scores'] = 'Scores';
                $labels['solutions_released'] = 'Solutions';
                $labels['students_can_view_assignment_statistics'] = 'Statistics';
                foreach ($auto_release_keys as $value) {
                    if ($value === 'show_scores' && Assignment::scoresAlwaysReleased($assignment->assessment_type)) {
                        continue;
                    }
                    if ($value === 'shown') {
                        $assignment_auto_release = $assignment_auto_releases['shown'] ? $assignment_auto_releases['shown'] . ' before your "available on"' : null;
                        $course_default_auto_release = $course_default_auto_releases['shown'] ? $course_default_auto_releases['shown'] . ' before your "available on"' : null;
                    } else {
                        $assignment_condition = $assignment_auto_releases[$value . '_after'] ?? null;
                        $course_default_condition = null;
                        if (isset($course_default_auto_releases[$value . '_after'])) {
                            if ($assignment->late_policy !== 'not accepted') {
                                $course_default_condition = $course_default_auto_releases[$value . '_after'];
                            } else {
                                $course_default_condition = 'due date';
                            }
                        }
                        $assignment_auto_release = $assignment_auto_releases[$value] ? $assignment_auto_releases[$value] . ' after your "' . $assignment_condition . '"' : null;
                        $course_default_auto_release = $course_default_auto_releases[$value] ? $course_default_auto_releases[$value] . ' after your "' . $course_default_condition . '"' : null;

                    }
                    if ($assignment_auto_release !== $course_default_auto_release) {
                        $non_matching_auto_releases[] = [
                            'key' => $value,
                            'label' => $labels[$value],
                            'assignment' => $assignment_auto_release,
                            'course_default' => $course_default_auto_release
                        ];
                    }
                }
            }
            $response['non_matching_auto_releases'] = $non_matching_auto_releases;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to compare the assignment to the course default. Please try again or contact us for assistance.";
        }
        return $response;
    }
}
