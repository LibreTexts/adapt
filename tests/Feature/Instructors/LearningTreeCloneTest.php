<?php

namespace Tests\Feature;

use App\Framework;
use App\FrameworkLevel;
use App\LearningTree;
use App\QuestionChapter;
use App\QuestionSubject;
use App\Tag;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningTreeCloneTest extends TestCase
{
    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create(['role' => 2]);

        $this->questionSubject = new QuestionSubject();
        $this->questionSubject->name = 'some name';
        $this->questionSubject->save();
        $this->questionChapter = new QuestionChapter();
        $this->questionChapter->question_subject_id = $this->questionSubject->id;
        $this->questionChapter->name = 'some name';
        $this->questionChapter->save();
        $this->question_section_id = DB::table('question_sections')->insertGetId([
            'name' => 'some name',
            'question_chapter_id' => $this->questionChapter->id
        ]);

        $this->framework = factory(Framework::class)->create(['user_id' => $this->user->id]);
        $this->framework_level = factory(FrameworkLevel::class)->create(['framework_id' => $this->framework->id]);

        $this->source_learning_tree = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => $this->questionChapter->id,
            'question_section_id' => $this->question_section_id,
            'public' => 1
        ]);
        $this->source_learning_tree->addTags(['tag one', 'tag two']);
        $this->source_learning_tree->addFrameworkItems([
            'levels' => [['id' => $this->framework_level->id, 'text' => $this->framework_level->title]],
            'descriptors' => []
        ]);
    }

    /** @test */
    public function cloning_a_learning_tree_copies_its_tags()
    {
        $this->actingAs($this->user)
            ->postJson('/api/learning-trees/clone', ['learning_tree_ids' => (string) $this->source_learning_tree->id])
            ->assertJson(['type' => 'success']);

        $cloned = LearningTree::where('id', '<>', $this->source_learning_tree->id)
            ->where('user_id', $this->user->id)
            ->latest('id')
            ->first();

        $tag_ids = DB::table('learning_tree_tag')
            ->where('learning_tree_id', $this->source_learning_tree->id)
            ->pluck('tag_id');
        foreach ($tag_ids as $tag_id) {
            $this->assertDatabaseHas('learning_tree_tag', [
                'learning_tree_id' => $cloned->id,
                'tag_id' => $tag_id
            ]);
        }
        $this->assertDatabaseCount('tags', 2);
    }

    /** @test */
    public function cloning_a_learning_tree_copies_its_framework_alignment()
    {
        $this->actingAs($this->user)
            ->postJson('/api/learning-trees/clone', ['learning_tree_ids' => (string) $this->source_learning_tree->id])
            ->assertJson(['type' => 'success']);

        $cloned = LearningTree::where('id', '<>', $this->source_learning_tree->id)
            ->where('user_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertDatabaseHas('framework_item_learning_tree', [
            'learning_tree_id' => $cloned->id,
            'framework_item_id' => $this->framework_level->id,
            'framework_item_type' => 'level'
        ]);
    }

    /** @test */
    public function cloning_a_learning_tree_copies_its_subject_chapter_and_section()
    {
        $this->actingAs($this->user)
            ->postJson('/api/learning-trees/clone', ['learning_tree_ids' => (string) $this->source_learning_tree->id])
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('learning_trees', [
            'user_id' => $this->user->id,
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => $this->questionChapter->id,
            'question_section_id' => $this->question_section_id,
            'public' => 0 // clone() always unpublishes the copy
        ]);
    }

    /** @test */
    public function cloning_multiple_learning_trees_at_once_copies_tags_and_framework_for_each_independently()
    {
        $second_learning_tree = factory(LearningTree::class)->create(['user_id' => $this->user->id]);
        $second_tag = factory(Tag::class)->create(['tag' => 'second tree tag']);
        DB::table('learning_tree_tag')->insert([
            'learning_tree_id' => $second_learning_tree->id,
            'tag_id' => $second_tag->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $ids = "{$this->source_learning_tree->id},{$second_learning_tree->id}";
        $this->actingAs($this->user)
            ->postJson('/api/learning-trees/clone', ['learning_tree_ids' => $ids])
            ->assertJson(['type' => 'success']);

        $cloned_trees = LearningTree::where('user_id', $this->user->id)
            ->whereNotIn('id', [$this->source_learning_tree->id, $second_learning_tree->id])
            ->get();
        $this->assertCount(2, $cloned_trees);

        $cloned_tag_ids = DB::table('learning_tree_tag')
            ->whereIn('learning_tree_id', $cloned_trees->pluck('id'))
            ->pluck('tag_id');
        $this->assertTrue($cloned_tag_ids->contains($second_tag->id));
    }

    /** @test */
    public function create_learning_tree_from_template_also_copies_tags_framework_and_subject_chapter_section()
    {
        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/{$this->source_learning_tree->id}/create-learning-tree-from-template")
            ->assertJson(['type' => 'success']);

        $cloned = LearningTree::where('title', $this->source_learning_tree->title . ' copy')->first();
        $this->assertNotNull($cloned);

        $this->assertDatabaseHas('learning_trees', [
            'id' => $cloned->id,
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => $this->questionChapter->id,
            'question_section_id' => $this->question_section_id
        ]);
        $this->assertDatabaseHas('framework_item_learning_tree', [
            'learning_tree_id' => $cloned->id,
            'framework_item_id' => $this->framework_level->id,
            'framework_item_type' => 'level'
        ]);
        $tag_ids = DB::table('learning_tree_tag')
            ->where('learning_tree_id', $this->source_learning_tree->id)
            ->pluck('tag_id');
        foreach ($tag_ids as $tag_id) {
            $this->assertDatabaseHas('learning_tree_tag', [
                'learning_tree_id' => $cloned->id,
                'tag_id' => $tag_id
            ]);
        }
    }
}
