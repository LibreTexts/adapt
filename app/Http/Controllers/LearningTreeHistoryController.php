<?php

namespace App\Http\Controllers;

use App\Exceptions\Handler;
use App\LearningTree;
use App\LearningTreeHistory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LearningTreeHistoryController extends Controller
{
    /**
     * Undo: move learningTree.current_history_id back to the previous
     * row (by id) in the learning_tree_histories log for this tree, and
     * copy that row's snapshot onto the live tree. Nothing is deleted —
     * learning_tree_histories stays exactly as
     * LearningTreeController@saveLearningTreeToHistory left it.
     */
    public function updateLearningTreeFromHistory(LearningTree $learningTree,
                                                  LearningTreeHistory $learningTreeHistory)
    {
        $response['type'] = 'error';

        $authorized = Gate::inspect('updateLearningTreeFromHistory', [$learningTreeHistory, $learningTree]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $current_history_id = $this->getCurrentHistoryId($learningTree, $learningTreeHistory);

            $previous = $learningTreeHistory->where('learning_tree_id', $learningTree->id)
                ->where('id', '<', $current_history_id)
                ->orderBy('id', 'desc')
                ->first();

            if (!$previous) {
                $response['type'] = 'info';
                $response['message'] = 'There is nothing left to undo.';
                $response['can_undo'] = false;
                $response['can_redo'] = $this->hasNext($learningTree, $learningTreeHistory, $current_history_id);
                return $response;
            }

            DB::beginTransaction();
            $learningTree->learning_tree = $previous->learning_tree;
            $learningTree->current_history_id = $previous->id;
            $learningTree->save();
            DB::commit();

            $response['type'] = 'success';
            $response['can_undo'] = $this->hasPrevious($learningTree, $learningTreeHistory, $previous->id);
            $response['can_redo'] = true;

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the learning tree from your history.  Please try again or contact us for assistance.";
        }

        return $response;
    }

    /**
     * Redo: move learningTree.current_history_id forward to the next row
     * (by id) in the log, and copy that row's snapshot onto the live
     * tree. Only reachable when a previous undo left the pointer behind
     * the most recent row — any real edit since then appends a new row
     * and re-points current_history_id at it, which leaves nothing ahead
     * of the pointer and makes redo naturally unavailable again.
     */
    public function redoLearningTreeFromHistory(LearningTree $learningTree,
                                                LearningTreeHistory $learningTreeHistory)
    {
        $response['type'] = 'error';

        $authorized = Gate::inspect('updateLearningTreeFromHistory', [$learningTreeHistory, $learningTree]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $current_history_id = $this->getCurrentHistoryId($learningTree, $learningTreeHistory);

            $next = $learningTreeHistory->where('learning_tree_id', $learningTree->id)
                ->where('id', '>', $current_history_id)
                ->orderBy('id', 'asc')
                ->first();

            if (!$next) {
                $response['type'] = 'info';
                $response['message'] = 'There is nothing left to redo.';
                $response['can_redo'] = false;
                $response['can_undo'] = $this->hasPrevious($learningTree, $learningTreeHistory, $current_history_id);
                return $response;
            }

            DB::beginTransaction();
            $learningTree->learning_tree = $next->learning_tree;
            $learningTree->current_history_id = $next->id;
            $learningTree->save();
            DB::commit();

            $response['type'] = 'success';
            $response['can_undo'] = true;
            $response['can_redo'] = $this->hasNext($learningTree, $learningTreeHistory, $next->id);

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the learning tree from your history.  Please try again or contact us for assistance.";
        }

        return $response;
    }

    /**
     * current_history_id is nullable (existing trees predate this
     * column). Treat null as "pointing at the most recent history row,"
     * since that's what the tree's current learning_tree content
     * actually reflects for any tree saved before this pointer existed.
     */
    private function getCurrentHistoryId(LearningTree $learningTree, LearningTreeHistory $learningTreeHistory): ?int
    {
        if ($learningTree->current_history_id) {
            return $learningTree->current_history_id;
        }
        $latest = $learningTreeHistory->where('learning_tree_id', $learningTree->id)
            ->orderBy('id', 'desc')
            ->first();
        return $latest ? $latest->id : null;
    }

    private function hasPrevious(LearningTree $learningTree, LearningTreeHistory $learningTreeHistory, ?int $fromId): bool
    {
        if (!$fromId) {
            return false;
        }
        return $learningTreeHistory->where('learning_tree_id', $learningTree->id)
            ->where('id', '<', $fromId)
            ->exists();
    }

    private function hasNext(LearningTree $learningTree, LearningTreeHistory $learningTreeHistory, ?int $fromId): bool
    {
        if (!$fromId) {
            return false;
        }
        return $learningTreeHistory->where('learning_tree_id', $learningTree->id)
            ->where('id', '>', $fromId)
            ->exists();
    }
}
