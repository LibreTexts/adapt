<?php

namespace Tests\Feature\Accounting;


use App\Question;
use App\SavedQuestionsFolder;
use App\Submission;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AccountingJournalEntryTest extends TestCase
{
    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create(['role' => 3]);
        $this->saved_questions_folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id, 'type' => 'my_questions']);

        $this->qti_question_info = [
            "question_type" => "assessment",
            "folder_id" => $this->saved_questions_folder->id,
            "public" => "0",
            "title" => "Journal Entry Test Question",
            "author" => "Instructor Kean",
            "tags" => [],
            "technology" => "qti",
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
            'open_ended_submission_type' => '0',
            "qti_json" => json_encode([
                "questionType" => "accounting_journal_entry",
                "entries" => [
                    [
                        "identifier" => "entry-1",
                        "entryText" => "Jan 1",
                        "entryDescription" => "Purchased equipment for cash.",
                        "solutionRows" => [
                            [
                                "identifier" => "row-1-1",
                                "accountTitle" => "Equipment",
                                "type" => "debit",
                                "amount" => "5000"
                            ],
                            [
                                "identifier" => "row-1-2",
                                "accountTitle" => "Cash",
                                "type" => "credit",
                                "amount" => "5000"
                            ]
                        ]
                    ],
                    [
                        "identifier" => "entry-2",
                        "entryText" => "Jan 15",
                        "entryDescription" => "Received payment from customer.",
                        "solutionRows" => [
                            [
                                "identifier" => "row-2-1",
                                "accountTitle" => "Cash",
                                "type" => "debit",
                                "amount" => "1000"
                            ],
                            [
                                "identifier" => "row-2-2",
                                "accountTitle" => "Accounts Receivable",
                                "type" => "credit",
                                "amount" => "1000"
                            ]
                        ]
                    ]
                ]
            ])
        ];
    }

    /** @test */
    public function can_create_accounting_journal_entry_question()
    {
        $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info)
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('questions', [
            'title' => 'Journal Entry Test Question'
        ]);
    }

    /** @test */
    public function entries_must_exist()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'] = [];
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errors = $response->json('errors.qti_json');
        $this->assertNotNull($errors);
        $errorData = json_decode($errors[0], true);
        $this->assertEquals('At least one journal entry is required.', $errorData['general']);
    }

    /** @test */
    public function entry_text_is_required()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'][0]['entryText'] = '';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errors = $response->json('errors.qti_json');
        $this->assertNotNull($errors);
        $errorData = json_decode($errors[0], true);
        $this->assertEquals('Entry text is required.', $errorData['specific'][0]['entryText']);
    }

    /** @test */
    public function entry_description_is_required()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'][0]['entryDescription'] = '';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errors = $response->json('errors.qti_json');
        $this->assertNotNull($errors);
        $errorData = json_decode($errors[0], true);
        $this->assertEquals('Entry description is required.', $errorData['specific'][0]['entryDescription']);
    }

    /** @test */
    public function solution_rows_must_have_at_least_two_rows()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'][0]['solutionRows'] = [
            [
                "identifier" => "row-1-1",
                "accountTitle" => "Equipment",
                "type" => "debit",
                "amount" => "5000"
            ]
        ];
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errors = $response->json('errors.qti_json');
        $this->assertNotNull($errors);
        $errorData = json_decode($errors[0], true);
        $this->assertEquals('At least 2 solution rows are required.', $errorData['specific'][0]['solutionRows']['general']);
    }

    /** @test */
    public function solution_rows_cannot_exceed_five_rows()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'][0]['solutionRows'] = [
            ["identifier" => "row-1", "accountTitle" => "Cash", "type" => "debit", "amount" => "100"],
            ["identifier" => "row-2", "accountTitle" => "Equipment", "type" => "credit", "amount" => "100"],
            ["identifier" => "row-3", "accountTitle" => "Supplies", "type" => "debit", "amount" => "100"],
            ["identifier" => "row-4", "accountTitle" => "Land", "type" => "credit", "amount" => "100"],
            ["identifier" => "row-5", "accountTitle" => "Buildings", "type" => "debit", "amount" => "100"],
            ["identifier" => "row-6", "accountTitle" => "Inventory", "type" => "credit", "amount" => "100"]
        ];
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errors = $response->json('errors.qti_json');
        $this->assertNotNull($errors);
        $errorData = json_decode($errors[0], true);
        $this->assertEquals('Maximum of 5 solution rows allowed.', $errorData['specific'][0]['solutionRows']['general']);
    }

    /** @test */
    public function account_title_is_required()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'][0]['solutionRows'][0]['accountTitle'] = '';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errors = $response->json('errors.qti_json');
        $this->assertNotNull($errors);
        $errorData = json_decode($errors[0], true);
        $this->assertEquals('Account title is required.', $errorData['specific'][0]['solutionRows'][0]['accountTitle']);
    }

    /** @test */
    public function account_title_must_be_from_valid_list()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'][0]['solutionRows'][0]['accountTitle'] = 'Invalid Account Name';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errors = $response->json('errors.qti_json');
        $this->assertNotNull($errors);
        $errorData = json_decode($errors[0], true);
        $this->assertEquals('Account title must be from the valid list of accounts.', $errorData['specific'][0]['solutionRows'][0]['accountTitle']);
    }

    /** @test */
    public function type_must_be_debit_or_credit()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'][0]['solutionRows'][0]['type'] = 'invalid';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errors = $response->json('errors.qti_json');
        $this->assertNotNull($errors);
        $errorData = json_decode($errors[0], true);
        $this->assertEquals('Type must be either debit or credit.', $errorData['specific'][0]['solutionRows'][0]['type']);
    }

    /** @test */
    public function amount_is_required()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'][0]['solutionRows'][0]['amount'] = '';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errors = $response->json('errors.qti_json');
        $this->assertNotNull($errors);
        $errorData = json_decode($errors[0], true);
        $this->assertEquals('Amount is required.', $errorData['specific'][0]['solutionRows'][0]['amount']);
    }

    /** @test */
    public function amount_of_zero_is_allowed()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'][0]['solutionRows'][0]['amount'] = '0';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertJson(['type' => 'success']);
    }

    /** @test */
    public function negative_amount_is_rejected()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'][0]['solutionRows'][0]['amount'] = '-5';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errors = $response->json('errors.qti_json');
        $this->assertNotNull($errors);
        $errorData = json_decode($errors[0], true);
        $this->assertEquals('Amount cannot be negative.', $errorData['specific'][0]['solutionRows'][0]['amount']);
    }

    /** @test */
    public function entry_must_balance()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['entries'][0]['solutionRows'][0]['amount'] = '5000';
        $qti_json['entries'][0]['solutionRows'][1]['amount'] = '3000'; // Doesn't balance
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errors = $response->json('errors.qti_json');
        $this->assertNotNull($errors);
        $errorData = json_decode($errors[0], true);
        $this->assertEquals('Entry does not balance. Debits: $5000.00, Credits: $3000.00', $errorData['specific'][0]['solutionRows']['general']);
    }

    /** @test */
    public function scores_all_correct_submission_as_100_percent()
    {
        // Create question
        $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $question = Question::first();

        // Submit correct answer
        $studentSubmission = [
            [
                'selectedEntryIndex' => 0,
                'rows' => [
                    ['accountTitle' => 'Equipment', 'debit' => '5000', 'credit' => ''],
                    ['accountTitle' => 'Cash', 'debit' => '', 'credit' => '5000']
                ]
            ],
            [
                'selectedEntryIndex' => 1,
                'rows' => [
                    ['accountTitle' => 'Cash', 'debit' => '1000', 'credit' => ''],
                    ['accountTitle' => 'Accounts Receivable', 'debit' => '', 'credit' => '1000']
                ]
            ]
        ];

        $submission = new Submission();
        $solution = json_decode($this->qti_question_info['qti_json'], true)['entries'];

        // Convert solution to objects (as it would come from database)
        $solutionObjects = json_decode(json_encode($solution));

        $result = $submission->computeScoreForAccountingJournalEntry($solutionObjects, $studentSubmission);

        $this->assertEquals(1.0, $result['proportionCorrect']);
        $this->assertTrue($result['allCorrect']);
    }

    /** @test */
    public function scores_partially_correct_submission_proportionally()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Equipment', 'type' => 'debit', 'amount' => '5000'],
                    (object)['accountTitle' => 'Cash', 'type' => 'credit', 'amount' => '5000']
                ]
            ]
        ];

        // Student gets account titles right but amounts wrong
        $studentSubmission = [
            [
                'selectedEntryIndex' => 0,
                'rows' => [
                    ['accountTitle' => 'Equipment', 'debit' => '3000', 'credit' => ''], // Wrong amount
                    ['accountTitle' => 'Cash', 'debit' => '', 'credit' => '5000'] // Correct
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        // Should have some correct, some incorrect
        $this->assertGreaterThan(0, $result['proportionCorrect']);
        $this->assertLessThan(1, $result['proportionCorrect']);
        $this->assertFalse($result['allCorrect']);
    }

    /** @test */
    public function scores_all_incorrect_submission_as_zero()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Equipment', 'type' => 'debit', 'amount' => '5000'],
                    (object)['accountTitle' => 'Cash', 'type' => 'credit', 'amount' => '5000']
                ]
            ]
        ];

        // Student gets everything wrong
        $studentSubmission = [
            [
                'selectedEntryIndex' => null, // Wrong
                'rows' => [
                    ['accountTitle' => 'Wrong Account', 'debit' => '', 'credit' => '999'], // All wrong
                    ['accountTitle' => 'Another Wrong', 'debit' => '111', 'credit' => ''] // All wrong
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        $this->assertEquals(0, $result['proportionCorrect']);
        $this->assertFalse($result['allCorrect']);
    }

    /** @test */
    public function empty_debit_is_correct_when_solution_is_credit()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Cash', 'type' => 'credit', 'amount' => '1000']
                ]
            ]
        ];

        $studentSubmission = [
            [
                'selectedEntryIndex' => 0,
                'rows' => [
                    ['accountTitle' => 'Cash', 'debit' => '', 'credit' => '1000']
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        // Check that the debit field (which should be empty) is marked correct
        $this->assertTrue($result['results'][0]['rows'][0]['debitCorrect']);
        $this->assertTrue($result['results'][0]['rows'][0]['creditCorrect']);
    }

    /** @test */
    public function empty_credit_is_correct_when_solution_is_debit()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Equipment', 'type' => 'debit', 'amount' => '5000']
                ]
            ]
        ];

        $studentSubmission = [
            [
                'selectedEntryIndex' => 0,
                'rows' => [
                    ['accountTitle' => 'Equipment', 'debit' => '5000', 'credit' => '']
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        // Check that the credit field (which should be empty) is marked correct
        $this->assertTrue($result['results'][0]['rows'][0]['debitCorrect']);
        $this->assertTrue($result['results'][0]['rows'][0]['creditCorrect']);
    }

    /** @test */
    public function wrong_entry_selection_is_marked_incorrect()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Cash', 'type' => 'debit', 'amount' => '1000'],
                    (object)['accountTitle' => 'Sales Revenue', 'type' => 'credit', 'amount' => '1000']
                ]
            ]
        ];

        $studentSubmission = [
            [
                'selectedEntryIndex' => 1, // Wrong - should be 0
                'rows' => [
                    ['accountTitle' => 'Cash', 'debit' => '1000', 'credit' => ''],
                    ['accountTitle' => 'Sales Revenue', 'debit' => '', 'credit' => '1000']
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        $this->assertFalse($result['results'][0]['selectedEntryCorrect']);
    }

    /** @test */
    public function handles_decimal_amounts_with_tolerance()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Cash', 'type' => 'debit', 'amount' => '100.50'],
                    (object)['accountTitle' => 'Sales Revenue', 'type' => 'credit', 'amount' => '100.50']
                ]
            ]
        ];

        // Student enters slightly different due to floating point
        $studentSubmission = [
            [
                'selectedEntryIndex' => 0,
                'rows' => [
                    ['accountTitle' => 'Cash', 'debit' => '100.50', 'credit' => ''],
                    ['accountTitle' => 'Sales Revenue', 'debit' => '', 'credit' => '100.50']
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        $this->assertTrue($result['results'][0]['rows'][0]['debitCorrect']);
        $this->assertTrue($result['results'][0]['rows'][1]['creditCorrect']);
    }

    /** @test */
    public function empty_string_is_not_equal_to_zero()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Cash', 'type' => 'debit', 'amount' => '1000']
                ]
            ]
        ];

        // Student enters 0 in credit instead of leaving it empty
        $studentSubmission = [
            [
                'selectedEntryIndex' => 0,
                'rows' => [
                    ['accountTitle' => 'Cash', 'debit' => '1000', 'credit' => '0']
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        // Credit should be empty, not 0, so this should be incorrect
        $this->assertFalse($result['results'][0]['rows'][0]['creditCorrect']);
    }

    /** @test */
    public function grading_results_contain_all_required_fields()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Cash', 'type' => 'debit', 'amount' => '1000'],
                    (object)['accountTitle' => 'Sales Revenue', 'type' => 'credit', 'amount' => '1000']
                ]
            ]
        ];

        $studentSubmission = [
            [
                'selectedEntryIndex' => 0,
                'rows' => [
                    ['accountTitle' => 'Cash', 'debit' => '1000', 'credit' => ''],
                    ['accountTitle' => 'Sales Revenue', 'debit' => '', 'credit' => '1000']
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        // Check top-level keys
        $this->assertArrayHasKey('proportionCorrect', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('allCorrect', $result);

        // Check entry-level keys
        $this->assertArrayHasKey('selectedEntryIndex', $result['results'][0]);
        $this->assertArrayHasKey('selectedEntryCorrect', $result['results'][0]);
        $this->assertArrayHasKey('rows', $result['results'][0]);
        $this->assertArrayHasKey('isCorrect', $result['results'][0]);

        // Check row-level keys
        $this->assertArrayHasKey('accountTitle', $result['results'][0]['rows'][0]);
        $this->assertArrayHasKey('debit', $result['results'][0]['rows'][0]);
        $this->assertArrayHasKey('credit', $result['results'][0]['rows'][0]);
        $this->assertArrayHasKey('accountTitleCorrect', $result['results'][0]['rows'][0]);
        $this->assertArrayHasKey('debitCorrect', $result['results'][0]['rows'][0]);
        $this->assertArrayHasKey('creditCorrect', $result['results'][0]['rows'][0]);
        $this->assertArrayHasKey('isCorrect', $result['results'][0]['rows'][0]);
    }

    /** @test */
    public function marks_rows_incorrect_when_credit_appears_before_debit_followed_by_debit()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Equipment', 'type' => 'debit', 'amount' => '5000'],
                    (object)['accountTitle' => 'Cash', 'type' => 'credit', 'amount' => '2500'],
                    (object)['accountTitle' => 'Notes Payable', 'type' => 'credit', 'amount' => '2500']
                ]
            ]
        ];

        // Student enters: Debit, Credit, Debit (wrong order - credit followed by debit)
        $studentSubmission = [
            [
                'selectedEntryIndex' => 0,
                'rows' => [
                    ['accountTitle' => 'Equipment', 'debit' => '5000', 'credit' => ''],    // Row 0: Debit - OK
                    ['accountTitle' => 'Cash', 'debit' => '', 'credit' => '2500'],          // Row 1: Credit - VIOLATION STARTS (debit follows)
                    ['accountTitle' => 'Notes Payable', 'debit' => '2500', 'credit' => '']  // Row 2: Debit after credit - forced incorrect
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        // Row 0 should be graded normally (correct)
        $this->assertTrue($result['results'][0]['rows'][0]['accountTitleCorrect']);
        $this->assertTrue($result['results'][0]['rows'][0]['debitCorrect']);
        $this->assertTrue($result['results'][0]['rows'][0]['creditCorrect']);
        $this->assertTrue($result['results'][0]['rows'][0]['isCorrect']);

        // Row 1 should be forced incorrect (credit before a debit)
        $this->assertFalse($result['results'][0]['rows'][1]['accountTitleCorrect']);
        $this->assertFalse($result['results'][0]['rows'][1]['debitCorrect']);
        $this->assertFalse($result['results'][0]['rows'][1]['creditCorrect']);
        $this->assertFalse($result['results'][0]['rows'][1]['isCorrect']);

        // Row 2 should be forced incorrect (comes after violation)
        $this->assertFalse($result['results'][0]['rows'][2]['accountTitleCorrect']);
        $this->assertFalse($result['results'][0]['rows'][2]['debitCorrect']);
        $this->assertFalse($result['results'][0]['rows'][2]['creditCorrect']);
        $this->assertFalse($result['results'][0]['rows'][2]['isCorrect']);

        $this->assertFalse($result['allCorrect']);
    }


    /** @test */
    public function allows_correct_order_debits_then_credits()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Equipment', 'type' => 'debit', 'amount' => '3000'],
                    (object)['accountTitle' => 'Inventory', 'type' => 'debit', 'amount' => '2000'],
                    (object)['accountTitle' => 'Cash', 'type' => 'credit', 'amount' => '5000']
                ]
            ]
        ];

        // Student enters correctly: Debit, Debit, Credit
        $studentSubmission = [
            [
                'selectedEntryIndex' => 0,
                'rows' => [
                    ['accountTitle' => 'Equipment', 'debit' => '3000', 'credit' => ''],
                    ['accountTitle' => 'Inventory', 'debit' => '2000', 'credit' => ''],
                    ['accountTitle' => 'Cash', 'debit' => '', 'credit' => '5000']
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        // All rows should be correct
        $this->assertTrue($result['results'][0]['rows'][0]['isCorrect']);
        $this->assertTrue($result['results'][0]['rows'][1]['isCorrect']);
        $this->assertTrue($result['results'][0]['rows'][2]['isCorrect']);

        $this->assertTrue($result['allCorrect']);
        $this->assertEquals(1.0, $result['proportionCorrect']);
    }

    /** @test */
    public function allows_multiple_credits_after_debits()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Equipment', 'type' => 'debit', 'amount' => '5000'],
                    (object)['accountTitle' => 'Cash', 'type' => 'credit', 'amount' => '2500'],
                    (object)['accountTitle' => 'Notes Payable', 'type' => 'credit', 'amount' => '2500']
                ]
            ]
        ];

        // Student enters correctly: Debit, Credit, Credit
        $studentSubmission = [
            [
                'selectedEntryIndex' => 0,
                'rows' => [
                    ['accountTitle' => 'Equipment', 'debit' => '5000', 'credit' => ''],
                    ['accountTitle' => 'Cash', 'debit' => '', 'credit' => '2500'],
                    ['accountTitle' => 'Notes Payable', 'debit' => '', 'credit' => '2500']
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        // All rows should be correct - multiple credits after debit is fine
        $this->assertTrue($result['results'][0]['rows'][0]['isCorrect']);
        $this->assertTrue($result['results'][0]['rows'][1]['isCorrect']);
        $this->assertTrue($result['results'][0]['rows'][2]['isCorrect']);

        $this->assertTrue($result['allCorrect']);
    }

    /** @test */
    public function order_violation_in_one_entry_does_not_affect_other_entries()
    {
        $submission = new Submission();

        $solution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'First entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Equipment', 'type' => 'debit', 'amount' => '5000'],
                    (object)['accountTitle' => 'Cash', 'type' => 'credit', 'amount' => '5000']
                ]
            ],
            (object)[
                'identifier' => 'entry-2',
                'entryText' => 'Jan 15',
                'entryDescription' => 'Second entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Cash', 'type' => 'debit', 'amount' => '1000'],
                    (object)['accountTitle' => 'Sales Revenue', 'type' => 'credit', 'amount' => '1000']
                ]
            ]
        ];

        $studentSubmission = [
            // Entry 0: Wrong order (Credit, Debit)
            [
                'selectedEntryIndex' => 0,
                'rows' => [
                    ['accountTitle' => 'Equipment', 'debit' => '', 'credit' => '5000'],  // Credit first - violation
                    ['accountTitle' => 'Cash', 'debit' => '5000', 'credit' => '']        // Debit after
                ]
            ],
            // Entry 1: Correct order (Debit, Credit)
            [
                'selectedEntryIndex' => 1,
                'rows' => [
                    ['accountTitle' => 'Cash', 'debit' => '1000', 'credit' => ''],
                    ['accountTitle' => 'Sales Revenue', 'debit' => '', 'credit' => '1000']
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($solution, $studentSubmission);

        // Entry 0 rows should be incorrect due to order violation
        $this->assertFalse($result['results'][0]['rows'][0]['isCorrect']);
        $this->assertFalse($result['results'][0]['rows'][1]['isCorrect']);
        $this->assertFalse($result['results'][0]['isCorrect']);

        // Entry 1 rows should be correct (not affected by entry 0's violation)
        $this->assertTrue($result['results'][1]['rows'][0]['isCorrect']);
        $this->assertTrue($result['results'][1]['rows'][1]['isCorrect']);
        $this->assertTrue($result['results'][1]['isCorrect']);
    }
}
