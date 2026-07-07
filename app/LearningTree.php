<?php

namespace App;

use App\Exceptions\TreeNotCreatedInAdaptException;
use App\Helpers\Helper;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LearningTree extends Model
{
    protected $guarded = [];

    /**
     * @return HasMany
     */
    public function learningTreeHistories(): HasMany
    {
        return $this->hasMany('App\LearningTreeHistory');
    }

    /**
     * @param array $learning_tree_assignments
     * @return array
     */
    public function getQuestionIdsInLearningTreeAssignments(array $learning_tree_assignments): array
    {
        $question_ids = [];
        $learning_tree_assignment_questions = DB::table('assignment_question')
            ->whereIn('assignment_id', $learning_tree_assignments)
            ->get();

        if ($learning_tree_assignment_questions) {
            foreach ($learning_tree_assignment_questions as $learning_tree_assignment_question) {
                $assignment_question_learning_tree = DB::table('assignment_question_learning_tree')
                    ->where('assignment_question_id', $learning_tree_assignment_question->id)
                    ->first();
                $learning_tree = LearningTree::find($assignment_question_learning_tree->learning_tree_id)->learning_tree;
                $learning_tree_arr = json_decode($learning_tree, 1);
                $blocks = $learning_tree_arr['blocks'];
                foreach ($blocks as $block) {
                    foreach ($block['data'] as $block_data) {
                        if ($block_data['name'] === 'question_id') {
                            $question_ids[] = +trim($block_data['value']);
                        }
                    }
                }
            }
        }
        return $question_ids;
    }

    /**
     * @param array $blocks
     * @param int $question_id
     * @return mixed
     * @throws Exception
     */
    public function learningNodeBlockByQuestionId(array $blocks, int $question_id)
    {
        foreach ($blocks as $block) {
            foreach ($block->data as $data) {
                if ($data->name === 'question_id' && (int)$data->value === $question_id) {
                    return $block;
                }
            }
        }
        throw new Exception ("There is no block with that question id.");
    }

    /**
     * @param object $block
     * @return object
     * @throws Exception
     */
    public function learningNodeParentAndQuestionIdByBlock(object $block): object
    {
        $question_id = 0;
        foreach ($block->data as $data) {
            if ($data->name === 'question_id') {
                $question_id = (int)$data->value;
            }
        }
        if (!$question_id) {
            throw new Exception("Error getting parent and question id from block.");
        }
        return (object)['parent' => $block->parent, 'question_id' => $question_id];
    }

    /**
     * @throws Exception
     */
    public function getParentsQuestionIdsByQuestionId(int $question_id): array
    {

        $blocks = json_decode($this->learning_tree)->blocks;
        $learning_node_question_id_by_block_id = [];
        foreach ($blocks as $block) {
            $learning_node_question_id_by_block_id[$block->id] = $this->learningNodeParentAndQuestionIdByBlock($block);
        }

        $learning_node_block = $this->learningNodeBlockByQuestionId($blocks, $question_id);

        $parents_by_question_id = [];
        while ($learning_node_block->parent !== -1) {
            $parent = $learning_node_block->parent;
            $parents_by_question_id[] = $learning_node_question_id_by_block_id[$parent]->question_id;
            $learning_node_block = $learning_node_question_id_by_block_id[$learning_node_block->parent];
        }
        return $parents_by_question_id;
    }

    public
    function getBranchStructure(): array
    {
        $blocks = json_decode($this->learning_tree)->blocks;

        $branches = [];
        foreach ($blocks as $block) {
            if ($block->parent === 0) {
                $branches[] = $block->id;
            }
        }
        $twigs_by_branch_id = [];
        foreach ($branches as $branch) {
            $twigs_by_branch_id[$branch] = [$branch];
            foreach ($blocks as $block) {
                if (in_array($block->parent, $twigs_by_branch_id[$branch])) {
                    $twigs_by_branch_id[$branch][] = $block->id;
                }
            }
        }
        return $twigs_by_branch_id;

    }

    /**
     * @param array $learning_tree_branch_structure
     * @param int $instructor_id
     * @return array
     * @throws TreeNotCreatedInAdaptException
     */
    public
    function getBranchAndTwigInfo(array $learning_tree_branch_structure, int $instructor_id): array
    {
        $branches_with_question_info = [];
        $blocks = json_decode($this->learning_tree)->blocks;
        foreach ($learning_tree_branch_structure as $branch => $twigs) {
            foreach ($twigs as $twig) {
                foreach ($blocks as $block) {
                    if ($block->id === $twig) {
                        $branches_with_question_info[$branch][$twig] = [
                            'id' => $twig,
                            'question_id' => $this->getBlockInfoByKey($block, 'question_id')
                        ];
                    }
                }
            }
        }
        $questions = DB::table('questions');
        foreach ($branches_with_question_info as $twigs) {
            foreach ($twigs as $twig) {
                $questions = $questions->orWhere(function ($query) use ($twig) {
                    $query->where('id', $twig['question_id']);
                });
            }
        }

        $questions = $questions->select('questions.id', 'title', 'technology')->get();

        $question_ids = [];
        foreach ($branches_with_question_info as $key => $twigs) {
            foreach ($twigs as $twig) {
                foreach ($questions as $question) {
                    $question_ids[] = $question->id;
                    if ((int)$question->id === (int)$twig['question_id']) {
                        $branches_with_question_info[$key][$twig['id']]['question_info'] = $question;
                    }
                }
            }
        }


        $branch_descriptions = DB::table('branches')
            ->whereIn('question_id', $question_ids)
            ->where('learning_tree_id', $this->id)
            ->where('user_id', $instructor_id)
            ->select('question_id', 'description')
            ->get();

        $branch_descriptions_by_question_id = [];
        foreach ($branch_descriptions as $branch_description) {
            $branch_descriptions_by_question_id[$branch_description->question_id] = $branch_description->description;
        }

        $branch_and_twig_info = [];
        foreach ($branches_with_question_info as $branch_id => $twigs) {

            $branch_and_twig_info[$branch_id] = [];
            $branch_and_twig_info[$branch_id]['twigs'] = $twigs;
            //get the description or use the first twig which is the branch
            if (!isset($twigs[key($twigs)]['question_info'])) {
                throw new TreeNotCreatedInAdaptException("Learning Tree $this->id has remediation nodes that were not created in ADAPT.  Please move them to ADAPT and try again.");
            }

            ///get number of assessments and non-assessments
            $num_assessments = 0;
            foreach ($twigs as $twig_id => $twig) {
                if ($twig['question_info']->technology !== 'text') {
                    $num_assessments++;
                }
                $question_id = $branch_and_twig_info[$branch_id]['twigs'][$twig_id]['question_info']->id;
                $title = $branch_and_twig_info[$branch_id]['twigs'][$twig_id]['question_info']->title;
                $branch_and_twig_info[$branch_id]['twigs'][$twig_id]['question_info']->description = $branch_descriptions_by_question_id[$question_id] ?? $title;

            }

            $branch_and_twig_info[$branch_id]['id'] = $branch_id;
            $branch_and_twig_info[$branch_id]['assessments'] = $num_assessments;
            $branch_and_twig_info[$branch_id]['expositions'] = count($twigs) - $num_assessments;
        }

        return array_values($branch_and_twig_info);
    }

    /**
     * @param $block
     * @param $key
     * @return string
     */
    public
    function getBlockInfoByKey($block, $key): string
    {
        foreach ($block->data as $data) {
            if ($data->name === $key) {
                return $data->value;
            }
        }
        return "Key does not exist";
    }

    /**
     * @return array
     */
    public
    function questionIds(): array
    {
        $question_ids = [];
        $blocks = json_decode($this->learning_tree)->blocks;
        foreach ($blocks as $block) {
            foreach ($block->data as $item) {

                if ($item->name === 'question_id') {
                    $question_ids[] = (int) trim($item->value);
                }
            }

        }

        return $question_ids;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function nodeParents(): array
    {
        $learning_tree_node_parents = [];
        foreach ($this->questionIds() as $question_id) {
            if ($question_id !== $this->root_node_question_id) {
                $learning_tree_node_parents[$question_id] = $this->getParentsQuestionIdsByQuestionId($question_id);
            }
        }
        return $learning_tree_node_parents;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function finalQuestionIds(): array
    {

        $learning_tree_node_parents = $this->nodeParents();

        $question_ids_that_are_parents = [];
        foreach ($learning_tree_node_parents as $learning_tree_node_parent) {
            foreach ($learning_tree_node_parent as $question_id)
                $question_ids_that_are_parents[] = $question_id;
        }
        $question_ids_that_are_parents = array_unique($question_ids_that_are_parents);
        $final_question_ids = [];
        foreach ($this->questionIds() as $question_id) {
            if (!in_array($question_id, $question_ids_that_are_parents)) {
                $final_question_ids[] = $question_id;
            }
        }
        return $final_question_ids;

    }

    /**
     * @param Request $request
     * @param string $action
     * @return string
     */
    public function inAssignment(Request $request,  string $action): string
    {

        if (Helper::isAdmin()) {
            // return false;
        }
        $assignment_learning_tree_info = DB::table('assignment_question_learning_tree')
            ->where('learning_tree_id', $this->id)
            ->first();

        if (!$assignment_learning_tree_info) {
            return '';
        }

        $assignment_info = DB::table('assignment_question_learning_tree')
            ->join('assignment_question', 'assignment_question_id', '=', 'assignment_question.id')
            ->join('assignments', 'assignment_id', '=', 'assignments.id')
            ->join('courses', 'course_id', '=', 'courses.id')
            ->join('users', 'user_id', '=', 'users.id')
            ->where('assignment_question_id', $assignment_learning_tree_info->assignment_question_id)
            ->select('users.id',
                DB::raw('assignments.name AS assignment'),
                DB::raw('courses.name AS course')
            )
            ->first();


        return
            ($assignment_info->id === $request->user()->id)
                ? "It looks like you're using this Learning Tree in $assignment_info->course --- $assignment_info->assignment.  Please first remove that question from the assignment before attempting to $action."
                : "It looks like another instructor is using this Learning Tree so you won't be able to $action.";

    }

}
