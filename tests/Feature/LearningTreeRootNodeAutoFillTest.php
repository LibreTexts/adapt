<?php

namespace Tests\Feature;

use App\Framework;
use App\FrameworkLevel;
use App\LearningTree;
use App\Question;
use App\QuestionChapter;
use App\QuestionSubject;
use App\Tag;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningTreeRootNodeAutoFillTest extends TestCase
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

        $this->root_question = factory(Question::class)->create(['question_editor_user_id' => $this->user->id]);
        $this->root_question->question_subject_id = $this->questionSubject->id;
        $this->root_question->question_chapter_id = $this->questionChapter->id;
        $this->root_question->question_section_id = $this->question_section_id;
        $this->root_question->save();
        $this->root_question->addTags(['question tag one', 'question tag two']);
        $this->root_question->addFrameworkItems([
            'levels' => [['id' => $this->framework_level->id, 'text' => $this->framework_level->title]],
            'descriptors' => []
        ]);

        $this->learningTree = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            'root_node_question_id' => $this->root_question->id
        ]);

        $this->base_update_data = [
            'title' => $this->learningTree->title,
            'description' => $this->learningTree->description,
            'public' => 1,
            'root_node_question_changed' => true
        ];
    }

    /** @test */
    public function it_does_nothing_when_root_node_question_changed_is_not_sent()
    {
        // same data, but WITHOUT the flag - normal Tree Properties save
        $data = $this->base_update_data;
        unset($data['root_node_question_changed']);

        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$this->learningTree->id}", $data)
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseMissing('learning_tree_tag', ['learning_tree_id' => $this->learningTree->id]);
        $this->assertDatabaseMissing('framework_item_learning_tree', ['learning_tree_id' => $this->learningTree->id]);
        $this->assertDatabaseHas('learning_trees', [
            'id' => $this->learningTree->id,
            'question_subject_id' => null,
            'question_chapter_id' => null,
            'question_section_id' => null
        ]);
    }

    /** @test */
    public function it_copies_tags_from_the_root_question_when_the_tree_has_none()
    {
        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$this->learningTree->id}", $this->base_update_data)
            ->assertJson(['type' => 'success']);

        $question_tag_ids = DB::table('question_tag')
            ->where('question_id', $this->root_question->id)
            ->pluck('tag_id');
        foreach ($question_tag_ids as $tag_id) {
            $this->assertDatabaseHas('learning_tree_tag', [
                'learning_tree_id' => $this->learningTree->id,
                'tag_id' => $tag_id
            ]);
        }
    }

    /** @test */
    public function it_does_not_overwrite_existing_tags_on_the_tree()
    {
        $existing_tag = factory(Tag::class)->create(['tag' => 'tree already has this']);
        DB::table('learning_tree_tag')->insert([
            'learning_tree_id' => $this->learningTree->id,
            'tag_id' => $existing_tag->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$this->learningTree->id}", $this->base_update_data)
            ->assertJson(['type' => 'success']);

        // tree's own tag survives...
        $this->assertDatabaseHas('learning_tree_tag', [
            'learning_tree_id' => $this->learningTree->id,
            'tag_id' => $existing_tag->id
        ]);
        // ...and the question's tags were NOT pulled in
        $this->assertDatabaseCount('learning_tree_tag', 1);
    }

    /** @test */
    public function it_copies_framework_alignment_from_the_root_question_when_the_tree_has_none()
    {
        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$this->learningTree->id}", $this->base_update_data)
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('framework_item_learning_tree', [
            'learning_tree_id' => $this->learningTree->id,
            'framework_item_id' => $this->framework_level->id,
            'framework_item_type' => 'level'
        ]);
    }

    /** @test */
    public function it_does_not_overwrite_existing_framework_alignment_on_the_tree()
    {
        $other_framework_level = factory(FrameworkLevel::class)->create(['framework_id' => $this->framework->id]);
        DB::table('framework_item_learning_tree')->insert([
            'learning_tree_id' => $this->learningTree->id,
            'framework_item_id' => $other_framework_level->id,
            'framework_item_type' => 'level',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$this->learningTree->id}", $this->base_update_data)
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('framework_item_learning_tree', [
            'learning_tree_id' => $this->learningTree->id,
            'framework_item_id' => $other_framework_level->id
        ]);
        $this->assertDatabaseCount('framework_item_learning_tree', 1);
    }

    /** @test */
    public function it_copies_subject_chapter_and_section_from_the_root_question_when_the_tree_has_none()
    {
        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$this->learningTree->id}", $this->base_update_data)
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('learning_trees', [
            'id' => $this->learningTree->id,
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => $this->questionChapter->id,
            'question_section_id' => $this->question_section_id
        ]);
    }

    /**
     * EK: subject/chapter/section is all-or-nothing - if the tree has ANY
     * of the three set, none are auto-filled, even if the other two are
     * empty. This is different from tags/framework, which are independent.
     *
     * @test
     */
    public function it_does_not_auto_fill_subject_chapter_section_if_the_tree_already_has_any_one_of_them()
    {
        $this->learningTree->question_subject_id = $this->questionSubject->id;
        $this->learningTree->save();
        // chapter and section are still null on the tree

        $data = array_merge($this->base_update_data, [
            'question_subject_id' => $this->questionSubject->id
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$this->learningTree->id}", $data)
            ->assertJson(['type' => 'success']);

        // chapter/section should NOT have been pulled from the question,
        // since the tree already had a subject set
        $this->assertDatabaseHas('learning_trees', [
            'id' => $this->learningTree->id,
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => null,
            'question_section_id' => null
        ]);
    }

    /** @test */
    public function it_does_nothing_if_the_root_question_itself_has_no_tags_framework_or_subject_chapter_section()
    {
        $bare_question = factory(Question::class)->create(['question_editor_user_id' => $this->user->id]);
        $this->learningTree->root_node_question_id = $bare_question->id;
        $this->learningTree->save();

        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$this->learningTree->id}", $this->base_update_data)
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseMissing('learning_tree_tag', ['learning_tree_id' => $this->learningTree->id]);
        $this->assertDatabaseMissing('framework_item_learning_tree', ['learning_tree_id' => $this->learningTree->id]);
        $this->assertDatabaseHas('learning_trees', [
            'id' => $this->learningTree->id,
            'question_subject_id' => null,
            'question_chapter_id' => null,
            'question_section_id' => null
        ]);
    }
}
