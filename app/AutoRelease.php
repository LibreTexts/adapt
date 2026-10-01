<?php

namespace App;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AutoRelease extends Model
{
    protected $guarded = [];

    /**
     * @return string[]
     */
    public function keys(): array
    {
        return ['shown', 'show_scores', 'solutions_released', 'students_can_view_assignment_statistics'];
    }

    /**
     * @return array
     */
    public function requestMessages(): array
    {
        $messages["auto_release_students_can_view_assignment_statistics.required"] = "Either remove the auto-release statistics condition or add a time frame.";
        $messages["auto_release_solutions_released.required"] = "Either remove the auto-release solutions condition or add a time frame.";
        $messages["auto_release_show_scores.required"] = "Either remove the show scores condition or add a time frame for auto-release.";
        $messages["auto_release_students_can_view_assignment_statistics_after.required"] = "The auto-release statistics condition is required.";
        $messages["auto_release_solutions_released_after.required"] = "The auto-release solutions condition is required.";
        $messages["auto_release_show_scores_after.required"] = "The auto-release show scores condition is required.";
        return $messages;
    }

    public function getAutoReleaseShowDatesByAssignmentId(array $assignment_ids): array
    {
        $auto_releases = DB::table('auto_releases')
            ->join('assignments', 'assignments.id', '=', 'auto_releases.type_id')
            ->join('assign_to_timings', 'assignments.id', '=', 'assign_to_timings.assignment_id')
            ->where('auto_releases.type', 'assignment')
            ->whereIn('assignments.id', $assignment_ids)
            ->select(
                'assign_to_timings.available_from',
                'assign_to_timings.due',
                'assign_to_timings.final_submission_deadline',
                'auto_releases.type_id AS assignment_id',
                'auto_releases.shown',
                'auto_releases.shown_activated',
                'auto_releases.show_scores',
                'auto_releases.show_scores_activated',
                'auto_releases.show_scores_after',
                'auto_releases.solutions_released',
                'auto_releases.solutions_released_activated',
                'auto_releases.solutions_released_after',
                'auto_releases.students_can_view_assignment_statistics',
                'auto_releases.students_can_view_assignment_statistics_activated',
                'auto_releases.students_can_view_assignment_statistics_after'
            )
            ->get();

        $auto_releases_by_assignment_id = $this->getAutoReleasesByAssignmentId($auto_releases);
        $auto_release_show_dates_by_assignment_id = [];
        foreach ($auto_releases_by_assignment_id as $assignment_id => $auto_release) {
            /*Log::info('first available from');
            Log::info($auto_release['first_available_from']);
            Log::info('shown');
            Log::info($auto_release['shown']);*/
            $shown_dates = [];
            $first_available_from = Carbon::parse($auto_release['first_available_from'])
                ->setTimeZone(request()->user()->time_zone)
                ->toImmutable();
            $shown_dates['show_date'] = $first_available_from->sub($auto_release['shown'])->toDateTimeString();
            foreach (['show_scores', 'solutions_released', 'students_can_view_assignment_statistics'] as $value) {
                $last_due = $value . "_last_due";
                /* Log::info('last due');
                 Log::info($auto_release[$last_due]);
                 Log::info('auto-release value');
                 Log::info($auto_release[$value]);*/
                $last_due_as_time[$value] = Carbon::parse($auto_release[$last_due])
                    ->setTimeZone(request()->user()->time_zone)
                    ->toImmutable();
                $shown_dates["{$value}_date"] = $auto_release[$value] ?
                    $last_due_as_time[$value]->add($auto_release[$value])->toDateTimeString()
                    : null;
            }
            $auto_release_show_dates_by_assignment_id[$assignment_id] = $shown_dates;
        }
        return $auto_release_show_dates_by_assignment_id;
    }

    /**
     * @param $auto_releases
     * @return array
     */
    public function getAutoReleasesByAssignmentId($auto_releases): array
    {
        $auto_releases_by_assignment_id = [];
        $last_dues = ['show_scores_after', 'solutions_released_after', 'students_can_view_assignment_statistics_after'];
        foreach ($auto_releases as $auto_release) {
            $first_available_from = $auto_release->available_from;
            foreach ($last_dues as $key) {
                $last_due = str_replace('_after', '', $key);
                $last_due .= "_last_due";
                $new_last_dues[$last_due] = $this->lastDue($auto_release, $key);
            }
            if (!isset($auto_releases_by_assignment_id[$auto_release->assignment_id])) {
                $auto_releases_by_assignment_id[$auto_release->assignment_id] = [
                    'assignment_id' => $auto_release->assignment_id,
                    'first_available_from' => $first_available_from,
                    'shown' => $auto_release->shown,
                    'shown_activated' => $auto_release->shown_activated,
                    'show_scores' => $auto_release->show_scores,
                    'show_scores_activated' => $auto_release->show_scores_activated,
                    'solutions_released' => $auto_release->solutions_released,
                    'solutions_released_activated' => $auto_release->solutions_released_activated,
                    'students_can_view_assignment_statistics' => $auto_release->students_can_view_assignment_statistics,
                    'students_can_view_assignment_statistics_activated' => $auto_release->students_can_view_assignment_statistics_activated,
                    'show_scores_last_due' => $new_last_dues['show_scores_last_due'],
                    'solutions_released_last_due' => $new_last_dues['solutions_released_last_due'],
                    'students_can_view_assignment_statistics_last_due' => $new_last_dues['students_can_view_assignment_statistics_last_due']];
            } else {
                $current_first_available_from = Carbon::parse($auto_releases_by_assignment_id[$auto_release->assignment_id]['first_available_from'])->toImmutable();

                $new_first_available_from = Carbon::parse($first_available_from)->toImmutable();
                $auto_releases_by_assignment_id[$auto_release->assignment_id]['first_available_from'] = $current_first_available_from->min($new_first_available_from);

                foreach ($last_dues as $key) {
                    $last_due = str_replace('_after', '', $key) . "_last_due";
                    $current_last_due = Carbon::parse($auto_releases_by_assignment_id[$auto_release->assignment_id][$last_due])->toImmutable();
                    $new_last_due = Carbon::parse($new_last_dues[$last_due])->toImmutable();
                    $auto_releases_by_assignment_id[$auto_release->assignment_id][$last_due] = $current_last_due->max($new_last_due);
                }


            }
        }
        return $auto_releases_by_assignment_id;
    }

    /**
     * @param $auto_release
     * @param $key_after
     * @return mixed
     */
    public function lastDue($auto_release, $key_after)
    {
        return $auto_release->{$key_after} === 'final submission deadline' && $auto_release->final_submission_deadline
            ? $auto_release->final_submission_deadline
            : $auto_release->due;

    }

    /**
     * @param $item
     * @return array
     * @throws Exception
     */
    public function timingAndAfterArr($item): array
    {

        $before_after_pos = strpos($item, 'after');
        $timing = substr($item, 0, $before_after_pos);
        $timing = rtrim($timing);
        if (strpos($item, 'due date') !== false) {
            $after = 'due date';
        } else if (strpos($item, 'final submission deadline') !== false) {
            $after = 'final submission deadline';
        } else throw new Exception("The after should either be 'due date' or 'final submission deadline'");

        return compact('timing', 'after');
    }

    /**
     * @param array $data
     * @param string $type
     * @param int $type_id
     * @param string|null $assessment_type
     * @return array
     */
    public function handleUpdateOrCreate(array $data, string $type, int $type_id, string $assessment_type = null): array
    {
        $auto_release_data = [];
        $auto_releases = ['auto_release_shown',
            'auto_release_show_scores',
            'auto_release_solutions_released',
            'auto_release_students_can_view_assignment_statistics'];
        foreach ($auto_releases as $auto_release) {
            $auto_release_data[str_replace('auto_release_', '', $auto_release)] = null;
        }
        $auto_release_afters = [
            'auto_release_show_scores_after',
            'auto_release_solutions_released_after',
            'auto_release_students_can_view_assignment_statistics_after'];
        foreach ($auto_release_afters as $auto_release_after) {
            $auto_release_data[str_replace('auto_release_', '', $auto_release_after)] = null;
        }
        foreach ($auto_release_afters as $auto_release_after) {
            $auto_release = str_replace('_after', '', $auto_release_after);
            if ($data['late_policy'] === 'not accepted') {
                $auto_release_data[str_replace('auto_release_', '', $auto_release_after)] = isset($data[$auto_release])
                    ? 'due date'
                    : null;
            } else {
                $auto_release_data[str_replace('auto_release_', '', $auto_release_after)] = isset($data[$auto_release])
                    ? $data[$auto_release_after]
                    : null;
            }
            unset($data[$auto_release_after]);
        }
        foreach ($auto_releases as $auto_release) {
            if (isset($data[$auto_release])) {
                $auto_release_data[str_replace('auto_release_', '', $auto_release)] = $data[$auto_release];
            }
            unset($data[$auto_release]);
        }

        if ($type === 'assignment') {
            if (Assignment::scoresAlwaysReleased($assessment_type)) {
                $auto_release_data['show_scores'] = null;
                $auto_release_data['show_scores_after'] = null;
            }
            $assignment = Assignment::find($type_id);
            if ($assignment->assessment_type === 'clicker') {
                $this->where('type', 'assignment')->where('type_id', $type_id)->delete();
                return $data;
            }
        }
        $autoRelease = $this->where('type', $type)->where('type_id', $type_id)->first();
        $delete = true;
        foreach ($auto_release_data as $value) {
            if ($value) {
                $delete = false;
            }
        }
        if ($delete) {
            $this->where('type', $type)->where('type_id', $type_id)->delete();
        } else {
            if ($autoRelease) {
                $autoRelease->update($auto_release_data);
            } else {
                $auto_release_data['type'] = $type;
                $auto_release_data['type_id'] = $type_id;
                DB::table('auto_releases')->insert($auto_release_data);
            }
        }
        return $data;
    }

    /**
     * @param int $assignment_id
     * @param string $property
     * @param $deactivate_auto_release
     * @return false
     */
    public function deactivateFromAssignment(int    $assignment_id,
                                             string $property,
                                                    $deactivate_auto_release): bool
    {
        $auto_release_deactivated = false;
        if ($deactivate_auto_release) {
            $auto_release = $this->where('type', 'assignment')
                ->where('type_id', $assignment_id)
                ->first();
            if ($auto_release) {
                $property = $property . "_activated";
                $auto_release->{$property} = 0;
                $auto_release->save();
                $auto_release_deactivated = true;
            }
        }
        return $auto_release_deactivated;
    }

    /**
     * @param $date_time
     * @return string
     */
    function formattedDateTime($date_time): string
    {
        $carbon_datetime = Carbon::createFromFormat('Y-m-d H:i:s', $date_time);
        $carbon_datetime->setTimezone(auth()->user()->time_zone);
        return $carbon_datetime->format('F j, Y \a\t g:i A');
    }


}
