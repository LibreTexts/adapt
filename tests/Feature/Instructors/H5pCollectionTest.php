<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\AssignmentTemplate;
use App\Course;
use App\SavedQuestionsFolder;
use App\User;
use Tests\TestCase;

class H5pCollectionTest extends TestCase
{

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignment_template = factory(AssignmentTemplate::class)->create(['user_id' => $this->user->id]);
        $this->folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id]);
        $this->collection_info = [
            'collection' => 10029,
            'import_to_course' => $this->course->id,
            'assignment_template' => $this->assignment_template->id,
            'folder_id' => $this->folder->id];
    }

    #[Test]
    public function non_instructors_cannot_get_h5p_collections()
    {
        $this->user->role = 3;
        $this->user->save();
        $this->actingAs($this->user)->getJson("/api/h5p-collections")
            ->assertJson(['message' => 'You are not allowed to get the list of H5P collections.']);
    }

    #[Test]
    public function instructors_can_get_h5p_collections()
    {
        $this->actingAs($this->user)->getJson("/api/h5p-collections")
            ->assertJson(['type' => 'success']);

    }

    #[Test]
    public function must_be_a_valid_folder_id()
    {
        $this->collection_info['folder_id'] = $this->folder->id + 1;
        $this->actingAs($this->user)->postJson("/api/h5p-collections/validate-import", $this->collection_info)
            ->assertJson(['message' => 'You do not own that folder.']);

    }

    #[Test]
    public function must_be_a_valid_course_id()
    {

        $this->collection_info['import_to_course'] = $this->course->id + 1;
        $this->actingAs($this->user)->postJson("/api/h5p-collections/validate-import", $this->collection_info)
            ->assertJson(['message' => 'You do not own that course.']);

    }

    public function must_be_a_valid_assignment_template()
    {
        $this->collection_info['assignment_template'] = $this->assignment_template->id + 1;
        $this->actingAs($this->user)->postJson("/api/h5p-collections/validate-import", $this->collection_info)
            ->assertJson(['message' => 'You do not own that assignment template.']);

    }

    #[Test]
    public function assignment_is_created_from_template_if_given_template()
    {
        $this->actingAs($this->user)->postJson("/api/h5p-collections/validate-import", $this->collection_info);
        $this->assertDatabaseHas('assignments', [
            'course_id' => $this->course->id,
            'name' => 'Chapter 2: How We See the Invisible World'
        ]);
    }

}
