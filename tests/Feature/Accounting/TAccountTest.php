<?php

namespace Tests\Feature\Accounting;


use App\Question;
use App\SavedQuestionsFolder;
use App\Submission;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AccountingTAccountTest extends TestCase
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
            "title" => "T-Account Test Question",
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
                        "entryDescription" => "Lutz contributed cash to the business.",
                        "solutionRows" => [
                            [
                                "identifier" => "row-1-1",
                                "accountTitle" => "Cash",
                                "type" => "debit",
                                "amount" => "5000"
                            ],
                            [
                                "identifier" => "row-1-2",
                                "accountTitle" => "Common Stock",
                                "type" => "credit",
                                "amount" => "5000"
                            ]
                        ]
                    ],
                    [
                        "identifier" => "entry-2",
                        "entryText" => "Jan 15",
                        "entryDescription" => "Paid rent expense with cash.",
                        "solutionRows" => [
                            [
                                "identifier" => "row-2-1",
                                "accountTitle" => "Rent Expense",
                                "type" => "debit",
                                "amount" => "500"
                            ],
                            [
                                "identifier" => "row-2-2",
                                "accountTitle" => "Cash",
                                "type" => "credit",
                                "amount" => "500"
                            ]
                        ]
                    ]
                ],
                "includeTAccounts" => true,
                "tAccounts" => [
                    [
                        "identifier" => "taccount-1",
                        "accountTitle" => "Cash",
                        "postings" => [
                            [
                                "identifier" => "posting-1",
                                "debitLabel" => "Jan 1",
                                "debit" => "5000",
                                "creditLabel" => "",
                                "credit" => ""
                            ],
                            [
                                "identifier" => "posting-2",
                                "debitLabel" => "",
                                "debit" => "",
                                "creditLabel" => "Jan 15",
                                "credit" => "500"
                            ]
                        ],
                        "balance" => null,
                        "beginningBalance" => null
                    ]
                ],
                "removedTAccountTitles" => []
            ])
        ];
    }

    // ------------------------------------------------------------------
    // Validation (question creation)
    // ------------------------------------------------------------------

    /** @test */
    public function can_create_question_with_t_accounts()
    {
        $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info)
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('questions', [
            'title' => 'T-Account Test Question'
        ]);
    }

    /** @test */
    public function t_account_requires_at_least_one_posting()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['postings'] = [];
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errorData = json_decode($response->json('errors.qti_json')[0], true);
        $this->assertEquals('At least one posting is required.', $errorData['tAccounts'][0]['postings']['general']);
    }

    /** @test */
    public function a_label_without_a_matching_amount_is_rejected()
    {
        // Regression test for the exact reported bug: a credit label was typed
        // in, but the credit amount box was left genuinely blank. The debit
        // side being fully and correctly filled in must not mask this.
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['postings'][0]['creditLabel'] = '2';
        // credit amount stays '' - the exact bug scenario.
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errorData = json_decode($response->json('errors.qti_json')[0], true);
        $this->assertEquals(
            'Credit amount is required since a credit label was entered.',
            $errorData['tAccounts'][0]['postings'][0]['credit']
        );
    }

    /** @test */
    public function an_amount_without_a_matching_label_is_rejected()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['postings'][0]['creditLabel'] = '';
        $qti_json['tAccounts'][0]['postings'][0]['credit'] = '300';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errorData = json_decode($response->json('errors.qti_json')[0], true);
        $this->assertEquals(
            'Credit label (date/number) is required.',
            $errorData['tAccounts'][0]['postings'][0]['creditLabel']
        );
    }

    /** @test */
    public function an_unused_side_left_fully_blank_is_valid()
    {
        // The base fixture's posting[0] uses only the debit side (credit label
        // AND credit amount both blank) - this must validate cleanly, since a
        // side is only invalid when it's half-filled, not when it's unused.
        $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info)
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function posting_requires_an_amount_on_at_least_one_side()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['postings'][0]['debitLabel'] = '';
        $qti_json['tAccounts'][0]['postings'][0]['debit'] = '';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errorData = json_decode($response->json('errors.qti_json')[0], true);
        $this->assertEquals(
            'Enter an amount on either the debit or credit side.',
            $errorData['tAccounts'][0]['postings'][0]['amount']
        );
    }

    /** @test */
    public function posting_may_have_both_a_debit_and_a_credit_amount()
    {
        // A row is just a shared grid line - it may legitimately hold both an
        // independent debit transaction and an independent credit transaction.
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['postings'][0]['creditLabel'] = 'Jan 2';
        $qti_json['tAccounts'][0]['postings'][0]['credit'] = '300';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info)
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function posting_amount_of_zero_is_allowed()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['postings'][0]['debit'] = '0';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info)
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function posting_negative_amount_is_rejected()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['postings'][0]['debit'] = '-100';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errorData = json_decode($response->json('errors.qti_json')[0], true);
        $this->assertEquals('Amount cannot be negative.', $errorData['tAccounts'][0]['postings'][0]['debit']);
    }

    /** @test */
    public function account_title_must_be_from_valid_list()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['accountTitle'] = 'Not A Real Account';
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errorData = json_decode($response->json('errors.qti_json')[0], true);
        $this->assertEquals(
            'Account title must be from the valid list of accounts.',
            $errorData['tAccounts'][0]['accountTitle']
        );
    }

    /** @test */
    public function balance_requires_exactly_one_side()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['balance'] = [
            'debitLabel' => '',
            'debit' => '',
            'creditLabel' => '',
            'credit' => ''
        ];
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errorData = json_decode($response->json('errors.qti_json')[0], true);
        $this->assertEquals(
            'Enter the balance on either the debit or credit side.',
            $errorData['tAccounts'][0]['balance']['amount']
        );
    }

    /** @test */
    public function balance_cannot_be_on_both_sides()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['balance'] = [
            'debitLabel' => 'Jan 30 Bal.',
            'debit' => '4500',
            'creditLabel' => 'Jan 30 Bal.',
            'credit' => '4500'
        ];
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errorData = json_decode($response->json('errors.qti_json')[0], true);
        $this->assertEquals(
            'Balance cannot be on both the debit and credit side.',
            $errorData['tAccounts'][0]['balance']['amount']
        );
    }

    /** @test */
    public function balance_requires_a_label_on_the_side_used()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['balance'] = [
            'debitLabel' => '',
            'debit' => '4500',
            'creditLabel' => '',
            'credit' => ''
        ];
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errorData = json_decode($response->json('errors.qti_json')[0], true);
        $this->assertEquals(
            'Label (date/number) is required.',
            $errorData['tAccounts'][0]['balance']['label']
        );
    }

    /** @test */
    public function beginning_balance_does_not_require_a_label()
    {
        // NOTE: this test's original premise ("Beginning Balance has no
        // editable label, always tagged simply 'Beginning Balance'") is no
        // longer accurate on the grading/student side - it's now a real
        // label the student picks from a dropdown, same as the ending
        // Balance, and IS graded (see
        // beginning_balance_now_requires_the_label_to_be_selected below).
        //
        // This test itself still passes and needs no change: it covers
        // question-creation validation (HasValidAccountingJournalEntries),
        // which was checked and deliberately does NOT require a label on
        // beginningBalance - unlike the ending Balance's validation block,
        // which does. That's fine because grading forces the expected label
        // to the constant "Beginning Balance" rather than reading it from
        // the solution object at all, so correctness never depends on the
        // solution having a label stored - this test's older, label-less
        // payload shape grades exactly as correctly as a labeled one would.
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['beginningBalance'] = [
            'debit' => '1900',
            'credit' => ''
        ];
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info)
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function beginning_balance_cannot_be_on_both_sides()
    {
        $qti_json = json_decode($this->qti_question_info['qti_json'], true);
        $qti_json['tAccounts'][0]['beginningBalance'] = [
            'debit' => '1900',
            'credit' => '1900'
        ];
        $this->qti_question_info['qti_json'] = json_encode($qti_json);

        $response = $this->actingAs($this->user)->postJson("/api/questions", $this->qti_question_info);
        $response->assertStatus(422);

        $errorData = json_decode($response->json('errors.qti_json')[0], true);
        $this->assertEquals(
            'Beginning balance cannot be on both the debit and credit side.',
            $errorData['tAccounts'][0]['beginningBalance']['amount']
        );
    }

    // ------------------------------------------------------------------
    // Grading
    // ------------------------------------------------------------------

    /** @test */
    public function scores_all_correct_t_account_submission_as_100_percent()
    {
        $submission = new Submission();

        $tAccountsSolution = [
            (object)[
                'accountTitle' => 'Cash',
                'postings' => [
                    (object)['debitLabel' => 'Jan 1', 'debit' => '5000', 'creditLabel' => '', 'credit' => ''],
                    (object)['debitLabel' => '', 'debit' => '', 'creditLabel' => 'Jan 15', 'credit' => '500']
                ],
                'balance' => null,
                'beginningBalance' => null
            ]
        ];

        $studentSubmission = [
            'entries' => [],
            'tAccounts' => [
                [
                    'rows' => [
                        ['debitLabel' => 'Jan 1', 'debit' => '5000', 'creditLabel' => '', 'credit' => ''],
                        ['debitLabel' => '', 'debit' => '', 'creditLabel' => 'Jan 15', 'credit' => '500']
                    ],
                    'balance' => null,
                    'beginningBalance' => null
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry([], $studentSubmission, $tAccountsSolution);

        $this->assertEquals(1.0, $result['proportionCorrect']);
        $this->assertTrue($result['allCorrect']);
    }

    /** @test */
    public function grades_a_row_with_both_debit_and_credit_amounts_independently()
    {
        // Regression test: a row is just a shared grid line and may hold both
        // an independent debit transaction and an independent credit
        // transaction. Grading must not silently drop one side just because
        // the other is also filled in.
        $submission = new Submission();

        $tAccountsSolution = [
            (object)[
                'accountTitle' => 'Cash',
                'postings' => [
                    (object)['debitLabel' => 'Jan 1', 'debit' => '500', 'creditLabel' => 'Jan 2', 'credit' => '300']
                ],
                'balance' => null,
                'beginningBalance' => null
            ]
        ];

        // Debit side correct, credit side wrong amount - on the SAME row.
        $studentSubmission = [
            'entries' => [],
            'tAccounts' => [
                [
                    'rows' => [
                        ['debitLabel' => 'Jan 1', 'debit' => '500', 'creditLabel' => 'Jan 2', 'credit' => '999']
                    ],
                    'balance' => null,
                    'beginningBalance' => null
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry([], $studentSubmission, $tAccountsSolution);

        $row = $result['tAccountResults'][0]['rows'][0];
        $this->assertTrue($row['debitLabelCorrect']);
        $this->assertTrue($row['debitCorrect']);
        $this->assertTrue($row['creditLabelCorrect']);
        $this->assertFalse($row['creditCorrect']); // wrong amount, must be caught independently
        $this->assertFalse($row['isCorrect']);

        // Debit side's correctness must not be affected by the credit side being wrong.
        $this->assertLessThan(1.0, $result['proportionCorrect']);
        $this->assertGreaterThan(0.0, $result['proportionCorrect']);
    }

    /** @test */
    public function stray_entry_on_a_side_the_solution_does_not_use_is_marked_wrong()
    {
        // Regression test: a box the solution has nothing on (this row/side is
        // never used in the solution) must not silently skip grading just
        // because it isn't a "real" position. Filling it in anyway is a wrong
        // answer and must be counted as one, not ignored.
        $submission = new Submission();

        $tAccountsSolution = [
            (object)[
                'accountTitle' => 'Cash',
                // This account only ever posts on the debit side.
                'postings' => [
                    (object)['debitLabel' => 'Jan 1', 'debit' => '500', 'creditLabel' => '', 'credit' => '']
                ],
                'balance' => null,
                'beginningBalance' => null
            ]
        ];

        // Debit side answered correctly, but the student also filled in the
        // credit side of the same row - a side the solution never uses here.
        $studentSubmission = [
            'entries' => [],
            'tAccounts' => [
                [
                    'rows' => [
                        ['debitLabel' => 'Jan 1', 'debit' => '500', 'creditLabel' => '5', 'credit' => '0']
                    ],
                    'balance' => null,
                    'beginningBalance' => null
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry([], $studentSubmission, $tAccountsSolution);

        $row = $result['tAccountResults'][0]['rows'][0];
        $this->assertTrue($row['debitLabelCorrect']);
        $this->assertTrue($row['debitCorrect']);
        // The stray credit entry must be marked wrong, not left ungraded (null).
        $this->assertFalse($row['creditLabelCorrect']);
        $this->assertFalse($row['creditCorrect']);

        // It must count against the score, not be a free/costless mistake.
        $this->assertLessThan(1.0, $result['proportionCorrect']);
    }

    /** @test */
    public function stray_label_alone_does_not_mark_a_correctly_blank_amount_as_wrong()
    {
        // Regression test: a stray label with no matching amount must only
        // mark the label sub-field wrong. The amount sub-field, being
        // genuinely blank (which is correct for a box the solution doesn't
        // use), must independently read as correct - not dragged down just
        // because the label on the same box is wrong.
        $submission = new Submission();

        $tAccountsSolution = [
            (object)[
                'accountTitle' => 'Cash',
                'postings' => [
                    (object)['debitLabel' => 'Jan 1', 'debit' => '500', 'creditLabel' => '', 'credit' => '']
                ],
                'balance' => null,
                'beginningBalance' => null
            ]
        ];

        // Stray credit label typed in, but credit amount left genuinely blank.
        $studentSubmission = [
            'entries' => [],
            'tAccounts' => [
                [
                    'rows' => [
                        ['debitLabel' => 'Jan 1', 'debit' => '500', 'creditLabel' => '5', 'credit' => '']
                    ],
                    'balance' => null,
                    'beginningBalance' => null
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry([], $studentSubmission, $tAccountsSolution);

        $row = $result['tAccountResults'][0]['rows'][0];
        $this->assertFalse($row['creditLabelCorrect']); // stray label - wrong
        $this->assertTrue($row['creditCorrect']); // genuinely blank amount - correct
    }

    /** @test */
    public function debit_and_credit_columns_are_graded_independently_for_order()
    {
        // A label out of order on one side must not affect the other side.
        $submission = new Submission();

        $tAccountsSolution = [
            (object)[
                'accountTitle' => 'Cash',
                'postings' => [
                    (object)['debitLabel' => 'Jan 1', 'debit' => '100', 'creditLabel' => '', 'credit' => ''],
                    (object)['debitLabel' => 'Jan 5', 'debit' => '200', 'creditLabel' => '', 'credit' => ''],
                    (object)['debitLabel' => '', 'debit' => '', 'creditLabel' => 'Jan 3', 'credit' => '50']
                ],
                'balance' => null,
                'beginningBalance' => null
            ]
        ];

        // Debit column posted out of order (Jan 5 before Jan 1); credit column correct.
        $studentSubmission = [
            'entries' => [],
            'tAccounts' => [
                [
                    'rows' => [
                        ['debitLabel' => 'Jan 5', 'debit' => '200', 'creditLabel' => '', 'credit' => ''],
                        ['debitLabel' => 'Jan 1', 'debit' => '100', 'creditLabel' => '', 'credit' => ''],
                        ['debitLabel' => '', 'debit' => '', 'creditLabel' => 'Jan 3', 'credit' => '50']
                    ],
                    'balance' => null,
                    'beginningBalance' => null
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry([], $studentSubmission, $tAccountsSolution);

        // The credit-side row (unaffected by the debit-side ordering mistake) should still be correct.
        $this->assertTrue($result['tAccountResults'][0]['rows'][2]['creditLabelCorrect']);
        $this->assertTrue($result['tAccountResults'][0]['rows'][2]['creditCorrect']);
    }

    /** @test */
    public function same_label_rows_may_be_posted_in_either_order()
    {
        $submission = new Submission();

        $tAccountsSolution = [
            (object)[
                'accountTitle' => 'Accounts Payable',
                'postings' => [
                    (object)['debitLabel' => '', 'debit' => '', 'creditLabel' => 'Jun 30 Adj.', 'credit' => '290'],
                    (object)['debitLabel' => '', 'debit' => '', 'creditLabel' => 'Jun 30 Adj.', 'credit' => '40']
                ],
                'balance' => null,
                'beginningBalance' => null
            ]
        ];

        // Same-label ("Jun 30 Adj.") rows swapped relative to the solution's listed order.
        $studentSubmission = [
            'entries' => [],
            'tAccounts' => [
                [
                    'rows' => [
                        ['debitLabel' => '', 'debit' => '', 'creditLabel' => 'Jun 30 Adj.', 'credit' => '40'],
                        ['debitLabel' => '', 'debit' => '', 'creditLabel' => 'Jun 30 Adj.', 'credit' => '290']
                    ],
                    'balance' => null,
                    'beginningBalance' => null
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry([], $studentSubmission, $tAccountsSolution);

        $this->assertTrue($result['allCorrect']);
        $this->assertEquals(1.0, $result['proportionCorrect']);
    }

    /** @test */
    public function balance_grades_debit_and_credit_independently()
    {
        // gradeSimpleBalance no longer derives one "side" from the amount and
        // uses it for both label and amount - each of the four boxes
        // (debitLabel, debit, creditLabel, credit) is graded on its own,
        // same as a posting row.
        $submission = new Submission();

        $tAccountsSolution = [
            (object)[
                'accountTitle' => 'Cash',
                'postings' => [
                    (object)['debitLabel' => 'Jan 1', 'debit' => '5000', 'creditLabel' => '', 'credit' => '']
                ],
                'balance' => (object)['debitLabel' => 'Jan 30 Bal.', 'debit' => '5000', 'creditLabel' => '', 'credit' => ''],
                'beginningBalance' => null
            ]
        ];

        // Correct side and amount, but wrong label text.
        $studentSubmission = [
            'entries' => [],
            'tAccounts' => [
                [
                    'rows' => [
                        ['debitLabel' => 'Jan 1', 'debit' => '5000', 'creditLabel' => '', 'credit' => '']
                    ],
                    'balance' => ['debitLabel' => 'Wrong Label', 'debit' => '5000', 'creditLabel' => '', 'credit' => ''],
                    'beginningBalance' => null
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry([], $studentSubmission, $tAccountsSolution);

        $balance = $result['tAccountResults'][0]['balance'];
        $this->assertFalse($balance['debitLabelCorrect']); // wrong text, right side
        $this->assertTrue($balance['debitCorrect']); // amount graded independently of the label
        $this->assertTrue($balance['creditLabelCorrect']); // correctly left blank
        $this->assertTrue($balance['creditCorrect']); // correctly left blank
        $this->assertFalse($balance['isCorrect']);
    }

    /** @test */
    public function balance_amount_can_be_correct_even_when_its_label_is_on_the_wrong_side()
    {
        // Regression test for the exact scenario walked through with the
        // instructor: the solution's Ending Balance belongs on credit. The
        // student puts the correct amount on credit, but mistakenly types
        // the "Ending Balance" label on debit instead. The credit amount
        // must still be marked correct on its own merit - it isn't tied to
        // wherever the label happened to land.
        $submission = new Submission();

        $tAccountsSolution = [
            (object)[
                'accountTitle' => 'Cash',
                'postings' => [
                    (object)['debitLabel' => 'Jan 1', 'debit' => '5000', 'creditLabel' => '', 'credit' => '']
                ],
                'balance' => (object)['debitLabel' => '', 'debit' => '', 'creditLabel' => 'Ending Balance', 'credit' => '5000'],
                'beginningBalance' => null
            ]
        ];

        $studentSubmission = [
            'entries' => [],
            'tAccounts' => [
                [
                    'rows' => [
                        ['debitLabel' => 'Jan 1', 'debit' => '5000', 'creditLabel' => '', 'credit' => '']
                    ],
                    // Amount correctly on credit; label mistakenly on debit.
                    'balance' => ['debitLabel' => 'Ending Balance', 'debit' => '', 'creditLabel' => '', 'credit' => '5000'],
                    'beginningBalance' => null
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry([], $studentSubmission, $tAccountsSolution);

        $balance = $result['tAccountResults'][0]['balance'];
        $this->assertTrue($balance['creditCorrect']); // right amount, right side
        $this->assertFalse($balance['creditLabelCorrect']); // the label that belongs here is missing
        $this->assertFalse($balance['debitLabelCorrect']); // stray label, wrong side
        $this->assertTrue($balance['debitCorrect']); // correctly left blank
        $this->assertFalse($balance['isCorrect']);
    }

    /** @test */
    public function beginning_balance_grades_debit_and_credit_independently()
    {
        // Beginning Balance now has a real editable label (a dropdown the
        // student picks, same interaction as the ending Balance), instead of
        // the old fixed "always tagged Beginning Balance, label never
        // graded" behavior. It's graded the same 4-independent-boxes way as
        // Balance. The expected label is forced to the constant "Beginning
        // Balance" regardless of what's stored on the solution object, so
        // this passes even though the fixture's solution never sets a
        // debitLabel/creditLabel field at all (matching older solution data
        // saved before the label existed).
        $submission = new Submission();

        $tAccountsSolution = [
            (object)[
                'accountTitle' => 'Cash',
                'postings' => [
                    (object)['debitLabel' => 'Jan 15', 'debit' => '500', 'creditLabel' => '', 'credit' => '']
                ],
                'balance' => null,
                'beginningBalance' => (object)['debit' => '1900', 'credit' => '']
            ]
        ];

        $studentSubmission = [
            'entries' => [],
            'tAccounts' => [
                [
                    'rows' => [
                        ['debitLabel' => 'Jan 15', 'debit' => '500', 'creditLabel' => '', 'credit' => '']
                    ],
                    'balance' => null,
                    'beginningBalance' => ['debitLabel' => 'Beginning Balance', 'debit' => '1900', 'creditLabel' => '', 'credit' => '']
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry([], $studentSubmission, $tAccountsSolution);

        $beginningBalance = $result['tAccountResults'][0]['beginningBalance'];
        $this->assertTrue($beginningBalance['debitLabelCorrect']);
        $this->assertTrue($beginningBalance['debitCorrect']);
        $this->assertTrue($beginningBalance['creditLabelCorrect']); // correctly left blank
        $this->assertTrue($beginningBalance['creditCorrect']); // correctly left blank
        $this->assertTrue($beginningBalance['isCorrect']);
    }

    /** @test */
    public function beginning_balance_now_requires_the_label_to_be_selected()
    {
        // Regression test: Beginning Balance used to be graded on side +
        // amount only, with the label never checked at all. It's now a real
        // dropdown the student must pick "Beginning Balance" from, so
        // leaving it blank must cost credit even when the amount is right.
        $submission = new Submission();

        $tAccountsSolution = [
            (object)[
                'accountTitle' => 'Cash',
                'postings' => [
                    (object)['debitLabel' => 'Jan 15', 'debit' => '500', 'creditLabel' => '', 'credit' => '']
                ],
                'balance' => null,
                'beginningBalance' => (object)['debit' => '1900', 'credit' => '']
            ]
        ];

        // Amount correct, but the label dropdown was never touched.
        $studentSubmission = [
            'entries' => [],
            'tAccounts' => [
                [
                    'rows' => [
                        ['debitLabel' => 'Jan 15', 'debit' => '500', 'creditLabel' => '', 'credit' => '']
                    ],
                    'balance' => null,
                    'beginningBalance' => ['debitLabel' => '', 'debit' => '1900', 'creditLabel' => '', 'credit' => '']
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry([], $studentSubmission, $tAccountsSolution);

        $beginningBalance = $result['tAccountResults'][0]['beginningBalance'];
        $this->assertFalse($beginningBalance['debitLabelCorrect']);
        $this->assertTrue($beginningBalance['debitCorrect']);
        $this->assertFalse($beginningBalance['isCorrect']);
    }

    /** @test */
    public function t_account_score_blends_with_journal_entry_score()
    {
        $submission = new Submission();

        $entriesSolution = [
            (object)[
                'identifier' => 'entry-1',
                'entryText' => 'Jan 1',
                'entryDescription' => 'Test entry',
                'solutionRows' => [
                    (object)['accountTitle' => 'Cash', 'type' => 'debit', 'amount' => '5000'],
                    (object)['accountTitle' => 'Common Stock', 'type' => 'credit', 'amount' => '5000']
                ]
            ]
        ];

        $tAccountsSolution = [
            (object)[
                'accountTitle' => 'Cash',
                'postings' => [
                    (object)['debitLabel' => 'Jan 1', 'debit' => '5000', 'creditLabel' => '', 'credit' => '']
                ],
                'balance' => null,
                'beginningBalance' => null
            ]
        ];

        // Journal entry portion fully correct; T-Account portion fully wrong.
        $studentSubmission = [
            'entries' => [
                [
                    'selectedEntryIndex' => 0,
                    'rows' => [
                        ['accountTitle' => 'Cash', 'debit' => '5000', 'credit' => ''],
                        ['accountTitle' => 'Common Stock', 'debit' => '', 'credit' => '5000']
                    ]
                ]
            ],
            'tAccounts' => [
                [
                    'rows' => [
                        ['debitLabel' => '', 'debit' => '', 'creditLabel' => '', 'credit' => '']
                    ],
                    'balance' => null,
                    'beginningBalance' => null
                ]
            ]
        ];

        $result = $submission->computeScoreForAccountingJournalEntry($entriesSolution, $studentSubmission, $tAccountsSolution);

        // Blended score should be neither 0 nor 1 - entries correct, T-Accounts wrong.
        $this->assertGreaterThan(0.0, $result['proportionCorrect']);
        $this->assertLessThan(1.0, $result['proportionCorrect']);
        $this->assertFalse($result['allCorrect']);

        // The journal entry itself should still show as fully correct in isolation.
        $this->assertTrue($result['results'][0]['isCorrect']);
    }

    /** @test */
    public function t_account_grading_is_skipped_when_no_t_accounts_solution_given()
    {
        // Backward compatibility: existing callers passing just (solution, studentSubmission)
        // with a bare array of entries must still work exactly as before.
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

        $this->assertEquals(1.0, $result['proportionCorrect']);
        $this->assertTrue($result['allCorrect']);
        $this->assertEquals([], $result['tAccountResults']);
    }
}
