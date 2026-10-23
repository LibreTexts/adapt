<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Question;
use App\SavedQuestionsFolder;
use App\SubmissionFile;
use App\Submission;
use App\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

class QuestionsGetTest extends TestCase
{

    /**Still must test the stuff with the correct/completed and number**/
    private $assignment_remixer;
    /**
     * @var Collection|Model|mixed
     */
    private $assignment;
    /**
     * @var Collection|Model|mixed
     */
    private $user_2;
    /**
     * @var Collection|Model|mixed
     */
    private $user;
    /**
     * @var Collection|Model|mixed
     */
    private $course;
    /**
     * @var Collection|Model|mixed
     */
    private $question;
    /**
     * @var Collection|Model|mixed
     */
    private $student_user;

    /** Should test that only an instructor can create an assignment... */
    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        factory(SavedQuestionsFolder::class)->create(['type' => 'my_questions',
            'name' => 'Main', 'user_id' => $this->user->id]);
        $this->beta_user = factory(User::class)->create();
        $this->user_2 = factory(User::class)->create();
        $this->user_2->role = 3;
        $this->user_2->save();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->beta_course = factory(Course::class)->create(['user_id' => $this->beta_user->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);

        $this->beta_assignment = factory(Assignment::class)->create(['course_id' => $this->beta_course->id]);

        DB::table('beta_assignments')->insert(['id' => $this->beta_assignment->id, 'alpha_assignment_id' => $this->assignment->id]);

        $this->question = factory(Question::class)->create(['page_id' => 24241]);

        $this->assignment_remixer = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        factory(Question::class)->create(['library' => 'chem', 'page_id' => 265531]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment_remixer->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => '0'
        ]);

        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;
        $this->student_user->save();
        $this->submission_file = [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'type' => 'q',
            'user_id' => $this->student_user->id,
            'original_filename' => 'blah blah',
            'submission' => 'sflkjfwlKEKLie.jpg',
            'score' => '0.00',
            'date_submitted' => Carbon::now()
        ];
        $this->h5pSubmission = [
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'score' => '0.00',
            'answered_correctly_at_least_once' => 0,
            'submission_count' => 1,
            'submission' => '{"actor":{"account":{"name":"5038b12a-1181-4546-8735-58aa9caef971","homePage":"https://h5p.libretexts.org"},"objectType":"Agent"},"verb":{"id":"http://adlnet.gov/expapi/verbs/answered","display":{"en-US":"answered"}},"object":{"id":"https://h5p.libretexts.org/wp-admin/admin-ajax.php?action=h5p_embed&id=97","objectType":"Activity","definition":{"extensions":{"http://h5p.org/x-api/h5p-local-content-id":97},"name":{"en-US":"1.3 Actividad # 5: comparativos y superlativos"},"interactionType":"fill-in","type":"http://adlnet.gov/expapi/activities/cmi.interaction","description":{"en-US":"<p><strong>Instrucciones: Ponga las palabras en orden. Empiece con el sujeto de la oración.</strong></p>\n<br/>1. de todas las universidades californianas / la / antigua / es / La Universidad del Pacífico / más <br/>__________ __________ __________ __________ __________ __________.<br/><br/>2. el / UC Merced / número de estudiantes / tiene / menor<br/>__________ __________ __________ __________ __________."},"correctResponsesPattern":["La Universidad del Pacífico[,]es[,]la[,]más[,]antigua[,]de todas las universidades californianas[,]UC Merced[,]tiene[,]el[,]menor[,]número de estudiantes"]}},"context":{"contextActivities":{"category":[{"id":"http://h5p.org/libraries/H5P.DragText-1.8","objectType":"Activity"}]}},"result":{"response":"[,][,][,][,][,][,][,]antigua[,][,][,]","score":{"min":0,"raw":11,"max":11,"scaled":0},"duration":"PT3.66S","completion":true}}'
        ];

    }
    #[Test]
    public function heck_for_discuss_it_questions_over_course_or_assignment_should_be_course_or_assignment()
    {
        $this->actingAs($this->user)
            ->getJson("/api/assignment-sync-question/discuss-it-clicker-or-open-ended-questions-by-course-or-assignment/blah/1")
            ->assertJson(['message' => 'There was an error determining whether discuss it questions exist in the blah.  Please try again or contact us for assistance.']);

    }

    #[Test]
    public function non_instructor_cannot_check_for_discuss_it_questions_over_course_or_assignment()
    {
        $this->actingAs($this->user_2)
            ->getJson("/api/assignment-sync-question/discuss-it-clicker-or-open-ended-questions-by-course-or-assignment/assignment/1")
            ->assertJson(['message' => 'You are not allowed to check for Discuss-it questions by course or assignment.']);

    }
    #[Test]
    public function non_instructor_cannot_check_for_discuss_it_questions_over_multiple_assignment_questions()
    {
        $this->actingAs($this->user_2)
            ->postJson("/api/assignment-sync-question/check-for-discuss-it-questions-over-multiple-assignment-questions",[])
            ->assertJson(['message' => 'You are not allowed to check for Discuss-it questions over multiple assignment questions.']);

    }

    #[Test]
    public function cannot_remix_assignment_of_based_on_weights_and_submissions_exist()
    {

        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 'none'
        ]);

        Submission::create([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'some other submission',
            'score' => 1,
            'answered_correctly_at_least_once' => 0,
            'submission_count' => 1]);
        $this->assignment->points_per_question = "question weight";
        $this->assignment->save();
        $data['chosen_questions'] = [
            ['question_id' => $this->question->id,
                'assignment_id' => $this->assignment_remixer->id]
        ];
        $data['question_source'] = 'my_courses';
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/remix-assignment-with-chosen-questions",
            $data)
            ->assertJson(['message' => 'You cannot access the remixer since there are already submissions and this assignment computes points using question weights.']);

    }

    #[Test]
    public function cannot_update_submit_work_override_if_not_owner()
    {
        $this->actingAs($this->user_2)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/can-submit-work-override/1")
            ->assertJson(['message' => 'You are not allowed update the can submit work override for this question.']);
    }

    #[Test]
    public function cannot_remove_a_question_from_an_assignment_if_there_are_submissions_and_using_weights()
    {
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 'none'
        ]);

        Submission::create([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'some other submission',
            'score' => 1,
            'answered_correctly_at_least_once' => 0,
            'submission_count' => 1]);
        $this->assignment->points_per_question = "question weight";
        $this->assignment->save();

        $this->actingAs($this->user)->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}")
            ->assertJson(['message' => 'You cannot remove this question since there are already submissions and this assignment computes points using question weights.']);
    }

    #[Test]
    public function cannot_update_weight_if_student_made_a_submission()
    {
        Submission::create($this->h5pSubmission);
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-weight",
            ['weight' => 10])
            ->assertJson(['type' => 'error',
                'message' => "This cannot be updated since students have already submitted responses to this assignment."]);

    }

    #[Test]
    public function cannot_update_weight_if_not_owner()
    {
        $this->actingAs($this->user_2)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-weight",
            ['weight' => 10])
            ->assertJson(['message' => 'You are not allowed to update that resource.']);
    }

    #[Test]
    public function cannot_update_the_weight_if_randomized()
    {
        $this->assignment->number_of_randomized_assessments = 2;
        $this->assignment->save();
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-weight",
            ['weight' => '2'])
            ->assertJson(['message' => 'Weights for randomized assignments cannot be altered.']);

    }

    #[Test]
    public function weight_must_be_valid()
    {
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-weight",
            ['weight' => ''])
            ->assertJsonValidationErrors('weight');

        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-weight",
            ['weight' => -1])
            ->assertJsonValidationErrors('weight');
    }

    #[Test]
    public function correct_computes_the_new_points()
    {
        $questions = Question::all();
        $this->assignment->points_per_question = 'question weight';
        $this->assignment->total_points = 20;
        $this->assignment->save();
        foreach ($questions as $key => $question) {
            DB::table('assignment_question')->insert([
                'assignment_id' => $this->assignment->id,
                'question_id' => $question->id,
                'points' => 10,
                'weight' => 1,
                'order' => $key + 1,
                'open_ended_submission_type' => 'file'
            ]);
        }
        $question_0 = $questions[0];
        $question_1 = $questions[1];
        $question_0_weight = 4;
        $question_1_weight = 1;
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$question_0->id}/update-weight",
            ['weight' => $question_0_weight])
            ->assertJson(['type' => 'success']);
        $question_0_points = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_0->id)
            ->first()
            ->points;

        $this->assertEquals($question_0_points, $this->assignment->total_points * $question_0_weight / ($question_0_weight + $question_1_weight));
        $question_1_points = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_1->id)
            ->first()
            ->points;
        $this->assertEquals($question_1_points, $this->assignment->total_points * $question_1_weight / ($question_0_weight + $question_1_weight));

    }

    #[Test]
    public function alpha_course_update_points_only_affects_beta_courses()
    {

        $this->course->alpha = 1;
        $this->course->save();

        /**alpha and beta assignments**/
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->beta_assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);

        $this->course_2 = factory(Course::class)->create(['user_id' => $this->user_2->id]);
        $this->assignment_2 = factory(Assignment::class)->create(['course_id' => $this->course_2->id]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment_2->id,
            'question_id' => $this->question->id,
            'points' => 5,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-points",
                ['points' => 32.15])
            ->assertJson(['type' => "success"]);
        $num_with_32_5_points = count(DB::table('assignment_question')->where('points', '32.15')->get());
        $this->assertEquals(2, $num_with_32_5_points);

    }

    #[Test]
    public function cannot_update_points_if_is_beta_course()
    {
        $this->actingAs($this->beta_user)
            ->patchJson("/api/assignments/{$this->beta_assignment->id}/questions/{$this->question->id}/update-points", ['points' => 10])
            ->assertJson(['message' => "This is an assignment in a Beta course so you can't change the points."]);
    }

    #[Test]
    public function alpha_course_cannot_update_points_if_beta_submission_exists()
    {

        $this->course->alpha = 1;
        $this->course->save();

        $data = [
            'type' => 'q',
            'assignment_id' => $this->beta_assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'fake_1.pdf',
            'original_filename' => 'orig_fake_1.pdf',
            'user_id' => $this->student_user->id,
            'date_submitted' => Carbon::now()];
        SubmissionFile::create($data);

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-points", ['points' => 10])
            ->assertJson(['message' => "There is at least one submission to this question in one of the Beta assignments so you can't change the points."]);
    }

    #[Test]
    public function must_remix_from_a_valid_question_source()
    {

        $data['chosen_questions'] = [
            ['question_id' => $this->question->id,
                'assignment_id' => $this->assignment_remixer->id,
            ]];
        $data['question_source'] = 'bogus question source';
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/remix-assignment-with-chosen-questions",
            $data)
            ->assertJson(['message' => "bogus question source is not a valid question source."]);

    }

    #[Test]
    public function cannot_add_non_file_questions_to_a_compiled_assignment()
    {
        $this->assignment->file_upload_mode = 'compiled_pdf';
        $this->assignment->save();

        DB::table('assignment_question')->where('question_id', $this->question->id)
            ->where('assignment_id', $this->assignment_remixer->id)
            ->update(['open_ended_submission_type' => 'audio']);
        $data['chosen_questions'] = [
            ['question_id' => $this->question->id,
                'assignment_id' => $this->assignment_remixer->id,
                'question_source' => 'my_courses']
        ];
        $data['question_source'] = 'my_courses';
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/remix-assignment-with-chosen-questions",
            $data)
            ->assertJson(['message' => "Your assignment is of file upload type Compiled PDF but you're trying to remix an open-ended type of audio.  If you would like to use this question, please edit your assignment and change the file upload type to 'Individual Assessment Upload' or 'Compiled Upload/Individual Assessment Upload'."]);
    }

    #[Test]
    public function remixed_question_must_be_valid()
    {
        $data['chosen_questions'] = [['question_id' => 0, 'assignment_id' => 0]];
        $data['question_source'] = 'my_courses';
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/remix-assignment-with-chosen-questions",
            $data)
            ->assertJson(['message' => 'Question 0 does not belong to that assignment.']);
    }

    #[Test]
    public function owner_can_remix_assignment_with_chosen_questions()
    {
        $data['chosen_questions'] = [
            ['question_id' => $this->question->id,
                'assignment_id' => $this->assignment_remixer->id]
        ];
        $data['question_source'] = 'my_courses';
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/remix-assignment-with-chosen-questions",
            $data)
            ->assertJson(['type' => 'success']);
    }

    #[Test]
    public function non_owner_cannot_remix_assignment_with_chosen_questions()
    {
        $data['chosen_questions'] = [
            ['question_id' => $this->question->id,
                'assignment_id' => $this->assignment_remixer->id]
        ];
        $data['question_source'] = 'my_courses';
        $this->actingAs($this->user_2)->patchJson("/api/assignments/{$this->assignment->id}/remix-assignment-with-chosen-questions",
            $data)
            ->assertJson(['message' => 'You are not allowed to remix that assignment.']);
    }

    #[Test]
    public function direct_import_of_adapt_id_can_be_imported_using_question_id()
    {
        $this->question->library = 'adapt';
        $this->question->save();
        $this->actingAs($this->user)
            ->postJson("/api/questions/{$this->assignment->id}/direct-import-question",
                ['direct_import' => "{$this->question->id}", 'type' => 'adapt id']
            )->assertJson(['type' => 'success']);
    }

    #[Test]
    public function cannot_direct_import_into_formative_assignment_if_you_do_not_own_question()
    {
        $this->question->library = 'adapt';
        $this->question->question_editor_user_id = $this->user_2->id;
        $this->question->save();
        $this->assignment->formative = 1;
        $this->assignment->save();
        $this->actingAs($this->user)
            ->postJson("/api/questions/{$this->assignment->id}/direct-import-question",
                ['direct_import' => "{$this->question->id}", 'type' => 'adapt id']
            )->assertJson(['message' => "You do not own {$this->question->id} so you cannot add it to this assignment which is part of a formative assignment."]);
    }

    #[Test]
    public function direct_import_must_be_a_valid_adapt_id()
    {
        $this->actingAs($this->user)
            ->postJson("/api/questions/{$this->assignment->id}/direct-import-question",
                ['direct_import' => "1-26", 'type' => 'adapt id']
            )->assertJson(['message' => '26 is not a valid question ID.']);

    }

    #[Test]
    public function direct_import_of_adapt_id_must_be_of_the_correct_form()
    {
        $this->actingAs($this->user)
            ->postJson("/api/questions/{$this->assignment->id}/direct-import-question",
                ['direct_import' => "7", 'type' => 'adapt id']
            )->assertJson(['message' => '7 is not a valid Question ID.']);

    }

    #[Test]
    public function direct_import_must_be_a_valid_type()
    {
        $this->actingAs($this->user)
            ->postJson("/api/questions/{$this->assignment->id}/direct-import-question",
                ['direct_import' => "1-26", 'type' => 'Not valid type']
            )->assertJson(['message' => 'Not valid type is not a valid direct import type.']);

    }

    #[Test]
    public function non_owner_cannot_do_a_direct_import()
    {
        $this->actingAs($this->user_2)->postJson("/api/questions/{$this->assignment->id}/direct-import-question",
            ['direct_import' => "1860,1862", 'type' => 'libretexts id'])
            ->assertJson(['message' => 'You are not allowed to update this assignment.']);

    }

    #[Test]
    public function page_ids_must_be_valid()
    {
        $this->actingAs($this->user)->postJson("/api/questions/{$this->assignment->id}/direct-import-question",
            ['direct_import' => "query-zzz", 'type' => 'libretexts id'])
            ->assertJson(['message' => 'zzz should be a positive integer.']);

    }

    #[Test]
    public function can_remove_a_question_from_an_assignment_if_you_are_the_owner()
    {
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 'none'
        ]);

        $this->actingAs($this->user)->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}")
            ->assertJson(['type' => 'info']);
    }

    #[Test]
    public function cannot_update_points_if_not_owner()
    {
        $this->actingAs($this->user_2)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-points",
            ['points' => 10])
            ->assertJson(['message' => 'You are not allowed to update that resource.']);
    }

    #[Test]
    public function non_owner_cannot_get_assignment_info_for_get_questions()
    {

        $this->actingAs($this->student_user)->getJson("/api/assignments/{$this->assignment->id}/get-questions-info")
            ->assertJson(['type' => 'error',
                'message' => "You are not allowed to get questions for this assignment."]);

    }

    #[Test]
    public function owner_can_get_assignment_info_for_get_questions()
    {

        $this->actingAs($this->user)->getJson("/api/assignments/{$this->assignment->id}/get-questions-info")
            ->assertJson(['type' => 'success']);

    }

    /**@test* */
    public function can_not_visit_get_questions_if_students_have_already_made_a_submission()
    {

    }

    #[Test]
    public function if_page_id_is_included_there_should_be_no_other_tags()
    {
        $this->markTestIncomplete(
            'TODO'
        );

    }

    #[Test]
    public function cannot_update_points_if_points_are_not_valid()
    {
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-points",
            ['points' => -1])
            ->assertJsonValidationErrors(['points']);

    }

    #[Test]
    public function can_update_points_if_points_if_owner()
    {

        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-points", ['points' => 10])
            ->assertJson(['type' => 'success']);

    }

    #[Test]
    public function cannot_update_points_if_student_made_a_submission()
    {
        Submission::create($this->h5pSubmission);
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-points", ['points' => 10])
            ->assertJson(['type' => 'error',
                'message' => "This cannot be updated since students have already submitted responses to this assignment."]);

    }

    #[Test]
    public function cannot_update_points_if_student_made_a_file_submission()
    {
        SubmissionFile::create($this->submission_file);
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-points", ['points' => 10])
            ->assertJson(['type' => 'error',
                'message' => "This cannot be updated since students have already submitted responses to this assignment."]);

    }

    /**@test */
    public function returns_an_error_with_an_invalid_page_id()
    {

        $this->markTestIncomplete(
            'TODO'
        );

    }

    /**@test */
    public function returns_the_correct_question_given_a_query_page_id()
    {
        $this->markTestIncomplete(
            'TODO'
        );

    }

    #[Test]
    public function user_gets_message_if_there_are_no_questions_associated_with_a_tag()
    {
        $this->markTestIncomplete(
            'TODO'
        );

    }

    #[Test]
    public function user_gets_message_if_there_are_no_questions_associated_with_an_intersection_of_tags()
    {
        $this->markTestIncomplete(
            'TODO'
        );

    }

    #[Test]
    public function can_get_tags_if_not_student()
    {
        $this->actingAs($this->user)->getJson("/api/tags")
            ->assertJson(['type' => 'success']);

    }

    #[Test]
    public function cannot_get_tags_if_student()
    {
        $this->actingAs($this->user_2)->getJson("/api/tags")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to retrieve the tags from the database.']);

    }

    #[Test]
    public function can_add_a_question_to_an_assignment_if_you_are_the_owner()
    {
        $this->actingAs($this->user)->postJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}")
            ->assertJson(['type' => 'success']);
    }

    #[Test]
    public function cannot_add_a_question_to_an_assignment_if_you_are_not_the_owner()
    {
        $this->actingAs($this->user_2)->postJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to add a question to this assignment.']);
    }

    #[Test]
    public function cannot_remove_a_question_to_an_assignment_if_you_are_not_the_owner()
    {
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 'none'
        ]);

        $this->actingAs($this->user_2)->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to remove a question from this assignment.']);

    }

    #[Test]
    public function can_get_assignment_question_ids_if_owner()
    {

        DB::table('assignment_question')
            ->insert([
                'assignment_id' => $this->assignment->id,
                'question_id' => $this->question->id,
                'points' => $this->assignment->default_points_per_question,
                'order' => 1,
                'open_ended_submission_type' => 'file'
            ]);

        $this->actingAs($this->user)->getJson("/api/assignments/{$this->assignment->id}/questions/ids")
            ->assertJson(['type' => 'success',
                'question_ids' => "[{$this->question->id}]"]);

    }

    #[Test]
    public function cannot_get_assignment_question_ids_if_not_owner()
    {
        DB::table('assignment_question')
            ->insert([
                'assignment_id' => $this->assignment->id,
                'question_id' => $this->question->id,
                'points' => $this->assignment->default_points_per_question,
                'order' => 1,
                'open_ended_submission_type' => 'file'
            ]);
        $this->actingAs($this->user_2)->getJson("/api/assignments/{$this->assignment->id}/questions/ids")
            ->assertJson(['type' => 'error']);

    }
}
