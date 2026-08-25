<?php

namespace Tests\Feature;

use App\JWE;
use App\Question;
use App\User;
use App\Webwork;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use function factory;

class WebworkTest extends TestCase
{
    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->question = factory(Question::class)->create(['page_id' =>23482671]);
    }


    /** @test */
    public function student_cannot_get_pre_signed_url_for_webwork_attachment()
    {
        $this->user->role = 3;
        $this->user->save();

        $this->actingAs($this->user)->postJson("/api/s3/pre-signed-url", [
            'upload_file_type' => 'webwork-attachment',
            'file_name' => 'graph.png'
        ])
            ->assertJson(['message' => 'You are not allowed to upload webwork attachments.']);
    }
    /** @test */
    public function student_cannot_get_the_webwork_templates()
    {
        $this->user->role = 3;
        $this->user->save();
        $this->actingAs($this->user)
            ->getJson("/api/webwork/templates")
            ->assertJson(['message' => 'You are not allowed to get the weBWork templates.']);
    }


    /** @test */
    public function non_instructor_cannot_get_webwork_code_from_filepath()
    {

        $this->user->role = 3;
        $this->user->save();
        $this->actingAs($this->user)->postJson("/api/questions/get-webwork-code-from-file-path", ['file_path' => 'some path'])
            ->assertJson(['message' => 'You are not allowed to get the weBWork code.']);

    }

    /** @test */
    public function question_must_be_webwork_question()
    {
        $this->question->technology = 'not webwork';
        $this->question->save();
        $this->actingAs($this->user)->getJson("/api/questions/export-webwork-code/{$this->question->id}")
            ->assertJson(['error' => 'This is not a weBWork question.']);

    }

    /** @test */
    public function non_instructor_cannot_export_webwork_code()
    {
        $this->user->role = 3;
        $this->user->save();
        $this->actingAs($this->user)->getJson("/api/questions/export-webwork-code/{$this->question->id}")
            ->assertJson(['message' => 'You are not allowed to export the weBWork code.']);

    }

    private function createWebworkProblemJWT(): string
    {
        $jwe = new JWE();
        $secret = $jwe->getSecret('webwork');
        \JWTAuth::getJWTProvider()->setSecret($secret);

        $token = \JWTAuth::claims([
            'adapt' => [
                'assignment_id' => 1,
                'question_id' => $this->question->id,
                'technology' => 'webwork',
            ],
            'webwork' => [],
            'imathas' => [],
            'h5p' => [],
        ])->fromUser($this->user);

        $problemJWT = $jwe->encrypt($token, 'webwork');
        \JWTAuth::getJWTProvider()->setSecret(config('myconfig.jwt_secret'));

        return $problemJWT;
    }

    /** @test */
    public function get_hint_returns_render_api_response()
    {
        Http::fake([
            '*/render-api/hint*' => Http::response([
                'status' => 200,
                'message' => '<div>Some hint</div>',
            ], 200),
        ]);

        $webwork = new Webwork();
        $problemJWT = $this->createWebworkProblemJWT();

        $response = $webwork->getHint($problemJWT);

        $this->assertEquals('success', $response['type']);
        $this->assertEquals('<div>Some hint</div>', $response['message']);
    }

    /** @test */
    public function get_hint_returns_error_response_when_http_call_fails()
    {
        Http::fake([
            '*/render-api/hint*' => Http::response('server error', 500),
        ]);

        $webwork = new Webwork();
        $problemJWT = $this->createWebworkProblemJWT();

        $response = $webwork->getHint($problemJWT);

        $this->assertEquals('error', $response['type']);
    }

    /** @test */
    public function student_cannot_access_webwork_hint_endpoint_directly()
    {
        $this->user->role = 3;
        $this->user->fake_student = 0;
        $this->user->save();

        Http::fake([
            '*/render-api/hint*' => Http::response(['status' => 200, 'message' => '<div>Some hint</div>'], 200),
        ]);

        $problemJWT = $this->createWebworkProblemJWT();

        $this->actingAs($this->user)
            ->getJson("/api/webwork/hint/{$problemJWT}")
            ->assertJson(['message' => 'You are not allowed to access this hint directly.']);
    }

    /** @test */
    public function instructor_can_access_webwork_hint_endpoint_directly()
    {
        $this->user->role = 2;
        $this->user->save();

        Http::fake([
            '*/render-api/hint*' => Http::response(['status' => 200, 'message' => '<div>Some hint</div>'], 200),
        ]);

        $problemJWT = $this->createWebworkProblemJWT();

        $this->actingAs($this->user)
            ->getJson("/api/webwork/hint/{$problemJWT}")
            ->assertJson(['type' => 'success', 'message' => '<div>Some hint</div>']);
    }

    /** @test */
    public function fake_student_can_access_webwork_hint_endpoint_directly()
    {
        $this->user->role = 3;
        $this->user->fake_student = 1;
        $this->user->save();

        Http::fake([
            '*/render-api/hint*' => Http::response(['status' => 200, 'message' => '<div>Some hint</div>'], 200),
        ]);

        $problemJWT = $this->createWebworkProblemJWT();

        $this->actingAs($this->user)
            ->getJson("/api/webwork/hint/{$problemJWT}")
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function has_hint_returns_true_when_code_contains_begin_pgml_hint()
    {
        $webwork = new Webwork();
        $this->question->webwork_code = "DOCUMENT();\nBEGIN_PGML_HINT\nUse dimensional analysis.\nEND_PGML_HINT\n";

        $this->assertTrue($webwork->hasHint($this->question));
    }

    /** @test */
    public function has_hint_returns_false_when_code_does_not_contain_begin_pgml_hint()
    {
        $webwork = new Webwork();
        $this->question->webwork_code = "DOCUMENT();\nBEGIN_PGML\nWhat is 2 + 2?\nEND_PGML\n";

        $this->assertFalse($webwork->hasHint($this->question));
    }

    /** @test */
    public function has_hint_returns_false_when_webwork_code_is_empty()
    {
        $webwork = new Webwork();
        $this->question->webwork_code = '';

        $this->assertFalse($webwork->hasHint($this->question));
    }

}
