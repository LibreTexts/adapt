<?php

namespace Tests\Feature;

use App\LearningTree;
use App\QuestionChapter;
use App\QuestionSubject;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningTreeSubjectChapterSectionTest extends TestCase
{
    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create(['role' => 2]);
        $this->admin_user = factory(User::class)->create(['role' => 2, 'email' => 'me@me.com']);
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
        $this->learning_tree_info = [
            'title' => 'a learning tree',
            'description' => 'a description',
            'public' => 1
        ];
    }

    /** @test */
    public function saving_a_learning_tree_persists_subject_chapter_and_section()
    {
        $data = array_merge($this->learning_tree_info, [
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => $this->questionChapter->id,
            'question_section_id' => $this->question_section_id
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/learning-trees/info', $data)
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('learning_trees', [
            'title' => 'a learning tree',
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => $this->questionChapter->id,
            'question_section_id' => $this->question_section_id
        ]);
    }

    /** @test */
    public function updating_a_learning_tree_can_change_its_subject_chapter_and_section()
    {
        $learningTree = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => $this->questionChapter->id,
            'question_section_id' => $this->question_section_id
        ]);

        $question_subject_id_2 = DB::table('question_subjects')->insertGetId(['name' => 'a different subject']);

        $data = array_merge($this->learning_tree_info, [
            'question_subject_id' => $question_subject_id_2,
            'question_chapter_id' => null,
            'question_section_id' => null
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/learning-trees/info/{$learningTree->id}", $data)
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('learning_trees', [
            'id' => $learningTree->id,
            'question_subject_id' => $question_subject_id_2,
            'question_chapter_id' => null,
            'question_section_id' => null
        ]);
    }

    /**
     * EK: regression test for a gap found this session -
     * QuestionChapterController::destroy() originally only nulled out
     * question_chapter_id/question_section_id on the `questions` and
     * `question_revisions` tables, never on `learning_trees`, even though
     * learning_trees shares those same columns. Fixed by adding a
     * LearningTree::where('question_chapter_id', ...)->update([...null...])
     * call alongside the existing Question/QuestionRevision updates.
     *
     * @test
     */
    public function deleting_a_chapter_nulls_it_out_on_learning_trees_too()
    {
        $learningTree = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => $this->questionChapter->id,
            'question_section_id' => $this->question_section_id
        ]);

        $this->actingAs($this->admin_user)
            ->deleteJson("/api/question-chapters/{$this->questionChapter->id}")
            ->assertJson(['type' => 'info']);

        $this->assertDatabaseHas('learning_trees', [
            'id' => $learningTree->id,
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => null,
            'question_section_id' => null
        ]);
    }

    /**
     * EK: same gap as above, but for deleting the subject itself - also
     * fixed, in QuestionSubjectController::destroy().
     *
     * @test
     */
    public function deleting_a_subject_nulls_it_out_on_learning_trees_too()
    {
        $learningTree = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => $this->questionChapter->id,
            'question_section_id' => $this->question_section_id
        ]);

        $this->actingAs($this->admin_user)
            ->deleteJson("/api/question-subjects/{$this->questionSubject->id}")
            ->assertJson(['type' => 'info']);

        $this->assertDatabaseHas('learning_trees', [
            'id' => $learningTree->id,
            'question_subject_id' => null,
            'question_chapter_id' => null,
            'question_section_id' => null
        ]);
    }

    /**
     * EK: same gap, for deleting the section itself - fixed in
     * QuestionSectionController::destroy(). Unlike the subject/chapter
     * cases, deleting a section only nulls out question_section_id -
     * question_subject_id and question_chapter_id are left untouched,
     * since only the section itself becomes invalid.
     *
     * @test
     */
    public function deleting_a_section_nulls_it_out_on_learning_trees_too()
    {
        $learningTree = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => $this->questionChapter->id,
            'question_section_id' => $this->question_section_id
        ]);

        $this->actingAs($this->admin_user)
            ->deleteJson("/api/question-sections/{$this->question_section_id}")
            ->assertJson(['type' => 'info']);

        $this->assertDatabaseHas('learning_trees', [
            'id' => $learningTree->id,
            'question_subject_id' => $this->questionSubject->id,
            'question_chapter_id' => $this->questionChapter->id,
            'question_section_id' => null
        ]);
    }
}
