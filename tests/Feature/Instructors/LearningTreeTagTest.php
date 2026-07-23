<?php

namespace Tests\Feature;

use App\LearningTree;
use App\Question;
use App\Tag;
use App\User;
use Tests\TestCase;

class LearningTreeTagTest extends TestCase
{
    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create(['role' => 2]);
        $this->learning_tree_info = [
            'title' => 'a learning tree',
            'description' => 'a description',
            'public' => 1
        ];
    }

    /** @test */
    public function saving_tags_creates_learning_tree_tag_rows_and_reuses_existing_tags()
    {
        $existing_tag = factory(Tag::class)->create(['tag' => 'existing tag']);

        $data = array_merge($this->learning_tree_info, ['tags' => ['existing tag', 'brand new tag']]);

        $this->actingAs($this->user)
            ->postJson('/api/learning-trees/info', $data)
            ->assertJson(['type' => 'success']);

        $learningTree = LearningTree::where('title', 'a learning tree')->first();

        $this->assertDatabaseHas('learning_tree_tag', [
            'learning_tree_id' => $learningTree->id,
            'tag_id' => $existing_tag->id
        ]);
        $this->assertDatabaseHas('tags', ['tag' => 'brand new tag']);
        // reused, not duplicated
        $this->assertDatabaseCount('tags', 2);
    }

    /** @test */
    public function removing_a_tag_deletes_it_from_the_shared_tags_table_if_nothing_else_uses_it()
    {
        $learningTree = factory(LearningTree::class)->create(['user_id' => $this->user->id]);
        $learningTree->addTags(['only used here']);
        $this->assertDatabaseHas('tags', ['tag' => 'only used here']);

        $data = array_merge($this->learning_tree_info, ['tags' => []]);
        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$learningTree->id}", $data)
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseMissing('tags', ['tag' => 'only used here']);
        $this->assertDatabaseMissing('learning_tree_tag', ['learning_tree_id' => $learningTree->id]);
    }

    /**
     * EK: this is the regression test for the bug found this session -
     * LearningTree::cleanUpTags() originally only checked learning_tree_tag
     * before deleting a shared tags row, so a tag still used by a Question
     * would get deleted the moment the last Learning Tree stopped using it
     * (SQLSTATE 23000 foreign key violation on question_tag, or silent data
     * loss if the FK constraint didn't exist). Fixed to also check
     * question_tag before deleting.
     *
     * @test
     */
    public function removing_a_tag_from_a_learning_tree_does_not_delete_it_if_a_question_still_uses_it()
    {
        $shared_tag = factory(Tag::class)->create(['tag' => 'shared tag']);
        $question = factory(Question::class)->create(['question_editor_user_id' => $this->user->id]);
        \Illuminate\Support\Facades\DB::table('question_tag')->insert([
            'question_id' => $question->id,
            'tag_id' => $shared_tag->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $learningTree = factory(LearningTree::class)->create(['user_id' => $this->user->id]);
        \Illuminate\Support\Facades\DB::table('learning_tree_tag')->insert([
            'learning_tree_id' => $learningTree->id,
            'tag_id' => $shared_tag->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $data = array_merge($this->learning_tree_info, ['tags' => []]);
        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$learningTree->id}", $data)
            ->assertJson(['type' => 'success']);

        // the pivot row for this tree should be gone...
        $this->assertDatabaseMissing('learning_tree_tag', ['learning_tree_id' => $learningTree->id]);
        // ...but the shared tag itself must survive, since the Question still uses it
        $this->assertDatabaseHas('tags', ['id' => $shared_tag->id, 'tag' => 'shared tag']);
        $this->assertDatabaseHas('question_tag', ['question_id' => $question->id, 'tag_id' => $shared_tag->id]);
    }

    /**
     * EK: mirror image of the above - a tag still used by a Learning Tree
     * must survive a Question's own cleanUpTags() call. Question.php's
     * cleanUpTags() needs the equivalent fix (check learning_tree_tag too),
     * matching what was done for LearningTree::cleanUpTags().
     *
     * @test
     */
    public function removing_a_tag_from_a_question_does_not_delete_it_if_a_learning_tree_still_uses_it()
    {
        $shared_tag = factory(Tag::class)->create(['tag' => 'shared tag']);
        $learningTree = factory(LearningTree::class)->create(['user_id' => $this->user->id]);
        \Illuminate\Support\Facades\DB::table('learning_tree_tag')->insert([
            'learning_tree_id' => $learningTree->id,
            'tag_id' => $shared_tag->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $question = factory(Question::class)->create(['question_editor_user_id' => $this->user->id]);
        $question->addTags(['shared tag']);
        $question->addTags([]); // re-save with no tags, triggers cleanUpTags()

        $this->assertDatabaseHas('tags', ['id' => $shared_tag->id, 'tag' => 'shared tag']);
        $this->assertDatabaseHas('learning_tree_tag', ['learning_tree_id' => $learningTree->id, 'tag_id' => $shared_tag->id]);
    }
}
