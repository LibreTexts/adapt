<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AssignmentQuestionLearningTree;
use App\Exceptions\Handler;
use App\JWE;
use App\LearningTree;
use App\LearningTreeAnalytics;
use App\LearningTreeNodeAssignmentQuestion;
use App\LearningTreeNodeDescription;
use App\LearningTreeNodeSeed;
use App\LearningTreeNodeSubmission;
use App\Policies\LearningTreeNodeAssignmentQuestionPolicy;
use App\Question;
use App\Traits\IframeFormatter;
use App\Traits\Seed;
use Carbon\Carbon;
use DOMDocument;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LearningTreeNodeAssignmentQuestionController extends Controller
{
    use IframeFormatter;
    use Seed;

    public function logVisit(Request                            $request,
                             Assignment                         $assignment,
                             LearningTree                       $learningTree,
                             Question                           $nodeQuestion,
                             LearningTreeNodeAssignmentQuestion $learningTreeNodeAssignmentQuestion)
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('logVisit', [$learningTreeNodeAssignmentQuestion,
            $assignment->id,
            $learningTree,
            $nodeQuestion]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        if (!$request->user()->fake_student || app()->environment() === 'local') {
            $assignment_course_info = $assignment->assignmentCourseInfo();
            LearningTreeAnalytics::create([
                'course_name'=>  $assignment_course_info->course_name,
                'assignment_name'=>  $assignment_course_info->assignment_name,
                'instructor'=>  $assignment_course_info->instructor,
                'user_id' => $request->user()->id,
                'learning_tree_id' => $learningTree->id,
                'assignment_id' => $assignment->id,
                'question_id' => $nodeQuestion->id,
                'root_node' => 0,
                'action' => 'visit',
                'response' => 'success'
            ]);
        }
        $response['type'] = 'success';
        return $response;

    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param LearningTree $learningTree
     * @param Question $nodeQuestion
     * @param LearningTreeNodeAssignmentQuestion $learningTreeNodeAssignmentQuestion
     * @return array
     * @throws Exception
     */
    public function giveCreditForCompletion(Request                            $request,
                                            Assignment                         $assignment,
                                            LearningTree                       $learningTree,
                                            Question                           $nodeQuestion,
                                            LearningTreeNodeAssignmentQuestion $learningTreeNodeAssignmentQuestion): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('giveCreditForCompletion', [$learningTreeNodeAssignmentQuestion,
                $assignment->id,
                $learningTree,
                $nodeQuestion]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            LearningTreeNodeSubmission::updateOrCreate([
                'user_id' => $request->user()->id,
                'assignment_id' => $assignment->id,
                'learning_tree_id' => $learningTree->id,
                'question_id' => $nodeQuestion->id],
                ['completed' => 1]);
            if (!$request->user()->fake_student || app()->environment() === 'local') {
                $assignment_course_info = $assignment->assignmentCourseInfo();
                LearningTreeAnalytics::create([
                    'course_name'=>  $assignment_course_info->course_name,
                    'assignment_name'=>  $assignment_course_info->assignment_name,
                    'instructor'=>  $assignment_course_info->instructor,
                    'user_id' => $request->user()->id,
                    'learning_tree_id' => $learningTree->id,
                    'assignment_id' => $assignment->id,
                    'question_id' => $nodeQuestion->id,
                    'root_node' => 0,
                    'action' => 'time completed',
                    'response' => 'success'
                ]);
            }
            $response['type'] = 'success';
            $response['message'] = "The learning tree node has been completed.";

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error giving you credit for this learning tree node.  Please try again or contact us for assistance.";

        }

        return $response;

    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param LearningTree $learningTree
     * @param Question $nodeQuestion
     * @param LearningTreeNodeSubmission $learningTreeNodeSubmission
     * @param LearningTreeNodeSeed $learningTreeNodeSeed
     * @param LearningTreeNodeAssignmentQuestion $learningTreeNodeAssignmentQuestion
     * @param AssignmentQuestionLearningTree $assignmentQuestionLearningTree
     * @return array
     * @throws Exception
     */
    public
    function show(Request                            $request,
                  Assignment                         $assignment,
                  LearningTree                       $learningTree,
                  Question                           $nodeQuestion,
                  LearningTreeNodeSubmission         $learningTreeNodeSubmission,
                  LearningTreeNodeSeed               $learningTreeNodeSeed,
                  LearningTreeNodeAssignmentQuestion $learningTreeNodeAssignmentQuestion,
                  AssignmentQuestionLearningTree     $assignmentQuestionLearningTree): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('show', [$learningTreeNodeAssignmentQuestion, $assignment->id, $learningTree, $nodeQuestion]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $question = new Question();

            // EK: lock this node to whatever question_revision_id was
            // recorded in this assignment's tree snapshot (set when the tree
            // was added, or last refreshed via "update to latest revision"),
            // rather than always serving the question's current/live content.
            // This has to happen BEFORE formatQuestionFromDatabase() below,
            // since that's what actually renders the revision-specific
            // fields (title, text_question, qti_json, webwork_code, etc.)
            // into what gets sent to the student.
            $assignment_question_learning_tree = null;
            try {
                $assignment_question_learning_tree = $assignmentQuestionLearningTree
                    ->getAssignmentQuestionLearningTreeByLearningTreeId($assignment->id, $learningTree->id);
            } catch (Exception $e) {
                //no matching row - nothing to lock to, so fall back to the live question below
            }
            $locked_question_revision_id = $assignmentQuestionLearningTree
                ->getLockedQuestionRevisionId($assignment_question_learning_tree, $nodeQuestion->id);
            $locked_question_revision = null;
            if ($locked_question_revision_id) {
                $locked_question_revision = DB::table('question_revisions')->where('id', $locked_question_revision_id)->first();
                if ($locked_question_revision) {
                    $nodeQuestion = $nodeQuestion->updateWithQuestionRevision($locked_question_revision);
                }
            }

            $node_question_result = $question->formatQuestionFromDatabase($request, $nodeQuestion);
            // EK: formatQuestionFromDatabase() rewrites technology_iframe
            // for webwork questions into a bare preview URL (via
            // getPreviewWebworkProblemJWT()), not real <iframe> HTML - fine
            // for whatever standalone preview use that method serves
            // elsewhere, but getTechnologySrcAndProblemJWT() below still
            // needs the real iframe HTML to extract sourceFilePath from,
            // and crashes trying to parse the bare URL as HTML instead.
            // $question and $nodeQuestion are the same object here (fill()
            // returns $this), so restoring the real value once covers both
            // that call and formatIframeSrc()'s use of the same property
            // further down.
            $locked_technology_iframe = $nodeQuestion->technology_iframe;
            $nodeQuestion = $question->fill($node_question_result);
            $nodeQuestion->technology_iframe = $locked_technology_iframe;
            if ($nodeQuestion->technology === 'text' || $nodeQuestion->assessment_type === 'exposition') {
                if (substr($nodeQuestion->non_technology_iframe_src, -2) === '/0') {
                    // EK: use the locked revision's own revision_number when we
                    // have one, so the exposition iframe points at the same
                    // revision the rest of this response was built from -
                    // previously this always pointed at whatever the latest
                    // revision happened to be, regardless of locking above.
                    $revision_number = $locked_question_revision
                        ? $locked_question_revision->revision_number
                        : $nodeQuestion->latestQuestionRevision('revision_number');
                    $nodeQuestion->non_technology_iframe_src = substr($nodeQuestion->non_technology_iframe_src, 0, -2) . '/' . $revision_number;
                }
                $min_number_of_minutes_in_exposition_node = $request->user()->fake_student || $request->user()->role !== 3
                    ? 5 * 1000
                    : $assignment->min_number_of_minutes_in_exposition_node * 60 * 1000;
                $nodeQuestion->time_left = $min_number_of_minutes_in_exposition_node;
            }
            $seed = '';
            if (in_array($question->technology, ['webwork', 'imathas', 'qti'])) {
                $learning_tree_node_seed = $learningTreeNodeSeed->where('user_id', $request->user()->id)
                    ->where('assignment_id', $assignment->id)
                    ->where('learning_tree_id', $learningTree->id)
                    ->where('question_id', $nodeQuestion->id)
                    ->first();
                if ($learning_tree_node_seed) {
                    $seed = $learning_tree_node_seed->seed;
                } else {
                    $seed = $this->createSeedByTechnologyAssignmentAndQuestion($assignment, $question);
                    $learningTreeNodeSeed->user_id = $request->user()->id;
                    $learningTreeNodeSeed->assignment_id = $assignment->id;
                    $learningTreeNodeSeed->learning_tree_id = $learningTree->id;
                    $learningTreeNodeSeed->question_id = $nodeQuestion->id;
                    $learningTreeNodeSeed->seed = $seed;
                    $learningTreeNodeSeed->save();
                }
            }
            $domd = new DOMDocument();
            $JWE = new JWE();
            $extra_custom_claims['is_learning_tree_node'] = true;
            $extra_custom_claims['learning_tree_id'] = $learningTree->id;
            //$extra_custom_claims['branch_id'] = $branch_id;

            $technology_src_and_problemJWT = $question->getTechnologySrcAndProblemJWT($request, $assignment, $nodeQuestion, $seed, true, $domd, $JWE, $extra_custom_claims);
            $technology_src = $technology_src_and_problemJWT['technology_src'];
            $problemJWT = $technology_src_and_problemJWT['problemJWT'];

            if ($technology_src) {
                $iframe_id = $this->createIframeId();
                //don't return if not available yet!
                $nodeQuestion['technology_iframe_src'] = $this->formatIframeSrc($question['technology_iframe'], $iframe_id, $problemJWT);
            }
            $nodeQuestion['technology_iframe'] = '';//hide this from students since it has the path
            if ($nodeQuestion['non_technology_iframe_src']) {
                session()->put('canViewLocallySavedContents', "$nodeQuestion->id");
            }
            $learning_tree_node_submission = $learningTreeNodeSubmission
                ->where('user_id', $request->user()->id)
                ->where('assignment_id', $assignment->id)
                ->where('learning_tree_id', $learningTree->id)
                ->where('question_id', $nodeQuestion->id)
                ->first();

            $learning_tree_node_description = DB::table('learning_tree_node_descriptions')
                ->where('learning_tree_id', $learningTree->id)
                ->where('question_id', $nodeQuestion->id)
                ->first();
            $nodeQuestion->completed = $learning_tree_node_submission && $learning_tree_node_submission->completed;
            $nodeQuestion->learning_tree_node_submission_id = $learning_tree_node_submission ? $learning_tree_node_submission->id : null;
            if ($learning_tree_node_description) {
                $nodeQuestion->title = $learning_tree_node_description->title;
                $description = $learning_tree_node_description->description ?: '';
                // EK: same fix as LearningTreeNodeController::getMetaInfo()
                // and LearningTree::getBranchAndTwigInfo() - blank out the
                // description if it's the literal 'None Available'
                // placeholder some existing rows have, or if it's identical
                // to the node's own title, so students see nothing rather
                // than a confusing placeholder or a duplicated title.
                if ($description === 'None Available' || $description === $nodeQuestion->title) {
                    $description = '';
                }
                $nodeQuestion->node_description = $description;
            }
            $response['node_question'] = $nodeQuestion;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the node question.  Please try again or contact us for assistance.";

        }

        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param LearningTree $learningTree
     * @param LearningTreeNodeDescription $learningTreeNodeDescription
     * @return array
     * @throws Exception
     */
    public function learningTreeNodeCompletionInfo(Request                     $request,
                                                   Assignment                  $assignment,
                                                   LearningTree                $learningTree,
                                                   LearningTreeNodeDescription $learningTreeNodeDescription): array
    {

        $learning_tree_question_ids = $learningTree->questionIds();
        try {
            $learning_tree_node_submissions = DB::table('learning_tree_node_submissions')
                ->where('user_id', $request->user()->id)
                ->where('assignment_id', $assignment->id)
                ->where('learning_tree_id', $learningTree->id)
                ->get();
            $completed_learning_tree_nodes = [];
            foreach ($learning_tree_node_submissions as $learning_tree_node_submission) {
                if ($learning_tree_node_submission->completed) {
                    $completed_learning_tree_nodes[] = $learning_tree_node_submission->question_id;
                }
            }
            $completed_learning_tree_nodes_by_question_id = [];
            foreach ($learning_tree_question_ids as $question_id) {
                $completed_learning_tree_nodes_by_question_id[$question_id] = in_array($question_id, $completed_learning_tree_nodes) ? 'completed' : 'not-completed';
            }


            $learning_tree_node_parents = $learningTree->nodeParents();


            //get the node descriptions to show to the student
            $learning_tree_node_descriptions = $learningTreeNodeDescription->where('learning_tree_id', $learningTree->id)->get();
            $learning_tree_node_descriptions_by_question_id = [];
            foreach ($learning_tree_node_descriptions as $learning_tree_node_description) {
                $learning_tree_node_descriptions_by_question_id[$learning_tree_node_description->question_id] = $learning_tree_node_description->title;
            }
            $learning_tree_node_uncompleted_parent_node_titles_by_question_id = [];

            //add in the ones that have parents which are not completed but don't include the root question node
            foreach ($learning_tree_node_parents as $key => $question_ids) {
                $learning_tree_node_uncompleted_parent_node_titles_by_question_id[$key] = [];
                foreach ($question_ids as $question_id) {
                    if ($question_id !== $learningTree->root_node_question_id && !in_array($question_id, $completed_learning_tree_nodes)) {
                        $learning_tree_node_uncompleted_parent_node_titles_by_question_id[$key][] = $learning_tree_node_descriptions_by_question_id[$question_id] ?? 'No description provided';
                    }
                }
            }

            $response['learning_tree_node_uncompleted_parent_node_titles_by_question_id'] = $learning_tree_node_uncompleted_parent_node_titles_by_question_id;
            $response['learning_tree_node_completion_info'] = $completed_learning_tree_nodes_by_question_id;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'We could not get the learning tree node completion information.  Please try again or contact us for assistance.';

        }
        return $response;
    }
}
