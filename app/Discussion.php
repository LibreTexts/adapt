<?php

namespace App;

use Carbon\Carbon;
use DOMDocument;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Discussion extends Model
{

    /**
     * @param Assignment $assignment
     * @return void
     * @throws Exception
     */
    public function deleteByAssignment(Assignment $assignment)
    {
        $questionMediaUpload = new QuestionMediaUpload();
        //with linked (daisy-chained) questions, comments can be made in this assignment on threads started elsewhere
        $discussion_ids = Discussion::where('assignment_id', $assignment->id)->pluck('id')->toArray();
        $discussion_comments = DiscussionComment::whereIn('discussion_id', $discussion_ids)
            ->orWhere('posted_in_assignment_id', $assignment->id)
            ->get();
        $possibly_empty_discussion_ids = [];
        foreach ($discussion_comments as $discussion_comment) {
            if ($discussion_comment->file) {
                $questionMediaUpload->deleteFileAndVttFile($discussion_comment->file);
            }
            $possibly_empty_discussion_ids[] = $discussion_comment->discussion_id;
            $discussion_comment->delete();
        }
        Discussion::whereIn('id', $discussion_ids)->delete();
        foreach (array_unique($possibly_empty_discussion_ids) as $discussion_id) {
            if (!DiscussionComment::where('discussion_id', $discussion_id)->exists()) {
                Discussion::where('id', $discussion_id)->delete();
            }
        }
        DB::table('discussion_groups')->where('assignment_id', $assignment->id)->delete();
    }

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @param int $user_id
     * @return mixed
     */
    public function numberOfDiscussionsThatSatisfiedTheRequirements(int $assignment_id,
                                                                    int $question_id,
                                                                    int $user_id)
    {

        return $this->join('discussion_comments', 'discussions.id', '=', 'discussion_comments.discussion_id')
            ->where('discussion_comments.posted_in_assignment_id', $assignment_id)
            ->where('discussions.question_id', $question_id)
            ->where('discussion_comments.user_id', $user_id)
            ->where('discussion_comments.satisfied_requirement', 1)
            ->distinct()
            ->count('discussions.id');
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param string $media_upload_id
     * @return array
     */
    public function getByAssignmentQuestionMediaUploadId(Assignment $assignment,
                                                         Question   $question,
                                                         string     $media_upload_id): array
    {

        $questionMediaUpload = new QuestionMediaUpload();
        $enrolled_student_ids = $assignment->course->enrolledUsersWithFakeStudent->pluck('id')->toArray();
        $enrolled_students = DB::table('users')
            ->whereIn('id', $enrolled_student_ids)
            ->orWhere('id', $assignment->course->user_id)
            ->select('id', DB::raw('CONCAT(first_name, " " , last_name) AS name'), 'time_zone')
            ->get();
        $enrolled_students_by_user_id = [];
        foreach ($enrolled_students as $enrolled_student) {
            $enrolled_students_by_user_id[$enrolled_student->id] = $enrolled_student->name;
            $enrolled_student_time_zones_by_user_id[$enrolled_student->id] = $enrolled_student->time_zone;
        }


        //a linked (daisy-chained) question shows the discussions from every linked assignment
        $linked_assignment_ids = DiscussItChain::linkedAssignmentIds($assignment->id, $question->id);
        $assignment_names_by_id = count($linked_assignment_ids) > 1
            ? DiscussItChain::assignmentNamesById($linked_assignment_ids)
            : [];
        //students only see the names of linked assignments that are visible to them
        if ($assignment_names_by_id && request()->user() && request()->user()->role !== 2) {
            $visible_assignment_ids = DiscussItChain::assignmentIdsVisibleToStudent(array_keys($assignment_names_by_id), request()->user()->id);
            foreach ($assignment_names_by_id as $assignment_id => $name) {
                if ($assignment_id !== $assignment->id && !in_array($assignment_id, $visible_assignment_ids)) {
                    $assignment_names_by_id[$assignment_id] = 'Another assignment';
                }
            }
        }
        $discussion_infos = $this->join('discussion_comments', 'discussions.id', '=', 'discussion_comments.discussion_id')
            ->whereIn('discussions.assignment_id', $linked_assignment_ids)
            ->where('question_id', $question->id)
            ->select('discussions.id AS discussion_id',
                'discussions.assignment_id AS discussion_assignment_id',
                DB::raw('COALESCE(discussion_comments.posted_in_assignment_id, discussions.assignment_id) AS comment_assignment_id'),
                'discussions.created_at AS discussion_created_at',
                'discussions.user_id AS discussion_user_id',
                'discussions.group',
                'discussion_comments.id AS discussion_comments_id',
                'discussion_comments.user_id AS discussion_comments_user_id',
                'discussion_comments.id AS comment_id',
                'discussion_comments.recording_type AS recording_type',
                'discussion_comments.text',
                'discussion_comments.pasted_comment',
                'discussion_comments.file',
                'discussion_comments.transcript',
                'discussion_comments.re_processed_transcript',
                'discussion_comments.created_at AS comment_created_at'
            );
        if ($media_upload_id) {
            $discussion_infos = $discussion_infos->where('media_upload_id', $media_upload_id);
        }
        $discussion_infos = $discussion_infos->orderBy('comment_created_at', 'ASC')
            ->get();
        $discussions = [];
        $discussions_by_user_id = [];
        $htmlDom = new DOMDocument();
        foreach ($discussion_infos as $value) {
            if (!isset($enrolled_student_time_zones_by_user_id[$value->discussion_user_id])) {
                $missing_student = User::find($value->discussion_user_id);
                $enrolled_student_time_zones_by_user_id[$value->discussion_user_id] = $missing_student->time_zone;
                $enrolled_students_by_user_id[$value->discussion_user_id] = "Fake Student";
            }
            $discussion_id = $value->discussion_id;
            if (!isset($discussions[$discussion_id])) {
                $discussions[$discussion_id] = [
                    'id' => $discussion_id,
                    'created_at' => $this->_formatDate($value->discussion_created_at, $enrolled_student_time_zones_by_user_id[$value->discussion_user_id]),
                    'started_by' => $enrolled_students_by_user_id[$value->discussion_user_id],
                    'group' => $value->group,
                    'assignment_id' => $value->discussion_assignment_id,
                    'assignment_name' => $assignment_names_by_id[$value->discussion_assignment_id] ?? '',
                    'comments' => []
                ];
            }
            $discussionComment = new DiscussionComment();
            if (!isset($enrolled_student_time_zones_by_user_id[$value->discussion_user_id])) {
                $enrolled_student_time_zones_by_user_id[$value->discussion_user_id] = User::find($value->discussion_user_id)->time_zone;
            }
            if (!isset($enrolled_students_by_user_id[$value->discussion_comments_user_id])) {
                $user_by_discussion_comments_user_id = User::find($value->discussion_comments_user_id);
                $enrolled_students_by_user_id[$value->discussion_comments_user_id] = "$user_by_discussion_comments_user_id->first_name $user_by_discussion_comments_user_id->last_name";
                $enrolled_student_time_zones_by_user_id[$value->discussion_comments_user_id] = $user_by_discussion_comments_user_id->time_zone;

            }


            $discussions[$discussion_id]['comments'][] = [
                'id' => $value->comment_id,
                'created_by_user_id' => $value->discussion_comments_user_id,
                'created_by_name' => $enrolled_students_by_user_id[$value->discussion_comments_user_id],
                'assignment_id' => (int)$value->comment_assignment_id,
                'assignment_name' => $assignment_names_by_id[$value->comment_assignment_id] ?? '',
                'text' => $question->addTimeToS3Files($value->text, $htmlDom, false),
                'pasted_comment' => $value->pasted_comment,
                'file' => $value->file,
                'recording_type' => $discussionComment->getRecordingType($value),
                'transcript' => $value->transcript ? $questionMediaUpload->parseVtt($value->transcript) : null,
                're_processed_transcript' => $value->re_processed_transcript,
                'created_at' => $this->_formatDate($value->comment_created_at, $enrolled_student_time_zones_by_user_id[$value->discussion_user_id])];
            //per-student comments (used for grading) only include what was posted in this assignment
            if ((int)$value->comment_assignment_id !== $assignment->id) {
                continue;
            }
            if (!isset($discussions_by_user_id[$value->discussion_comments_user_id])) {
                $discussions_by_user_id[$value->discussion_comments_user_id] = [
                    'user_id' => $value->discussion_comments_user_id,
                    'comments' => []];
            }
            $discussions_by_user_id[$value->discussion_comments_user_id]['comments'][] = [
                'id' => $value->comment_id, //needed for the trnascript
                'discussion_comment_id' => $value->comment_id,
                'discussion_id' => $discussion_id,
                'text' => $question->addTimeToS3Files($value->text, $htmlDom, false),
                'pasted_comment' => $value->pasted_comment,
                'file' => $value->file,
                'transcript' => $value->transcript ? $questionMediaUpload->parseVtt($value->transcript) : null,
                're_processed_transcript' => $value->re_processed_transcript,
                'created_at' => $this->_formatDate($value->comment_created_at, $enrolled_student_time_zones_by_user_id[$value->discussion_comments_user_id])
            ];
        }
        foreach ($discussions as $key => $discussion) {
            if (isset($discussion['comments'])) {
                $discussion['comments'][$key] = rsort($discussion['comments']);
            }
        }
        foreach ($enrolled_students as $enrolled_student) {
            $discussions_by_user_id[$enrolled_student->id]['user_id'] = $enrolled_student->id;
        }
        return ['discussions' => array_values($discussions), 'discussions_by_user_id' => array_values($discussions_by_user_id)];
    }

    /**
     * @param $date
     * @param $time_zone
     * @return string
     */
    private function _formatDate($date, $time_zone): string
    {
        return Carbon::parse($date)
            ->setTimezone($time_zone)
            ->format('n/j/y \a\t g:iA', $time_zone);

    }
}
