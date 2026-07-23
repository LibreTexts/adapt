<?php

namespace App\Http\Controllers;

use App\Exceptions\Handler;
use App\FrameworkDescriptor;
use App\FrameworkItemSyncLearningTree;
use App\LearningTree;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;


class FrameworkItemSyncLearningTreeController extends Controller
{
    /**
     * @param FrameworkDescriptor $frameworkDescriptor
     * @param FrameworkItemSyncLearningTree $frameworkItemSyncLearningTree
     * @return array
     * @throws Exception
     */
    public function getLearningTreesByDescriptor(FrameworkDescriptor            $frameworkDescriptor,
                                                 FrameworkItemSyncLearningTree $frameworkItemSyncLearningTree): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getLearningTreesByDescriptor', $frameworkItemSyncLearningTree);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $learning_trees_synced_to_descriptor = DB::table('framework_item_learning_tree')
                ->join('learning_trees', 'framework_item_learning_tree.learning_tree_id', '=', 'learning_trees.id')
                ->where('framework_item_type', 'descriptor')
                ->where('framework_item_id', $frameworkDescriptor->id)
                ->get();
            $response['learning_trees_synced_to_descriptor'] = $learning_trees_synced_to_descriptor;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the descriptors for this learning tree.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param LearningTree $learningTree
     * @param FrameworkItemSyncLearningTree $frameworkItemSyncLearningTree
     * @return array
     * @throws Exception
     */
    public function getFrameworkItemsByLearningTree(LearningTree                   $learningTree,
                                                    FrameworkItemSyncLearningTree $frameworkItemSyncLearningTree): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getFrameworkItemsByLearningTree', $frameworkItemSyncLearningTree);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $descriptors = DB::table('framework_item_learning_tree')
                ->join('framework_descriptors', 'framework_item_learning_tree.framework_item_id', '=', 'framework_descriptors.id')
                ->where('framework_item_type', 'descriptor')
                ->where('learning_tree_id', $learningTree->id)
                ->select('framework_descriptors.id', 'framework_descriptors.descriptor AS text')
                ->get();
            $descriptors_by_id = [];
            foreach ($descriptors as $descriptor) {
                $descriptors_by_id[] = ['id' => $descriptor->id,
                    'text' => $descriptor->text];
            }

            $framework_levels = DB::table('framework_item_learning_tree')
                ->join('framework_levels', 'framework_item_learning_tree.framework_item_id', '=', 'framework_levels.id')
                ->where('framework_item_type', 'level')
                ->where('learning_tree_id', $learningTree->id)
                ->select('framework_levels.id', 'framework_levels.title AS text')
                ->get();
            $framework_levels_by_id = [];
            foreach ($framework_levels as $framework_level) {
                $framework_levels_by_id[] = ['id' => $framework_level->id,
                    'text' => $framework_level->text];
            }
            $response['type'] = 'success';
            $response['framework_item_sync_learning_tree'] = [
                'descriptors' => $descriptors_by_id,
                'levels' => $framework_levels_by_id
            ];
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error get the framework alignments for this learning tree.  Please try again or contact us for assistance.";
        }
        return $response;

    }

}
