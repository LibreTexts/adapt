<?php

namespace App\Http\Controllers;


use App\EmptyLearningTreeNode;
use App\Http\Requests\CloneLearningTreesRequest;
use App\Http\Requests\StoreLearningTreeInfo;
use App\Http\Requests\UpdateLearningTreeInfo;
use App\LearningTree;
use App\LearningTreeHistory;
use App\Question;
use App\SavedQuestionsFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Exceptions\Handler;
use \Exception;
use Illuminate\Support\Facades\Storage;

class LearningTreeController extends Controller
{


    public
    function getAll(Request      $request,
                    LearningTree $learningTree): array
    {
        $per_page = $request->per_page;
        $current_page = $request->current_page;
        $author = $request->author;
        $title = $request->title;

        $response['type'] = 'error';
        $authorized = Gate::inspect('getAll', $learningTree);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {

            $learning_tree_ids = DB::table('learning_trees')
                ->select('id')
                ->where(function ($query) use ($request) {
                    $query->where('public', 1)
                        ->orWhere('user_id', '=', $request->user()->id);
                });

            if ($title) {
                $learning_tree_ids = $learning_tree_ids->where('title', 'LIKE', "%$title%");
            }
            if ($author) {
                $all_author_ids = DB::table('learning_trees')
                    ->get('user_id')
                    ->pluck('user_id');
                $users = DB::table('users')
                    ->select('')
                    ->whereIn('id', $all_author_ids)
                    ->select('id', DB::raw('CONCAT(first_name, " ", last_name) AS name'))
                    ->get();
                $author_ids = [];
                foreach ($users as $user) {
                    if (stripos($user->name, $author) !== false) {
                        $author_ids[] = $user->id;
                    }

                }
                $learning_tree_ids = $learning_tree_ids->whereIn('user_id', $author_ids);
            }


            $total_rows = $learning_tree_ids->count();

            $learning_tree_ids = $learning_tree_ids
                ->orderBy('id')
                ->skip($per_page * ($current_page - 1))
                ->take($per_page)
                ->get()
                ->sortBy('id')
                ->pluck('id')
                ->toArray();

            $learning_trees = DB::table('learning_trees')
                ->join('users', 'learning_trees.user_id', '=', 'users.id')
                ->select('learning_trees.id',
                    'user_id',
                    'title',
                    DB::raw('CONCAT(first_name, " ", last_name) AS author')
                )
                ->whereIn('learning_trees.id', $learning_tree_ids)
                ->get();

            $response['learning_trees'] = $learning_trees;
            $response['total_rows'] = $total_rows;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to retrieve all of the learning trees.  Please try again or contact us for assistance.";
        }

        return $response;

    }


    public function getLearningTreeByAssignmentQuestion()
    {
///after submitting, check if there's a learning tree
/// if there is


    }

    /**
     * @param CloneLearningTreesRequest $request
     * @param LearningTree $learningTree
     * @return array
     * @throws Exception
     */
    public function clone(CloneLearningTreesRequest $request,
                          LearningTree              $learningTree): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('clone', $learningTree);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {

            $request->validated();


            $learning_tree_ids = explode(',', $request->learning_tree_ids);
            DB::beginTransaction();
            foreach ($learning_tree_ids as $learning_tree_id) {
                $learning_tree_to_clone = LearningTree::find(trim($learning_tree_id))
                    ->replicate()
                    ->fill(['user_id' => $request->user()->id, 'public' => 0]);
                $learning_tree_to_clone->save();

                $learningTreeHistory = new LearningTreeHistory();
                $learningTreeHistory->learning_tree = $learning_tree_to_clone->learning_tree;
                $learningTreeHistory->learning_tree_id = $learning_tree_to_clone->id;
                $learningTreeHistory->root_node_question_id = $learning_tree_to_clone->root_node_question_id;
                $learningTreeHistory->save();

                $learning_tree_to_clone->current_history_id = $learningTreeHistory->id;
                $learning_tree_to_clone->save();

            }
            $plural = str_contains($request->learning_tree_ids, ',') ? "s have been" : ' was';
            $response['type'] = 'success';
            $response['message'] = "The learning tree$plural cloned.";

            DB::commit();
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error cloning the learning trees.  Please try again or contact us for assistance.";
        }
        return $response;
    }


    /**
     * @param Request $request
     * @param LearningTree $learningTree
     * @return array
     * @throws Exception
     */
    public function createLearningTreeFromTemplate(Request $request, LearningTree $learningTree): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('createLearningTreeFromTemplate', $learningTree);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $response['type'] = 'error';

        try {
            DB::beginTransaction();
            $new_learning_tree = $learningTree->replicate();
            $new_learning_tree->title = $new_learning_tree->title . ' copy';
            $new_learning_tree->save();


            $learningTreeHistory = new LearningTreeHistory();
            $learningTreeHistory->root_node_question_id = $new_learning_tree->root_node_question_id;
            $learningTreeHistory->learning_tree = $new_learning_tree->learning_tree;
            $learningTreeHistory->learning_tree_id = $new_learning_tree->id;
            $learningTreeHistory->save();

            $new_learning_tree->current_history_id = $learningTreeHistory->id;
            $new_learning_tree->save();

            DB::commit();
            $response['message'] = "The Learning Tree has been created.";
            $response['type'] = 'success';

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error creating a learning tree from this template.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public function learningTreeExists(Request $request)
    {
        $response['type'] = 'error';

        try {
            $learning_tree = DB::table('learning_trees')->where('id', $request->learning_tree_id)->first();
            if (!$learning_tree) {
                $response['message'] = "We were not able to locate that learning tree.";
                return $response;
            }
            if (!$learning_tree->learning_tree) {
                $response['message'] = "You cannot add an empty learning tree to an assignment.";
                return $response;
            }
            if (count(json_decode($learning_tree->learning_tree)->blocks) === 1) {
                $response['message'] = "Your learning tree only has a single node. Please add at least one branch before adding this to an assignment.";
                return $response;
            }
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error determining whether the learning tree exists.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    public function index(Request $request, LearningTree $learningTree)
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('index', $learningTree);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $response['type'] = 'error';

        try {
            $response['learning_trees'] = $learningTree->where('user_id', Auth::user()->id)->get();
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error retrieving your learning trees.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Request $request
     * @param LearningTree $learningTree
     * @param LearningTreeHistory $learningTreeHistory
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function updateLearningTree(Request             $request,
                                       LearningTree        $learningTree,
                                       LearningTreeHistory $learningTreeHistory,
                                       Question            $question): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('update', $learningTree);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        $response['type'] = 'error';
        $learning_tree_old = json_decode($learningTree->learning_tree, true);
        $learning_tree_parsed = str_replace('\"', "'", $request->learning_tree);
        $learning_tree_new = json_decode($learning_tree_parsed, true);
        $no_change = $learning_tree_old === $learning_tree_new;
        $question_types = $question->getQuestionTypes($request->question_ids);

        if ($no_change) {
            $response['type'] = 'no_change';
        } else {
            try {
                $learningTree->learning_tree = $learning_tree_parsed;
                $blocks = json_decode($learningTree->learning_tree, true)['blocks'][0]['data'];
                $root_node_question_id = null;
                foreach ($blocks as $block) {
                    if ($block['name'] === 'question_id') {
                        $root_node_question_id = $block['value'];
                    }
                }
                if (!$root_node_question_id) {
                    $response['message'] = 'We could not get data from the root node.  Please try again or contact us for assistance.';
                    return $response;
                }
                $learningTree->root_node_question_id = $root_node_question_id;

                DB::beginTransaction();
                $learningTree->save();
                $this->saveLearningTreeToHistory($learningTree->root_node_question_id,
                    $learningTree,
                    $learningTreeHistory);
                $response['type'] = 'success';
                $response['message'] = "The learning tree has been saved.";
                $response['question_types'] = $question_types;
                $response['no_change'] = false;
                $response['can_undo'] = $learningTreeHistory->where('learning_tree_id', $learningTree->id)->get()->count() > 1;
                $response['can_redo'] = false;
                DB::commit();
            } catch (Exception $e) {
                DB::rollback();
                $h = new Handler(app());
                $h->report($e);
                $response['message'] = "There was an error saving the learning tree.  Please try again or contact us for assistance.";
            }
        }
        return $response;

    }

    /**
     * EK: writes a new row to the append-only learning_tree_histories
     * log for this edit, then re-points learningTree.current_history_id
     * at the row that was just created.
     *
     * Because a real edit always moves the pointer to the newest row,
     * any older rows that a previous undo had left "ahead" of the
     * pointer are simply left behind — never pointed at again — which is
     * what makes redo naturally unavailable again after a new edit,
     * without needing to delete or flag anything in the history table.
     */
    public function saveLearningTreeToHistory(int $question_id, LearningTree $learningTree, LearningTreeHistory $learningTreeHistory)
    {
        $learningTreeHistory->root_node_question_id = $question_id;
        $learningTreeHistory->learning_tree_id = $learningTree->id;
        $learningTreeHistory->learning_tree = $learningTree->learning_tree;
        $learningTreeHistory->save();

        $learningTree->current_history_id = $learningTreeHistory->id;
        $learningTree->save();
    }

    /**
     * @param UpdateLearningTreeInfo $request
     * @param LearningTree $learningTree
     * @return array
     * @throws Exception
     */
    public function updateLearningTreeInfo(UpdateLearningTreeInfo $request, LearningTree $learningTree): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('update', $learningTree);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        $response['type'] = 'error';


        try {

            $data = $request->validated();
            $learningTree->title = $data['title'];
            $learningTree->description = $data['description'];
            $learningTree->public = $data['public'];
            $learningTree->notes = $request->notes;
            $learningTree->save();

            $response['type'] = 'success';
            $response['message'] = "The Learning Tree has been updated.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the learning tree.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param StoreLearningTreeInfo $request
     * @param LearningTree $learningTree
     * @return array
     * @throws Exception
     */
    public function storeLearningTreeInfo(StoreLearningTreeInfo $request,
                                          LearningTree          $learningTree): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('store', $learningTree);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        $response['type'] = 'error';

        try {

            $data = $request->validated();
            $learningTree->title = $data['title'];
            $learningTree->description = $data['description'];
            $learningTree->notes = $request->notes;
            $learningTree->public = $data['public'];
            $learningTree->user_id = request()->user()->id;
            $learningTree->root_node_question_id = 1;
            $learningTree->learning_tree = '';
            DB::beginTransaction();
            $learningTree->save();

            $response['type'] = 'success';
            $response['message'] = "The Learning Tree has been created.";
            $response['learning_tree_id'] = $learningTree->id;
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error saving the learning tree.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param $user_id
     * @param $question_id
     * @return Collection
     */
    public function getLearningTreeByUserAndQuestionId($user_id, $question_id): Collection
    {
        return DB::table('learning_trees')
            ->where('question_id', $question_id)
            ->where('user_id', $user_id)
            ->pluck('learning_tree');
    }

    /**
     * @param int $question_id
     * @return Collection
     */
    public function getDefaultLearningTreeByQuestionId(int $question_id): Collection
    {
        return DB::table('learning_trees')
            ->where('question_id', $question_id)
            ->orderBy('created_at', 'asc')
            ->pluck('learning_tree');
    }

    /**
     * @param LearningTree $learningTree
     * @param LearningTreeHistory $learningTreeHistory
     * @return array
     * @throws Exception
     */
    public function show(LearningTree        $learningTree,
                         LearningTreeHistory $learningTreeHistory): array
    {
        //anybody who is logged in can do this!
        $response['type'] = 'error';
        try {
            $response['learning_tree'] = $learningTree->learning_tree;
            $response['title'] = $learningTree->title;
            $response['description'] = $learningTree->description;
            $response['public'] = $learningTree->public;
            $response['author_id'] = $learningTree->user_id;
            $response['notes'] = $learningTree->user_id === request()->user()->id ? $learningTree->notes : '';

            $current_history_id = $learningTree->current_history_id;
            if (!$current_history_id) {
                // Tree predates the current_history_id column, or has no
                // history rows at all yet: fall back to "most recent row
                // is current," matching what the old count()>1 check
                // assumed.
                $latest = $learningTreeHistory->where('learning_tree_id', $learningTree->id)
                    ->orderBy('id', 'desc')
                    ->first();
                $current_history_id = $latest ? $latest->id : null;
            }
            $response['can_undo'] = $current_history_id
                ? $learningTreeHistory->where('learning_tree_id', $learningTree->id)
                    ->where('id', '<', $current_history_id)
                    ->exists()
                : false;
            $response['can_redo'] = $current_history_id
                ? $learningTreeHistory->where('learning_tree_id', $learningTree->id)
                    ->where('id', '>', $current_history_id)
                    ->exists()
                : false;

            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error retrieving the learning tree.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    public function getDefaultLearningTree()
    {
        return <<<EOT
{"html":"<div class="blockelem noselect block" style="left: 363px; top: 215px; border: 2px solid; color: rgb(18, 123, 196);"><input type="hidden" name="blockelemtype" class="blockelemtype" value="1"><input type="hidden" name="blockid" class="blockid" value="0"><div class="blockyleft"><p class="blockyname"><img src="/assets/img/adapt.svg">Assessment</p></div><div class="blockydiv"></div><div class="blockyinfo">The original question.</div></div><div class="indicator invisible" style="left: 154px; top: 119px;"></div>","blockarr":[{"childwidth":318,"parent":-1,"id":0,"x":825,"y":274,"width":318,"height":109}],"blocks":[{"id":0,"parent":-1,"data":[{"name":"blockelemtype","value":"1"},{"name":"blockid","value":"0"}],"attr":[{"class":"blockelem noselect block"},{"style":"left: 363px; top: 215px; border: 2px solid; color: rgb(18, 123, 196);"}]}]}
EOT;

    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function showByQuestion(Request $request, Question $question): array
    {
        //anybody who is logged in can do this!
        $response['type'] = 'error';
        try {
            $learning_tree = $this->getLearningTreeByUserAndQuestionId(Auth::user()->id, $question->id);

            if ($learning_tree->isEmpty()) {
                $learning_tree = $this->getDefaultLearningTreeByQuestionId($question->id);
            }

            if ($learning_tree->isEmpty()) {
                $learning_tree = $this->getDefaultLearningTree();
            }

            $response['type'] = 'success';
            $response['learning_tree'] = $learning_tree;

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error retrieving the learning tree.  Please try again or contact us for assistance.";
        }
        return $response;


    }


    /**
     * @param Request $request
     * @param LearningTree $learningTree
     * @param LearningTreeHistory $learningTreeHistory
     * @return array
     * @throws Exception
     */
    public function destroy(Request             $request,
                            LearningTree        $learningTree,
                            LearningTreeHistory $learningTreeHistory): array
    {
        //anybody who is logged in can do this!
        $response['type'] = 'error';
        $authorized = Gate::inspect('destroy', $learningTree);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $message = $learningTree->inAssignment($request, 'delete it');
            if ($message) {
                $response['message'] = $message;
                return $response;
            }
            DB::beginTransaction();
            $learningTree->learningTreeHistories()->delete();
            DB::table('branches')->where('learning_tree_id', $learningTree->id)
                ->where('user_id', $request->user()->id)
                ->delete();
            DB::table('learning_tree_node_learning_outcome')
                ->where('learning_tree_id', $learningTree->id)
                ->delete();
            DB::table('learning_tree_node_descriptions')
                ->where('learning_tree_id', $learningTree->id)
                ->delete();
            $learningTree->delete();
            DB::commit();
            $response['type'] = 'info';
            $response['message'] = "The Learning Tree has been deleted.";

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error deleting the learning Tree.  Please try again or contact us for assistance.";
        }
        return $response;


    }


    /**
     * @param Request $request
     * @param string $assignmentQuestionId
     * @param int $isRootNode
     * @return array
     * @throws Exception
     */
    public function validateRemediationByAssignmentQuestionId(Request $request,
                                                              string  $assignmentQuestionId,
                                                              int     $isRootNode): array
    {
        $response['type'] = 'error';
        $empty_learning_tree_node = false;
        DB::beginTransaction();
        try {
            if ($assignmentQuestionId === '0') {
                $new_question['title'] = 'Empty Learning Tree Node';
                $new_question['question_editor_user_id'] = $request->user()->id;
                $new_question['page_id'] = Question::max('page_id') + $request->user()->id;
                $new_question['public'] = 0;
                $new_question['cached'] = 1;
                $new_question['version'] = 1;
                $new_question['auto_attribution'] = 1;
                $new_question['non_technology'] = 1;
                $new_question['technology'] = 'text';
                $new_question['technology_iframe'] = '';
                $new_question['library'] = 'adapt';
                $new_question['non_technology_html'] = 'Empty Learning Tree Node';
                $new_question['author'] = "{$request->user()->first_name} {$request->user()->last_name}";
                $saved_questions_folder = DB::table('saved_questions_folders')
                    ->where('type', 'my_questions')
                    ->where('user_id', auth()->user()->id)
                    ->orderBy('id')
                    ->first();
                if (!$saved_questions_folder) {
                    $saved_questions_folder = new SavedQuestionsFolder();
                    $saved_questions_folder->user_id = $request->user()->id;
                    $saved_questions_folder->name = 'Main';
                    $saved_questions_folder->type = 'my_questions';
                    $saved_questions_folder->save();
                }
                $new_question['folder_id'] = $saved_questions_folder->id;
                $question = Question::create($new_question);
                $question->page_id = $question->id;
                $question->save();
                $emptyLearningTreeNode = new EmptyLearningTreeNode();
                $emptyLearningTreeNode->question_id = $question->id;
                $emptyLearningTreeNode->save();
                $empty_learning_tree_node = true;
            } else {
                $id_type = strpos($assignmentQuestionId, "-") !== false ? 'ADAPT' : 'Question';
                $question_id = $id_type === 'ADAPT'
                    ? substr($assignmentQuestionId, strpos($assignmentQuestionId, "-") + 1)
                    : $assignmentQuestionId;

                $question = Question::find($question_id);
                if (!$question) {
                    $response['message'] = "There is no question associated with ID $assignmentQuestionId.";
                    DB::rollback();
                    return $response;
                }
                if ($isRootNode && $question->technology === 'text') {
                    $response['message'] = "The root node in the assessment should have an auto-graded technology.";
                    DB::rollback();
                    return $response;
                }
            }
            DB::commit();
            $question->empty_learning_tree_node = $empty_learning_tree_node;
            $response['question'] = $question;
            $response['type'] = 'success';
        } catch (Exception $e) {
            if (DB::transactionLevel()) {
                DB::rollback();
            }
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error validating this node by assignment and question ID.";
        }
        return $response;

    }

}
