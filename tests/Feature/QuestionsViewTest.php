<?php

namespace Tests\Feature;

use App\Assignment;
use App\AssignmentLevelOverride;
use App\AssignToTiming;
use App\CanGiveUp;
use App\CompiledPDFOverride;
use App\Course;
use App\Enrollment;
use App\JWE;
use App\LtiLaunch;
use App\Question;
use App\QuestionLevelOverride;
use App\RubricCategory;
use App\RubricCategorySubmission;
use App\SavedQuestionsFolder;
use App\Score;
use App\Section;
use App\Solution;
use App\Submission;
use App\SubmissionFile;
use App\Traits\Statistics;
use App\Traits\Test;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use function dd;
use function factory;

class QuestionsViewTest extends TestCase
{
    use Statistics;
    use Test;


    private $upload_file_data;
    private $assignment;
    private $student_user;
    /**
     * @var array
     */
    private $upload_solution_data;
    /**
     * @var array
     */
    private $upload_file_submission_data;
    private $question;

    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->user_2 = factory(User::class)->create();
        $this->beta_user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;
        $this->student_user->save();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->beta_course = factory(Course::class)->create(['user_id' => $this->beta_user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);


        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id, 'solutions_released' => 0]);
        $this->beta_assignment = factory(Assignment::class)->create(['course_id' => $this->beta_course->id]);
        $this->non_beta_assignment = factory(Assignment::class)->create(['course_id' => $this->beta_course->id]);

        DB::table('beta_assignments')->insert(['id' => $this->beta_assignment->id, 'alpha_assignment_id' => $this->assignment->id]);

        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);

        $this->question = factory(Question::class)->create(['page_id' => 1]);
        $this->question_2 = factory(Question::class)->create(['page_id' => 2]);
        $this->question_points = 10;
        $this->assignment_question_id = DB::table('assignment_question')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => $this->question_points,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question_2->id,
            'points' => $this->question_points,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);

        DB::table('assignment_question')->insert([
            'assignment_id' => $this->non_beta_assignment->id,
            'question_id' => $this->question->id,
            'points' => $this->question_points,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);


        $this->student_user_2 = factory(User::class)->create();
        $this->student_user_2->role = 3;

        $this->student_user_3 = factory(User::class)->create();
        $this->student_user_3->role = 3;

        $this->submission_object = '{"actor":{"account":{"name":"5038b12a-1181-4546-8735-58aa9caef971","homePage":"https://h5p.libretexts.org"},"objectType":"Agent"},"verb":{"id":"http://adlnet.gov/expapi/verbs/answered","display":{"en-US":"answered"}},"object":{"id":"https://h5p.libretexts.org/wp-admin/admin-ajax.php?action=h5p_embed&id=97","objectType":"Activity","definition":{"extensions":{"http://h5p.org/x-api/h5p-local-content-id":97},"name":{"en-US":"1.3 Actividad # 5: comparativos y superlativos"},"interactionType":"fill-in","type":"http://adlnet.gov/expapi/activities/cmi.interaction","description":{"en-US":"<p><strong>Instrucciones: Ponga las palabras en orden. Empiece con el sujeto de la oración.</strong></p>\n<br/>1. de todas las universidades californianas / la / antigua / es / La Universidad del Pacífico / más <br/>__________ __________ __________ __________ __________ __________.<br/><br/>2. el / UC Merced / número de estudiantes / tiene / menor<br/>__________ __________ __________ __________ __________."},"correctResponsesPattern":["La Universidad del Pacífico[,]es[,]la[,]más[,]antigua[,]de todas las universidades californianas[,]UC Merced[,]tiene[,]el[,]menor[,]número de estudiantes"]}},"context":{"contextActivities":{"category":[{"id":"http://h5p.org/libraries/H5P.DragText-1.8","objectType":"Activity"}]}},"result":{"response":"[,][,][,][,][,][,][,]antigua[,][,][,]","score":{"min":0,"raw":11,"max":11,"scaled":0},"duration":"PT3.66S","completion":true}}';
        $this->h5pSubmission = [
            'technology' => 'h5p',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => $this->submission_object
        ];
        $this->upload_solution_data =
            ['assignment_id' => $this->assignment->id,
                'upload_file_type' => 'solution',
                'file_name' => 'blah.pdf'
            ];

        $this->upload_file_submission_data =
            ['assignment_id' => $this->assignment->id,
                'upload_file_type' => 'submission',
                'file_name' => 'blah.pdf'
            ];
    }

    function getPoints($assignment_id, $question_id)
    {
        $points = DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->first()
            ->points;
        if (!$points || $points === '0.00') {
            dd('points should be at least 0');
        }
        return $points;
    }

    function getScore($assignment_id, $student_user_id)
    {
        return DB::table('scores')
            ->where('assignment_id', $assignment_id)
            ->where('user_id', $student_user_id)
            ->first()
            ->score;
    }


    public function createQTIMatchingQuestion($points): int
    {

        $saved_questions_folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id, 'type' => 'my_questions']);
        $qti_question_info = ["question_type" => "assessment",
            "folder_id" => $saved_questions_folder->id,
            "public" => "0",
            'page_id' => 128931298,
            "title" => "some title",
            "author" => "Instructor Kean",
            "technology" => "qti",
            "technology_id" => null,
            'library' => 'adapt',
            "license" => "publicdomain",
            "license_version" => null,
            'technology_iframe' => '',
            'non_technology' => 0,
            "qti_json" => '{"questionType":"matching","prompt":"some prompt","termsToMatch":[{"identifier":"1654952557281","termToMatch":"<p>1</p>\n","matchingTermIdentifier":"1654952557281-1","feedback":""},{"identifier":"666","termToMatch":"<p>2</p>\n","matchingTermIdentifier":"666-1","feedback":""}],"possibleMatches":[{"identifier":"1654952557281-1","matchingTerm":"<p>1</p>\n"},{"identifier":"666-1","matchingTerm":"<p>4</p>\n"},{"identifier":"1654952563168","matchingTerm":"<p>2</p>\n"}]}'
        ];

        $question_id = DB::table('questions')->insertGetId($qti_question_info);

        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'points' => $points,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);
        return $question_id;
    }

    /** @test */
    public function non_owner_cannot_delete_custom_rubric()
    {

        $this->actingAs($this->user_2)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/use-existing-rubric")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to update whether to use an overriding or existing rubric for that question.']);

    }

    /** @test */
    public function non_owner_cannot_update_use_custom_rubric()
    {

        $this->actingAs($this->user_2)
            ->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/delete-custom-rubric")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to delete the custom rubric for that question.']);

    }


    /** @test */
    public function cannot_ask_instructor_to_release_assignment_if_not_student()
    {
        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/contact-instructor-to-release-assignment")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to contact this instructor.']);
    }

    /** @test */
    public function non_owner_cannot_set_the_current_page_for_a_clicker_assessment()
    {
        $this->actingAs($this->user_2)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/set-current-page")
            ->assertJson(['message' => 'You are not allowed to set the current page.']);
    }


    /** @test */
    public function non_owner_cannot_start_a_clicker_assessment()
    {
        $this->actingAs($this->user_2)->postJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/start-clicker-assessment")
            ->assertJson(['message' => 'You are not allowed to start this clicker assessment.']);
    }


    /** @test */
    public function a11y_student_served_regular_technology_if_a11y_technology_does_not_exist()
    {
        $url = "https://studio.libretexts.org/h5p/12/embed";
        DB::table('enrollments')
            ->where('user_id', $this->student_user->id)
            ->update(['a11y_redirect' => 'a11y_technology']);
        $this->question->technology = 'h5p';
        $this->question->technology_iframe = '<iframe src="' . $url . '" frameborder="0" allowfullscreen="allowfullscreen"></iframe>';
        $this->question->save();
        $this->actingAs($this->student_user)->getJson("/api/assignments/{$this->assignment->id}/questions/view", $this->headers())
            ->assertJson(['questions' => [['technology_iframe' => $url]]]);
    }

    /** @test */
    public function a11y_student_served_a11y_technology_if_it_exists()
    {
        DB::table('enrollments')
            ->where('user_id', $this->student_user->id)
            ->update(['a11y_redirect' => 'a11y_technology']);
        $question = $this->question->replicate();
        $question->technology = 'h5p';
        $question->technology_id = 3;
        $question->save();
        $this->question->a11y_auto_graded_question_id = $question->id;
        $this->question->save();
        $this->actingAs($this->student_user)->getJson("/api/assignments/{$this->assignment->id}/questions/view", $this->headers())
            ->assertJson(['questions' => [['technology_id' => "3"]]]);

    }


    /** @test */
    public function non_a11y_student_served_regular_technology()
    {
        $question = $this->question->replicate();
        $question->technology = 'h5p';
        $question->technology_id = 3;
        $question->save();
        //$this->question->a11y_auto_graded_question_id = $question->id;
        $this->question->technology = 'h5p';
        $this->question->technology_iframe = '<iframe src="https://studio.libretexts.org/h5p/12/embed" frameborder="0" allowfullscreen="allowfullscreen"></iframe>';
        $this->question->technology_id = 12;
        $this->question->save();
        $this->actingAs($this->student_user)->getJson("/api/assignments/{$this->assignment->id}/questions/view", $this->headers())
            ->assertJson(['questions' => [['technology_iframe' => "https://studio.libretexts.org/h5p/12/embed"]]]);

    }


    /** @test */
    public function correctly_computes_score_with_number_of_allowed_attempts_penalty()
    {

        $this->assignment->assessment_type = 'real time';
        $this->assignment->number_of_allowed_attempts = 'unlimited';
        $this->assignment->number_of_allowed_attempts_penalty = '10';
        $this->assignment->save();

        $this->h5pSubmission['submission'] = str_replace('raw":11', 'raw":3', $this->submission_object);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);
        $this->h5pSubmission['submission'] = str_replace('raw":3', 'raw":11', $this->submission_object);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);
        $points = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->first()->points;
        $this->assertEquals(9, $points * (1 - $this->assignment->number_of_allowed_attempts_penalty / 100));
    }

    /** @test */
    public function correctly_recomputes_assignment_score_of_removed_question_for_points_scoring_type_for_rubric_categories()
    {
        $custom_score = 5;
        $current_assignment_score = 93;
        $rubric_category = RubricCategory::create(['question_id' => $this->question->id,
            'category' => 'some category',
            'criteria' => 'some criteria',
            'question_revision_id' => 0,
            'score' => 10,
            'order' => 1]);
        RubricCategorySubmission::create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student_user->id,
            'rubric_category_id' => $rubric_category->id,
            'submission' => 'some submission',
            'custom_score' => $custom_score,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Score::create(['assignment_id' => $this->assignment->id,
            'user_id' => $this->student_user->id,
            'score' => $current_assignment_score]);

        $this->actingAs($this->user)->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}")
            ->assertJson(['type' => 'info']);
        $new_score = Score::where('assignment_id', $this->assignment->id)->where('user_id', $this->student_user->id)->first()->score;
        $this->assertEquals($current_assignment_score - $custom_score, $new_score);
    }

    /** @test */
    public function non_owner_cannot_get_submission_array()
    {
        $this->actingAs($this->student_user_2)
            ->postJson("/api/submissions/submission-array/assignment/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['message' => 'You are not allowed to access that submission array.']);
    }

    /** @test */
    public function non_owner_cannot_get_submission_array_history()
    {
        $this->actingAs($this->student_user_2)
            ->postJson("/api/submission-history/assignment/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['message' => 'You are not allowed to access that submission array.']);
    }


    /** @test */
    public function non_owner_cannot_update_the_question_title()
    {

        $this->actingAs($this->student_user_2)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/custom-title")
            ->assertJson(['message' => 'You are not allowed to update the question title for that question.']);

    }

    /** @test */
    public function cannot_save_confirmation_if_not_enrolled()
    {

        $this->actingAs($this->student_user_2)->postJson("/api/submission-confirmations/assignment/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['message' => 'Not authorized to store a submission confirmation.']);

    }

    /** @test */
    public function can_save_confirmation_if_enrolled()
    {

        $this->actingAs($this->student_user)->postJson("/api/submission-confirmations/assignment/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['type' => 'success']);


    }


    /** @test */
    public function non_enrolled_student_cannot_view_case_study_info()
    {
        $this->actingAs($this->student_user_2)->getJson("/api/case-study-notes/assignment/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['message' => 'You are not allowed to access this assignment.']);

    }

    /** @test */
    public function enrolled_student_can_view_case_study_info()
    {
        $this->actingAs($this->student_user)->getJson("/api/case-study-notes/assignment/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['type' => 'success']);
    }


    /** @test */
    public function user_cannot_get_query_page_if_page_id_is_not_in_one_of_their_assignments()
    {

        $content = $this->actingAs($this->student_user)->getJson("/api/get-header-html/500/0")->getContent();
        $this->assertTrue(str_contains($content, 'You are not allowed to view the text associated with this question.'));

    }


    /** @test */
    public function user_can_get_header_html_is_in_one_of_their_assignments()
    {

        DB::table('questions')->where('id', $this->question->id)->update(['non_technology_html' => 'blah']);
        $content = $this->actingAs($this->student_user)->getJson("/api/get-header-html/{$this->question->id}/0")->getContent();
        $this->assertTrue(str_contains($content, 'blah'));

    }

    /** @test */
    public function non_student_user_cannot_update_time_spent()
    {
        $this->course->user_id = $this->course->user_id + 1;
        $this->course->save();
        $this->actingAs($this->user)->patchJson("/api/assignment-question-time-on-tasks/assignment/{$this->assignment->id}/question/{$this->question->id}",
            ['time_on_task' => 10])
            ->assertJson(['type' => 'error']);

    }


    /** @test */
    public function student_user_can_update_time_on_task()
    {
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->student_user)->patchJson("/api/assignment-question-time-on-tasks/assignment/{$this->assignment->id}/question/{$this->question->id}",
            ['time_on_task' => 10])
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->where('question_id', $this->question->id)
            ->first();
        $this->assertEquals(10, $submission->time_on_task);
    }


    /** @test */
    public function instructor_can_get_header_html()
    {
        $this->question->non_technology_html = "blah";
        $this->question->save();
        $content = $this->actingAs($this->user)->getJson("/api/get-header-html/{$this->question->id}/0")->getContent();
        $this->assertTrue(str_contains($content, 'blah'));

    }


    /** @test * */
    public
    function student_cannot_choose_the_same_matching_term_more_than_once()
    {
        $question_id = $this->createQTIMatchingQuestion(10);
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[{"identifier":"1654952557281","chosenMatchIdentifier":"654952563168"},{"identifier":"666","chosenMatchIdentifier":"654952563168"}]',
            'technology' => "qti"
        ];
        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['message' => 'Each matching term should be chosen only once.']);
    }


    /** @test * */
    public
    function matching_is_scored_correctly()
    {
        $points = 10;
        $question_id = $this->createQTIMatchingQuestion($points);

        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[{"identifier":"1654952557281","chosenMatchIdentifier":"1654952557281-1"},{"identifier":"666","chosenMatchIdentifier":"666-1"}]',
            'technology' => "qti"
        ];
        //get it right
        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal($points), floatVal($submission->score));
        DB::table('submissions')->delete();

        //get it wrong
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[{"identifier":"1654952557281","chosenMatchIdentifier":"654952563168"},{"identifier":"666","chosenMatchIdentifier":"1654952557281-1"}]',
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal(0), floatVal($submission->score));

    }

    /** @test * */
    public
    function student_must_make_a_choice_for_each_matching_term()
    {
        $question_id = $this->createQTIMatchingQuestion(10);
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[]',
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['message' => 'Please choose a matching term for all terms to match.']);
    }


    /** @test */
    public function correctly_scores_native_select_choice()
    {

        $this->saved_questions_folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id, 'type' => 'my_questions']);
        $qti_question_info = ["question_type" => "assessment",
            "folder_id" => $this->saved_questions_folder->id,
            "public" => "0",
            "title" => "fill in the blank",
            "author" => "Instructor Kean",
            "technology" => "qti",
            "technology_id" => null,
            'technology_iframe' => '',
            'non_technology' => 0,
            'page_id' => 187364,
            'library' => 'adapt',
            "license" => "publicdomain",
            "license_version" => null,
            "qti_json" => '{"questionType":"select_choice","responseDeclaration":{"correctResponse":[]},"itemBody":"<p>[weapon] is something that [action].</p>\n","inline_choice_interactions":{"weapon":[{"value":"adapt-qti-1653922909877","text":"guns","correctResponse":true},{"value":"1653922924703","text":"celery","correctResponse":false}],"action":[{"value":"adapt-qti-1653922919813","text":"kills","correctResponse":true},{"value":"1653922931680","text":"bites","correctResponse":false}]}}'
        ];
        $question_id = DB::table('questions')->insertGetId($qti_question_info);
        $points = 10;
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'points' => $points,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);

        //Exact
        //get it correct
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[{"identifier":"weapon","value":"adapt-qti-1653922909877"},{"identifier":"action","value":"adapt-qti-1653922919813"}]',
            'technology' => "qti"
        ];
//correctly scores if correct
        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal($points), floatVal($submission->score));
        DB::table('submissions')->delete();

        //correctly scores if  not correct
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[{"value":"1653922924703","text":"celery"},{"identifier":"action","value":"adapt-qti-1653922919813"}]',
            'technology' => "qti"
        ];
        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(.5 * floatVal($points), floatVal($submission->score));
        DB::table('submissions')->delete();
    }

    /** @test */
    public function correctly_scores_native_numerical_submission()
    {
        $this->saved_questions_folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id, 'type' => 'my_questions']);
        $qti_question_info = ["question_type" => "assessment",
            "folder_id" => $this->saved_questions_folder->id,
            "public" => "0",
            "title" => "fill in the blank",
            "author" => "Instructor Kean",
            "technology" => "qti",
            "technology_id" => null,
            'technology_iframe' => '',
            'non_technology' => 0,
            'page_id' => 1823124,
            'library' => 'adapt',
            "license" => "publicdomain",
            "license_version" => null,
            "qti_json" => '{"prompt":"<p>What is 4+4?</p>","correctResponse":{"value":"8","marginOfError":"2"},"feedback":{"any":"<p>Some other info</p>\n","correct":"<p>general correct</p>","incorrect":"<p>general incorrect</p>"},"questionType":"numerical"}'
        ];

        $question_id = DB::table('questions')->insertGetId($qti_question_info);
        $points = 10;
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'points' => $points,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);

        //get it correct within the margin of error
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '9',
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal($points), floatVal($submission->score));
        DB::table('submissions')->delete();

        //get it incorrect within the margin of error
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '13',
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal(0), floatVal($submission->score));
    }

    /** @test */
    public function correctly_scores_native_fill_in_the_blank_submission()
    {

        $this->saved_questions_folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id, 'type' => 'my_questions']);
        $qti_question_info = ["question_type" => "assessment",
            "folder_id" => $this->saved_questions_folder->id,
            "public" => "0",
            "title" => "fill in the blank",
            "author" => "Instructor Kean",
            "technology" => "qti",
            "technology_id" => null,
            'technology_iframe' => '',
            'non_technology' => 0,
            'page_id' => 187364,
            'library' => 'adapt',
            "license" => "publicdomain",
            "license_version" => null,
            "qti_json" => '{"responseDeclaration":{"correctResponse":[{"value":"star","matchingType":"exact","caseSensitive":"yes"},{"value":"animal","matchingType":"exact","caseSensitive":"yes"}]},"itemBody":{"textEntryInteraction":"<p>The sun is a <u></u>. And a cat is an <u></u>.</p>\n"},"questionType":"fill_in_the_blank"}'
        ];
        $question_id = DB::table('questions')->insertGetId($qti_question_info);
        $points = 10;
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'points' => $points,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);

        //Exact
        //get it correct
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[{"identifier":"response_1 fill-in-the-blank form-control form-control-sm","value":"star"},{"identifier":"response_2 fill-in-the-blank form-control form-control-sm","value":"animal"}]',
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal($points), floatVal($submission->score));
        DB::table('submissions')->delete();

        //get it half correct
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[{"identifier":"response_1 fill-in-the-blank form-control form-control-sm","value":"star"},{"identifier":"response_2 fill-in-the-blank form-control form-control-sm","value":"ooga"}]',
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(.5 * floatVal($points), floatVal($submission->score));

        //gets it correct if substring option is on and a substring is submitted

        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[{"identifier":"response_1 fill-in-the-blank form-control form-control-sm","value":"sta"},{"identifier":"response_2 fill-in-the-blank form-control form-control-sm","value":"animal"}]',
            'technology' => "qti"
        ];
        $question = Question::find($question_id);
        $qti_json = json_decode($question->qti_json, true);
        $qti_json['responseDeclaration']['correctResponse'][0]['matchingType'] = 'substring';
        $question->qti_json = json_encode($qti_json);
        $question->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal($points), floatVal($submission->score));
        DB::table('submissions')->delete();


        //gets it correct if off by case sensitive
        $question = Question::find($question_id);
        $qti_json = json_decode($question->qti_json, true);
        $qti_json['responseDeclaration']['correctResponse'][0]['matchingType'] = 'exact';
        $qti_json['responseDeclaration']['correctResponse'][0]['caseSensitive'] = 'no';
        $question->qti_json = json_encode($qti_json);
        $question->save();

        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[{"identifier":"response_1 fill-in-the-blank form-control form-control-sm","value":"Star"},{"identifier":"response_2 fill-in-the-blank form-control form-control-sm","value":"animal"}]',
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal($points), floatVal($submission->score));
        DB::table('submissions')->delete();

    }

    /** @test */
    public function correctly_scores_native_fill_in_the_blank_submission_with_multiple_correct_answers()
    {

        $this->saved_questions_folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id, 'type' => 'my_questions']);
        $qti_question_info = ["question_type" => "assessment",
            "folder_id" => $this->saved_questions_folder->id,
            "public" => "0",
            "title" => "fill in the blank",
            "author" => "Instructor Kean",
            "technology" => "qti",
            "technology_id" => null,
            'technology_iframe' => '',
            'non_technology' => 0,
            'page_id' => 187364,
            'library' => 'adapt',
            "license" => "publicdomain",
            "license_version" => null,
            "qti_json" => '{"responseDeclaration":{"correctResponse":[{"value":"star|noun","matchingType":"exact","caseSensitive":"yes"},{"value":"animal","matchingType":"exact","caseSensitive":"yes"}]},"itemBody":{"textEntryInteraction":"<p>The sun is a <u></u>. And a cat is an <u></u>.</p>\n"},"questionType":"fill_in_the_blank"}'
        ];
        $question_id = DB::table('questions')->insertGetId($qti_question_info);
        $points = 10;
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'points' => $points,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);

        //Exact
        //get it correct using "star"
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[{"identifier":"response_1 fill-in-the-blank form-control form-control-sm","value":"star"},{"identifier":"response_2 fill-in-the-blank form-control form-control-sm","value":"animal"}]',
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal($points), floatVal($submission->score));
        DB::table('submissions')->delete();
        //Exact
        //get it correct using "noun"
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '[{"identifier":"response_1 fill-in-the-blank form-control form-control-sm","value":"noun"},{"identifier":"response_2 fill-in-the-blank form-control form-control-sm","value":"animal"}]',
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal($points), floatVal($submission->score));
        DB::table('submissions')->delete();


    }


    /** @test */
    public function correctly_scores_native_multiple_answers_submission()
    {

        $this->saved_questions_folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id, 'type' => 'my_questions']);
        $qti_question_info = ["question_type" => "assessment",
            "folder_id" => $this->saved_questions_folder->id,
            "public" => "0",
            "title" => "some title",
            "author" => "Instructor Kean",
            "tags" => [],
            "technology" => "qti",
            "technology_id" => null,
            "non_technology_text" => null,
            "text_question" => null,
            "a11y_auto_graded_question_id" => null,
            'open_ended_submission_type' => '0',
            "answer_html" => null,
            "solution_html" => null,
            "notes" => null,
            "hint" => null,
            "license" => "publicdomain",
            "license_version" => null,
            "qti_randomize_order" => "yes",
            "qti_prompt" => "<p>This is my prompt</p>",
            "qti_correct_response" => "2455",
            "qti_simple_choice_0" => "<p>This is the correct response</p>\n",
            "qti_simple_choice_1" => "<p>This is not correct</p>",
            "qti_simple_choice_2" => "<p>This is also correct.</p>\n",
            "qti_json" => '{"questionType":"multiple_answers","prompt":"<p>This is my prompt</p>\n","simpleChoice":[{"identifier":"adapt-qti-1","value":"<p>This is the correct response</p>\n","correctResponse":true,"feedback":"<p>feedback 1</p>\n"},{"identifier":"adapt-qti-2","value":"<p>This is not correct</p>\n","correctResponse":false},{"identifier":"1654536664810","value":"<p>This is also correct.</p>\n","correctResponse":true,"feedback":""}]}'
        ];
        $this->actingAs($this->user)->postJson("/api/questions",
            $qti_question_info)
            ->assertJson(['type' => 'success']);
        $question_id = DB::table('questions')
            ->where('qti_json', '<>', null)
            ->orderBy('id', 'desc')
            ->first()
            ->id;
        $points = 10;
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'points' => $points,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);

        //get it correct
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => json_encode(['adapt-qti-1', '1654536664810']),
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);

        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal($points), floatVal($submission->score));
        DB::table('submissions')->delete();

        //gets partial credit
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => json_encode(['1654536664810']),
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);

        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal(Round(10 * 2 / 3, 4)), floatVal($submission->score));

    }


    /** @test */
    public function correctly_scores_native_multiple_choice_submission()
    {

        $this->saved_questions_folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id, 'type' => 'my_questions']);
        $qti_question_info = ["question_type" => "assessment",
            "folder_id" => $this->saved_questions_folder->id,
            "public" => "0",
            "title" => "some title",
            "author" => "Instructor Kean",
            "tags" => [],
            "technology" => "qti",
            'open_ended_submission_type' => '0',
            "technology_id" => null,
            "non_technology_text" => null,
            "text_question" => null,
            "a11y_auto_graded_question_id" => null,
            "answer_html" => null,
            "solution_html" => null,
            "notes" => null,
            "hint" => null,
            "license" => "publicdomain",
            "license_version" => null,
            "qti_randomize_order" => "yes",
            "qti_prompt" => "<p>The derivative of sin(x) is cos(x).</p>",
            "qti_correct_response" => "2455",
            "qti_simple_choice_0" => "cscx",
            "qti_simple_choice_1" => "cotx",
            "qti_json" => '{"prompt":"<div><p>What is the derivative of sin(x)?","simpleChoice":[{"identifier":"6516","value":"csc(x)","correctResponse":false},{"identifier":"2455","value":"cos(x)","correctResponse":true}],"questionType":"multiple_choice"}'
        ];
        $this->actingAs($this->user)->postJson("/api/questions",
            $qti_question_info)
            ->assertJson(['type' => 'success']);
        $question_id = DB::table('questions')
            ->where('qti_json', '<>', null)
            ->orderBy('id', 'desc')
            ->first()
            ->id;
        $points = 10;
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'points' => $points,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);

        //get it correct
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '2455',
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal($points), floatVal($submission->score));
        DB::table('submissions')->delete();

        //get it incorrect
        $qti_submission = ['assignment_id' => $this->assignment->id,
            'question_id' => $question_id,
            'submission' => '6516',
            'technology' => "qti"
        ];

        $this->actingAs($this->student_user)->postJson("/api/submissions", $qti_submission)
            ->assertJson(['type' => 'success']);
        $submission = DB::table('submissions')->where('assignment_id', $this->assignment->id)->where('question_id', $question_id)->first();
        $this->assertEquals(floatVal(0), floatVal($submission->score));


    }


    /** @test */
    public function non_instructor_cannot_update_the_completion_scoring_mode()
    {
        $this->actingAs($this->student_user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-completion-scoring-mode",
                ['completion_scoring_mode' => '100% for either'])
            ->assertJson(['message' => 'You are not allowed to update that resource.']);

    }


    /** @test */
    public function correct_score_is_computed_if_shown_hint()
    {

        $this->assignment->assessment_type = 'real time';
        $this->assignment->number_of_allowed_attempts = 'unlimited';
        $this->assignment->can_view_hint = 1;
        $this->assignment->hint_penalty = 10;
        $this->assignment->save();
        DB::table('shown_hints')->insert(['user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id]);
        //$this->h5pSubmission['submission'] = str_replace('"score":{"min":0,"raw":11,"max":11,"scaled":0}', '"score":{"min":0,"raw":3,"max":11,"scaled":0}', $this->submission_object);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);
        $this->assertDatabaseHas('submissions', [
            'user_id' => $this->student_user->id,
            'question_id' => $this->question->id,
            'score' => $this->question_points * (100 - $this->assignment->hint_penalty) / 100]);
    }

    /** @test */
    public function non_student_cannot_ask_for_hint_to_be_shown()
    {
        $this->assignment->can_view_hint = true;
        $this->assignment->save();

        // $this->user owns the course and is role 2, which the policy always allows
        // regardless of enrollment. Use a role-3 user who is genuinely not enrolled
        // in this course to test the "not part of the course" denial.
        $this->actingAs($this->student_user_2)
            ->postJson("/api/shown-hints/assignments/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['message' => 'You cannot view the hint since you are not part of the course.']);

    }

    /** @test */
    public function students_can_ask_for_hint_to_be_shown_if_instructor_allows_it()
    {
        $this->assignment->can_view_hint = true;
        $this->assignment->save();

        $this->actingAs($this->student_user)
            ->postJson("/api/shown-hints/assignments/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function students_cannot_ask_for_hint_to_be_shown_if_instructor_does_not_allow_it()
    {
        $this->assignment->can_view_hint = false;
        $this->assignment->save();

        $this->actingAs($this->student_user)
            ->postJson("/api/shown-hints/assignments/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['message' => 'The instructor does not want students to view this hint.']);
    }

    private function createWebworkProblemJWT(): string
    {
        $jwe = new JWE();
        $secret = $jwe->getSecret('webwork');
        \JWTAuth::getJWTProvider()->setSecret($secret);

        $token = \JWTAuth::claims([
            'adapt' => [
                'assignment_id' => $this->assignment->id,
                'question_id' => $this->question->id,
                'technology' => 'webwork',
            ],
            'webwork' => [],
            'imathas' => [],
            'h5p' => [],
        ])->fromUser($this->student_user);

        $problemJWT = $jwe->encrypt($token, 'webwork');
        \JWTAuth::getJWTProvider()->setSecret(config('myconfig.jwt_secret'));

        return $problemJWT;
    }

    /** @test */
    public function student_requesting_hint_on_webwork_question_gets_content_from_render_api()
    {
        $this->assignment->can_view_hint = true;
        $this->assignment->save();

        $this->question->technology = 'webwork';
        $this->question->save();

        Http::fake([
            '*/render-api/hint*' => Http::response([
                'status' => 200,
                'message' => '<div>Use dimensional analysis.</div>',
            ], 200),
        ]);

        $problemJWT = $this->createWebworkProblemJWT();

        $this->actingAs($this->student_user)
            ->postJson("/api/shown-hints/assignments/{$this->assignment->id}/question/{$this->question->id}", [
                'problemJWT' => $problemJWT,
            ])
            ->assertJson([
                'type' => 'success',
                'hint' => '<div>Use dimensional analysis.</div>',
            ]);
    }

    /** @test */
    public function webwork_hint_render_api_failure_is_surfaced_to_student()
    {
        $this->assignment->can_view_hint = true;
        $this->assignment->save();

        $this->question->technology = 'webwork';
        $this->question->save();

        Http::fake([
            '*/render-api/hint*' => Http::response('server error', 500),
        ]);

        $problemJWT = $this->createWebworkProblemJWT();

        $this->actingAs($this->student_user)
            ->postJson("/api/shown-hints/assignments/{$this->assignment->id}/question/{$this->question->id}", [
                'problemJWT' => $problemJWT,
            ])
            ->assertJson(['type' => 'error']);
    }

    /** @test */
    public function requesting_the_same_hint_twice_does_not_throw_a_duplicate_key_error()
    {
        $this->assignment->can_view_hint = true;
        $this->assignment->save();

        $this->actingAs($this->student_user)
            ->postJson("/api/shown-hints/assignments/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['type' => 'success']);

        // Second call must not blow up on the shown_hints unique constraint
        $this->actingAs($this->student_user)
            ->postJson("/api/shown-hints/assignments/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['type' => 'success']);

        $this->assertEquals(1, DB::table('shown_hints')
            ->where('user_id', $this->student_user->id)
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->count());
    }

    /** @test */
    public function second_request_for_webwork_hint_refetches_fresh_content_instead_of_reusing_stale_data()
    {
        $this->assignment->can_view_hint = true;
        $this->assignment->save();

        $this->question->technology = 'webwork';
        $this->question->save();

        $problemJWT = $this->createWebworkProblemJWT();

        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            return Http::response([
                'status' => 200,
                'message' => $callCount === 1 ? '<div>First hint call</div>' : '<div>Second hint call</div>',
            ], 200);
        });

        $this->actingAs($this->student_user)
            ->postJson("/api/shown-hints/assignments/{$this->assignment->id}/question/{$this->question->id}", [
                'problemJWT' => $problemJWT,
            ])
            ->assertJson(['hint' => '<div>First hint call</div>']);

        $this->actingAs($this->student_user)
            ->postJson("/api/shown-hints/assignments/{$this->assignment->id}/question/{$this->question->id}", [
                'problemJWT' => $problemJWT,
            ])
            ->assertJson(['hint' => '<div>Second hint call</div>']);
    }

    /** @test */
    public function instructor_can_reset_submission_of_fake_student()
    {
        $this->student_user->fake_student = 1;
        $this->student_user->instructor_user_id = $this->user->id;
        $this->student_user->save();
        $this->actingAs($this->student_user)
            ->patchJson("/api/submissions/assignments/{$this->assignment->id}/question/{$this->question->id}/reset-submission")
            ->assertJson(['message' => 'Resetting the submission.']);
    }

    /** @test */
    public function non_instructor_cannot_resubmit_submission_of_fake_student()
    {
        $this->student_user->fake_student = 1;
        $this->user->role = 3;
        $this->user->save();
        $this->actingAs($this->user) //not sending the session information
        ->patchJson("/api/submissions/assignments/{$this->assignment->id}/question/{$this->question->id}/reset-submission")
            ->assertJson(['message' => 'You are not allowed to reset this submission.']);
    }

    /** @test */
    public function student_must_be_fake_student()
    {
        $this->student_user->role = 3;
        $this->student_user->save();
        $this->actingAs($this->student_user)
            ->withSession(['instructor_user_id' => $this->user->id])
            ->patchJson("/api/submissions/assignments/{$this->assignment->id}/question/{$this->question->id}/reset-submission")
            ->assertJson(['message' => 'You are not allowed to reset this submission.']);

    }

    /** @test */
    public function formative_student_can_reset_submission()
    {
        $this->student_user->formative_student = 1;
        $this->student_user->save();
        $this->actingAs($this->student_user)
            ->withSession(['instructor_user_id' => $this->user->id])
            ->patchJson("/api/submissions/assignments/{$this->assignment->id}/question/{$this->question->id}/reset-submission")
            ->assertJson(['message' => 'Resetting the submission.']);

    }

    public function getSubmissionFileData(): array
    {
        $original_filename = 'some_file.txt';
        $upload_file_data = ['assignment_id' => $this->assignment->id,
            'upload_file_type' => 'submission',
            'file_name' => $original_filename];
        $response = $this->actingAs($this->student_user)->postJson("/api/s3/pre-signed-url", $upload_file_data)->original;
        $s3_key = $response['s3_key'];
        Storage::disk('s3')->put($s3_key, 'some file contents');
        return [
            "submissionFile" => $response['submission'],
            "assignmentId" => $this->assignment->id,
            "questionId" => $this->question->id,
            "type" => "submission",
            "s3_key" => $s3_key,
            "original_filename" => $original_filename,
            "uploadLevel" => "question",
            "_method" => "put"
        ];
    }

    /** @test */
    public function non_instructor_cannot_get_question_to_edit()
    {
        $this->actingAs($this->student_user)->getJson("/api/questions/get-question-to-edit/{$this->question->id}")
            ->assertJson(['message' => 'You are not allowed to get that question for editing.']);
    }

    /** @test */
    public function instructor_can_get_question_to_edit()
    {
        $this->actingAs($this->user)->getJson("/api/questions/get-question-to-edit/{$this->question->id}")
            ->assertJson(['type' => 'success']);
    }


    /** @test */
    public function will_not_give_a_lower_score_if_number_of_attempts_penalty_makes_the_score_lower_than_what_you_have()
    {

        $this->assignment->assessment_type = 'real time';
        $this->assignment->number_of_allowed_attempts = 'unlimited';
        $this->assignment->number_of_allowed_attempts_penalty = '10';
        $this->assignment->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => 'Your current score on this problem is 10.0000 points.<br><br>This new submission would give you a score of 9 points (including a penalty of 10%).<br><br>If accepted, this submission would reduce your question score by 1 points and is therefore, not accepted.']);


    }

    /** @test */
    public function score_is_not_reduced_if_with_late_policy_score_is_lower_than_current_score()
    {

        $this->assignment->late_policy = 'deduction';
        $this->assignment->late_deduction_application_period = '1 hour';
        $this->assignment->late_deduction_percent = 10;
        $this->assignment->save();
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission);

        $now = Carbon::now();
        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = $now->subHour()->subMinutes(2)->toDateTimeString();//was due an hour and 2 minutes ago -- should penalize 20%
        $assignToTiming->final_submission_deadline = $now->addHours(5)->toDateTimeString();
        $assignToTiming->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => 'With the late deduction, submitting will give you a lower score on this question than you currently have so the submission will not be accepted.']);

    }


    /** @test */
    public function performance_score_is_correctly_computed_for_a_deduction_only_once_late_policy()
    {

        $this->assignment->late_policy = 'deduction';
        $this->assignment->late_deduction_application_period = 'once';
        $this->assignment->late_deduction_percent = 50;
        $this->assignment->save();
        $now = Carbon::now();
        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();

        $assignToTiming->due = $now->subHour()->toDateTimeString();//was due an hour ago.
        $assignToTiming->final_submission_deadline = $now->addHours(2)->toDateTimeString();
        $assignToTiming->save();

        $this->actingAs($this->student_user)
            ->postJson("/api/submissions", $this->h5pSubmission);

        $submission = Submission::where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->where('question_id', $this->question->id)
            ->first();

        $this->assertEquals($submission->score, $this->question_points * $this->assignment->late_deduction_percent / 100, 'works correctly for performance');


    }

    /** @test */

    public function completion_score_is_correctly_computed_for_a_deduction_only_once_late_policy()
    {

        $this->assignment->late_policy = 'deduction';
        $this->assignment->late_deduction_application_period = 'once';
        $this->assignment->late_deduction_percent = 50;
        $question_points = 100;//making it different than before to test out the completion version
        DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['points' => $question_points,
                'completion_scoring_mode' => '100% for either']);

        $this->assignment->scoring_type = 'c';
        $this->assignment->save();
        $now = Carbon::now();
        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();

        $assignToTiming->due = $now->subHour()->toDateTimeString();//was due an hour ago.
        $assignToTiming->final_submission_deadline = $now->addHours(2)->toDateTimeString();
        $assignToTiming->save();

        $this->actingAs($this->student_user)
            ->postJson("/api/submissions", $this->h5pSubmission);

        $submission = Submission::where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->where('question_id', $this->question->id)
            ->first();
        $this->assertEquals($submission->score, $question_points * $this->assignment->late_deduction_percent / 100, 'works correctly for completion');

    }

    /** @test */
    public function must_be_part_of_a_real_time_unlimited_attempts_to_view_solutions_in_real_time()
    {

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->student_user)
            ->postJson("/api/solutions/show-solution/{$this->assignment->id}/{$this->question->id}")
            ->assertJson(['message' => 'You cannot view the solutions since this is not a real time nor learning tree assessment.']);

    }

    /** @test */
    public function if_part_of_a_real_time_unlimited_attempts_with_submission_can_view_solution()
    {
        $this->assignment->assessment_type = 'real time';
        $this->assignment->number_of_allowed_attempts = 'unlimited';
        $this->assignment->save();
        $submission = Submission::where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->where('user_id', $this->student_user->id)
            ->first();
        $this->assertEquals(null, $submission);

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->student_user)
            ->postJson("/api/solutions/show-solution/{$this->assignment->id}/{$this->question->id}")
            ->assertJson(['type' => 'success']);
        $submission = Submission::where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->where('user_id', $this->student_user->id)
            ->first();
        $this->assertEquals(1, $submission->show_solution);
    }

    /** @test */
    public function cannot_submit_if_it_is_show_solution()
    {
        $this->assignment->assessment_type = 'real time';
        $this->assignment->number_of_allowed_attempts = 'unlimited';
        $this->assignment->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);
        Submission::where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->where('user_id', $this->student_user->id)
            ->update(['show_solution' => 1]);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => "The solution is already available so you cannot resubmit."]);


    }

    /** @test */
    public function cannot_submit_if_they_gave_up()
    {
        $this->assignment->assessment_type = 'real time';
        $this->assignment->number_of_allowed_attempts = 'unlimited';
        $this->assignment->save();

        CanGiveUp::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'status' => 'gave up']);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => "The solution is already available so you cannot resubmit."]);


    }


    /** @test */
    public function must_submit_at_least_once_to_view_solutions_in_real_time()
    {

        $this->assignment->assessment_type = 'real time';
        $this->assignment->number_of_allowed_attempts = 'unlimited';
        $this->assignment->save();
        $this->actingAs($this->student_user)
            ->postJson("/api/solutions/show-solution/{$this->assignment->id}/{$this->question->id}")
            ->assertJson(['message' => 'Please submit at least once before looking at the solution.']);
    }

    /** @test */
    public function if_no_submission_but_can_give_up_exists_they_can_view_the_solution()
    {
        $canGiveUp = new CanGiveUp();
        $canGiveUp->user_id = $this->student_user->id;
        $canGiveUp->assignment_id = $this->assignment->id;
        $canGiveUp->question_id = $this->question->id;
        $canGiveUp->status = 'can give up';
        $canGiveUp->save();

        $this->assignment->assessment_type = 'real time';
        $this->assignment->number_of_allowed_attempts = 'unlimited';
        $this->assignment->save();
        $this->actingAs($this->student_user)
            ->postJson("/api/solutions/show-solution/{$this->assignment->id}/{$this->question->id}")
            ->assertJson(['type' => 'success']);
    }


    /** @test */
    public function number_of_attempts_dictated_in_real_time()
    {
        $this->assignment->assessment_type = 'real time';
        $this->assignment->number_of_allowed_attempts = '1';
        $this->assignment->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => 'You are only allowed 1 attempt.  ']);

        $this->assignment->number_of_allowed_attempts = '2';
        $this->assignment->save();
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => 'You are only allowed 2 attempts.  ']);

        $this->assignment->number_of_allowed_attempts = 'unlimited';
        $this->assignment->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);


    }

    /** @test */
    public function non_instructor_cannot_email_about_solution_error()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/libretexts/solution-error",
                ['question_id' => $this->question->id,
                    'text' => 'Here is my text explaining the issue'])
            ->assertJson(['message' => 'You are not allowed to send a solution email error.']);
    }

    /** @test */
    public function instructor_can_email_about_solution_error()
    {
        $this->actingAs($this->user)
            ->postJson("/api/libretexts/solution-error",
                ['question_id' => $this->question->id,
                    'text' => 'Here is my text explaining the issue'])
            ->assertJson(['type' => 'success']);
    }


    /** @test */

    public function with_a_late_assignment_policy_of_not_accepted_a_student_can_submit_auto_graded_response_if_assignment_past_due_and_no_extension_if_allowed()
    {

        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = "2001-03-05 09:00:00";//was due an hour ago.
        $assignToTiming->save();
        QuestionLevelOverride::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'auto_graded' => '1',
            'open_ended' => '0'
        ]);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => 'Auto-graded submission saved.']);
        DB::table('question_level_overrides')->delete();
        AssignmentLevelOverride::create(['assignment_id' => $this->assignment->id,
            'user_id' => $this->student_user->id]);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => 'Auto-graded submission saved.']);

    }


    /** @test */
    public function with_a_late_assignment_policy_of_not_accepted_a_student_can_submit_text_response_if_assignment_past_due_and_no_extension_if_allowed()
    {

        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = "2001-03-05 09:00:00";//was due an hour ago.
        $assignToTiming->save();
        QuestionLevelOverride::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'auto_graded' => '0',
            'open_ended' => '1'
        ]);

        $this->actingAs($this->student_user)->postJson("/api/submission-texts", [
                'questionId' => $this->question->id,
                'assignmentId' => $this->assignment->id,
                'text_submission' => 'Some other cool text after it was graded.']
        )->assertJson(['message' => 'Your text submission was saved.']);
        DB::table('question_level_overrides')->delete();
        AssignmentLevelOverride::create(['assignment_id' => $this->assignment->id,
            'user_id' => $this->student_user->id]);
        $this->actingAs($this->student_user)->postJson("/api/submission-texts", [
                'questionId' => $this->question->id,
                'assignmentId' => $this->assignment->id,
                'text_submission' => 'Some other cool text after it was graded.']
        )->assertJson(['message' => 'Your text submission was saved.']);

    }

    /** @test */

    public function with_a_late_assignment_policy_of_not_accepted_a_student_can_submit_question_level_file_response_if_assignment_past_due_and_no_extension_if_allowed()
    {

        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = "2001-03-05 09:00:00";//was due an hour ago.
        $assignToTiming->save();
        QuestionLevelOverride::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'auto_graded' => '0',
            'open_ended' => '1'
        ]);

        $submission_file_data = $this->getSubmissionFileData();
        $this->actingAs($this->student_user)->postJson("/api/submission-files", $submission_file_data)
            ->assertJson(['message' => 'Your file submission has been saved.']);

    }


    /** @test */

    public function student_gets_full_credit_if_incorrect_for_complete_incomplete_assignment()
    {

        $this->assignment->scoring_type = 'c';
        $this->assignment->save();
        $question_points = 20;
        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['points' => $question_points, 'open_ended_submission_type' => 0]);

        $this->h5pSubmission['submission_object'] = str_replace('"score":{
        "min":0,"raw":11,"max":11,"scaled":0}', '"score":{
        "min":0,"raw":3,"max":11,"scaled":0}', $this->submission_object);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission);
        $assignment_score = DB::table('scores')
            ->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->first()
            ->score;
        $this->assertEquals($assignment_score, $question_points);

    }


    /** @test */

    public function student_gets_correct_credit_if_incorrect_for_complete_incomplete_assignment_with_open_ended_component()
    {
        $this->assignment->scoring_type = 'c';
        $this->assignment->save();
        $question_points = 20;
        $percent = 50;
        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['points' => $question_points,
                'open_ended_submission_type' => 'file',
                'completion_scoring_mode' => "$percent% for auto-graded"]);
        $this->h5pSubmission['submission_object'] = str_replace('"score":{
        "min":0,"raw":11,"max":11,"scaled":0}', '"score":{
        "min":0,"raw":3,"max":11,"scaled":0}', $this->submission_object);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission);
        $assignment_score = DB::table('scores')
            ->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->first()
            ->score;
        $this->assertEquals($assignment_score, ($percent / 100) * $question_points);

    }


    /** @test */
    public function submitted_file_gets_correct_credit_if_scoring_type_is_completed_and_score_is_either()
    {
        $this->assignment->scoring_type = 'c';
        $this->assignment->save();
        $submission_file_data = $this->getSubmissionFileData();

        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['completion_scoring_mode' => '100% for either']);
        $this->actingAs($this->student_user)->postJson("/api/submission-files", $submission_file_data)
            ->assertJson(['type' => 'success']);
        $points = $this->getPoints($this->assignment->id, $this->question->id);
        $score = $this->getScore($this->assignment->id, $this->student_user->id);

        $this->assertEquals(floatval($points), floatval($score));//includes a technology piece

    }

    /** @test */
    public function submitted_file_gets_correct_credit_if_scoring_type_is_completed_and_score_is_either_with_already_submitted_auto_graded()
    {

        $this->h5pSubmission['submission_object'] = str_replace('"score":{
        "min":0,"raw":11,"max":11,"scaled":0}', '"score":{
        "min":0,"raw":3,"max":11,"scaled":0}', $this->submission_object);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission);
        $score_with_just_auto_graded = $this->getScore($this->assignment->id, $this->student_user->id);

        $this->assignment->scoring_type = 'c';
        $this->assignment->save();
        $submission_file_data = $this->getSubmissionFileData();


        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['completion_scoring_mode' => '100% for either']);
        $this->actingAs($this->student_user)->postJson("/api/submission-files", $submission_file_data)
            ->assertJson(['type' => 'success']);
        $points = $this->getPoints($this->assignment->id, $this->question->id);
        $score_with_both = $this->getScore($this->assignment->id, $this->student_user->id);

        $this->assertEquals(floatval($score_with_just_auto_graded), floatval($points));//includes a technology piece
        $this->assertEquals(floatval($score_with_both), floatval($points));//includes a technology piece

    }

    /** @test */
    public function submitted_file_gets_correct_credit_if_scoring_type_is_completed_and_score_is_split()
    {
        $this->assignment->scoring_type = 'c';
        $this->assignment->save();
        $percent = 20;
        $submission_file_data = $this->getSubmissionFileData();

        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['completion_scoring_mode' => "$percent% for auto-graded"]);
        $this->actingAs($this->student_user)->postJson("/api/submission-files", $submission_file_data)
            ->assertJson(['type' => 'success']);
        $points = $this->getPoints($this->assignment->id, $this->question->id);
        $score = $this->getScore($this->assignment->id, $this->student_user->id);

        $this->assertEquals(floatval($points) * (100 - $percent) / 100, floatval($score));//includes a technology piece

    }

    /** @test */

    public function update_page_gets_full_credit_if_scoring_type_is_completed()
    {
        $percent = 50;
        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['completion_scoring_mode' => "$percent% for auto-graded"]);
        $this->assignment->scoring_type = 'c';
        $this->assignment->save();
        $submission_file_data = [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'type' => 'a',
            'user_id' => $this->student_user->id,
            'original_filename' => 'blah blah',
            'submission' => 'sflkjfwlKEKLie.pdf',
            'date_submitted' => Carbon::now()
        ];
        DB::table('submission_files')->insert($submission_file_data);
        $this->actingAs($this->student_user)->patchJson("/api/submission-files/{$this->assignment->id}/{$this->question->id}/page", ['page' => 1])
            ->assertJson(['type' => 'success']);
        $points = $this->getPoints($this->assignment->id, $this->question->id);
        $score = $this->getScore($this->assignment->id, $this->student_user->id);

        $this->assertEquals(floatval($points * (100 - $percent) / 100), floatval($score));//includes a technology piece

    }

    /** @test */
    public function text_file_gets_full_credit_if_scoring_type_is_completed()
    {
        $percent = 50;
        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['completion_scoring_mode' => "$percent% for auto-graded"]);
        $this->assignment->scoring_type = 'c';
        $this->assignment->save();
        $submission_text_data = [
            "text_submission" => "Here is my text!",
            "assignmentId" => $this->assignment->id,
            "questionId" => $this->question->id
        ];
        $this->actingAs($this->student_user)->postJson("/api/submission-texts", $submission_text_data)
            ->assertJson(['type' => 'success']);
        $points = $this->getPoints($this->assignment->id, $this->question->id);
        $score = $this->getScore($this->assignment->id, $this->student_user->id);

        $this->assertEquals(floatval($points * (100 - $percent) / 100), floatval($score));//includes a technology piece

    }


    /** @test */
    public function submitted_file_gets_full_credit_if_scoring_type_is_completed_for_just_text_type_question()
    {
        $this->assignment->scoring_type = 'c';
        $this->assignment->save();
        $this->question->technology = 'text';
        $this->question->save();
        $submission_file_data = $this->getSubmissionFileData();
        $this->actingAs($this->student_user)->postJson("/api/submission-files", $submission_file_data)
            ->assertJson(['type' => 'success']);
        $points = $this->getPoints($this->assignment->id, $this->question->id);
        $score = $this->getScore($this->assignment->id, $this->student_user->id);

        $this->assertEquals(floatval($points), floatval($score));//no technology piece

    }

    /** @test */

    public function student_can_submit_a_file_submission()
    {

        $submission_file_data = $this->getSubmissionFileData();
        $this->actingAs($this->student_user)->postJson("/api/submission-files", $submission_file_data)
            ->assertJson(['message' => 'Your file submission has been saved.']);

    }


    /** @test */

    public function submission_with_lti_launch_is_entered_as_pending()
    {
        LTiLaunch::create(['assignment_id' => $this->assignment->id,
            'user_id' => $this->student_user->id,
            'launch_id' => '12345',
            'jwt_body' => 'some body'
        ]);
        $this->course->lms = 1;
        $this->course->save();
        $this->assignment->lms_grade_passback = 'automatic';
        $this->assignment->save();

        $this->h5pSubmission['submission_object'] = str_replace('"score":{
        "min":0,"raw":11,"max":11,"scaled":0}', '"score":{
        "min":0,"raw":3,"max":11,"scaled":0}', $this->submission_object);
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission);
        $this->assertDatabaseHas('lti_grade_passbacks', ['assignment_id' => $this->assignment->id,
            'user_id' => $this->student_user->id,
            'launch_id' => '12345',
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function alpha_course_cannot_switch_open_ended_submission_type_if_beta_submission_exists()
    {

        $this->course->alpha = 1;
        $this->course->save();

        $data = [
            'type' => 'q',
            'assignment_id' => $this->beta_assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'fake_1.pdf',
            'score' => 10,
            'original_filename' => 'orig_fake_1.pdf',
            'user_id' => $this->student_user->id,
            'date_submitted' => Carbon::now()];
        SubmissionFile::create($data);

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-open-ended-submission-type", ['open_ended_submission_type' => 'file'])
            ->assertJson(['message' => "There is at least one graded submission to this question in either the Alpha assignment or one of the Beta assignments so you can't change the open-ended submission type."]);
    }

    /** @test */
    public function alpha_course_switch_open_ended_submission_type_only_affects_beta_courses()
    {

        $this->course->alpha = 1;
        $this->course->save();

        DB::table('assignment_question')->insert([
            'assignment_id' => $this->beta_assignment->id,
            'question_id' => $this->question->id,
            'points' => $this->question_points,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);

        $before_change_count = count(DB::table('assignment_question')
            ->where('open_ended_submission_type', 'file')
            ->where('question_id', $this->question->id)
            ->get());
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-open-ended-submission-type", ['open_ended_submission_type' => 'rich text'])
            ->assertJson(['type' => "success"]);

        //start with 1 alpha, 1 beta, and 1 other with the same question
        $this->assertEquals($before_change_count - 2, 1);

    }


    /** @test */
    public function cannot_switch_open_ended_submission_type_if_is_beta_course()
    {
        $this->actingAs($this->beta_user)
            ->patchJson("/api/assignments/{$this->beta_assignment->id}/questions/{$this->question->id}/update-open-ended-submission-type", ['open_ended_submission_type' => 'file'])
            ->assertJson(['message' => "This is an assignment in a Beta course so you can't change the open-ended submission type."]);

    }


    /** @test */
    public function cannot_switch_open_ended_submission_type_if_graded_submission_exists()
    {
        $data = [
            'type' => 'q',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'fake_1.pdf',
            'original_filename' => 'orig_fake_1.pdf',
            'user_id' => $this->student_user->id,
            'score' => 10,
            'date_submitted' => Carbon::now()];
        SubmissionFile::create($data);

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-open-ended-submission-type", ['open_ended_submission_type' => 'file'])
            ->assertJson(['message' => "There is at least one graded submission to this question so you can't change the open-ended submission type."]);
    }

    /** @test */
    public function owner_can_switch_open_ended_submission_type()
    {
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-open-ended-submission-type", ['open_ended_submission_type' => 'file'])
            ->assertJson(['message' => 'The open-ended submission type has been updated.']);

    }

    /** @test */

    public function non_owner_cannot_update_iframe_properties()
    {
        $this->actingAs($this->user_2)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/iframe-properties",
            ['item' => 'submission'])
            ->assertJson(['message' => "You are not allowed to update the iframe properties for that question."]);
    }

    /** @test */

    public function owner_can_update_iframe_properties()
    {
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/iframe-properties",
            ['item' => 'submission'])
            ->assertJson(['type' => 'success']);
        $this->assertDatabaseHas('assignment_question',
            ['assignment_id' => $this->assignment->id,
                'question_id' => $this->question->id,
                'submission_information_shown_in_iframe' => 1]);

    }


    /** @test */

    public function non_instructor_cannot_update_properties()
    {

        $this->actingAs($this->student_user)->patchJson("/api/questions/properties/{$this->question->id}")
            ->assertJson(['message' => "You are not allowed to update the question's properties."]);

    }

    /** @test */

    public function instructor_can_update_properties()
    {
        $this->actingAs($this->user)->patchJson("/api/questions/properties/{$this->question->id}", ['auto_attribution' => 1])
            ->assertJson(['message' => "The question's properties have been updated."]);
    }

    /** @test */

    public function non_owner_cannot_remove_solution()
    {

        $this->actingAs($this->user_2)->deleteJson("/api/solution-files/{$this->assignment->id}/{$this->question->id}")
            ->assertJson(['message' => 'You are not allowed to remove this solution.']);
    }

    /** @test */
    public function owner_can_remove_solution()
    {
        Solution::create([
            'user_id' => $this->user->id,
            'type' => 'q',
            'file' => 'some_file.pdf',
            'original_filename' => 'blah blah',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id]);
        $this->actingAs($this->user)->deleteJson("/api/solution-files/{$this->assignment->id}/{$this->question->id}")
            ->assertJson(['message' => 'The solution has been removed.']);
    }


    /** @test */

    public function completed_assignment_returns_true_when_all_submitted()
    {


    }


    /** @test */
    public function non_owner_cannot_switch_open_ended_submission_type()
    {
        $this->actingAs($this->student_user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-open-ended-submission-type", ['open_ended_submission_type' => 'file'])
            ->assertJson(['message' => 'You are not allowed to update the open-ended submission type.']);

    }


    /** @test */
    public function cannot_store_a_file_if_the_number_of_uploads_exceeds_the_max_number_of_uploads()
    {


    }

    /** @test */
    public function cannot_store_a_file_if_the_size_of_the_file_exceeds_the_max_size_permitted()
    {

    }


    /** @test */

    public function non_instructor_cannot_get_pre_signed_url_for_solutions()
    {
        $this->actingAs($this->student_user)->postJson("/api/s3/pre-signed-url", $this->upload_solution_data)
            ->assertJson(['message' => 'You are not allowed to upload solution files.']);


    }


    /** @test */
    public function instructor_can_get_pre_signed_url_for_solutions()
    {
        $this->actingAs($this->user)->postJson("/api/s3/pre-signed-url", $this->upload_solution_data)
            ->assertJson(['type' => 'success']);

    }

    /** @test */

    public function non_enrolled_student_cannot_get_pre_signed_url_for_submissions()
    {
        $this->actingAs($this->student_user_2)->postJson("/api/s3/pre-signed-url", $this->upload_file_submission_data)
            ->assertJson(['message' => 'You are not allowed to upload submission files.']);

    }

    /** @test */

    public function enrolled_student_can_get_pre_signed_url_for_submissions()
    {
        $this->actingAs($this->student_user)->postJson("/api/s3/pre-signed-url", $this->upload_file_submission_data)
            ->assertJson(['type' => 'success']);

    }


    public function cannot_store_a_question_file_if_it_is_not_in_the_assignment()
    {
        /** tested for regular submissions */

    }

    /** @test */
    public function correctly_computes_the_z_score_for_a_file_submission()
    {

        $scores = [80, 40, 36];


        //create fake submissions --- I just care about the scores.
        $this->assignment->show_scores = 1;
        $this->assignment->save();

        $this->question->technology = 'h5p';
        $this->question->save();
        $data = [
            'type' => 'q',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'fake_1.pdf',
            'original_filename' => 'orig_fake_1.pdf',
            'date_submitted' => Carbon::now()];

        $user_ids = [$this->student_user->id, $this->student_user_2->id, $this->student_user_3->id];
        foreach ($user_ids as $key => $user_id) {
            $data['score'] = $scores[$key];
            $data['user_id'] = $user_ids[$key];
            SubmissionFile::create($data);
        }

        $mean = array_sum($scores) / count($scores);
        $std_dev = $this->stats_standard_deviation($scores);
        $z_score = Round(($scores[0] - $mean) / $std_dev, 2);


        $response = $this->actingAs($this->student_user)->getJson("/api/assignments/{$this->assignment->id}/questions/view", $this->headers());
        $this->assertEquals($z_score, $response['questions'][0]['submission_file_z_score']);

    }

    /** @test */

    public function students_get_correct_number_of_points_for_randomized_assignment()
    {

        $this->assignment->number_of_randomized_assessments = 1;
        $this->assignment->save();
        $response = $this->actingAs($this->student_user)
            ->getJson("/api/assignments/{$this->assignment->id}/view-questions-info", $this->headers());
        $this->assertEquals($response['assignment']['total_points'], $this->assignment->number_of_randomized_assessments * $this->assignment->default_points_per_question);
    }


    /** @test */

    public function students_get_correct_number_of_questions_for_randomized_assignment()
    {

        $this->assignment->number_of_randomized_assessments = 1;
        $this->assignment->save();
        $response = $this->actingAs($this->student_user)
            ->getJson("/api/assignments/{$this->assignment->id}/questions/view", $this->headers());
        $this->assertEquals(count($response['questions']), $this->assignment->number_of_randomized_assessments);

    }

    /** @test */

    public function owner_can_start_a_clicker_assessment()
    {
        $this->actingAs($this->user)->postJson("/api/assignments/{$this->assignment->id}}/questions/{$this->question->id}/start-clicker-assessment", ['time_to_submit' => '30 seconds'])
            ->assertJson(['message' => 'Your students can begin submitting responses.']);
    }

    /** @test */

    public function time_to_submit_a_clicker_assessment_must_be_valid()
    {
        $this->actingAs($this->user)->postJson("/api/assignments/{$this->assignment->id}}/questions/{$this->question->id}/start-clicker-assessment", ['time_to_submit' => '30 oogas'])
            ->assertJsonValidationErrors(['time_to_submit']);
    }

    /** @test */

    public function students_cannot_email_users_if_the_user_did_not_grade_their_question()
    {
        $this->actingAs($this->student_user_2)->postJson('/api/email/send', [
            'name' => 'Ima Student',
            'email' => 'some@email.com',
            'subject' => 'Grading issue',
            'text' => 'some student complaint',
            'type' => 'contact_grader',
            'extraParams' => ['question_id' => $this->question->id, 'assignment_id' => $this->assignment->id],
            'to_user_id' => 100000,
        ])
            ->assertJson(['message' => 'You are not allowed to send that person an email.']);

    }


    /** @test */
    public function removes_submission_files_if_question_removed()
    {

        //submitted the first one
        $data = [
            'type' => 'q',
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'fake_1.pdf',
            'original_filename' => 'orig_fake_1.pdf',
            'date_submitted' => Carbon::now()];
        SubmissionFile::create($data);
        $data['question_id'] = $this->question_2->id;

        //submitted the second one
        SubmissionFile::create($data);

        //remove the first one
        $num_submissions_before_delete = SubmissionFile::where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->get();

        $this->actingAs($this->user)->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question_2->id}");
        //should give back score of complete
        $num_submissions_after_delete = SubmissionFile::where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->get();

        $this->assertEquals(count($num_submissions_before_delete) - 1, count($num_submissions_after_delete));

    }

    /** @test */
    public function removes_submissions_if_question_removed()
    {

        //submitted the first one
        Submission::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'score' => 5,
            'submission_count' => 1,
            'answered_correctly_at_least_once' => false,
            'submission' => $this->submission_object]);
        //submitted the second one
        Submission::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question_2->id,
            'user_id' => $this->student_user->id,
            'score' => 5,
            'submission_count' => 1,
            'answered_correctly_at_least_once' => false,
            'submission' => $this->submission_object]);
        //remove the first one
        $num_submissions_before_delete = Submission::where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->get();

        $this->actingAs($this->user)->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question_2->id}");
        //should give back score of complete
        $num_submissions_after_delete = Submission::where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->get();

        $this->assertEquals(count($num_submissions_before_delete) - 1, count($num_submissions_after_delete));

    }


    /** @test */
    public function owner_can_submit_solution_text_attached_to_audio()
    {

        Solution::create([
            'user_id' => $this->user->id,
            'type' => 'audio',
            'file' => 'some_file.mpg',
            'original_filename' => 'blah blah',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id]);
        $this->actingAs($this->user)->postJson("/api/solutions/text/{$this->assignment->id}/{$this->question->id}",
            ['solution_text' => 'some text',
                'question_id' => $this->question->id]
        )->assertJson(['message' => 'Your text solution has been saved.']);

    }


    /** @test */

    public function audio_must_exist_before_submitting_solution_text()
    {
        $this->actingAs($this->user)->postJson("/api/solutions/text/{$this->assignment->id}/{$this->question->id}",
            ['solution_text' => 'My super cool text']
        )->assertJsonValidationErrors('solution_text');

    }

    /** @test */

    public function solution_text_must_not_be_empty()
    {
        Solution::create([
            'user_id' => $this->user->id,
            'type' => 'q',
            'file' => 'some_file.pdf',
            'original_filename' => 'blah blah',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id]);

        $this->actingAs($this->user)->postJson("/api/solutions/text/{$this->assignment->id}/{$this->question->id}",
            ['solution_text' => '']
        )->assertJsonValidationErrors('solution_text');

    }


    /** @test */
    public function correctly_recomputes_assignment_score_of_removed_question_for_points_scoring_type_for_submission_or_submission_file()
    {
        $submission_file_score = 10;
        $submission_score = 20;
        $current_assignment_score = 93;
        SubmissionFile::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'type' => 'text',
            'original_filename' => '',
            'score' => $submission_file_score,
            'submission' => 'some text',
            'date_submitted' => Carbon::now()]);
        Submission::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'score' => $submission_score,
            'submission_count' => 1,
            'answered_correctly_at_least_once' => false,
            'submission' => $this->submission_object]);
        Score::create(['assignment_id' => $this->assignment->id,
            'user_id' => $this->student_user->id,
            'score' => $current_assignment_score]);

        $this->actingAs($this->user)->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}")
            ->assertJson(['type' => 'info']);
        $new_score = Score::where('assignment_id', $this->assignment->id)->where('user_id', $this->student_user->id)->first()->score;
        $this->assertEquals($current_assignment_score - $submission_file_score - $submission_score, $new_score);
    }


    /** @test */
    public function student_cannot_submit_text_if_it_was_graded()
    {

        SubmissionFile::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'type' => 'text',
            'original_filename' => '',
            'submission' => 'some text',
            'date_submitted' => Carbon::now()]);
        DB::table('submission_files')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->where('user_id', $this->student_user->id)
            ->update(['date_graded' => Carbon::now()]);

        $this->actingAs($this->student_user)->postJson("/api/submission-texts", [
                'questionId' => $this->question->id,
                'assignmentId' => $this->assignment->id,
                'text_submission' => 'Some other cool text after it was graded.']
        )->assertJson(['message' => 'Your submission has already been graded and may not be re-submitted.']);

    }

    public function student_can_submit_text()
    {
        $this->actingAs($this->student_user)->postJson("/api/submission-texts", [
                'questionId' => $this->question->id,
                'assignmentId' => $this->assignment->id,
                'text_submission' => 'Here is my cool text.']
        )->assertJson(['message' => 'Your text submission was saved.']);
    }


    /** @test */
    public function must_contain_text_when_submitting()
    {

        $this->actingAs($this->student_user)->postJson("/api/submission-texts", [
                'questionId' => $this->question->id,
                'assignmentId' => $this->assignment->id,
                'text_submission' => '']
        )->assertJson(['message' => 'You did not submit any text.']);

    }


    /** @test */

    public function must_submit_a_question_with_a_valid_technology()
    {

        $this->h5pSubmission['technology'] = 'bogus technology';
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)->assertStatus(422);

    }


    /** @test */
    public function correctly_computes_the_z_score_if_there_is_no_file_submission()
    {

        $scores = [40, 36];

        DB::table('assignment_question')
            ->where('question_id', $this->question->id)
            ->update(['open_ended_submission_type' => 'file']);

        //create fake submissions --- I just care about the scores.
        $this->assignment->show_scores = 1;
        $this->assignment->save();

        $this->question->technology = 'h5p';
        $this->question->save();
        $data = [
            'type' => 'q',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'fake_1.pdf',
            'original_filename' => 'orig_fake_1.pdf',
            'date_submitted' => Carbon::now()];

        $user_ids = [$this->student_user_2->id, $this->student_user_3->id];
        foreach ($user_ids as $key => $user_id) {
            $data['score'] = $scores[$key];
            $data['user_id'] = $user_ids[$key];
            SubmissionFile::create($data);
        }

        $mean = array_sum($scores) / count($scores);
        $std_dev = $this->stats_standard_deviation($scores);
        $z_score = Round(($scores[0] - $mean) / $std_dev, 2);
        //need the token....

        $response = $this->actingAs($this->student_user)->getJson("/api/assignments/{$this->assignment->id}/questions/view", $this->headers());
        $this->assertEquals('N/A', $response['questions'][0]['submission_file_z_score']);

    }

    /** @test */
    public function correctly_computes_the_z_score_for_a_question_submission()
    {
        $scores = [80, 40, 36];

        //create fake submissions --- I just care about the scores.
        $this->assignment->show_scores = 1;
        $this->assignment->save();

        $this->question->technology = 'h5p';
        $this->question->save();
        $data = ['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission_count' => 1,
            'answered_correctly_at_least_once' => false,
            'submission' => $this->submission_object];
        $user_ids = [$this->student_user->id, $this->student_user_2->id, $this->student_user_3->id];
        foreach ($user_ids as $key => $user_id) {
            $data['score'] = $scores[$key];
            $data['user_id'] = $user_ids[$key];
            Submission::create($data);
        }
    }


    /** @test */
    public function correctly_computes_the_z_score_if_there_is_no_question_submission()
    {
        $scores = [40, 36];

        //create fake submissions --- I just care about the scores.
        $this->assignment->show_scores = 1;
        $this->assignment->save();

        $this->question->technology = 'h5p';
        $this->question->save();
        $data = ['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission_count' => 1,
            'answered_correctly_at_least_once' => false,
            'submission' => $this->submission_object];
        $user_ids = [$this->student_user_2->id, $this->student_user_3->id];
        foreach ($user_ids as $key => $user_id) {
            $data['score'] = $scores[$key];
            $data['user_id'] = $user_ids[$key];
            Submission::create($data);
        }

        $mean = array_sum($scores) / count($scores);
        $std_dev = $this->stats_standard_deviation($scores);
        $z_score = Round(($scores[0] - $mean) / $std_dev, 2);


        $response = $this->actingAs($this->student_user)->getJson("/api/assignments/{$this->assignment->id}/questions/view", $this->headers());
        $this->assertEquals('N/A', $response['questions'][0]['submission_z_score']);

    }


    /** @test */
    public function score_is_correctly_computed_for_a_deduction_with_time_periods_late_policy()
    {

        $this->assignment->late_policy = 'deduction';
        $this->assignment->late_deduction_application_period = '1 hour';
        $this->assignment->late_deduction_percent = 10;
        $this->assignment->save();
        $now = Carbon::now();
        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = $now->subHour()->subMinutes(2)->toDateTimeString();//was due an hour and 2 minutes ago -- should penalize 20%
        $assignToTiming->final_submission_deadline = $now->addHours(5)->toDateTimeString();
        $assignToTiming->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission);
        $submission = Submission::where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->where('question_id', $this->question->id)
            ->first();
//2 periods, therefore the 2....
        $this->assertEquals($submission->score, $this->question_points * (1 - 2 * $this->assignment->late_deduction_percent / 100));

    }


    /** @test */
    public function real_time_solutions_can_only_be_downloaded_after_initial_submission()
    {


    }


    /** @test */

    public function late_question_submission_marked_late_for_marked_late_late_policy()
    {
//todo

    }

    /** @test */

    public function late_file_submission_marked_late_for_marked_late_late_policy()
    {
//todo

    }


    /** @test */
    public function not_accepted_late_policy_will_not_accept_late_submissions()
    {

        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = "2001-03-05 09:00:00";//was due an hour ago.
        $assignToTiming->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => 'No responses will be saved since the due date for this assignment has passed.']);

    }

    /** @test */
    public function deduction_or_marked_late_policy_will_accept_past_the_due_date_and_before_the_final_submission_deadline()
    {

        $this->assignment->late_policy = 'marked late';
        $this->assignment->save();

        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = "2020-12-10 09:00:00";
        $assignToTiming->final_submission_deadline = "2029-03-05 09:00:00";
        $assignToTiming->save();


        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => 'Auto-graded submission saved.']);

        $this->assignment->late_policy = 'delayed';
        $this->assignment->save();
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => 'Auto-graded submission saved.']);

    }

    /** @test */
    public function deduction_or_marked_late_policy_will_not_accept_past_the_due_date_and_after_the_final_submission_deadline()
    {

        $this->assignment->late_policy = 'marked late';
        $this->assignment->save();

        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = "2020-12-10 09:00:00";
        $assignToTiming->final_submission_deadline = "2020-12-11 09:00:00";
        $assignToTiming->save();


        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => 'No more late responses are being accepted.']);

        $this->assignment->late_policy = 'deduction';
        $this->assignment->save();
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['message' => 'No more late responses are being accepted.']);

    }


    /** @test */
    public function learning_tree_do_not_allow_submissions_if_solutions_released()
    {
        $this->assignment->assessment_type = 'learning tree';
        $this->assignment->show_scores = false;
        $this->assignment->solutions_released = true;
        $this->assignment->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'error',
                'message' => 'No responses will be saved since the solutions to this assignment have been released.']);

    }

    /** @test */
    public function delayed_do_not_allow_submissions_if_scores_are_shown_or_solutions_released()
    {

        $this->assignment->assessment_type = 'delayed';
        $this->assignment->show_scores = false;
        $this->assignment->solutions_released = true;
        $this->assignment->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'error',
                'message' => 'No responses will be saved since the solutions to this assignment have been released.']);

    }

    /** @test */
    public function can_submit_response()
    {

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);

    }


    /** @test */

    public function student_can_set_page_if_the_assignment_is_past_due_with_set_page_override()
    {
        CompiledPDFOverride::create(['assignment_id' => $this->assignment->id,
            'user_id' => $this->student_user->id,
            'set_page_only' => '1'
        ]);

        $this->createSubmissionFile();
        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = Carbon::yesterday();
        $assignToTiming->save();


        $this->assignment->save();
        $this->actingAs($this->student_user)->patchJson("/api/submission-files/{$this->assignment->id}/{$this->question->id}/page", ['page' => 1])
            ->assertJson(['type' => "success"]);
        DB::table('compiled_pdf_overrides')->delete();
        AssignmentLevelOverride::create(['assignment_id' => $this->assignment->id,
            'user_id' => $this->student_user->id]);
        $this->actingAs($this->student_user)->patchJson("/api/submission-files/{$this->assignment->id}/{$this->question->id}/page", ['page' => 1])
            ->assertJson(['type' => "success"]);

    }

    /** @test */

    public function student_cannot_set_page_if_the_assignment_is_past_due()
    {
        $this->createSubmissionFile();
        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = Carbon::yesterday();
        $assignToTiming->save();


        $this->assignment->save();
        $this->actingAs($this->student_user)->patchJson("/api/submission-files/{$this->assignment->id}/{$this->question->id}/page", ['page' => 1])
            ->assertJson(['message' => "No responses will be saved since the due date for this assignment has passed."]);

    }


    /** @test */
    public function with_a_late_assignment_policy_of_not_accepted_a_student_cannot_submit_response_if_assignment_past_due_and_no_extension()
    {

        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = "2001-03-05 09:00:00";//was due an hour ago.
        $assignToTiming->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'error', 'message' => 'No responses will be saved since the due date for this assignment has passed.']);

    }


    public function assignments_of_scoring_type_c_will_count_the_number_of_submissions_and_compare_to_the_number_of_questions()
    {
        $this->assignment->scoring_type = 'c';
        $this->assignment->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission);

        $score = DB::table('scores')->where('user_id', $this->student_user->id)
            ->where('assignment_id', $this->assignment->id)
            ->first();
        $this->assertEquals(null, $score, 'No assignment score saved in not completed assignment.');


        $this->actingAs($this->student_user)->postJson("/api/submissions", [
            'technology' => 'h5p',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question_2->id,
            'submission' => $this->h5pSubmission['submission']])
            ->assertJson(['type' => 'success']);

        $score = DB::table('scores')->where('user_id', $this->student_user->id)
            ->where('assignment_id', $this->assignment->id)
            ->get()
            ->pluck('score');
        $this->assertEquals('c', $score[0], 'Assignment marked as completed when all questions are answered.');

    }


    /** @test */
    public function can_get_assignment_title_if_owner_course()
    {
        $response['assignment']['name'] = $this->assignment->name;
        $this->actingAs($this->user)->getJson("/api/assignments/{$this->assignment->id}/view-questions-info")
            ->assertJson($response);
    }

    /** @test */
    public function can_get_assignment_title_if_student_in_course()
    {
        $response['assignment']['name'] = $this->assignment->name;
        $this->actingAs($this->student_user)->getJson("/api/assignments/{$this->assignment->id}/view-questions-info")
            ->assertJson($response);
    }

    /** @test */
    public function cannot_get_assignment_title_if_not_student_in_course()
    {
        $this->actingAs($this->student_user_2)->getJson("/api/assignments/{$this->assignment->id}/view-questions-info")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to access this assignment.']);
    }


    /** @test */

    public function student_view_scores_info_if_enrolled_in_the_course()
    {
        $score = 10;
        Score::create(['user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'score' => $score]);
        $response['assignment']['scores'] = [$score];
        $this->actingAs($this->user)->getJson("/api/assignments/{$this->assignment->id}/view-questions-info")
            ->assertJson($response);
    }

    /** @test */

    public function student_can_view_questions_info()
    {
        $this->actingAs($this->student_user)->getJson("/api/assignments/{$this->assignment->id}/view-questions-info")
            ->assertJson(['type' => 'success']);
    }


    /** @test */

    public function student_cannot_view_questions_info_if_not_enrolled_in_the_course()
    {
        $this->actingAs($this->student_user_2)->getJson("/api/assignments/{$this->assignment->id}/view-questions-info")
            ->assertJson(['type' => 'error', 'message' => 'You are not allowed to access this assignment.']);
    }


    /** @test */

    public function anonymous_student_can_view_questions_info_if_not_enrolled_in_the_course()
    {
        $this->course->anonymous_users = 1;
        $this->course->save();
        $this->student_user_2->email = 'anonymous';
        $this->student_user_2->save();

        //student_user_2 isn't enrolled but I made them anonymous
        $this->actingAs($this->student_user_2)->getJson("/api/assignments/{$this->assignment->id}/view-questions-info")
            ->assertJson(['type' => 'success']);
    }


    /** @test */

    public function owner_can_view_questions_info_if_owner_of_the_course()
    {
        $this->actingAs($this->user)->getJson("/api/assignments/{$this->assignment->id}/view-questions-info")
            ->assertJson(['type' => 'success']);
    }


    /** @test */

    public function student_cannot_get_scores_by_assignment_and_question()
    {
        $this->actingAs($this->student_user)->getJson("/api/scores/summary/{$this->assignment->id}/{$this->question->id}")
            ->assertJson(['type' => 'error', 'message' => "You can't get the scores for an assignment that is not in one of your courses."]);

    }

    /** @test */

    public function owner_can_get_scores_by_assignment_and_question()
    {
        factory(SubmissionFile::class)->create([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'type' => 'q',
            'original_filename' => 'some original name.pdf',
            'score' => 4]);

        $this->h5pSubmission = [
            'technology' => 'h5p',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => '{
    "actor":{
        "account":{
            "name":"5038b12a-1181-4546-8735-58aa9caef971","homePage":"https://h5p.libretexts.org"},"objectType":"Agent"},"verb":{
        "id":"http://adlnet.gov/expapi/verbs/answered","display":{
            "en-US":"answered"}},"object":{
        "id":"https://h5p.libretexts.org/wp-admin/admin-ajax.php?action=h5p_embed&id=97","objectType":"Activity","definition":{
            "extensions":{
                "http://h5p.org/x-api/h5p-local-content-id":97},"name":{
                "en-US":"1.3 Actividad # 5: comparativos y superlativos"},"interactionType":"fill-in","type":"http://adlnet.gov/expapi/activities/cmi.interaction","description":{
                "en-US":"<p><strong>Instrucciones: Ponga las palabras en orden. Empiece con el sujeto de la oración.</strong></p>\n<br/>1. de todas las universidades californianas / la / antigua / es / La Universidad del Pacífico / más <br/>__________ __________ __________ __________ __________ __________.<br/><br/>2. el / UC Merced / número de estudiantes / tiene / menor<br/>__________ __________ __________ __________ __________."},"correctResponsesPattern":["La Universidad del Pacífico[,]es[,]la[,]más[,]antigua[,]de todas las universidades californianas[,]UC Merced[,]tiene[,]el[,]menor[,]número de estudiantes"]}},"context":{
        "contextActivities":{
            "category":[{
                "id":"http://h5p.org/libraries/H5P.DragText-1.8","objectType":"Activity"}]}},"result":{
        "response":"[,][,][,][,][,][,][,]antigua[,][,][,]","score":{
            "min":0,"raw":11,"max":11,"scaled":0},"duration":"PT3.66S","completion":true}}',
        ];//gives them 10 points for the question since they got it correct

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission);

        $response['scores'] = ["14.00"];
        $this->actingAs($this->user)->getJson("/api/scores/summary/{$this->assignment->id}/{$this->question->id}")
            ->assertJson($response);

    }

    /** @test */
    public function if_there_are_no_scores_it_returns_an_empty_array()
    {

        $this->actingAs($this->user)->getJson("/api/scores/summary/{$this->assignment->id}/{$this->question->id}")
            ->assertJson([]);

    }


    /** @test */

    public function students_can_email_users_if_the_user_graded_their_question()
    {


    }

    /** @test */

    public function anyone_can_contact_us()
    {


    }

    /** @test */
    public function can_get_assignment_questions_if_student_in_course()
    {

        //needed because the token wasn't being passed through
        //https://laracasts.com/discuss/channels/testing/laravel-testcase-not-sending-authorization-headers

        $this->actingAs($this->student_user)->getJson("/api/assignments/{$this->assignment->id}/questions/view", $this->headers())
            ->assertJson(['type' => 'success']);

    }

    public function createSubmissionFile()
    {
        //set up this way since I wouldn't have been able to remove questions below if there was already a submission
        factory(SubmissionFile::class)->create([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'type' => 'a',
            'original_filename' => 'some original name.pdf']);

    }

    /** @test */

    public function student_cannot_create_pages_for_a_question_not_in_their_assignment()
    {
        factory(Question::class)->create(['id' => 10000000, 'page_id' => 100000000]);
        $this->actingAs($this->student_user)->patchJson("/api/submission-files/{$this->assignment->id}/10000000/page", ['page' => 1])
            ->assertJson(['message' => "No responses will be saved since that question is not in the assignment."]);


    }

    /** @test */

    public function expect_a_valid_page_number()
    {
        $this->createSubmissionFile();
        $this->actingAs($this->student_user)->patchJson("/api/submission-files/{$this->assignment->id}/{$this->question->id}/page",
            ['page' => -1])
            ->assertJsonValidationErrors('page');
        $this->actingAs($this->student_user)->patchJson("/api/submission-files/{$this->assignment->id}/{$this->question->id}/page",
            ['page' => "not a number"])
            ->assertJsonValidationErrors('page');

    }

    /** @test */

    public function a_student_can_create_cutups_from_a_comma_separated_list()
    {

    }


    /** @test */

    public function student_can_create_cutups_if_the_assignment_is_past_due_but_the_extension_has_not_past()
    {

        //Need to mock out the uploaded file

        //$this->createSubmissionFile();
        // $this->assignment->due = Carbon::yesterday();
        // $this->assignment->save();
        // factory(Extension::class)->create(['user_id' => $this->student_user->id, 'assignment_id' => $this->assignment->id]);
        // $this->actingAs($this->student_user)->postJson("/api/cutups/{$this->assignment->id}/{$this->question->id}/set-as-solution-or-submission")
        //     ->assertJson(['message' => "Your cutup has been saved as your file submission for this question."]);

    }

    /** @test */

    public function instructor_cannot_create_cutups_if_they_are_not_the_owner_of_the_course()
    {
        $this->actingAs($this->user_2)->patchJson("/api/cutups/{$this->assignment->id}/{$this->question->id}/solution")
            ->assertJson(['message' => "You are not allowed to create a cutup for this assignment."]);


    }

    /** @test */

    public function instructor_can_create_cutups_if_they_are_the_owner()
    {
        //need to mock out the file
        // $this->actingAs($this->user)->postJson("/api/cutups/{$this->assignment->id}/{$this->question->id}/set-as-solution-or-submission")
        //    ->assertJson(['message' => "Your cutup has been set as the solution."]);
    }

    /** @test */

    public function one_cannot_add_a_question_to_an_assignment_if_a_student_has_submitted_a_response()
    {


    }


    /** @test */

    public function a_student_cannot_download_a_solution_to_a_question_if_the_solutions_are_not_released()
    {
        $this->actingAs($this->student_user)->postJson('/api/solution-files/download', [
            'level' => 'q',
            'question_id' => $this->question->id,
            'assignment_id' => $this->assignment->id])
            ->assertJson(['message' => "The solutions are not released so you can't download the solution."]);


    }


    /** @test */

    public function a_student_cannot_download_a_solution_to_a_question_in_an_assignment_that_is_not_in_an_enrolled_course()
    {
        $this->assignment->solutions_released = 1;
        $this->assignment->save();
        $this->actingAs($this->student_user_2)->postJson('/api/solution-files/download', [
            'level' => 'q',
            'question_id' => $this->question->id,
            'assignment_id' => $this->assignment->id])
            ->assertJson(['message' => 'You are not allowed to download these solutions.']);


    }

    /** @test */

    public function a_student_can_download_a_solution_uploaded_by_their_instructor()
    {


    }


    /** @test */

    public function a_non_instructor_cannot_upload_a_solution()
    {
        $this->actingAs($this->student_user)->putJson("/api/solution-files", [
            'question_id' => 1])
            ->assertJson(['message' => 'You are not allowed to upload solutions.']);


    }

    /** @test */

    public function an_instructor_can_upload_a_solution()
    {


    }

    /** @test */

    public function you_cannot_download_a_solution_that_is_not_part_of_an_assignment()
    {
        $this->actingAs($this->user)->postJson('/api/solution-files/download', [
            'level' => 'q',
            'question_id' => 1000,
            'assignment_id' => $this->assignment->id])
            ->assertJson(['message' => 'That question is not part of the assignment so you cannot download the solutions.']);

    }


    /** @test */
    public function must_submit_a_question_with_a_valid_assignment_number()
    {

        $this->h5pSubmission['assignment_id'] = false;
        $this->assignment->save();
        $this->actingAs($this->student_user)->postJson("/api/submissions",
            $this->h5pSubmission)->assertStatus(422);

    }

    /** @test */
    public function must_submit_a_question_with_a_valid_question_number()
    {


        $this->h5pSubmission['question_id'] = false;
        $this->actingAs($this->student_user)->postJson("/api/submissions",
            $this->h5pSubmission)->assertStatus(422);

    }


    /** @test */

    public function assignments_of_scoring_type_p_and_no_question_files_will_compute_the_score_based_on_the_question_points()
    {

        $this->actingAs($this->student_user)->postJson("/api/submissions",
            $this->h5pSubmission);


        $score = DB::table('scores')->where('user_id', $this->student_user->id)
            ->where('assignment_id', $this->assignment->id)
            ->get()
            ->pluck('score');
        $points_1 = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->get()
            ->pluck('points');


        $this->assertEquals(number_format($points_1[0], 2), number_format($score[0], 2), 'Score saved when student submits.');

        //do it again and it should update

        $this->actingAs($this->student_user)->postJson("/api/submissions", [
                'technology' => 'h5p',
                'assignment_id' => $this->assignment->id,
                'question_id' => $this->question_2->id,
                'submission' => $this->h5pSubmission['submission']]
        );

        $points_2 = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question_2->id)
            ->get()
            ->pluck('points');

        $score = DB::table('scores')->where('user_id', $this->student_user->id)
            ->where('assignment_id', $this->assignment->id)
            ->get()
            ->pluck('score');

        $this->assertEquals(number_format($points_1[0] + $points_2[0], 2), number_format($score[0], 2), 'Score saved when student submits.');


    }

    /**@test* */

    public function score_is_computed_correctly_for_h5p()
    {

    }

    /** @test */

    public function score_is_computed_correctly_for_imathas()
    {

    }

    /**@test* */

    public function score_is_computed_correctly_for_webwork()
    {

    }

    /**@test* */

    public function the_associated_technology_is_valid()
    {


    }

    /**@test* */

    public function the_assignment_id_is_an_integer()
    {


    }

    /**@test* */

    public function the_question_id_is_an_integer()
    {


    }

    /**@test* */
    public function can_not_update_question_points_if_students_have_already_made_a_submission()
    {

//not sure if this is even a real thing: I have an update in the controller but nothing in the questions.get.vue?
    }


    /**@test* */

    public function the_submission_is_a_string()
    {


    }

    /** @test */


    /** @test */

    public function cannot_store_a_question_file_if_it_has_the_wrong_type()
    {
//testing for question/assignment

    }

    /** @test */

    public function cannot_store_a_question_file()
    {


    }

    /** @test */

    public function can_toggle_question_files_if_you_are_the_owner()
    {


    }

    /** @test */

    public function cannot_toggle_question_files_if_you_are_not_the_owner()
    {


    }

    /** @test */

    public function will_mark_assignment_as_completed_if_number_of_questions_is_equal_to_number_of_questions()
    {


    }


    /** @test */
    public function can_update_response()
    {

        ///to do ---- change the second one to see if the database actually updated!
        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission);


        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'success']);

    }

    /** @test */
    public function cannot_submit_response_if_question_not_in_assignment()
    {
        $this->actingAs($this->student_user)->postJson("/api/submissions", [
            'assignment_id' => $this->assignment->id,
            'question_id' => 0,
            'submission' => 'some submission'])
            ->assertJson(['type' => 'error',
                'message' => 'That question is not part of the assignment.']);
    }

    /** @test */
    public function cannot_submit_response_if_user_not_assigned_the_assignment()
    {
        $this->actingAs($this->student_user_2)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'error',
                'message' => 'No responses will be saved since you were not assigned to this assignment.']);

    }


    /** @test */
    public function cannot_submit_response_if_assignment_not_yet_available()
    {


        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->available_from = "2035-03-05 09:00:00";
        $assignToTiming->save();

        $this->actingAs($this->student_user)->postJson("/api/submissions", $this->h5pSubmission)
            ->assertJson(['type' => 'error',
                'message' => 'No responses will be saved since this assignment is not yet available.']);

    }


    /** @test */
    public function cannot_get_assignment_questions_if_not_student_in_course()
    {
        $this->actingAs($this->student_user_2)->getJson("/api/assignments/{$this->assignment->id}/questions/view")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to access this assignment.']);

    }

    /** @test */
    public function can_remove_question_from_assignment_if_owner()
    {
        $this->actingAs($this->user)->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}")
            ->assertJson(['type' => 'info']);

    }

    /** @test */
    public function cannot_remove_question_from_assignment_if_not_owner()
    {
        $this->actingAs($this->user_2)->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to remove a question from this assignment.']);
    }

    /** @test */
    public function can_view_page_if_grader_in_course()
    {


    }

    /** @test */

    public function non_owner_cannot_add_default_open_ended_text()
    {
        $this->actingAs($this->user_2)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/open-ended-default-text", [
        ])->assertJson(['message' => 'You are not allowed to add default text to this assignment.']);

    }

    /** @test */

    public function owner_can_add_default_open_ended_text()
    {
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/open-ended-default-text", [
            'default_open_ended_text' => 'Some default text'])->assertJson(['message' => 'The default text has been updated.']);

    }

    /** @test */

    public function non_owner_cannot_delete_a_submission()
    {

        $this->actingAs($this->student_user_3)->deleteJson("/api/submission-texts/{$this->assignment->id}/{$this->question->id}")
            ->assertJson(['message' => 'You are not allowed to reset this text submission. No responses will be saved since you were not assigned to this assignment.']);

    }

    /** @test */

    public function owner_can_delete_a_submission()
    {
        SubmissionFile::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'type' => 'text',
            'original_filename' => '',
            'submission' => 'some text',
            'date_submitted' => Carbon::now()]);

        SubmissionFile::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question_2->id,
            'user_id' => $this->student_user->id,
            'type' => 'text',
            'original_filename' => '',
            'submission' => 'some more text',
            'date_submitted' => Carbon::now()]);
        $submission_files = SubmissionFile::where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->get();

        $this->assertEquals(2, count($submission_files));
        $this->actingAs($this->student_user)->deleteJson("/api/submission-texts/{$this->assignment->id}/{$this->question->id}")
            ->assertJson(['message' => 'Your submission was removed.']);
        $submission_files = SubmissionFile::where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->get();
        $this->assertEquals(1, count($submission_files));
    }

}
