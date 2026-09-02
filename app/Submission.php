<?php


namespace App;

use App\Exceptions\Handler;
use App\Helpers\Helper;
use App\Traits\JWT;
use DOMDocument;
use \Exception;
use App\Http\Requests\StoreSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;
use App\Traits\DateFormatter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use PHPUnit\TextUI\Help;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use stdClass;
use Telegram\Bot\Laravel\Facades\Telegram;
use function Aws\boolean_value;


class Submission extends Model
{

    use DateFormatter;
    use JWT;

    protected $guarded = [];


    public function computeScoreForAccountingMultiPartComputation(array $qtiArray, array $studentSubmission): array
    {
        $results = [];
        $totalAnswerCells = 0;
        $correctCells = 0;

        $tables = $qtiArray['tables'] ?? [];

        foreach ($tables as $ti => $table) {
            $rows = $table['rows'] ?? [];

            foreach ($rows as $ri => $row) {
                if (($row['rowType'] ?? '') !== 'data') continue;
                if (!isset($row['cells'])) continue;

                foreach ($row['cells'] as $ci => $cell) {
                    if (($cell['mode'] ?? '') !== 'answer') continue;

                    $totalAnswerCells++;
                    $expectedValue = $cell['value'] ?? '';
                    $answerType = $cell['answerType'] ?? 'dollar';
                    $rawStudent = $studentSubmission[$ti][$ri][$ci] ?? '';

                    // Strip all formatting characters before comparison
                    $cleanStudent = str_replace(['$', ',', '%', ' '], '', (string)$rawStudent);
                    // Also strip the :1 ratio suffix if present
                    $cleanStudent = preg_replace('/:1$/', '', $cleanStudent);
                    $cleanExpected = str_replace(['$', ',', '%', ' '], '', (string)$expectedValue);
                    $cleanExpected = preg_replace('/:1$/', '', $cleanExpected);

                    if ($answerType === 'dropdown') {
                        // Exact case-insensitive match
                        $isCorrect = strtolower(trim($rawStudent)) === strtolower(trim($expectedValue));
                    } else {
                        // Numeric comparison with tolerance based on rounding setting
                        if (!is_numeric($cleanStudent) || !is_numeric($cleanExpected)) {
                            $isCorrect = false;
                        } else {
                            $tolerance = $this->_getMpcTolerance($cell);
                            $isCorrect = abs((float)$cleanExpected - (float)$cleanStudent) <= $tolerance;
                        }
                    }

                    $results[$ti][$ri][$ci] = [
                        'studentValue' => $rawStudent,
                        'expectedValue' => $expectedValue,
                        'isCorrect' => $isCorrect
                    ];

                    if ($isCorrect) $correctCells++;
                }
            }
        }

        return [
            'results' => $results,
            'proportionCorrect' => $totalAnswerCells > 0 ? round($correctCells / $totalAnswerCells, 4) : 0
        ];
    }

    /**
     * Compute the grading tolerance for a multi-part computation answer cell
     * based on its type and rounding settings.
     */
    private function _getMpcTolerance(array $cell): float
    {
        $answerType = $cell['answerType'] ?? 'dollar';

        switch ($answerType) {
            case 'dollar':
                // Nearest dollar: tolerance of 0.50 (anything that rounds to correct integer)
                // Nearest cent: tolerance of 0.005
                return ($cell['dollarRounding'] ?? 'dollar') === 'cent' ? 0.005 : 0.50;
            case 'general':
            case 'percentage':
            case 'ratio':
            case 'custom':
                // Tolerance is half a unit at the specified decimal place
                // e.g. 2 decimal places → tolerance of 0.005
                $decimalPlaces = isset($cell['decimalPlaces']) ? (int)$cell['decimalPlaces'] : 2;
                return 0.5 * pow(10, -$decimalPlaces);

            default:
                return 0.005;
        }
    }

    /**
     * Replace the existing computeScoreForAccountingReport() method and
     * _getAccountingReportSections() method with the versions below.
     * Everything else in Submission.php stays the same.
     */

    public function computeScoreForAccountingReport(array $qtiArray, array $studentSubmission): array
    {
        $results = [];
        $totalAnswerCells = 0;
        $correctCells = 0;

        $rows = $qtiArray['rows'] ?? [];
        $columns = $qtiArray['columns'] ?? [];
        $orderMode = $qtiArray['orderMode'] ?? 'exact';

        if ($orderMode === 'exact') {
            foreach ($rows as $ri => $row) {
                if ($row['isHeader'] ?? false) continue;
                if (!isset($row['cells'])) continue;
                foreach ($row['cells'] as $ci => $cell) {
                    if (($cell['mode'] ?? '') !== 'answer') continue;
                    $totalAnswerCells++;
                    $expectedValue = $cell['value'] ?? '';
                    $colType = $columns[$ci]['type'] ?? 'text';
                    if ($colType === 'numeric') {
                        $studentValue = str_replace(['$', ',', ' '], '', $studentSubmission[$ri][$ci] ?? '');
                        $cleanExpected = str_replace(['$', ',', ' '], '', $expectedValue);
                        $isCorrect = $studentValue !== '' && abs((float)$cleanExpected - (float)$studentValue) < 0.01;
                    } else {
                        $studentValue = $studentSubmission[$ri][$ci] ?? '';
                        $isCorrect = strtolower(trim($expectedValue)) === strtolower(trim($studentValue));
                    }
                    $results[$ri][$ci] = [
                        'studentValue' => $studentSubmission[$ri][$ci] ?? '',
                        'expectedValue' => $expectedValue,
                        'isCorrect' => $isCorrect
                    ];
                    if ($isCorrect) $correctCells++;
                }
            }
        } else {
            // within_sections mode:
            // - flexible: true rows → best-match scoring within the section
            // - flexible: false/missing rows → positional (exact order)
            $sections = $this->_getAccountingReportSections($rows);

            foreach ($sections as $section) {
                $positionalEntries = [];
                $flexibleEntries = [];

                foreach ($section['rows'] as $entry) {
                    if (!empty($entry['row']['flexible'])) {
                        $flexibleEntries[] = $entry;
                    } else {
                        $positionalEntries[] = $entry;
                    }
                }

                // --- Positional rows: grade by position ---
                foreach ($positionalEntries as $entry) {
                    $ri = $entry['ri'];
                    $row = $entry['row'];
                    if (!isset($row['cells'])) continue;
                    foreach ($row['cells'] as $ci => $cell) {
                        if (($cell['mode'] ?? '') !== 'answer') continue;
                        $totalAnswerCells++;
                        $expectedValue = $cell['value'] ?? '';
                        $colType = $columns[$ci]['type'] ?? 'text';
                        $rawStudentValue = $studentSubmission[$ri][$ci] ?? '';
                        if ($colType === 'numeric') {
                            $studentValue = str_replace(['$', ',', ' '], '', $rawStudentValue);
                            $cleanExpected = str_replace(['$', ',', ' '], '', $expectedValue);
                            $isCorrect = $studentValue !== '' && abs((float)$cleanExpected - (float)$studentValue) < 0.01;
                        } else {
                            $isCorrect = strtolower(trim($expectedValue)) === strtolower(trim($rawStudentValue));
                        }
                        $results[$ri][$ci] = [
                            'studentValue' => $rawStudentValue,
                            'expectedValue' => $expectedValue,
                            'isCorrect' => $isCorrect
                        ];
                        if ($isCorrect) $correctCells++;
                    }
                }

                // --- Flexible rows: best-match scoring ---
                if (!empty($flexibleEntries)) {
                    // Build expected rows (answer cells only)
                    $expectedRows = [];
                    foreach ($flexibleEntries as $entry) {
                        $ri = $entry['ri'];
                        $row = $entry['row'];
                        if (!isset($row['cells'])) continue;
                        $answerCells = [];
                        foreach ($row['cells'] as $ci => $cell) {
                            if (($cell['mode'] ?? '') === 'answer') {
                                $answerCells[$ci] = $cell['value'] ?? '';
                            }
                        }
                        if (!empty($answerCells)) {
                            $expectedRows[] = ['ri' => $ri, 'cells' => $answerCells];
                        }
                    }

                    // Build student rows (cleaned values, same positional indices)
                    $studentRows = [];
                    foreach ($flexibleEntries as $entry) {
                        $ri = $entry['ri'];
                        $row = $entry['row'];
                        if (!isset($row['cells'])) continue;
                        $studentCells = [];
                        foreach ($row['cells'] as $ci => $cell) {
                            if (($cell['mode'] ?? '') === 'answer') {
                                $colType = $columns[$ci]['type'] ?? 'text';
                                $rawValue = $studentSubmission[$ri][$ci] ?? '';
                                $studentCells[$ci] = $colType === 'numeric'
                                    ? str_replace(['$', ',', ' '], '', $rawValue)
                                    : $rawValue;
                            }
                        }
                        $studentRows[] = ['ri' => $ri, 'cells' => $studentCells];
                    }

                    // Greedy best-match: each student row can only be used once
                    $usedStudentIndices = [];

                    foreach ($expectedRows as $expected) {
                        $bestStudentIndex = null;
                        $bestMatchCount = -1;

                        foreach ($studentRows as $studentIndex => $student) {
                            if (in_array($studentIndex, $usedStudentIndices)) continue;
                            $matchCount = 0;
                            foreach ($expected['cells'] as $ci => $expectedValue) {
                                $studentValue = $student['cells'][$ci] ?? '';
                                $colType = $columns[$ci]['type'] ?? 'text';
                                if ($colType === 'numeric') {
                                    $cleanExpected = str_replace(['$', ',', ' '], '', $expectedValue);
                                    if ($studentValue !== '' && abs((float)$cleanExpected - (float)$studentValue) < 0.01) {
                                        $matchCount++;
                                    }
                                } else {
                                    if (strtolower(trim($expectedValue)) === strtolower(trim($studentValue))) {
                                        $matchCount++;
                                    }
                                }
                            }
                            if ($matchCount > $bestMatchCount) {
                                $bestMatchCount = $matchCount;
                                $bestStudentIndex = $studentIndex;
                            }
                        }

                        if ($bestStudentIndex !== null) {
                            $usedStudentIndices[] = $bestStudentIndex;
                        }
                        $studentRi = $bestStudentIndex !== null ? $studentRows[$bestStudentIndex]['ri'] : null;

                        // Grade each answer cell; store against the student's row index
                        // so feedback highlights the right input in the viewer
                        foreach ($expected['cells'] as $ci => $expectedValue) {
                            $totalAnswerCells++;
                            $colType = $columns[$ci]['type'] ?? 'text';
                            $rawStudentValue = $studentRi !== null ? ($studentSubmission[$studentRi][$ci] ?? '') : '';
                            if ($colType === 'numeric') {
                                $studentValue = str_replace(['$', ',', ' '], '', $rawStudentValue);
                                $cleanExpected = str_replace(['$', ',', ' '], '', $expectedValue);
                                $isCorrect = $studentValue !== '' && abs((float)$cleanExpected - (float)$studentValue) < 0.01;
                            } else {
                                $isCorrect = strtolower(trim($expectedValue)) === strtolower(trim($rawStudentValue));
                            }
                            $resultRi = $studentRi ?? $expected['ri'];
                            $results[$resultRi][$ci] = [
                                'studentValue' => $rawStudentValue,
                                'expectedValue' => $expectedValue,
                                'isCorrect' => $isCorrect
                            ];
                            if ($isCorrect) $correctCells++;
                        }
                    }
                }
            }
        }

        return [
            'results' => $results,
            'proportionCorrect' => $totalAnswerCells > 0 ? round($correctCells / $totalAnswerCells, 4) : 0
        ];
    }

    /**
     * Split rows into sections based on section headers.
     * Rows with no preceding header fall into the first implicit section.
     */
    private function _getAccountingReportSections(array $rows): array
    {
        $sections = [];
        $currentSection = ['rows' => []];

        foreach ($rows as $ri => $row) {
            if ($row['isHeader'] ?? false) {
                if (!empty($currentSection['rows'])) {
                    $sections[] = $currentSection;
                }
                $currentSection = ['headerRi' => $ri, 'rows' => []];
            } else {
                $currentSection['rows'][] = ['ri' => $ri, 'row' => $row];
            }
        }

        if (!empty($currentSection['rows'])) {
            $sections[] = $currentSection;
        }

        return $sections;
    }

    public function updateScoresWithNewTotalWeight($assignment_id, $old_total_points, $new_total_points)
    {
        $factor = $new_total_points / $old_total_points;
        $submissions = $this->where('assignment_id', $assignment_id)->get();
        foreach ($submissions as $submission) {
            $submission->update(['score' => $factor * $submission->score]);
        }
    }

    /**
     * @throws Exception
     */
    public function getAutoGradedSubmissionsByUser($enrolled_users, $assignment, $question): array
    {
        $submissions = $assignment->submissions->where('question_id', $question->id);

        $submissions_by_user = [];

        foreach ($submissions as $submission) {
            $submissions_by_user[$submission->user_id] = $submission;
        }


        $auto_graded_submission_info_by_user = [];

        foreach ($enrolled_users as $enrolled_user) {
            if (isset($submissions_by_user[$enrolled_user->id])) {
                $submission = $submissions_by_user[$enrolled_user->id];
                $submitted_work = $submission->submitted_work
                    ? Storage::disk('s3')->temporaryUrl("submitted-work/$assignment->id/$submission->submitted_work", now()->addDay())
                    : null;
                $submitted_work_at = $submission->submitted_work_at
                    ? Carbon::now('UTC') // Get the current UTC time
                    ->setTimezone(request()->user()->time_zone) // Adjust to the user's timezone
                    ->format('M d, Y \a\t g:i:s a') : null;
                $auto_graded_submission_info_by_user[] = [
                    'user_id' => $enrolled_user->id,
                    'question_id' => $question->id,
                    'name' => $enrolled_user->first_name . ' ' . $enrolled_user->last_name,
                    'last_first' => $enrolled_user->last_name . ', ' . $enrolled_user->first_name,
                    'email' => $enrolled_user->email,
                    'submission' => $this->getStudentResponse($submission, $question->technology, true),
                    'submitted_work' => $submitted_work,
                    'submitted_work_at' => $submitted_work_at,
                    'submission_count' => $submission->submission_count,
                    'score' => Helper::removeZerosAfterDecimal($submission->score),
                    'updated_at' => $submission->updated_at
                ];
            }

        }
        if ($auto_graded_submission_info_by_user) {
            usort($auto_graded_submission_info_by_user, function ($a, $b) {
                return $a['name'] <=> $b['name'];
            });
        }
        return $auto_graded_submission_info_by_user;
    }

    /**
     * @throws Exception
     */
    function getProportionCorrect(string $technology, $submission)
    {
        switch ($technology) {
            case('h5p'):
                $proportion_correct = (floatval($submission->result->score->raw) / floatval($submission->result->score->max));
                break;
            case('imathas'):
                $proportion_correct = floatval($submission->score);
                break;
            case('webwork'):
                $score = (object)$submission->score;
                if (!isset($score->result)) {
                    throw new Exception ('Please refresh the page and resubmit to use the upgraded Webwork renderer.');
                }
                $weighted_score = 0;
                $num_answers = 0;
                foreach ($score->answers as $value) {
                    $num_answers++;
                }
                ///if there's an issue with a weighted question, use: install_weighted_grader()
                foreach ($score->answers as $answer) {
                    $answer = (object)$answer;
                    if (isset($answer->score)) {
                        $weighted_score += isset($answer->weight) ? $answer->score * $answer->weight / 100 : $answer->score / $num_answers;
                    }
                }
                $proportion_correct = floatval($weighted_score);

                break;
            case('qti'):
                $question_type = $submission->question->questionType;
                switch ($question_type) {
                    case('accounting_report'):
                        $studentSubmission = json_decode($submission->student_response, 1);
                        $qtiArray = json_decode(json_encode($submission->question), true);
                        $proportion_correct_info = $this->computeScoreForAccountingReport($qtiArray, $studentSubmission);
                        $proportion_correct = $proportion_correct_info['proportionCorrect'];
                        break;
                    case('accounting_journal_entry'):
                        $studentSubmission = json_decode($submission->student_response, 1);
                        $solution = $submission->question->entries;
                        $tAccountsSolution = $submission->question->includeTAccounts
                            ? ($submission->question->tAccounts ?? [])
                            : [];
                        $proportion_correct_info = $this->computeScoreForAccountingJournalEntry($solution, $studentSubmission, $tAccountsSolution);
                        $proportion_correct = $proportion_correct_info['proportionCorrect'];
                        break;
                    case('accounting_multi_part_computation'):
                        $studentSubmission = json_decode($submission->student_response, true);
                        $qtiArray = json_decode(json_encode($submission->question), true);
                        $proportion_correct_info = $this->computeScoreForAccountingMultiPartComputation($qtiArray, $studentSubmission);
                        $proportion_correct = $proportion_correct_info['proportionCorrect'];
                        break;
                    case('three_d_model_multiple_choice'):
                        $student_selected_index = json_decode($submission->student_response)->selectedIndex;
                        if (!is_int($student_selected_index) || $student_selected_index < 0) {
                            $response['type'] = 'error';
                            $response['message'] = "You did not select any part of the model.";
                            $this->_returnJsonAndExit($response);
                        }
                        $proportion_correct = +($submission->question->solutionStructure->selectedIndex === $student_selected_index);

                        break;
                    case('marker'):
                        $compare_marks_info = $this->_compareMarks($submission->question->partialCredit, $submission->question->solutionStructure, json_decode($submission->student_response)->structure);
                        $proportion_correct = Round($compare_marks_info['proportion_correct'], 2);
                        break;
                    case('submit_molecule'):
                        $proportion_correct_response = $this->computeScoreFromSubmitMolecule($submission->question, $submission->student_response);
                        if ($proportion_correct_response['type'] === 'error') {
                            $this->_returnJsonAndExit($proportion_correct_response);
                        } else {
                            $proportion_correct = $proportion_correct_response['proportion_correct'];
                        }
                        break;
                    case('highlight_text'):
                        $student_responses = json_decode($submission->student_response);
                        $score = 0;
                        $num_correct_answers = 0;
                        foreach ($submission->question->responses as $response) {
                            $num_correct_answers += +$response->correctResponse;
                            $score = $score + $this->computeScoreFromPlusMinusScoring($response, $student_responses);
                        }
                        $proportion_correct = Max($score, 0) / $num_correct_answers;
                        break;
                    case('drag_and_drop_cloze'):
                        $student_responses = json_decode($submission->student_response);
                        $score = 0;
                        $correct_responses = [];
                        foreach ($submission->question->correctResponses as $response) {
                            $correct_responses[] = $response->identifier;
                        }
                        foreach ($student_responses as $response) {
                            $score += in_array($response, $correct_responses);
                        }
                        $proportion_correct = $score / count($student_responses);
                        break;
                    case('matrix_multiple_choice'):
                        $student_responses = json_decode($submission->student_response);
                        $message = '';
                        foreach ($student_responses as $key => $student_response) {
                            $row_num = $key + 1;
                            if ($student_response === null) {
                                $message .= "Row $row_num of the table does not have a selected response.<br>";
                            }
                        }
                        if ($message) {
                            $response['message'] = $message;
                            $response['type'] = 'error';
                            $this->_returnJsonAndExit($response);
                        }

                        $score = 0;
                        $num_rows = count($submission->question->rows);
                        foreach ($submission->question->rows as $key => $row) {
                            $score += +($student_responses[$key] === $row->correctResponse);
                        }
                        $proportion_correct = $score / $num_rows;
                        break;
                    case('drop_down_table'):
                        $student_responses = json_decode($submission->student_response);
                        $number_to_select = count($submission->question->rows);
                        if (count($student_responses) !== $number_to_select) {
                            $response['message'] = "Please make a selection from each row before submitting.";
                            $response['type'] = 'error';
                            $this->_returnJsonAndExit($response);
                        }

                        $score = 0;
                        $num_rows = count($submission->question->rows);
                        foreach ($submission->question->rows as $row) {
                            foreach ($row->responses as $response) {
                                if ($response->correctResponse && in_array($response->identifier, $student_responses)) {
                                    $score++;
                                }
                            }
                        }
                        $proportion_correct = $score / $num_rows;
                        break;
                    case('highlight_table'):
                    case('multiple_response_grouping'):
                        $student_responses = json_decode($submission->student_response);
                        $score = 0;
                        $num_correct_answers = 0;
                        foreach ($submission->question->rows as $row) {
                            foreach ($row->responses as $response) {
                                $num_correct_answers += +$response->correctResponse;
                                $score = $score + $this->computeScoreFromPlusMinusScoring($response, $student_responses);
                            }
                        }

                        $proportion_correct = Max($score, 0) / $num_correct_answers;
                        break;
                    case('multiple_response_select_n'):
                    case('multiple_response_select_all_that_apply'):
                    {
                        $penalty = 0;
                        $student_responses = json_decode($submission->student_response);
                        if ($question_type === 'multiple_response_select_all_that_apply') {
                            if (!count($student_responses)) {
                                $response['message'] = "Please select at least one of the responses.";
                                $response['type'] = 'error';
                                $this->_returnJsonAndExit($response);
                            }
                            $penalty = -1;
                        }
                        if ($question_type === 'multiple_response_select_n') {
                            $number_to_select = +$submission->question->numberToSelect;
                            if (count($student_responses) !== $number_to_select) {
                                $response['message'] = "Please check $number_to_select boxes before submitting.";
                                $response['type'] = 'error';
                                $this->_returnJsonAndExit($response);
                            }
                        }
                        $correct_responses = [];
                        foreach ($submission->question->responses as $response) {
                            if ($response->correctResponse) {
                                $correct_responses[] = $response->identifier;
                            }
                        }
                        $score = 0;
                        foreach ($student_responses as $response) {
                            $change = in_array($response, $correct_responses) ? 1 : $penalty;
                            $score += $change;
                        }
                        $score = max($score, 0);
                        $proportion_correct = $score / count($correct_responses);
                        break;
                    }
                    case('bow_tie'):
                    {
                        $student_response = json_decode($submission->student_response);
                        $num_correct = 0;
                        foreach (['actionsToTake', 'potentialConditions', 'parametersToMonitor'] as $group) {
                            foreach ($submission->question->{$group} as $item) {
                                if ($item->correctResponse && in_array($item->identifier, $student_response->{$group})) {
                                    $num_correct++;
                                }
                            }
                        }
                        $proportion_correct = $num_correct / 5;
                        break;
                    }
                    case('numerical'): {
                        $cr            = $submission->question->correctResponse;
                        $toleranceType = $cr->toleranceType ?? 'absolute';
                        $correct       = (float) $cr->value;
                        $diff          = round(abs((float) $submission->student_response - $correct), 10);

                        if ($toleranceType === 'relative') {
                            $pct              = (float) ($cr->relativeTolerance ?? 0);
                            $proportion_correct = (int) ($diff <= round(abs($correct) * $pct / 100, 10));
                        } else {
                            $margin_of_error  = round((float) $cr->marginOfError, 10);
                            $proportion_correct = (int) ($diff <= $margin_of_error);
                        }
                        break;
                    }
                    case('multi_numerical'): {
                        $placeholders = isset($submission->question->placeholders)
                            ? (array) $submission->question->placeholders
                            : [];
                        $total   = count($placeholders);
                        $correct = 0;

                        // Handle legacy bare scalar submission
                        $decoded = json_decode($submission->student_response, true);
                        if (!isset($decoded['answers'])) {
                            $cr   = $submission->question->correctResponse;
                            $diff = round(abs((float) $submission->student_response - (float) $cr->value), 10);
                            $proportion_correct = (int) ($diff <= round((float) $cr->marginOfError, 10));
                            break;
                        }

                        foreach ($placeholders as $i => $placeholder) {
                            $placeholder  = (array) $placeholder;
                            $studentValue = $decoded['answers'][$i]['response'] ?? '';
                            if (!is_numeric($studentValue) || !is_numeric($placeholder['value'])) {
                                continue;
                            }
                            $diff          = round(abs((float) $studentValue - (float) $placeholder['value']), 10);
                            $toleranceType = $placeholder['toleranceType'] ?? 'absolute';
                            if ($toleranceType === 'relative') {
                                $pct             = (float) ($placeholder['relativeTolerance'] ?? 0);
                                $withinTolerance = $diff <= round(abs((float) $placeholder['value']) * $pct / 100, 10);
                            } else {
                                $withinTolerance = $diff <= round((float) ($placeholder['absoluteTolerance'] ?? 0), 10);
                            }
                            if ($withinTolerance) {
                                $correct++;
                            }
                        }
                        $proportion_correct = $total > 0 ? $correct / $total : 0;
                        break;
                    }
                    case('matching'):
                        $student_response = json_decode($submission->student_response);
                        $student_response_by_term_identifier = [];
                        $chosen_match_identifiers = [];
                        foreach ($student_response as $value) {
                            $student_response_by_term_identifier[$value->identifier] = $value->chosenMatchIdentifier;
                        }
                        $terms_to_match = $submission->question->termsToMatch;
                        $num_matches = count($terms_to_match);
                        $num_correct = 0;
                        foreach ($terms_to_match as $term_to_match) {
                            if (isset($student_response_by_term_identifier[$term_to_match->identifier])) {
                                if ($student_response_by_term_identifier[$term_to_match->identifier] === $term_to_match->matchingTermIdentifier) {
                                    $num_correct++;
                                }
                            } else {
                                $response['message'] = "Please choose a matching term for all terms to match.";
                                if (app()->environment('testing')) {
                                    throw new Exception ($response['message']);
                                }
                                $this->_returnJsonAndExit($response);
                            }
                        }

                        foreach ($student_response as $value) {
                            if (in_array($value->chosenMatchIdentifier, $chosen_match_identifiers)) {
                                $response['message'] = "Each matching term should be chosen only once.";
                                if (app()->environment('testing')) {
                                    throw new Exception ($response['message']);
                                }
                                $this->_returnJsonAndExit($response);
                            }
                            $chosen_match_identifiers[] = $value->chosenMatchIdentifier;
                        }

                        $proportion_correct = $num_correct / $num_matches;
                        break;
                    case('multiple_choice'):
                    case('true_false'):
                        $simpleChoices = $submission->question->simpleChoice;
                        if (!$submission->student_response) {
                            $response['message'] = "Please make a selection before submitting.";
                            $this->_returnJsonAndExit($response);
                        }
                        $proportion_correct = floatval(0);
                        foreach ($simpleChoices as $choice) {
                            if ($submission->student_response === $choice->identifier
                                && property_exists($choice, 'correctResponse')
                                && $choice->correctResponse) {
                                $proportion_correct = floatval(1);
                            }
                        }

                        break;
                    case('matrix_multiple_response'):
                        $student_response = json_decode($submission->student_response);
                        $col_headers = $submission->question->colHeaders;
                        array_shift($col_headers); //first column isn't for the checkboxes
                        $rows = $submission->question->rows;
                        $unsubmitted_cols = [];
                        foreach ($col_headers as $col_key => $col) {
                            $submitted_at_least_one = false;
                            foreach ($rows as $row) {
                                $row_response_by_col = $row->responses[$col_key];
                                if (in_array($row_response_by_col->identifier, $student_response)) {
                                    $submitted_at_least_one = true;
                                }
                            }
                            if (!$submitted_at_least_one) {
                                $unsubmitted_cols[] = $col;
                            }
                        }

                        if ($unsubmitted_cols) {
                            $cols_to_fix = implode(', ', $unsubmitted_cols);

                            $plural = count($unsubmitted_cols) > 1 ? 's' : '';
                            $response['message'] = "You have not made any selections for the following column$plural: $cols_to_fix.";
                            $this->_returnJsonAndExit($response);
                        }
                        $total_responses = count($rows) * count($col_headers);
                        $num_correct = 0;
                        foreach ($rows as $row) {
                            foreach ($row->responses as $response) {
                                $correct = ($response->correctResponse && in_array($response->identifier, $student_response))
                                    || (!$response->correctResponse && !in_array($response->identifier, $student_response));
                                $num_correct += $correct ? 1 : -1;
                            }

                        }
                        $proportion_correct = max(floatval($num_correct / $total_responses), 0);
                        break;
                    case
                    ('multiple_answers'):
                        $student_response = json_decode($submission->student_response);
                        if (!$student_response) {
                            $response['message'] = "Please make at least one selection before submitting.";
                            $response['type'] = 'error';
                            $this->_returnJsonAndExit($response);

                        }
                        $simpleChoices = $submission->question->simpleChoice;
                        $num_answers = count($simpleChoices);
                        $num_correct = 0;
                        foreach ($simpleChoices as $choice) {
                            $correct = ($choice->correctResponse && in_array($choice->identifier, $student_response))
                                || (!$choice->correctResponse && !in_array($choice->identifier, $student_response));
                            $num_correct += (int)$correct;
                        }
                        $proportion_correct = floatval($num_correct / $num_answers);
                        break;
                    case('drop_down_rationale_triad'):
                        $student_responses = json_decode($submission->student_response);
                        $responses_by_type['condition'] = $responses_by_type['rationales'] = [];
                        foreach ($student_responses as $response) {
                            $key = $response->identifier === 'condition' ? 'condition' : 'rationales';
                            $responses_by_type[$key][] = $response->value;
                        }
                        if ($responses_by_type['rationales'][0] === $responses_by_type['rationales'][1]) {
                            $result['message'] = "You have chosen the same rationale twice.";
                            $result['type'] = 'error';
                            if (app()->environment() === 'testing') {
                                throw new Exception($result['message']);
                            }
                            $this->_returnJsonAndExit($result);
                        }
                        $num_points = 0;
                        $cause_is_correct = false;
                        foreach ($submission->question->inline_choice_interactions->condition as $condition) {
                            if ($condition->correctResponse && in_array($condition->value, $responses_by_type['condition'])) {
                                $cause_is_correct = true;
                            }
                        }

                        if ($cause_is_correct) {
                            $num_points = 1;
                            $num_correct_rationales = 0;
                            foreach ($submission->question->inline_choice_interactions->rationales as $rationale) {
                                if ($rationale->correctResponse && in_array($rationale->value, $responses_by_type['rationales'])) {
                                    $num_correct_rationales++;
                                }
                            }
                            if ($num_correct_rationales === 2) {
                                $num_points = 2;
                            }
                        }
                        $proportion_correct = $num_points / 2;
                        break;

                    case('drop_down_rationale'):
                    case('select_choice'):
                        preg_match_all('/\[(.*?)\]/', $submission->question->itemBody, $matches);
                        $identifiers = $matches[1];
                        $student_responses = json_decode($submission->student_response);
                        $num_identifiers = count($identifiers);
                        $num_correct = 0;
                        foreach ($identifiers as $key => $identifier) {
                            $student_response = $student_responses[$key]->value;
                            $identifier_choices = $submission->question->inline_choice_interactions->{$identifier};
                            foreach ($identifier_choices as $choice) {
                                if ($choice->value === $student_response && $choice->correctResponse) {
                                    $num_correct++;
                                }
                            }
                        }
                        if ($question_type === 'select_choice') {
                            $proportion_correct = floatval($num_correct / $num_identifiers);
                        }

                        if ($question_type === 'drop_down_rationale') {
                            switch ($submission->question->dropDownRationaleType) {
                                case('dyad'):
                                    $proportion_correct = $num_correct === 2 ? 1 : 0;
                                    break;
                                default;
                                    throw new Exception("$submission->question->dropDownRationaleType is not a valid drop down rationale type.");
                            }
                        }
                        break;
                    case('fill_in_the_blank'):
                        $correct_responses = $submission->question->responseDeclaration->correctResponse;
                        $student_responses = json_decode($submission->student_response);
                        $num_fill_in_the_blanks = count($correct_responses);
                        $num_correct = 0;
                        foreach ($correct_responses as $key => $correct_response) {
                            if ($this->correctFillInTheBlank($correct_response, $student_responses[$key]->value))
                                $num_correct++;
                        }
                        $proportion_correct = floatval($num_correct / $num_fill_in_the_blanks);

                        break;
                    case('flashcard'):
                        $proportion_correct = $submission->student_response === 'correct' ? 1.0 : 0.0;
                        break;
                    default:
                        throw new Exception("$question_type is not yet available for scoring.");

                }
                break;
            default:
                $proportion_correct = 0;
        }

        return $proportion_correct;
    }

    /**
     * @throws Exception
     */
    public
    function correctFillInTheBlank(object $correct_response, string $student_response): bool
    {
        $student_response = trim($student_response);
        $student_response = str_replace('ë', '&euml;', $student_response);//had some encoding issues
        //Log::info($correct_response->value);

        $correct_values = explode('|', $correct_response->value);
        //Log::info(print_r($correct_values, 1));

        $correct = false;
        foreach ($correct_values as $correct_value) {
            $correct_value = trim($correct_value);
            $correct_value = str_replace("\xC2\xA0", ' ', $correct_value);//removed the nbsp; which may appear in the question if they copied from Google
            $correct_value = str_replace('ë', '&euml;', $correct_value);//had some encoding issues

            if (!$correct) {
                switch ($correct_response->matchingType) {
                    case('exact'):
                        $correct = $correct_response->caseSensitive === 'yes'
                            ? $correct_value === $student_response
                            : strtolower($correct_value) === strtolower($student_response);
                        break;
                    case('substring'):
                        $correct = $correct_response->caseSensitive === 'yes'
                            ? strpos($correct_value, $student_response) !== false
                            : stripos($correct_value, $student_response) !== false;
                        break;
                    default:
                        throw new Exception("$correct_response->matching_type is not a valid matching type.");
                }
            }
        }
        return $correct;

    }

    /**
     * @param StoreSubmission $request
     * @param Submission $submission
     * @param Assignment $Assignment
     * @param Score $score
     * @param DataShop $dataShop
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function store(StoreSubmission        $request,
                   Submission             $submission,
                   Assignment             $Assignment,
                   Score                  $score,
                   DataShop               $dataShop,
                   AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        $response['type'] = 'error';//using an alert instead of a noty because it wasn't working with post message
        // $data = $request->validated();//TODO: validate here!!!!!
        // $data = $request->all(); ///maybe request->all() flag in the model or let it equal request???
        // Log::info(print_r($request->all(), true));


        $data = $request;

        $data['user_id'] = Auth::user()->id;
        $assignment = $Assignment->find($data['assignment_id']);


        if ($assignment->course->anonymous_users && (Helper::isAnonymousUser() || Helper::hasAnonymousUserSession())) {
            $response['type'] = 'success';
            return $response;
        }

        $assignment_question = DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $data['question_id'])
            ->select('id',
                'points',
                'question_id',
                'assignment_id',
                'completion_scoring_mode',
                'open_ended_submission_type')
            ->first();

        $gave_up = DB::table('can_give_ups')
            ->where('question_id', $data['question_id'])
            ->where('assignment_id', $assignment->id)
            ->where('user_id',$data['user_id'])
            ->where('status', 'gave up')
            ->first();
        if ($gave_up){
            $response['message'] = 'The solution is already available so you cannot resubmit.';
            return $response;
        }






        if (!$assignment_question) {
            $response['message'] = 'That question is not part of the assignment.';
            return $response;
        }


        $authorized = Gate::inspect('store', [$submission, $assignment, $assignment->id, $data['question_id']]);

        $questionLevelOverride = new QuestionLevelOverride();
        $assignmentLevelOverride = new AssignmentLevelOverride();
        $has_question_level_override = $questionLevelOverride->hasAutoGradedOverride($assignment->id, $data['question_id'], $assignmentLevelOverride);
        if (!$authorized->allowed()) {

            if (!$has_question_level_override) {
                $response['message'] = $authorized->message();
                return $response;
            }
        }
        switch ($data['technology']) {
            case('h5p'):
                $submission = json_decode($data['submission']);
                //hotspots don't have anything
                $no_submission = isset($submission->result->response) && str_replace('[,]', '', $submission->result->response) === '';
                if ($no_submission) {
                    $response['type'] = 'info';
                    $response['message'] = $response['not_updated_message'] = "It looks like you submitted a blank response.  Please make a selection before submitting.";
                    return $response;
                }
                if ($data['is_h5p_activity_set']) {
                    $url_components = parse_url($submission->object->id);
                    parse_str($url_components['query'], $params);
                    if (isset($params['subContentId'])) {
                        return $this->processh5pActivitySet($assignment_question, $submission, $data, $assignment, $score, $assignmentSyncQuestion, $dataShop, $params['subContentId']);
                    }
                }

                $proportion_correct = $this->getProportionCorrect('h5p', $submission);
                $data['score'] = $assignment->scoring_type === 'p'
                    ? floatval($assignment_question->points) * $proportion_correct
                    : $this->computeScoreForCompletion($assignment_question);
                break;
            case('imathas'):
                $submission = $data['submission'];
                $proportion_correct = $this->getProportionCorrect('imathas', $submission);
                $data['score'] = $assignment->scoring_type === 'p'
                    ? floatval($assignment_question->points) * $proportion_correct
                    : $this->computeScoreForCompletion($assignment_question);
                $data['submission'] = json_encode($data['submission'], JSON_UNESCAPED_SLASHES);
                break;
            case('webwork'):
                // Log::info('case webwork');
                $submission = $data['submission'];
                //json_encode($submission, )
                #Log::info('Submission:' . json_encode($submission));
                #Log::info('Submission Score:' . json_encode($submission->score));
                //$submission_score = json_decode(json_encode($submission->score));
                $proportion_correct = $this->getProportionCorrect('webwork', (object)$submission);//
                //Log::info( $submission_score->result);
                $data['score'] = $assignment->scoring_type === 'p'
                    ? floatval($assignment_question->points) * $proportion_correct
                    : $this->computeScoreForCompletion($assignment_question);
                $data['submission'] = json_encode($data['submission']);
                break;
            case('qti'):
                $question = DB::table('questions')
                    ->where('id', $data['question_id'])->first();
                if (!$question) {
                    $response['message'] = "{$data['question_id']} does not exist in the database.";
                    return $response;
                }
                $submission = new stdClass();
                $submission->question = json_decode($question->qti_json);
                $submission->student_response = $data['submission'];
                if (property_exists($submission->question, 'questionType')
                    && $submission->question->questionType === 'marker'
                    && $submission->question->oneHundredPercentOverride) {
                    if ($this->_markedAll($submission)) {
                        $response['message'] = "We are unable to score the question since it looks like you just marked every item in the structure.";
                        return $response;
                    }
                }

                $proportion_correct = $this->getProportionCorrect('qti', $submission);
                $submission->proportion_correct = $proportion_correct;
                $data['score'] = $assignment->scoring_type === 'p'
                    ? floatval($assignment_question->points) * $proportion_correct
                    : $this->computeScoreForCompletion($assignment_question);
                $data['submission'] = json_encode($submission);
                break;
            default:
                throw new Exception("{$data['technology']} is not a valid technology.");
        }


        $data['all_correct'] = $data['score'] >= floatval($assignment_question->points);//>= so I don't worry about decimals

        try {
            //do the extension stuff also
            $submission = Submission::where('user_id', $data['user_id'])
                ->where('assignment_id', $data['assignment_id'])
                ->where('question_id', $data['question_id'])
                ->first();

            $learning_tree = DB::table('assignment_question')
                ->join('assignment_question_learning_tree', 'assignment_question.id', '=', 'assignment_question_learning_tree.assignment_question_id')
                ->join('learning_trees', 'assignment_question_learning_tree.learning_tree_id', '=', 'learning_trees.id')
                ->where('assignment_id', $data['assignment_id'])
                ->where('question_id', $data['question_id'])
                ->select('assignment_question_learning_tree.learning_tree')
                ->get();
            $message = 'Auto-graded submission saved.';
            $submitted_work = false;
            if ($assignment->can_submit_work) {
                $submitted_work = DB::table('submitted_works')
                    ->where('assignment_id', $data['assignment_id'])
                    ->where('question_id', $data['question_id'])
                    ->where('user_id', $data['user_id'])
                    ->exists();
                switch ($assignment->submitted_work_policy) {
                    case('optional'):
                        $message .= '  You may optionally "Submit Work" that can be reviewed by your instructor.';
                        break;
                    case('required with auto-approval'):
                        if (!$submitted_work) {
                            $message .= '  In order for any points to be awarded, please also be sure to "Submit Work".';
                        }
                        break;
                    case('required with manual approval'):
                        if (!$submitted_work) {
                            $message .= '  Please also "Submit Work" so that your instructor may review your submission and associated work.';
                        } else {
                            $message .= '  Your instructor will review your submission and associated work.';
                        }
                        break;

                }
            }
            $hint_penalty = in_array($assignment->assessment_type, ['real time', 'learning tree'])
                ? $this->getHintPenalty($data['user_id'], $assignment, $data['question_id'])
                : 0;

            if ($assignment->assessment_type === 'learning tree') {
                $assignment_question_learning_tree = DB::table('assignment_question')
                    ->join('assignment_question_learning_tree', 'assignment_question.id', '=', 'assignment_question_learning_tree.assignment_question_id')
                    ->where('assignment_id', $data['assignment_id'])
                    ->where('question_id', $data['question_id'])
                    ->select('number_of_successful_paths_for_a_reset', 'learning_tree_id')
                    ->first();
                $learningTree = LearningTree::find($assignment_question_learning_tree->learning_tree_id);
            }
            if ($submission) {
                if (in_array($assignment->assessment_type, ['real time', 'learning tree'])) {
                    $too_many_submissions = $this->tooManySubmissions($assignment, $submission);
                    if ($too_many_submissions) {
                        $plural = $assignment->number_of_allowed_attempts === '1' ? '' : 's';
                        $message = "You are only allowed $assignment->number_of_allowed_attempts attempt$plural.  ";
                        $response['message'] = $message;
                        if ($assignment->assessment_type === 'learning tree') {
                            $response['learning_tree_message'] = true;
                            $response['message'] = $message;
                            $response['type'] = 'info';
                        }
                        return $response;
                    }
                    $num_deductions_to_apply = $submission->submission_count;
                    $proportion_of_score_received = 1 - (($num_deductions_to_apply * $assignment->number_of_allowed_attempts_penalty + $hint_penalty) / 100);
                    // Log::info($submission->score . ' ' . $data['score'] . ' ' . $num_deductions_to_apply . ' ' . $assignment->number_of_allowed_attempts_penalty . ' ' . $hint_penalty . ' ' . $proportion_of_score_received);

                    if (request()->user()->role === 3) {
                        $data['score'] = max($data['score'] * $proportion_of_score_received, 0);
                        $applied_penalty = (1 - $proportion_of_score_received) * 100;
                        $rounded_score = Round($data['score'], 4);
                        $difference = Round($submission->score - $rounded_score, 4);
                        if ($data['score'] < $submission->score) {
                            $response['type'] = 'error';
                            $lower_score_message = "Your current score on this problem is $submission->score points.<br><br>";
                            $lower_score_message .= "This new submission would give you a score of $rounded_score points (including a penalty of $applied_penalty%).<br><br>";
                            $lower_score_message .= "If accepted, this submission would reduce your question score by $difference points and is therefore, not accepted.";
                            $response['message'] = $proportion_of_score_received < 1
                                ? $lower_score_message
                                : "This attempt would give you less points than you currently have so it will not be accepted.";
                            return $response;
                        }
                    }
                }
                if (request()->user()->role === 3) {
                    if ($this->latePenaltyPercent($assignment, Carbon::now('UTC'))) {
                        $score_with_late_penalty = $this->applyLatePenalyToScore($assignment, $data['score']);
                        if ($score_with_late_penalty < $submission->score) {
                            $response['type'] = 'error';
                            $response['message'] = "With the late deduction, submitting will give you a lower score on this question than you currently have so the submission will not be accepted.";
                            return $response;
                        }
                    }
                }
                DB::beginTransaction();
                $submission->submission = $data['submission'];
                $submission->answered_correctly_at_least_once = $data['all_correct'];
                $submission->score = request()->user()->role === 3 ? $this->applyLatePenalyToScore($assignment, $data['score']) : $data['score'];
                $submission->submission_count = $submission->submission_count + 1;
                $submission->save();
            } else {
                $proportion_of_score_received = 1 - ($hint_penalty / 100);
                $data['score'] = $data['score'] * $proportion_of_score_received;
                if ($assignment->assessment_type === 'learning tree') {
                    $assignment_question_learning_tree = DB::table('assignment_question')
                        ->join('assignment_question_learning_tree', 'assignment_question.id', '=', 'assignment_question_learning_tree.assignment_question_id')
                        ->where('assignment_id', $data['assignment_id'])
                        ->where('question_id', $data['question_id'])
                        ->select('number_of_successful_paths_for_a_reset', 'learning_tree_id')
                        ->first();

                    $number_of_successful_paths_for_a_reset = $assignment_question_learning_tree->number_of_successful_paths_for_a_reset;
                    $learningTree = LearningTree::find($assignment_question_learning_tree->learning_tree_id);
                    if (!$data['all_correct']) {
                        $number_of_learning_tree_paths = count($learningTree->finalQuestionIds());

                        $learningTreeReset = LearningTreeReset::where('user_id', $data['user_id'])
                            ->where('assignment_id', $assignment->id)
                            ->where('learning_tree_id', $assignment_question_learning_tree->learning_tree_id)
                            ->first();
                        $number_resets_available = $learningTreeReset ? $learningTreeReset->number_resets_available : 0;
                        $resets_available = $number_of_learning_tree_paths - $number_resets_available >= $number_of_successful_paths_for_a_reset;


                        $plural = $number_of_successful_paths_for_a_reset > 1 ? 's' : '';
                        $message = "<p>Unfortunately, you did not answer this question correctly.  ";
                        if ($resets_available) {
                            $message .= "Explore the Learning Tree and complete <strong>$number_of_successful_paths_for_a_reset path$plural</strong> for a reset.</p>";
                            $message .= "<p>Here's a general example of what a completed path through a Learning Tree looks like. Your tree may have a different number of nodes or a different layout, but the idea is the same: follow a connected line of boxes from the top all the way down to a final assessment.</p>";
                            $message .= "<hr>";
                            $path_examples = [
                                1 => 'Slide1.png',
                                2 => 'Slide2.png',
                                3 => 'Slide3.png',
                                4 => 'Slide4.png'
                            ];
                            $path_count_to_show = min($number_of_successful_paths_for_a_reset, 4);
                            $path_example_image_src = asset("assets/img/learning_trees/{$path_examples[$path_count_to_show]}");
                            $message .= "<p style=\"text-align:center\"><img alt=\"Example of $path_count_to_show completed path$plural highlighted from the root of a Learning Tree down to $path_count_to_show final assessment node$plural\" style=\"max-width:100%;width:500px\" src=\"$path_example_image_src\"></p>";
                            if ($number_of_successful_paths_for_a_reset > 4) {
                                $extra_paths = $number_of_successful_paths_for_a_reset - 4;
                                $extra_plural = $extra_paths > 1 ? 's' : '';
                                $message .= "<p>Your tree requires <strong>$number_of_successful_paths_for_a_reset paths</strong> total for a reset. "
                                    . "The example above shows 4 completed paths, but the same idea continues: keep following connected lines of boxes from the top all the way down to a final assessment for $extra_paths more path$extra_plural.</p>";
                            }
                        } else {
                            $message .= "You can explore the tree but there are not enough paths remaining for you to earn a reset of your original submission.</p>";
                        }

                    }
                }
                DB::beginTransaction();
                $submission = Submission::create(['user_id' => $data['user_id'],
                    'assignment_id' => $data['assignment_id'],
                    'question_id' => $data['question_id'],
                    'submission' => $data['submission'],
                    'score' => $this->applyLatePenalyToScore($assignment, $data['score']),
                    'answered_correctly_at_least_once' => $data['all_correct'],
                    'submission_count' => 1]);
            }
            //update the score if it's supposed to be updated
            if ($assignment->assessment_type === 'learning tree'
                && (!request()->user()->fake_student || app()->environment() === 'local')) {
                $assignment_course_info = $assignment->assignmentCourseInfo();
                $learning_tree_analytics_data = [
                    'course_name' => $assignment_course_info->course_name,
                    'assignment_name' => $assignment_course_info->assignment_name,
                    'instructor' => $assignment_course_info->instructor,
                    'user_id' => request()->user()->id,
                    'learning_tree_id' => $learningTree->id,
                    'assignment_id' => $data['assignment_id'],
                    'question_id' => $data['question_id'],
                    'root_node' => 1,
                    'action' => 'submit',
                    'response' => $data['all_correct'] ? 'success' : 'failure'
                ];
                LearningTreeAnalytics::create($learning_tree_analytics_data);
            }
            if ($assignment->can_submit_work && $assignment->submitted_work_policy !== 'optional') {
                $submitted_work = DB::table('submitted_works')
                    ->where('assignment_id', $data['assignment_id'])
                    ->where('question_id', $data['question_id'])
                    ->where('user_id', $data['user_id'])
                    ->exists();
                $submittedWorkPendingScore = new SubmittedWorkPendingScore();
                $score_to_move_to_pending = request()->user()->role === 3 ? $this->applyLatePenalyToScore($assignment, $data['score']) : $data['score'];
                switch ($assignment->submitted_work_policy) {
                    case('required with manual approval'):
                        $this->moveScoreToPending($submittedWorkPendingScore, $submission, $data, $score_to_move_to_pending);
                        break;
                    case('required with auto-approval'):
                        if (!$submitted_work) {
                            $this->moveScoreToPending($submittedWorkPendingScore, $submission, $data, $score_to_move_to_pending);
                        } else {
                            $submittedWorkPendingScore->where('user_id', $data['user_id'])
                                ->where('assignment_id', $data['assignment_id'])
                                ->where('question_id', $data['question_id'])
                                ->delete();
                        }
                }
            }

            $score->updateAssignmentScore($data['user_id'], $assignment->id, $assignment->lms_grade_passback === 'automatic');
            try {
                $assignment_course_info = $assignment->assignmentCourseInfo();
                if ($assignment_course_info->instructor === 'Brian Lindshield') {
                    $assignment_score = Score::where('user_id', request()->user()->id)
                        ->where('assignment_id', $data['assignment_id'])
                        ->first();
                    $submission_score = Submission::where('assignment_id', $data['assignment_id'])
                        ->select(DB::raw('SUM(score) as total_score'))
                        ->where('user_id', request()->user()->id)
                        ->first();
                    if ((int)$assignment_score->score !== (int)$submission_score->total_score) {
                        Telegram::sendMessage([
                            'chat_id' => config('myconfig.telegram_channel_id'),
                            'parse_mode' => 'HTML',
                            'text' => "Scores don't match up for User " . request()->user()->id . " on assignment " . $data['assignment_id'] . " for question " . $data['question_id']
                        ]);
                    }
                }
            } catch (Exception $e) {
                $h = new Handler(app());
                $h->report($e);

            }
            $response['completed_all_assignment_questions'] = $assignmentSyncQuestion->completedAllAssignmentQuestions($assignment);
            UnconfirmedSubmission::where('user_id', $data['user_id'])
                ->where('assignment_id', $data['assignment_id'])
                ->where('question_id', $data['question_id'])
                ->delete();
            $score_not_updated = ($learning_tree->isNotEmpty() && !$data['all_correct']);
            if (\App::runningUnitTests()) {
                $response['submission_id'] = $submission->id;
            }
            $response['type'] = $score_not_updated ? 'info' : 'success';
            $response['completed_all_assignment_questions'] = $assignmentSyncQuestion->completedAllAssignmentQuestions($assignment);
            $response['message'] = $message;
            $response['learning_tree_message'] = !$data['all_correct'];
            if ($assignment->assessment_type === 'flashcard') {
                $response['score'] = Helper::removeZerosAfterDecimal($submission->score);
                $response['question_id'] = $question->id;
                $response['last_submitted'] = now();
            }
            //don't really care if this gets messed up from the user perspective
            if (User::find($data['user_id'])->role === 3) {
                try {
                    session()->put('submission_id', md5(uniqid('', true)));
                    $data['submission'] = $submission;
                    $dataShop->store('submission', $data, $assignment, $assignment_question);
                } catch (Exception $e) {
                    $h = new Handler(app());
                    $h->report($e);

                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error saving your response.  Please try again or contact us for assistance.";
        }

        return $response;

    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public
    function applyLatePenalyToScore($assignment, $score, $user_id = null, $updated_at = null)
    {
        if (!$updated_at) {
            $updated_at = Carbon::now('UTC');
        }
        $late_penalty_percent = $this->latePenaltyPercent($assignment, $updated_at, $user_id);
        return Round($score * (100 - $late_penalty_percent) / 100, 4);
    }

    private
    function _computeLatePercent(Assignment $assignment, Carbon $due, Carbon $submitted_at)
    {
        $late_deduction_application_period = $assignment->late_deduction_application_period;
        $late_deduction_percent = 0;
        if ($late_deduction_application_period !== 'once') {
            $late_deduction_percent = $assignment->late_deduction_percent;
            $max_num_iterations = (int)floor(100 / $late_deduction_percent);
            for ($num_late_periods = 0; $num_late_periods < $max_num_iterations; $num_late_periods++) {
                if ($due > $submitted_at) {
                    break;
                }
                $due->add($late_deduction_application_period);
            }
            $late_deduction_percent = $late_deduction_percent * $num_late_periods;
        }
        if ($late_deduction_application_period === 'once' && $submitted_at > $due) {
            $late_deduction_percent = $assignment->late_deduction_percent;
        }
        return $late_deduction_percent;
    }

    /**
     * @param int $user_id
     * @param Assignment $assignment
     * @param Carbon $submitted_at
     * @return float|int
     */
    public
    function latePenaltyPercentGivenUserId(int $user_id, Assignment $assignment, Carbon $submitted_at)
    {
        //helper function to specifically get the
        $late_deduction_percent = 0;
        if ($assignment->late_policy === 'deduction') {
            $due = Carbon::parse($assignment->assignToTimingDueDateGivenUserId($user_id));
            $late_deduction_percent = $this->_computeLatePercent($assignment, $due, $submitted_at);
        }

        return $late_deduction_percent;
    }

    /**
     * @param Assignment $assignment
     * @param Carbon $now
     * @param null $user_id
     * @return float|int|mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public
    function latePenaltyPercent(Assignment $assignment, Carbon $now, $user_id = null)
    {
        if (session()->get('instructor_user_id')) {
            //logged in as student
            return 0;
        }
        $late_deduction_percent = 0;
        if ($assignment->late_policy === 'deduction') {
            $due = Carbon::parse($assignment->assignToTimingByUser('due', $user_id));
            return $this->_computeLatePercent($assignment, $due, $now);
        }

        return $late_deduction_percent;
    }

    /**
     * @param object $submission
     * @param string $technology
     * @param bool $formatted
     * @return false|mixed|string
     * @throws Exception
     */
    public
    function getStudentResponse(object $submission, string $technology, bool $formatted = false)
    {

        $submission_object = json_decode($submission->submission);
        $student_response = '';
        switch ($technology) {
            case('h5p'):
                //Log::info(json_encode($submission_object->result));
                $student_response = 'N/A';
                if (isset($submission_object->result->response)) {
                    if (isset($submission_object->object->definition)
                        && isset($submission_object->object->definition->choices)
                        && $submission_object->object->definition->interactionType === 'choice') {
                        $choices = $submission_object->object->definition->choices;
                        foreach ($choices as $choice) {
                            if ((int)$choice->id === (int)$submission_object->result->response) {
                                $student_response = $choice->description->{'en-US'};
                            }
                        }
                    } else {
                        $student_response = $submission_object->result->response;
                    }

                }

                //$correct_response = $submission_object->object->definition->correctResponsesPattern;
                break;
            case('webwork'):
                $student_response = 'N/A';
                $student_response_arr = [];

                if (isset($submission_object->platform) && $submission_object->platform === 'standaloneRenderer') {
                    $answers_arr = json_decode(json_encode($submission_object->score->answers), true);
                    //AnSwEr0003
                    foreach ($answers_arr as $answer_key => $value) {
                        $numeric_key = (int)ltrim(str_replace('AnSwEr', '', $answer_key), 0);
                        $student_response_arr[$numeric_key] = $value['original_student_ans'] ?? '';
                    }

                } else {
                    $JWE = new JWE();
                    $session_JWT = $this->getPayload($submission_object->sessionJWT, $JWE->getSecret('webwork'));
                    //session_JWT will be null for bad submissions
                    if (is_object($session_JWT) && $session_JWT->answersSubmitted) {
                        $answer_template = (array)$session_JWT->answerTemplate;
                        foreach ($answer_template as $key => $value) {
                            if (is_numeric($key) && isset($value['answer']) && isset($value['answer']['original_student_ans'])) {
                                $student_response_arr[$key] = $value['answer']['original_student_ans'];
                            }
                        }
                    }
                }
                if ($student_response_arr) {
                    ksort($student_response_arr);//order by keys
                    $student_response = implode(',', $student_response_arr);
                }
                break;
            case('imathas'):
                $tks = explode('.', $submission_object->state);
                list($headb64, $bodyb64, $cryptob64) = $tks;
                $state = json_decode(base64_decode($bodyb64));
                if (!$state) {
                    //happening with imathas that uses the equation editor; see imathas id 442929 with stu answer: CL+ NaOH-> H_2 O + Na Cl
                    $state = json_decode(base64_decode(strtr($bodyb64, '-_,', '+/=')));
                }

                $student_response = json_decode(json_encode($state->stuanswers), 1);
                if ($student_response) {
                    $student_response = array_values($student_response);
                }
                break;
            case('qti'):
                $submission = json_decode($submission->submission);
                if ($submission->question->questionType === 'submit_molecule') {
                    return $submission->student_response;
                }
                $student_response = $submission->student_response ?: '';
                if ($formatted && $student_response) {
                    $student_response = $this->formattedStudentResponse($submission->question, $student_response);

                }
                break;
        }
        return $student_response;
    }

    /**
     * @param $question
     * @param $student_response
     * @return mixed|string
     */
    public
    function formattedStudentResponse($question, $student_response)
    {

        $student_response = $question->questionType !== 'multiple_choice' ? json_decode($student_response) : $student_response;

        if (!$student_response) {
            return $student_response;
        }
        switch ($question->questionType) {
            case('multiple_choice'):
                foreach ($question->simpleChoice as $choice) {
                    if ($choice->identifier === $student_response) {
                        $formatted_student_response = $choice->value;
                        $formatted_student_response = str_replace(['<p>', '</p>'], '', $formatted_student_response);
                    }
                }
                break;
            case('matrix_multiple_response'):
                $student_responses = [];

                $col_headers = [];
                foreach ($question->colHeaders as $key => $col_header) {
                    if ($key !== 0) {
                        $col_headers[] = $col_header;
                    }
                }

                foreach ($question->rows as $row) {
                    foreach ($student_response as $response) {
                        foreach ($row->responses as $row_response_key => $row_response) {
                            if ($response === $row_response->identifier) {
                                $student_responses[$row->header][] = $col_headers[$row_response_key];
                            }
                        }
                    }
                }
                $formatted_student_response = '';
                foreach ($student_responses as $row_header => $responses) {
                    $formatted_student_response .= $row_header . ": " . implode(', ', $responses) . '<hr>';
                }

                break;
            case('select_choice'):
                $student_responses = [];
                foreach ($student_response as $response) {
                    foreach ($question->inline_choice_interactions as $inline_choice_interaction) {
                        foreach ($inline_choice_interaction as $option) {
                            if ($option->value === $response->value) {
                                $student_responses[] = $option->text;
                            }
                        }
                    }
                }
                $formatted_student_response = $student_responses ? implode(', ', $student_responses) : null;
                break;
            case('multiple_answers'):
                $student_responses = [];
                foreach ($student_response as $response) {
                    foreach ($question->simpleChoice as $choice) {
                        if ($choice->identifier === $response) {
                            $student_responses[] = trim(str_replace(['<p>', '</p>'], '', $choice->value));
                        }
                    }
                }
                $formatted_student_response = $student_responses ? implode(', ', $student_responses) : null;
                break;
            case('matching'):
                $possible_matches = json_decode(json_encode($question->possibleMatches), 1);
                $student_responses = [];
                foreach ($student_response as $response) {
                    foreach ($possible_matches as $possible_match) {
                        if ($response->chosenMatchIdentifier === $possible_match['identifier']) {
                            $student_responses[] = str_replace(['<p>', '</p>'], ['', ''], $possible_match['matchingTerm']);
                        }
                    }
                }
                $formatted_student_response = $student_responses ? implode(', ', $student_responses) : null;
                break;
            case('bow_tie'):
                $possible_responses = [];
                foreach (['actionsToTake', 'potentialConditions', 'parametersToMonitor'] as $items) {
                    foreach ($question->{$items} as $item) {
                        $possible_responses[$item->identifier] = $item->value;
                    }
                }

                $formatted_student_response = "Actions to take: ";
                foreach ($student_response->actionsToTake as $action_to_take) {
                    $formatted_student_response .= $possible_responses[$action_to_take] . ', ';
                }
                $formatted_student_response = trim($formatted_student_response, ', ');
                $formatted_student_response .= "  -- Potential conditions: ";
                foreach ($student_response->potentialConditions as $potential_condition) {
                    $formatted_student_response .= $possible_responses[$potential_condition] . ', ';
                }
                $formatted_student_response = trim($formatted_student_response, ', ');
                $formatted_student_response .= "  -- Parameters to monitor: ";
                foreach ($student_response->parametersToMonitor as $parameter_to_monitor) {
                    $formatted_student_response .= $possible_responses[$parameter_to_monitor] . ', ';
                }
                $formatted_student_response = trim($formatted_student_response, ', ');
                break;
            case('matrix_multiple_choice'):
                $formatted_student_response = [];
                $headers = $question->headers;
                foreach ($student_response as $response) {
                    $formatted_student_response[] = $headers[$response + 1]; //first column isn't an actual response
                }
                $formatted_student_response = implode(', ', $formatted_student_response);
                break;
            case('multiple_response_select_all_that_apply'):
                $formatted_student_response = [];
                $responses = [];
                foreach ($question->responses as $item) {
                    $responses[$item->identifier] = $item->value;
                }
                foreach ($student_response as $response) {
                    $formatted_student_response[] = $responses[$response];
                }
                $formatted_student_response = implode(', ', $formatted_student_response);
                break;
            case('highlight_text'):
                $formatted_student_response = [];
                $responses = [];
                foreach ($question->responses as $item) {
                    $responses[$item->identifier] = $item->text;
                }
                foreach ($student_response as $response) {
                    $formatted_student_response[] = $responses[$response];
                }
                $formatted_student_response = implode(', ', $formatted_student_response);
                break;
            case('multiple_response_grouping'):
                $formatted_student_response = [];
                foreach ($question->rows as $row) {
                    $responses = [];
                    $formatted_responses_by_grouping = [];
                    foreach ($row->responses as $item) {
                        $responses[$item->identifier] = $item->value;
                    }
                    foreach ($student_response as $response) {
                        if (isset($responses[$response])) {
                            $formatted_responses_by_grouping[] = $responses[$response];
                        }
                    }
                    $formatted_student_response[] = $row->grouping . ': ' . implode(', ', $formatted_responses_by_grouping);
                }
                $formatted_student_response = implode(' --- ', $formatted_student_response);
                break;
            case('drop_down_table'):
                $formatted_student_response = [];
                foreach ($question->rows as $row) {
                    $responses = [];
                    foreach ($row->responses as $item) {
                        $responses[$item->identifier] = $item->value;
                    }
                    foreach ($student_response as $response) {
                        if (isset($responses[$response])) {
                            $formatted_student_response[] = $row->header . ': ' . $responses[$response];
                        }
                    }
                }
                $formatted_student_response = implode(' --- ', $formatted_student_response);
                break;
            case('highlight_table'):
                $formatted_student_response = [];

                foreach ($question->rows as $row) {
                    $responses = [];
                    foreach ($row->responses as $item) {
                        $responses[$item->identifier] = $item->text;
                    }

                    foreach ($student_response as $response) {
                        if (isset($responses[$response])) {
                            if (!isset($formatted_student_response[$row->header])) {
                                $formatted_student_response[$row->header] = [];
                            }
                            $formatted_student_response[$row->header][] = $responses[$response];
                        }
                    }
                }
                $formatted_student_response_by_row = [];
                foreach ($formatted_student_response as $header => $responses) {
                    $formatted_student_response_by_row[] = $header . ': ' . implode(', ', $responses);
                }

                $formatted_student_response = implode(' --- ', $formatted_student_response_by_row);
                break;
            case('drag_and_drop_cloze'):

                $formatted_student_response = [];
                $responses = [];
                foreach ($question->correctResponses as $response) {
                    $responses[$response->identifier] = $response->value;
                }
                foreach ($question->distractors as $response) {
                    $responses[$response->identifier] = $response->value;
                }
                foreach ($student_response as $response) {
                    $formatted_student_response[] = $responses[$response];
                }
                $formatted_student_response = implode(', ', $formatted_student_response);
                break;
            default:
                $formatted_student_response = $student_response;
        }
        return $formatted_student_response;


    }

    public
    function getSubmissionDatesByAssignmentIdAndUser($assignment_id, User $user)
    {
        $last_submitted_by_user = [];
        $submissions = DB::table('submissions')
            ->where('assignment_id', $assignment_id)
            ->where('user_id', $user->id)
            ->select('updated_at', 'question_id')
            ->get();

        foreach ($submissions as $key => $value) {
            $last_submitted_by_user[$value->question_id] = $value->updated_at;
        }

        return $last_submitted_by_user;
    }

    public
    function getSubmissionsCountByAssignmentIdsAndUser(Collection $assignments, Collection $assignment_ids, User $user)
    {

        $assignment_question_submissions = [];
        $assignment_file_submissions = [];
        $assignment_questions = [];
        $results = DB::table('assignment_question')
            ->whereIn('assignment_id', $assignment_ids)
            ->select('assignment_id', 'question_id')
            ->get();
        foreach ($results as $value) {
            $assignment_questions[$value->assignment_id][] = $value->question_id;
        }

        $results = DB::table('randomized_assignment_questions')
            ->whereIn('assignment_id', $assignment_ids)
            ->where('user_id', $user->id)
            ->select('assignment_id', 'question_id')
            ->get();
        foreach ($results as $value) {
            if (isset($assignment_questions[$value->assignment_id])) {
                unset($assignment_questions[$value->assignment_id]);
            }
        }

        foreach ($results as $value) {
            $assignment_questions[$value->assignment_id][] = $value->question_id;
        }


        $results = DB::table('submissions')
            ->whereIn('assignment_id', $assignment_ids)
            ->where('user_id', $user->id)
            ->select('question_id', 'assignment_id')
            ->get();
//dd($results);
        foreach ($results as $key => $value) {
            if (!isset($assignment_question_submissions[$value->assignment_id])) {
                $assignment_question_submissions[$value->assignment_id] = [];
            }
            $assignment_question_submissions[$value->assignment_id][] = $value->question_id;

        }

        $results = DB::table('submission_files')
            ->whereIn('assignment_id', $assignment_ids)
            ->where('user_id', $user->id)
            ->whereIn('type', ['q', 'text', 'discuss_it', 'forge'])
            ->select('question_id', 'assignment_id')
            ->get();

        foreach ($results as $key => $value) {
            $assignment_file_submissions[$value->assignment_id][] = $value->question_id;
        }


        $submissions_count_by_assignment_id = [];

        foreach ($assignments as $assignment) {
            $question_submissions = [];
            $file_submissions = [];
            $total_submissions_for_assignment = 0;
            if (isset($assignment_question_submissions[$assignment->id])) {
                foreach ($assignment_question_submissions[$assignment->id] as $question_id) {
                    $question_submissions[] = $question_id;
                }
            }
            if (isset($assignment_file_submissions[$assignment->id])) {
                foreach ($assignment_file_submissions[$assignment->id] as $question_id) {
                    $file_submissions[] = $question_id;
                }
            }
            if (isset($assignment_questions[$assignment->id])) {

                foreach ($assignment_questions[$assignment->id] as $question_id) {

                    if (in_array($question_id, $question_submissions) || in_array($question_id, $file_submissions)) {

                        $total_submissions_for_assignment++;
                    }
                }
            }

            $submissions_count_by_assignment_id[$assignment->id] = $total_submissions_for_assignment;
        }

        return $submissions_count_by_assignment_id;
    }


    public
    function getNumberOfUserSubmissionsByCourse($course, $user)
    {
        $AssignmentSyncQuestion = new AssignmentSyncQuestion();
        $num_sumbissions_per_assignment = [];
        $assignment_source = [];
        $assignment_ids = collect([]);
        $assignments = $course->assignments;
        if ($assignments->isNotEmpty()) {

            foreach ($course->assignments as $assignment) {
                $assignment_ids[] = $assignment->id;
                $assignment_source[$assignment->id] = $assignment->source;

            }

            $questions_count_by_assignment_id = $AssignmentSyncQuestion->getQuestionCountByAssignmentIds($assignments);

            $submissions_count_by_assignment_id = $this->getSubmissionsCountByAssignmentIdsAndUser($course->assignments, $assignment_ids, $user);

            //set to 0 if there are no questions

            foreach ($assignment_ids as $assignment_id) {
                $num_questions = $questions_count_by_assignment_id[$assignment_id] ?? 0;
                $num_submissions = $submissions_count_by_assignment_id[$assignment_id] ?? 0;
                switch ($assignment_source[$assignment_id]) {
                    case('a'):
                        $num_sumbissions_per_assignment[$assignment_id] = ($num_questions === 0) ? "No questions" : "$num_submissions/$num_questions";
                        break;
                    case('x'):
                        $num_sumbissions_per_assignment[$assignment_id] = 'N/A';
                }
            }

        }
        return $num_sumbissions_per_assignment;


    }

    public
    function computeScoreForCompletion($assignment_question)
    {
        $completion_scoring_factor = 1;
        if (in_array($assignment_question->open_ended_submission_type, ['file', 'audio', 'text'])) {
            if ($assignment_question->completion_scoring_mode === '100% for either') {
                $open_ended_submission_exists = DB::table('submission_files')->where('user_id', Auth::user()->id)
                    ->where('assignment_id', $assignment_question->assignment_id)
                    ->where('question_id', $assignment_question->question_id)
                    ->first();
                if ($open_ended_submission_exists) {
                    $completion_scoring_factor = 0;//don't give more points
                }
            } else {
                $percent = preg_replace('~\D~', '', $assignment_question->completion_scoring_mode);
                $completion_scoring_factor = floatval($percent) / 100;

            }

        }
        return floatval($assignment_question->points) * $completion_scoring_factor;
    }


    public
    function getHintPenalty(int $user_id, Assignment $assignment, int $question_id)
    {
        $viewed_hint = DB::table('shown_hints')
            ->where('user_id', $user_id)
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question_id)
            ->first();

        return $viewed_hint && $assignment->hint_penalty ? $assignment->hint_penalty : 0;
    }

    /**
     * @param Assignment $Assignment
     * @param Submission $submission
     * @return bool
     */
    public
    function tooManySubmissions(Assignment $Assignment, Submission $submission): bool
    {
        if (in_array(Request::user()->role, [2, 5])) {
            return false;
        }
        return $Assignment->number_of_allowed_attempts !== 'unlimited'
            && (int)$submission->submission_count === (int)$Assignment->number_of_allowed_attempts;
    }

    /**
     * @param int $user_id
     * @param int $assignment_id
     * @param int $question_id
     * @param int $max_score
     * @return float
     */
    public
    function geth5pActivitySetProportionCorrect(int $user_id, int $assignment_id, int $question_id, int $max_score): float
    {
        $h5p_activity_sets = H5pActivitySet::where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->where('user_id', $user_id)
            ->get();
        $total_raw = 0;
        foreach ($h5p_activity_sets as $h5p_activity_set) {
            $partial_submission = json_decode($h5p_activity_set->submission);
            $total_raw += $partial_submission->result->score->raw;
        }
        return floatval($total_raw / $max_score);


    }

    /**
     * @param object $assignment_question
     * @param object $submission
     * @param StoreSubmission $data
     * @param Assignment $assignment
     * @param Score $score
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param DataShop $dataShop
     * @param string $subContentId
     * @return array
     * @throws Exception
     */
    public
    function processh5pActivitySet(
        object                 $assignment_question,
        object                 $submission,
        StoreSubmission        $data,
        Assignment             $assignment,
        Score                  $score,
        AssignmentSyncQuestion $assignmentSyncQuestion,
        DataShop               $dataShop,
        string                 $subContentId): array
    {

        $response['type'] = 'error';
        try {
            $h5pMaxScore = H5pMaxScore::where('question_id', $data['question_id'])->first();
            if (!$h5pMaxScore) {
                $h5pMaxScore = H5pMaxScore::create([
                    'question_id' => $data['question_id'],
                    'max_score' => $data['max_score']
                ]);
            }
            $h5pActivitySet = H5pActivitySet::where('user_id', $data['user_id'])
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $data['question_id'])
                ->where('sub_content_id', $subContentId)
                ->first();
            DB::beginTransaction();
            if (!$h5pActivitySet) {
                $h5pActivitySet = H5pActivitySet::create(
                    ['user_id' => $data['user_id'],
                        'assignment_id' => $assignment->id,
                        'question_id' => $data['question_id'],
                        'sub_content_id' => $subContentId,
                        'correct' => $this->geth5pActivitySetNumCorrect($assignment, json_decode($data['submission'])),
                        'submission' => $data['submission'],
                        'submission_count' => 1
                    ]
                );
            } else {
                if ($h5pActivitySet->submission === $data['submission']) {
                    $h5pActivitySet->save();
                    $response['type'] = 'info';
                    $response['message'] = 'Partial submission re-saved.';
                    return $response;
                }
                if ($assignment->number_of_allowed_attempts !== 'unlimited'
                    && (int)$h5pActivitySet->submission_count === (int)$assignment->number_of_allowed_attempts) {
                    $response['type'] = 'error';
                    $plural = $assignment->number_of_allowed_attempts > 1 ? 's' : '';
                    $response['message'] = "Nothing saved since you are only allowed $assignment->number_of_allowed_attempts attempt$plural.";
                    return $response;
                }
                $h5pActivitySet->submission = $data['submission'];
                $h5pActivitySet->submission_count = $h5pActivitySet->submission_count + 1;
                $h5pActivitySet->correct = $this->geth5pActivitySetNumCorrect($assignment, json_decode($data['submission']));
                $h5pActivitySet->save();
            }

            $submission = Submission::where('user_id', $data['user_id'])
                ->where('assignment_id', $data['assignment_id'])
                ->where('question_id', $data['question_id'])
                ->first();

            $h5pActivitySets = $h5pActivitySet->where('user_id', $data['user_id'])
                ->where('assignment_id', $data['assignment_id'])
                ->where('question_id', $data['question_id'])
                ->get();

            $num_correct = 0;
            foreach ($h5pActivitySets as $h5pActivitySet) {
                $h5p_activity_set_submission = json_decode($h5pActivitySet->submission);
                $num_correct += $this->geth5pActivitySetNumCorrect($assignment, $h5p_activity_set_submission);
            }

            $all_correct = $num_correct === $h5pMaxScore->max_score;
            $data['score'] = ($num_correct / $h5pMaxScore->max_score) * $assignment_question->points;
            $data['score'] = $this->applyLatePenalyToScore($assignment, $data['score']);

            if ($submission) {
                $submission->submission = $data['submission'];
                $submission->answered_correctly_at_least_once = $all_correct || $submission->answered_correctly_at_least_once;
                $submission->score = $data['score'];
                $submission->save();
            } else {
                $submission = Submission::create(['user_id' => $data['user_id'],
                    'assignment_id' => $data['assignment_id'],
                    'question_id' => $data['question_id'],
                    'submission' => $data['submission'],
                    'score' => $data['score'],
                    'answered_correctly_at_least_once' => $all_correct,
                    'submission_count' => 0]);
            }
            $lms_grade_passback = Assignment::find($assignment->id)->lms_grade_passback;
            $score->updateAssignmentScore($data['user_id'], $assignment->id, $lms_grade_passback === 'automatic');
            $response['completed_all_assignment_questions'] = $assignmentSyncQuestion->completedAllAssignmentQuestions($assignment);
            try {
                session()->put('submission_id', md5(uniqid('', true)));
                $data['sub_content_id'] = $subContentId;
                $data['submission'] = $submission;
                $dataShop->store('submission', $data, $assignment, $assignment_question);
            } catch (Exception $e) {
                $h = new Handler(app());
                $h->report($e);
            }
            $response['type'] = 'info';
            $response['message'] = 'Partial submission saved.';
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error saving your response.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    function geth5pActivitySetNumCorrect(Assignment $assignment, object $submission)
    {
        // there was an issue with question 172535.  it had multiple parts for each and the scoring wasn't correct.
        //However, I'm not sure if this screws something else up.  The old code was to return scaled if performance
        //I'm using scaled because they could have multiple parts to each.  Ummm...now I'm back to raw...
        return $assignment->scoring_type === 'p'
            ? $submission->result->score->scaled
            : $submission->result->score->max;

    }

    /**
     * @param $response
     * @param $student_responses
     * @return int
     * @throws Exception
     */
    public
    function computeScoreFromPlusMinusScoring($response, $student_responses): int
    {
        if ($response->correctResponse && in_array($response->identifier, $student_responses)) {
            return 1;
        }
        if ($response->correctResponse && !in_array($response->identifier, $student_responses)) {
            return -1;
        }
        if (!$response->correctResponse && in_array($response->identifier, $student_responses)) {
            return -1;
        }
        if (!$response->correctResponse && !in_array($response->identifier, $student_responses)) {
            return 0;
        }
        throw new Exception ('Error in plus/minus scoring logic.');
    }

    /**
     * @param $question
     * @param $student_response
     * @return array
     */
    public
    function computeScoreFromSubmitMolecule($question, $student_response): array
    {
        $token = DB::table('key_secrets')->where('key', 'sketcher')->first()->secret;
        $proportion_correct_response['type'] = 'error';
        $data = [
            'reference_diagram' => $question->solutionStructure,
            'student_diagram' => json_decode($student_response)->structure,
            'match_stereo' => property_exists($question, 'matchStereo') ? +$question->matchStereo : 0
        ];
        // Make the POST request
        $response = Http::withHeaders([
            'Authorization' => $token  // Add your Bearer token here
        ])->post('https://api.molview.libretexts.org/api/v1/compare', $data);

        if ($response->successful()) {
            $proportion_correct_response['type'] = 'success';
            $proportion_correct_response['proportion_correct'] = (int)$response->json()['equal'];
        } else {
            $proportion_correct_response['message'] = "Sketcher error: " . $response->json()['err'] ?: $response->body();
        }
        return $proportion_correct_response;
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param string $response_format
     * @return array
     * @throws Exception
     */
    public
    function submissionChartData(Assignment $assignment, Question $question, string $response_format): array
    {
        try {
            $response['type'] = 'error';
            $number_enrolled = $assignment->course->enrolledUsers()->count();
            $submission_results = DB::table('submissions')
                ->join('questions', 'submissions.question_id', '=', 'questions.id')
                ->where('submissions.assignment_id', $assignment->id)
                ->where('submissions.question_id', $question->id)
                //->where('submissions.user_id','<>', $fake_student_user_id)
                ->select('submission', 'technology', 'score')
                ->get();

            if ($submission_results->isNotEmpty()) {
                $pretty_presentation_exists = $this->prettyPresentationExists($submission_results[0], $response_format);
                if ($pretty_presentation_exists) {
                    $response = $this->getPieChartResults($assignment, $question, $submission_results);
                } else {
                    $response['default_submission_results'] = $this->getDefaultSubmissionResults($submission_results);
                }
            }

            $number_submission_results = count($submission_results); //don't include Fake
            $response['response_percent'] = $number_enrolled ? Round(100 * $number_submission_results / $number_enrolled, 1) : 0;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            $h = new Handler(app());
            $h->report($e);
        }
        return $response;
    }

    /**
     * @param object $submission_result
     * @param string $response_format
     * @return bool
     */
    public
    function prettyPresentationExists(object $submission_result,
                                      string $response_format): bool
    {
        $technology = $submission_result->technology;
        $submission = json_decode($submission_result->submission, true);
        $pretty_presentation_exists = false;
        switch ($technology) {
            case('qti'):
                $question_type = $submission['question']['questionType'] ?? null;
                $pretty_presentation_exists = $question_type && in_array($question_type, ['true_false', 'multiple_choice']);
                break;
            case('h5p'):
                $object = $submission['object'];
                $pretty_presentation_exists = $object
                    && $object['definition']
                    && $object['definition']['interactionType']
                    && in_array($object['definition']['interactionType'], ['choice', 'true-false']);
                break;
            case('webwork'):
                $pretty_presentation_exists = in_array($response_format, ['multiple choice', 'numeric']);
                break;
            default:
                break;
        }
        return $pretty_presentation_exists;
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param $submission_results
     * @return array
     */
    public
    function getPieChartResults(Assignment $assignment, Question $question, $submission_results): array
    {

        $choices = [];
        $counts = [];
        $choices_by_identifier = [];
        $counts_by_identifier = [];
        $correct_answer = '';
        $correct_answer_index = -1;
        foreach ($submission_results as $value) {

            $submission = json_decode($value->submission, true);
            $technology = $value->technology;

            switch ($technology) {
                case('qti'):
                    $question_type = $submission['question']['questionType'] ?? null;
                    if (!in_array($question_type, ['true_false', 'multiple_choice'])) {
                        $response['message'] = 'Native questions only support True/False and Multiple Choice.';
                        return $response;

                    }
                    switch ($question_type) {
                        case('true_false'):
                            if (!$choices_by_identifier) {
                                $choices = ['True', 'False'];
                                $counts = [0, 0];
                                foreach ($submission['question']['simpleChoice'] as $key => $choice) {
                                    $choices_by_identifier[$choice['identifier']] = $choice['value'];
                                }
                            }
                            if ($correct_answer_index === -1) {
                                foreach ($submission['question']['simpleChoice'] as $key => $choice) {
                                    if ($choice['correctResponse']) {
                                        $correct_answer = $choice['value'];
                                        $correct_answer_index = $key;
                                    }
                                }
                            }
                            $choices_by_identifier[$submission['student_response']] === 'True'
                                ? $counts[0]++
                                : $counts[1]++;
                            break;

                        case('multiple_choice'):
                            if (!$choices_by_identifier) {
                                foreach ($submission['question']['simpleChoice'] as $choice) {
                                    $choices_by_identifier[$choice['identifier']] = $choice['value'];
                                    $counts_by_identifier[$choice['identifier']] = 0;
                                }
                            }

                            if ($correct_answer_index === -1) {
                                foreach ($submission['question']['simpleChoice'] as $key => $choice) {
                                    if ($choice['correctResponse']) {
                                        $correct_answer = $choice['value'];
                                        $correct_answer_index = $key;
                                    }
                                }
                            }
                            foreach ($counts_by_identifier as $identifier => $count_by_identifier) {
                                if ((string)$submission['student_response'] === (string)$identifier) {
                                    $counts_by_identifier[$identifier]++;
                                }
                            }
                            break;
                    }

                    break;
                case('h5p'):
                    $object = $submission['object'];
                    //Log::info(print_r($submission, true));
                    // Log::info($object['definition']['interactionType']);
                    switch ($object['definition']['interactionType']) {
                        case('choice'):
                            if (!$choices) {
                                $choices = $this->getChoices($technology, $object['definition']);
                                foreach ($choices as $choice) {
                                    $counts[] = 0;
                                }

                                $correct_answer_index = +$object['definition']['correctResponsesPattern'][0];
                                $correct_answer = $this->getCorrectAnswer($technology, $object['definition'], $correct_answer_index);
                            }
                            if (isset($submission['result']['response'])) {
                                $h5p_response = $submission['result']['response'];
                                $counts[$h5p_response]++;
                                $response['counts'] = $counts;
                            }
                            break;
                        case('true-false'):
                            if (!$choices) {
                                $choices = ['True', 'False'];
                                $counts = [0, 0];
                                $correct_answer_index = $object['definition']['correctResponsesPattern'][0] === 'true' ? 0 : 1;
                                $correct_answer = $choices[$correct_answer_index];
                            }
                            if (isset($submission['result']['response'])) {
                                $submission['result']['response'] === "true" ? $counts[0]++ : $counts[1]++;
                                $response['counts'] = $counts;
                            }
                            break;
                    }

                    break;
                case('webwork'):
                    $student_ans = null;
                    $webwork = new Webwork();
                    if ($submission
                        && isset($submission['score'])
                        && isset($submission['score']['answers'])) {
                        $cache_key = "webwork_code_assignment_question_{$assignment->id}_$question->id";
                        $webwork_code = Cache::remember($cache_key, now()->addMinutes(10), function () use ($assignment, $question) {
                            $assignment_question = AssignmentSyncQuestion::where('assignment_id', $assignment->id)
                                ->where('question_id', $question->id)
                                ->first();
                            return $assignment_question->question_revision_id
                                ? QuestionRevision::find($assignment_question->question_revision_id)->webwork_code
                                : Question::find($assignment_question->question_id)->webwork_code;
                        });
                        $radio_buttons = $webwork->getRadioButtonLabels($webwork_code, false);
                        if ($radio_buttons) {
                            /* if (isset($value['preview_latex_string'])) {
                                 $formatted_submission = isset($value['preview_latex_string']) ? '\(' . $value['preview_latex_string'] . '\)';
                             } else {
                                 $formatted_submission = $value['original_student_ans'] ?? 'Nothing submitted.';
                             }*/
                            $first_answer = reset($submission['score']['answers']);
                            $student_ans = $first_answer['original_student_ans'];
                            if (!isset($response['correct_answer'])) {
                                $response['correct_answer'] = $first_answer['correct_ans'];
                            }
                            foreach ($radio_buttons as $radio_button) {
                                $counts_by_identifier[$radio_button] = 0;
                            }
                        }
                        foreach ($counts_by_identifier as $identifier => $count_by_identifier) {
                            if ($student_ans === $identifier) {
                                $counts_by_identifier[$identifier]++;
                            }
                        }
                        $choices = array_values($radio_buttons);
                        $counts = array_values($counts_by_identifier);
                    }
                    break;
                default:
                    $response['message'] = 'Only True/False or Multiple Choice Native/H5P questions are supported at this time.';
                    return $response;
            }
        }

        if (isset($technology) && isset($question_type) && $technology === 'qti' && $question_type === 'multiple_choice') {
            $choices = array_values($choices_by_identifier);
            $counts = array_values($counts_by_identifier);
        }
        $submissions = [];
        foreach ($choices as $key => $choice) {
            $submissions[] = ['submission' => $choice, 'number_of_students' => $counts[$key]];
        }
        $scores = $this->formatSubmissionResultScores($submission_results);
        $pie_chart_data['labels'] = array_values($choices);
        $pie_chart_data['datasets']['borderWidth'] = 1;

        foreach ($choices as $key => $choice) {
            $percent = 90 - 10 * $key;
            $first = 60 - 20 * $key;
            $pie_chart_data['datasets']['backgroundColor'][$key] = "hsla($first, 85%, $percent%, 0.9)";

        }

        $total = array_sum($counts);
        ksort($counts);
        if ($total) {
            foreach ($counts as $key => $count) {
                $counts[$key] = Round(100 * $count / $total);
            }
        }
        foreach ($pie_chart_data['labels'] as $key => $label) {
            $pie_chart_data['labels'][$key] .= "  &mdash; $counts[$key]%";
        }

        $pie_chart_data['datasets']['data'] = $counts;
        $pie_chart_data['correct_answer_index'] = $correct_answer_index;
        $default_submission_results['default_submission_results'][0] = [];
        $default_submission_results['default_submission_results'][0]['display'] = "pie-chart";
        $default_submission_results['default_submission_results'][0]['submissions'] = $submissions;
        $default_submission_results['default_submission_results'][0]['scores'] = $scores;
        $default_submission_results['default_submission_results'][0]['pie_chart_data'] = $pie_chart_data;
        $default_submission_results['default_submission_results'][0]['correct_ans'] = $correct_answer;
        return $default_submission_results;
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param $submission
     * @param $is_learning_tree_node
     * @param $assignment_question
     * @return array
     * @throws Exception
     */

    public
    function getSubmissionArray(Assignment $assignment, Question $question, $submission, $is_learning_tree_node, $assignment_question = null): array
    {
        $submission_array = [];
        if ($question->technology === 'qti') {
            return [];
        }
        if ($submission &&
            in_array($question->technology, ['webwork', 'imathas'])
            && (in_array(request()->user()->role, [2, 5]) || (in_array($assignment->assessment_type, ['learning tree', 'real time']) || ($assignment->assessment_type === 'delayed' && $assignment->solutions_released)))) {
            $assignment_question = $assignment_question ?: DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();
            $submission_info = json_decode($submission->submission, 1);
            switch ($question->technology) {
                case('webwork'):
                    if ($submission_info && isset($submission_info['score']) && isset($submission_info['score']['answers'])) {
                        foreach ($submission_info['score']['answers'] as $identifier => $value) {
                            if (isset($value['preview_latex_string'])) {
                                $submission_has_html = $this->hasHtml($value['preview_latex_string']);
                                $formatted_submission = $submission_has_html ? $value['preview_latex_string'] : '\(' . $value['preview_latex_string'] . '\)';
                            } else {
                                $formatted_submission = $value['original_student_ans'] ?? 'Nothing submitted.';
                                $submission_has_html = $this->hasHtml($formatted_submission);
                            }
                            $value['score'] = $value['score'] ?? 0;
                            $is_correct = $value['score'] === 1;
                            $weight = isset($value['weight']) ? $value['weight'] / 100 : (1 / count($submission_info['score']['answers']));
                            $points = !$is_learning_tree_node && count($submission_info['score']['answers'])
                                ? Helper::removeZerosAfterDecimal(round($assignment_question->points * (+$value['score'] * $weight), 4))
                                : 0;
                            $percent = !$is_learning_tree_node && $assignment_question->points > 0 ? Helper::removeZerosAfterDecimal(round(100 * $points / $assignment_question->points, 2)) : 0;

                            $submission_array_value = [
                                'submission_has_html' => $submission_has_html,
                                'submission' => $formatted_submission,
                                'identifier' => $identifier,
                                'correct' => $is_correct,
                                'partial_credit' => !$is_correct && $value['score'] > 0,
                                'points' => $points,
                                'percent' => $percent];
                            $show_solution_as_student = Cache::get('show_solution_' . request()->user()->id . '_' . $assignment->id . '_' . $question->id, false);
                            if (request()->user()->role === 2 || $show_solution_as_student) {
                                if (isset($value['correct_ans_latex_string'])) {
                                    $correct_ans_has_html = $this->hasHtml($value['correct_ans_latex_string']);
                                    $correct_ans = $correct_ans_has_html ? $value['correct_ans_latex_string'] : '\(' . $value['correct_ans_latex_string'] . '\)';
                                } elseif (isset($value['correct_ans'])) {
                                    $correct_ans_has_html = $this->hasHtml($value['correct_ans']);
                                    $correct_ans = $correct_ans_has_html ? $value['correct_ans'] : '\(' . $value['correct_ans'] . '\)';
                                } else {
                                    $correct_ans = "This WeBWorK question is missing the 'correct_ans' key.  Please fix the weBWork code.";
                                    $correct_ans_has_html = false;
                                }
                                $submission_array_value['correct_ans'] = $correct_ans;
                                $submission_array_value['correct_ans_has_html'] = $correct_ans_has_html;
                            }

                            $submission_array[] = $submission_array_value;
                        }
                    }
                    break;
                case
                ('imathas'):
                    if ($submission_info) {
                        $tks = explode('.', $submission_info['state']);
                        list($headb64, $bodyb64, $cryptob64) = $tks;
                        $state = json_decode(base64_decode($bodyb64), 1);
                        $state = json_decode(base64_decode(strtr($bodyb64, '-_,', '+/=')), 1);
                        $raw_scores = array_values($state['rawscores']);
                        /**
                         * If
                         * stuanswers[qn+1] is an array (indicates a multipart or conditional question)
                         * AND
                         * scoreiscorrect[qn+1] is not an array (indicates the question acts like a single score, which would limit us to a conditional question)
                         *
                         * With a conditional question:
                         * rawscores[qn] will always be an array of only one value, the score for the whole question.
                         * partattemptn[qn] will be an array with a value for each part. The values will always be equal since a conditional question always submits every part.
                         * stuanswers[qn+1] will be an array with a value for each part
                         * stuanswersval[qn+1] will be an array with a value for each part
                         * scoreiscorrect[qn+1] is a scalar
                         * scorenonzero[qn+1] is a scalar
                         */
                        if (isset($state['stuanswers']) && $state['stuanswers']) {
                            if (isset($state['qtype']) && $state['qtype'] === 'conditional') {
                                $qsid = array_key_first($state['qsid']);
                                $raw = $state['scoreiscorrect'][$qsid + 1];
                                $points = !$is_learning_tree_node ? $this->getPoints($assignment_question, $raw, [$submission]) : 0;
                                $percent = !$is_learning_tree_node ? $this->getPercent($assignment_question, $points) : 0;
                                $at_least_one_submission = false;
                                foreach ($state['stuanswers'][$qsid + 1] as $submission_value) {
                                    if ($submission_value !== '') {
                                        $at_least_one_submission = true;
                                    }
                                }
                                $formatted_submission = implode(', ', $state['stuanswers'][$qsid + 1]);
                                $submission_array_value = [
                                    'submission' => $at_least_one_submission ? $formatted_submission : 'Nothing submitted.',
                                    'correct' => $raw === 1,
                                    'points' => $points,
                                    'percent' => $percent];
                                $submission_array[] = $submission_array_value;
                            } else {
                                foreach ($state['stuanswers'] as $key => $submission) {
                                    if (is_array($submission)) {
                                        if (isset($state['qtype']) && $state['qtype'] !== 'conditional') {
                                            foreach ($submission as $part_key => $part) {
                                                $raw = $submission_info['raw'][$part_key];
                                                $points = !$is_learning_tree_node ? $this->getPoints($assignment_question, $raw, $submission) : 0;
                                                $percent = !$is_learning_tree_node ? $this->getPercent($assignment_question, $points) : 0;

                                                $submission_array_value = [
                                                    'submission' => $part !== '' ? '\(' . $part . '\)' : 'Nothing submitted.',
                                                    'correct' => $raw === 1,
                                                    'points' => $points,
                                                    'percent' => $percent];
                                                $submission_array[] = $submission_array_value;
                                            }
                                        }
                                    } else {
                                        $points = !$is_learning_tree_node ? $this->getPoints($assignment_question, $submission_info['raw'][0], [$submission]) : 0;
                                        $percent = !$is_learning_tree_node ? $this->getPercent($assignment_question, $points) : 0;
                                        $submission_array[] = ['submission' => $submission !== '' ? '\(' . $submission . '\)' : 'Nothing submitted.',
                                            'points' => $points,
                                            'percent' => $percent,
                                            'correct' => $submission_info['raw'][0] === 1];

                                    }
                                }
                            }
                        }
                    }
                    break;
                default:
                    throw new Exception ("$question->technology is not set up for a submission array.");

            }
        }
        return $submission_array;
    }

    /**
     * @param $assignment_question
     * @param $score
     * @param $answers
     * @return int|mixed|string
     */
    public
    function getPoints($assignment_question, $score, $answers)
    {
        return count($answers)
            ? Helper::removeZerosAfterDecimal(round($assignment_question->points * (+$score / count($answers)), 4))
            : 0;
    }

    /**
     * @param $assignment_question
     * @param $points
     * @return int|mixed|string
     */
    public
    function getPercent($assignment_question, $points)
    {
        return $assignment_question->points > 0 ? Helper::removeZerosAfterDecimal(round(100 * $points / $assignment_question->points, 2)) : 0;
    }

    /**
     * @param $string
     * @return bool
     */
    public
    function hasHtml($string): bool
    {
        return $string !== strip_tags($string);
    }

    private
    function _returnJsonAndExit($result)
    {
        header('appversion: ignore');
        echo json_encode($result);
        exit;
    }

    /**
     * @throws Exception
     */
    private
    function _compareMarks(string $partial_credit, $solution, $student): array
    {
        $result = [
            'atoms' => [],
            'bonds' => [],
            'all_correct' => true,
        ];

        foreach (['atoms', 'bonds'] as $key) {
            $solution_items = $solution->$key;
            $student_items = $student->$key;

            foreach ($solution_items as $index => $solution_item) {
                $student_item = $student_items[$index] ?? null;

                $solution_mark = $this->_getMark($solution_item);
                $student_mark = $this->_getMark($student_item);

                $correct = ($solution_mark === $student_mark);

                $result[$key][] = [
                    'index' => $index,
                    'expected' => $solution_mark,
                    'actual' => $student_mark,
                    'correct' => $correct,
                    'score_adjustment_percent' => $correct ? +$solution_item->correct : -$solution_item->incorrect
                ];

                if (!$correct) {
                    $result['all_correct'] = false;
                }
            }
        }
        switch ($partial_credit) {
            case('inclusive'):
                $percent_correct = 0;
                foreach (['atoms', 'bonds'] as $key) {
                    foreach ($result[$key] as $student_result) {
                        $percent_correct += $student_result['score_adjustment_percent'];
                    }
                }
                break;
            case('exclusive'):
                $percent_correct = $result['all_correct'] ? 100 : 0;
                break;
            default:
                throw new Exception("$partial_credit is not a valid partial credit option.");
        }
        $result['proportion_correct'] = max($percent_correct / 100, 0);
        return $result;
    }

    /**
     * @param $item
     * @return bool
     */
    private
    function _getMark($item): ?bool
    {
        if (is_object($item)) {
            return property_exists($item, 'mark') ? $item->mark : null;
        } elseif (is_array($item)) {
            return $item['mark'] ?? null;
        }
        return null;
    }

    /**
     * @param $submission
     * @return bool
     */
    private
    function _markedAll($submission): bool
    {
        $num_things_to_mark = count($submission->question->solutionStructure->atoms) + count($submission->question->solutionStructure->bonds);
        $student_submission = json_decode($submission->student_response, 1)['structure'];
        $num_marks_submitted = 0;
        foreach (['atoms', 'bonds'] as $item) {
            if (isset($item['mark'])) {
                $num_marks_submitted++;
            }
        }
        return $num_things_to_mark === $num_marks_submitted;

    }

    /**
     * @param $submission_results
     * @return array
     */
    public
    function getDefaultSubmissionResults($submission_results): array
    {

        if (!$submission_results) {
            return [];
        }

        $technology = $submission_results[0]->technology;
        $summary_of_results = [];
        switch ($technology) {
            case 'qti':
            case 'imathas':
            case 'h5p':
                //make an array to allow for parts in webwork
                $summary_of_results[0]['scores'] = $this->formatSubmissionResultScores($submission_results);
                $summary_of_results[0]['display'] = 'histogram';
                break;
            case 'webwork':
                $summary_of_results = $this->defaultSummaryOfResultsForWebwork($submission_results);
                break;

        }
        return $summary_of_results;

    }

    /**
     * @param $submission_results
     * @return array
     */
    public
    function defaultSummaryOfResultsForWebwork($submission_results): array
    {
        $results_by_part = [];
        foreach ($submission_results as $submission_result) {
            $submission = json_decode($submission_result->submission, true);
            if ($submission
                && isset($submission['score'])
                && isset($submission['score']['answers'])) {
                foreach ($submission['score']['answers'] as $key => $part) {
                    // Initialize structure for this part
                    if (!isset($results_by_part[$key])) {
                        $results_by_part[$key] = [
                            'display' => 'histogram',
                            'use_mathjax' => $this->isLikelyLatex($part['correct_ans_latex_string']),
                            'correct_ans' => $this->isLikelyLatex($part['correct_ans']) ? '\(' . $part['correct_ans_latex_string'] . '\)' : $part['correct_ans'],
                            'correct_ans_latex_string' => $part['correct_ans_latex_string'],
                            'counts' => [
                                'original_student_ans' => [],
                                'preview_latex_string' => [],
                                'score' => [],
                            ],
                            'submissions' => [],
                        ];
                    }

                    // Capture student submission
                    if (!is_numeric($part['original_student_ans'])) {
                        $results_by_part[$key]['display'] = 'pie-chart';
                    }
                    $student_result = [
                        'original_student_ans' => $part['original_student_ans'],
                        'preview_latex_string' => $part['preview_latex_string'],
                        'score' => $part['score'],
                    ];
                    $results_by_part[$key]['submissions'][] = $student_result;

                    // Update counts
                    foreach (['original_student_ans', 'preview_latex_string', 'score'] as $field) {
                        $value = $part[$field];
                        if (!isset($results_by_part[$key]['counts'][$field][$value])) {
                            $results_by_part[$key]['counts'][$field][$value] = 0;
                        }
                        $results_by_part[$key]['counts'][$field][$value]++;
                    }
                }

            }
        }
        $summary_of_results = [];
        foreach ($results_by_part as $key => $value) {
            $summary_of_results[$key] = $value;
            $submissions = [];
            $scores = [];
            foreach ($value['counts']['original_student_ans'] as $submission => $count) {
                $submissions[] = ['submission' => $this->isLikelyLatex($submission) ? '\(' . $submission . '\)' : $submission, 'number_of_students' => $count];
            }
            foreach ($value['counts']['score'] as $score => $count) {
                $scores[] = ['score' => $score, 'number_of_students' => $count];
            }
            $summary_of_results[$key]['submissions'] = $submissions;
            $summary_of_results[$key]['scores'] = $scores;
            unset($summary_of_results[$key]['counts']);

        }
        foreach ($summary_of_results as $key => $value) {
            $summary_of_results[$key] = $value;
        }
        foreach ($summary_of_results as &$summary) {
            if ($summary['display'] === 'pie-chart') {
                $submissions = $summary['submissions'];
                $summary['pie_chart_data'] = [];
                $summary['pie_chart_data']['labels'] = [];
                $counts = [];
                foreach ($submissions as $value) {
                    $summary['pie_chart_data']['labels'][] = $value['submission'];
                    $counts[] = $value['number_of_students'];
                }
                $summary['pie_chart_data']['datasets']['borderWidth'] = 1;
                $summary['pie_chart_data']['correct_answer_index'] = -1;
                foreach ($summary['pie_chart_data']['labels'] as $key => $choice) {
                    $percent = 90 - 10 * $key;
                    $first = 60 - 20 * $key;
                    $summary['pie_chart_data']['datasets']['backgroundColor'][$key] = "hsla($first, 85%, $percent%, 0.9)";
                    if ($choice === $summary['correct_ans']) {
                        $summary['pie_chart_data']['correct_answer_index'] = $key;
                    }
                }
                $total = array_sum($counts);
                ksort($counts);
                if ($total) {
                    foreach ($counts as $key => $count) {
                        $counts[$key] = Round(100 * $count / $total);
                    }
                }
                foreach ($summary['pie_chart_data']['labels'] as $key => $label) {
                    $summary['pie_chart_data']['labels'][$key] .= "  &mdash; $counts[$key]%";
                }
                $summary['pie_chart_data']['datasets']['data'] = $counts;
            }
        }
        return array_values($summary_of_results);
    }

    /**
     * @param $technology
     * @param $object
     * @param $correct_answer_index
     * @return string
     */
    public
    function getCorrectAnswer($technology, $object, $correct_answer_index): string
    {
        $correct_answer = 'Could not determine.';
        switch ($technology) {
            case('h5p'):
                foreach ($object['choices'] as $choice) {
                    if ($choice['id'] === $correct_answer_index)
                        $correct_answer = trim(array_values($choice['description'])[0]);
                }
                break;
        }
        return $correct_answer;


    }

    /**
     * @param $technology
     * @param $object
     * @return array
     */
    public
    function getChoices($technology, $object): array
    {
        $choices = [];
        switch ($technology) {
            case('h5p'):
                foreach ($object['choices'] as $choice) {
                    $choices[$choice['id']] = array_values($choice['description'])[0];
                }
                break;

        }
        ksort($choices);
        return $choices;
    }

    public
    function formatSubmissionResultScores($submission_results)
    {
        return $submission_results->groupBy('score')->map(function ($group, $score) {
            $normalized_score = rtrim(rtrim(number_format((float)$score, 3, '.', ''), '0'), '.');

            return [
                'score' => $normalized_score,
                'number_of_students' => $group->count(),
            ];
        })->values()->toArray();
    }

    /**
     * @param $string
     * @return false|int
     */
    function isLikelyLatex($string)
    {
        return preg_match('/(\\\\(?!text)[a-zA-Z]+|[a-zA-Z0-9]+_[a-zA-Z0-9]+|[a-zA-Z0-9]+\^[a-zA-Z0-9]+|[\^_]\{.*?\})/', $string);
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param Score $score
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param $assignment_question
     * @param $data
     * @return array|float|int
     * @throws Exception
     */
    public
    function computeScore(Assignment             $assignment,
                          Question               $question,
                          Score                  $score,
                          AssignmentSyncQuestion $assignmentSyncQuestion,
                                                 $assignment_question,
                                                 $data)

    {
        switch ($data['technology']) {
            case('h5p'):
                $submission = json_decode($data['submission']);
                $h5p_activity_set_max_score = DB::table('h5p_max_scores')
                    ->where('question_id', $question->id)
                    ->first();
                if ($h5p_activity_set_max_score) {
                    $h5pActivitySets = DB::table('h5p_activity_sets')
                        ->where('user_id', $data['user_id'])
                        ->where('assignment_id', $assignment->id)
                        ->where('question_id', $question->id)
                        ->get();

                    $num_correct = 0;
                    foreach ($h5pActivitySets as $h5pActivitySet) {
                        $h5p_activity_set_submission = json_decode($h5pActivitySet->submission);
                        $num_correct += $this->geth5pActivitySetNumCorrect($assignment, $h5p_activity_set_submission);
                    }

                    $proportion_correct = ($num_correct / $h5p_activity_set_max_score->max_score);
                } else {
                    $proportion_correct = $this->getProportionCorrect('h5p', $submission);
                }
                $data['score'] = $assignment->scoring_type === 'p'
                    ? floatval($assignment_question->points) * $proportion_correct
                    : $this->computeScoreForCompletion($assignment_question);
                break;
            case('imathas'):
                $submission = json_decode($data['submission']);
                $proportion_correct = $this->getProportionCorrect('imathas', $submission);
                $data['score'] = $assignment->scoring_type === 'p'
                    ? floatval($assignment_question->points) * $proportion_correct
                    : $this->computeScoreForCompletion($assignment_question);
                break;
            case('webwork'):
                $submission = json_decode($data['submission']);
                $proportion_correct = $this->getProportionCorrect('webwork', $submission);//
                $data['score'] = $assignment->scoring_type === 'p'
                    ? floatval($assignment_question->points) * $proportion_correct
                    : $this->computeScoreForCompletion($assignment_question);
                break;
            case('qti'):
                $submission = new stdClass();
                $submission->question = json_decode($question->qti_json);
                $submission->student_response = json_decode($data['submission'])->student_response;
                $proportion_correct = $this->getProportionCorrect('qti', $submission);
                $submission->proportion_correct = $proportion_correct;
                $data['score'] = $assignment->scoring_type === 'p'
                    ? floatval($assignment_question->points) * $proportion_correct
                    : $this->computeScoreForCompletion($assignment_question);
                break;
            default:
                throw new Exception("{$data['technology']} is not a valid technology.");
        }
        return $data['score'];
    }

    /**
     * @param SubmittedWorkPendingScore $submittedWorkPendingScore
     * @param Submission $submission
     * @param StoreSubmission $data
     * @param $score
     * @return void
     */
    public function moveScoreToPending(SubmittedWorkPendingScore $submittedWorkPendingScore,
                                       Submission                $submission,
                                       StoreSubmission           $data,
                                                                 $score)
    {
        $submittedWorkPendingScore->updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'assignment_id' => $data['assignment_id'],
                'question_id' => $data['question_id']
            ],
            ['score' => $score]
        );
        $submission->score = 0;
        $submission->save();
    }

    public function computeScoreForAccountingJournalEntry($solution, $studentSubmission, $tAccountsSolution = []): array
    {
        $results = [];
        $totalFields = 0;
        $correctFields = 0;

        // Student submission may be the legacy bare-array-of-entries shape, or the
        // current { entries: [...], tAccounts: [...] } shape once T-Accounts exist.
        $studentEntries = $studentSubmission;
        $studentTAccounts = [];
        if (is_array($studentSubmission) && array_key_exists('entries', $studentSubmission)) {
            $studentEntries = $studentSubmission['entries'] ?? [];
            $studentTAccounts = $studentSubmission['tAccounts'] ?? [];
        }

        foreach ($studentEntries as $entryIndex => $studentEntry) {
            $entryResult = [
                'selectedEntryIndex' => $studentEntry['selectedEntryIndex'] ?? null,
                'selectedEntryCorrect' => false,
                'rows' => [],
                'isCorrect' => false
            ];

            $correctEntryIndex = $entryIndex;
            $entryResult['selectedEntryCorrect'] = $studentEntry['selectedEntryIndex'] === $correctEntryIndex;

            if ($entryResult['selectedEntryCorrect']) {
                $correctFields++;
            }
            $totalFields++;

            $solutionEntry = $solution[$entryIndex] ?? null;

            if (is_array($solutionEntry)) {
                $solutionEntry = (object)$solutionEntry;
            }

            $solutionRows = null;
            if (is_object($solutionEntry) && isset($solutionEntry->solutionRows)) {
                $solutionRows = $solutionEntry->solutionRows;
            } elseif (is_array($solutionEntry) && isset($solutionEntry['solutionRows'])) {
                $solutionRows = $solutionEntry['solutionRows'];
            }

            if (!$solutionRows) {
                $results[$entryIndex] = $entryResult;
                continue;
            }

            // Find which specific rows are out of order (returns array of indices)
            $outOfOrderRows = $this->findOutOfOrderRows($studentEntry['rows']);

            $allRowsCorrect = true;
            foreach ($studentEntry['rows'] as $rowIndex => $studentRow) {
                $solutionRow = $solutionRows[$rowIndex] ?? null;

                if (is_array($solutionRow)) {
                    $solutionRow = (object)$solutionRow;
                }

                $rowResult = [
                    'accountTitle' => $studentRow['accountTitle'] ?? '',
                    'debit' => $studentRow['debit'] ?? '',
                    'credit' => $studentRow['credit'] ?? '',
                    'accountTitleCorrect' => false,
                    'debitCorrect' => false,
                    'creditCorrect' => false,
                    'isCorrect' => false
                ];

                // Only force incorrect for rows that are actually out of order
                $forceIncorrect = in_array($rowIndex, $outOfOrderRows);

                if ($solutionRow) {
                    $solutionAccountTitle = $solutionRow->accountTitle ?? '';
                    $solutionType = $solutionRow->type ?? '';
                    $solutionAmount = $solutionRow->amount ?? '';

                    $solutionDebit = ($solutionType === 'debit') ? $solutionAmount : '';
                    $solutionCredit = ($solutionType === 'credit') ? $solutionAmount : '';

                    $studentDebit = $studentRow['debit'] ?? '';
                    $studentCredit = $studentRow['credit'] ?? '';

                    // Account title
                    if ($forceIncorrect) {
                        $rowResult['accountTitleCorrect'] = false;
                    } else {
                        $rowResult['accountTitleCorrect'] = trim($studentRow['accountTitle'] ?? '') === trim($solutionAccountTitle);
                    }
                    if ($rowResult['accountTitleCorrect']) {
                        $correctFields++;
                    }
                    $totalFields++;

                    // Debit
                    if ($forceIncorrect) {
                        $rowResult['debitCorrect'] = false;
                    } elseif ($studentDebit === '' && $solutionDebit === '') {
                        $rowResult['debitCorrect'] = true;
                    } elseif ($studentDebit !== '' && $solutionDebit !== '') {
                        $rowResult['debitCorrect'] = abs($this->parseAmount($studentDebit) - $this->parseAmount($solutionDebit)) < 0.01;
                    } else {
                        $rowResult['debitCorrect'] = false;
                    }
                    if ($rowResult['debitCorrect']) {
                        $correctFields++;
                    }
                    $totalFields++;

                    // Credit
                    if ($forceIncorrect) {
                        $rowResult['creditCorrect'] = false;
                    } elseif ($studentCredit === '' && $solutionCredit === '') {
                        $rowResult['creditCorrect'] = true;
                    } elseif ($studentCredit !== '' && $solutionCredit !== '') {
                        $rowResult['creditCorrect'] = abs($this->parseAmount($studentCredit) - $this->parseAmount($solutionCredit)) < 0.01;
                    } else {
                        $rowResult['creditCorrect'] = false;
                    }
                    if ($rowResult['creditCorrect']) {
                        $correctFields++;
                    }
                    $totalFields++;

                    $rowResult['isCorrect'] =
                        $rowResult['accountTitleCorrect'] &&
                        $rowResult['debitCorrect'] &&
                        $rowResult['creditCorrect'];

                    if (!$rowResult['isCorrect']) {
                        $allRowsCorrect = false;
                    }
                }

                $entryResult['rows'][] = $rowResult;
            }

            $entryResult['isCorrect'] = $entryResult['selectedEntryCorrect'] && $allRowsCorrect;
            $results[$entryIndex] = $entryResult;
        }

        // T-Accounts (optional add-on) - blended into the same overall score.
        $tAccountResults = [];
        if (!empty($tAccountsSolution)) {
            $tAccountGrading = $this->computeScoreForTAccounts($tAccountsSolution, $studentTAccounts);
            $tAccountResults = $tAccountGrading['results'];
            $totalFields += $tAccountGrading['totalFields'];
            $correctFields += $tAccountGrading['correctFields'];
        }

        $proportionCorrect = $totalFields > 0 ? round($correctFields / $totalFields, 4) : 0;

        return [
            'proportionCorrect' => $proportionCorrect,
            'results' => $results,
            'tAccountResults' => $tAccountResults,
            'allCorrect' => $correctFields === $totalFields
        ];
    }

    /**
     * Grade T-Accounts. The debit column and credit column are graded
     * independently of each other (a row is just a shared grid line - it may
     * legitimately hold both a debit transaction and a credit transaction).
     * Within each side, order matters only by label (date/number): postings
     * that share an identical label may be posted in either order relative to
     * each other, but the overall label sequence must be correct. Balance rows
     * always sit after every posting, are not subject to reordering, and are
     * graded on side + amount only (their label is not compared).
     *
     * @param array $tAccountsSolution
     * @param array $studentTAccounts
     * @return array
     */

    /**
     * Grades a single-row balance. Both the ending Balance and the Beginning
     * Balance now have a real editable label ($requireLabel = true, checked
     * alongside amount). Beginning Balance only ever has one valid label
     * value ("Beginning Balance"), so $forcedLabel lets the caller supply
     * that expected value directly instead of reading it from the solution's
     * stored label - this also means older solution data that predates the
     * editable label still grades correctly with no migration.
     *
     * The debit side and credit side are graded completely independently of
     * each other - same philosophy as gradeTAccountSide() for postings. A
     * student can split their answer across sides (e.g. picking the right
     * label on the wrong side while still typing the right amount on the
     * right side); each of the four boxes (debitLabel, debit, creditLabel,
     * credit) gets judged on its own rather than the label's correctness
     * being tied to wherever the amount happened to land. For whichever side
     * the solution doesn't use, the correct answer for that side is simply
     * "leave it blank" - a stray value there is wrong, a blank matches.
     *
     * Returns null if the solution doesn't have one.
     */
    private function gradeSimpleBalance($solutionBalance, $studentBalance, bool $requireLabel = true, ?string $forcedLabel = null): ?array
    {
        $solutionBalance = is_array($solutionBalance) ? (object)$solutionBalance : $solutionBalance;
        if (empty($solutionBalance)) {
            return null;
        }

        $studentDebit = $studentBalance['debit'] ?? '';
        $studentCredit = $studentBalance['credit'] ?? '';
        $studentDebitLabel = trim($studentBalance['debitLabel'] ?? '');
        $studentCreditLabel = trim($studentBalance['creditLabel'] ?? '');

        $solDebit = $solutionBalance->debit ?? '';
        $solCredit = $solutionBalance->credit ?? '';
        $solSide = ($solDebit !== '' && $solDebit !== null) ? 'debit' : (($solCredit !== '' && $solCredit !== null) ? 'credit' : null);
        $solDebitAmount = $solSide === 'debit' ? $this->parseAmount($solDebit) : null;
        $solCreditAmount = $solSide === 'credit' ? $this->parseAmount($solCredit) : null;
        $solDebitLabel = $solSide === 'debit' ? ($forcedLabel ?? trim($solutionBalance->debitLabel ?? '')) : '';
        $solCreditLabel = $solSide === 'credit' ? ($forcedLabel ?? trim($solutionBalance->creditLabel ?? '')) : '';

        // Amount correctness on a side the solution doesn't use just means
        // "did the student correctly leave it blank" - matches how postings
        // treat an unused side/row.
        $debitCorrect = $solDebitAmount !== null
            ? ($studentDebit !== '' && abs($this->parseAmount($studentDebit) - $solDebitAmount) < 0.01)
            : $studentDebit === '';
        $creditCorrect = $solCreditAmount !== null
            ? ($studentCredit !== '' && abs($this->parseAmount($studentCredit) - $solCreditAmount) < 0.01)
            : $studentCredit === '';
        $debitLabelCorrect = $requireLabel ? ($studentDebitLabel === $solDebitLabel) : null;
        $creditLabelCorrect = $requireLabel ? ($studentCreditLabel === $solCreditLabel) : null;

        // Kept as a simple summary signal (did they put an amount on the side
        // the solution actually uses) - no longer drives label correctness.
        $studentSide = $studentDebit !== '' ? 'debit' : ($studentCredit !== '' ? 'credit' : null);
        $sideCorrect = $studentSide !== null && $studentSide === $solSide;

        return [
            'debitLabel' => $studentDebitLabel,
            'debit' => $studentDebit,
            'creditLabel' => $studentCreditLabel,
            'credit' => $studentCredit,
            'debitLabelCorrect' => $debitLabelCorrect,
            'debitCorrect' => $debitCorrect,
            'creditLabelCorrect' => $creditLabelCorrect,
            'creditCorrect' => $creditCorrect,
            'sideCorrect' => $sideCorrect,
            'isCorrect' => $requireLabel
                ? ($debitLabelCorrect && $debitCorrect && $creditLabelCorrect && $creditCorrect)
                : ($debitCorrect && $creditCorrect)
        ];
    }

    /**
     * Grades one side (debit or credit) of a T-Account's postings, independent
     * of the other side - a row is just a shared grid line, so its debit half
     * and credit half are graded as if they were separate columns. Rows within
     * a "same label" block may be answered in any order relative to each other,
     * but the block sequence itself must be correct (matching the label order
     * of that side's postings in the solution).
     *
     * @return array Row-index => ['labelCorrect' => bool, 'amountCorrect' => bool, 'isCorrect' => bool],
     *               only for row indices the solution actually uses on this side.
     */
    private function gradeTAccountSide($solutionPostings, $studentRows, $side): array
    {
        $labelField = $side . 'Label';

        // Row indices (original order) where the solution actually uses this side.
        $relevantIndices = [];
        foreach ($solutionPostings as $i => $posting) {
            $posting = is_array($posting) ? (object)$posting : $posting;
            $amount = $posting->{$side} ?? '';
            if ($amount !== '' && $amount !== null) {
                $relevantIndices[] = $i;
            }
        }

        // Group into blocks of consecutive identical labels within this side's
        // own sequence - same-label rows may swap order with each other.
        $blocks = [];
        $currentBlock = [];
        $currentLabel = null;
        foreach ($relevantIndices as $i) {
            $posting = is_array($solutionPostings[$i]) ? (object)$solutionPostings[$i] : $solutionPostings[$i];
            $label = trim($posting->{$labelField} ?? '');
            if ($currentLabel !== null && $label !== $currentLabel) {
                $blocks[] = $currentBlock;
                $currentBlock = [];
            }
            $currentBlock[] = $i;
            $currentLabel = $label;
        }
        if (!empty($currentBlock)) {
            $blocks[] = $currentBlock;
        }

        $rowResults = [];
        $unmatched = [];

        foreach ($blocks as $blockIndices) {
            $available = $blockIndices;
            $blockPosting = is_array($solutionPostings[$blockIndices[0]]) ? (object)$solutionPostings[$blockIndices[0]] : $solutionPostings[$blockIndices[0]];
            $blockLabel = trim($blockPosting->{$labelField} ?? '');

            foreach ($blockIndices as $rowIndex) {
                $studentRow = $studentRows[$rowIndex] ?? [];
                $studentAmount = $studentRow[$side] ?? '';
                $studentLabel = trim($studentRow[$labelField] ?? '');

                $matchedSolutionIndex = null;
                foreach ($available as $solIndex) {
                    $solPosting = is_array($solutionPostings[$solIndex]) ? (object)$solutionPostings[$solIndex] : $solutionPostings[$solIndex];
                    $solAmount = $this->parseAmount($solPosting->{$side} ?? '');
                    if ($studentLabel === $blockLabel && $studentAmount !== ''
                        && abs($this->parseAmount($studentAmount) - $solAmount) < 0.01) {
                        $matchedSolutionIndex = $solIndex;
                        break;
                    }
                }

                if ($matchedSolutionIndex !== null) {
                    $available = array_values(array_diff($available, [$matchedSolutionIndex]));
                    $rowResults[$rowIndex] = ['labelCorrect' => true, 'amountCorrect' => true, 'isCorrect' => true];
                } else {
                    $unmatched[] = $rowIndex;
                }
            }
        }

        foreach ($unmatched as $rowIndex) {
            $studentRow = $studentRows[$rowIndex] ?? [];
            $studentAmount = $studentRow[$side] ?? '';
            $studentLabel = trim($studentRow[$labelField] ?? '');

            $solPosting = isset($solutionPostings[$rowIndex])
                ? (is_array($solutionPostings[$rowIndex]) ? (object)$solutionPostings[$rowIndex] : $solutionPostings[$rowIndex])
                : null;
            $solLabel = $solPosting ? trim($solPosting->{$labelField} ?? '') : '';
            $solAmount = $solPosting ? $this->parseAmount($solPosting->{$side} ?? '') : null;

            $labelCorrect = $studentLabel === $solLabel;
            $amountCorrect = $studentAmount !== '' && $solAmount !== null
                && abs($this->parseAmount($studentAmount) - $solAmount) < 0.01;

            $rowResults[$rowIndex] = ['labelCorrect' => $labelCorrect, 'amountCorrect' => $amountCorrect, 'isCorrect' => false];
        }

        // Any row where the solution doesn't use this side at all, but the
        // student entered something (label and/or amount) here anyway, is a
        // genuine wrong answer for whichever sub-field is non-blank - the
        // "correct" value for an unused box is blank, so each sub-field is
        // still graded independently: a stray label doesn't make a correctly
        // -left-blank amount wrong too. Leaving both genuinely blank (matching
        // the solution's non-use of this side/row) stays ungraded entirely.
        foreach ($studentRows as $rowIndex => $studentRow) {
            if (in_array($rowIndex, $relevantIndices)) continue; // already graded above
            $studentAmount = $studentRow[$side] ?? '';
            $studentLabel = trim($studentRow[$labelField] ?? '');
            if ($studentAmount === '' && $studentLabel === '') continue; // genuinely blank - fine

            $labelCorrect = $studentLabel === '';
            $amountCorrect = $studentAmount === '';

            $rowResults[$rowIndex] = ['labelCorrect' => $labelCorrect, 'amountCorrect' => $amountCorrect, 'isCorrect' => $labelCorrect && $amountCorrect];
        }

        return $rowResults;
    }

    private function computeScoreForTAccounts($tAccountsSolution, $studentTAccounts): array
    {
        $results = [];
        $totalFields = 0;
        $correctFields = 0;

        foreach ($tAccountsSolution as $accountIndex => $solutionAccount) {
            if (is_array($solutionAccount)) {
                $solutionAccount = (object)$solutionAccount;
            }

            $solutionPostings = $solutionAccount->postings ?? [];
            $studentAccount = $studentTAccounts[$accountIndex] ?? ['rows' => [], 'balance' => null];
            $studentRows = $studentAccount['rows'] ?? [];

            $debitResults = $this->gradeTAccountSide($solutionPostings, $studentRows, 'debit');
            $creditResults = $this->gradeTAccountSide($solutionPostings, $studentRows, 'credit');

            $rowResults = [];
            foreach ($solutionPostings as $rowIndex => $posting) {
                $studentRow = $studentRows[$rowIndex] ?? ['debitLabel' => '', 'debit' => '', 'credit' => '', 'creditLabel' => ''];
                $debitResult = $debitResults[$rowIndex] ?? null;
                $creditResult = $creditResults[$rowIndex] ?? null;

                if ($debitResult) {
                    $totalFields += 2; // label, amount
                    if ($debitResult['labelCorrect']) $correctFields++;
                    if ($debitResult['amountCorrect']) $correctFields++;
                }
                if ($creditResult) {
                    $totalFields += 2; // label, amount
                    if ($creditResult['labelCorrect']) $correctFields++;
                    if ($creditResult['amountCorrect']) $correctFields++;
                }

                $rowResults[$rowIndex] = [
                    'debitLabel' => $studentRow['debitLabel'] ?? '',
                    'debit' => $studentRow['debit'] ?? '',
                    'credit' => $studentRow['credit'] ?? '',
                    'creditLabel' => $studentRow['creditLabel'] ?? '',
                    'debitLabelCorrect' => $debitResult['labelCorrect'] ?? null,
                    'debitCorrect' => $debitResult['amountCorrect'] ?? null,
                    'creditLabelCorrect' => $creditResult['labelCorrect'] ?? null,
                    'creditCorrect' => $creditResult['amountCorrect'] ?? null,
                    'isCorrect' => ($debitResult['isCorrect'] ?? true) && ($creditResult['isCorrect'] ?? true)
                ];
            }

            // Balance: at most one, always after postings, never reordered. Graded
            // on 4 independent fields (debitLabel, debit, creditLabel, credit),
            // same shape as a posting row - a stray label on the unused side is
            // its own wrong answer, independent of whether the amount is right.
            $balanceResult = $this->gradeSimpleBalance($solutionAccount->balance ?? null, $studentAccount['balance'] ?? null, true);
            if ($balanceResult) {
                $totalFields += 4; // debitLabel, debit, creditLabel, credit
                if ($balanceResult['debitLabelCorrect']) $correctFields++;
                if ($balanceResult['debitCorrect']) $correctFields++;
                if ($balanceResult['creditLabelCorrect']) $correctFields++;
                if ($balanceResult['creditCorrect']) $correctFields++;
            }

            // Beginning Balance: at most one, always first, never reordered - the
            // student now picks "Beginning Balance" from a label dropdown on
            // whichever side they choose, same interaction as the ending Balance
            // row, so it's graded the same way (4 independent fields). The
            // expected label is forced to the constant "Beginning Balance"
            // rather than read from the solution, since that's the only value
            // it can ever validly be - this also means old solution data (saved
            // before the label existed) still grades correctly with no
            // migration needed.
            $beginningBalanceResult = $this->gradeSimpleBalance($solutionAccount->beginningBalance ?? null, $studentAccount['beginningBalance'] ?? null, true, 'Beginning Balance');
            if ($beginningBalanceResult) {
                $totalFields += 4; // debitLabel, debit, creditLabel, credit
                if ($beginningBalanceResult['debitLabelCorrect']) $correctFields++;
                if ($beginningBalanceResult['debitCorrect']) $correctFields++;
                if ($beginningBalanceResult['creditLabelCorrect']) $correctFields++;
                if ($beginningBalanceResult['creditCorrect']) $correctFields++;
            }

            $results[$accountIndex] = [
                'accountTitle' => $solutionAccount->accountTitle ?? '',
                'rows' => $rowResults,
                'balance' => $balanceResult,
                'beginningBalance' => $beginningBalanceResult
            ];
        }

        return [
            'results' => $results,
            'totalFields' => $totalFields,
            'correctFields' => $correctFields
        ];
    }

    /**
     * Find which rows are out of order based on debit/credit sequencing.
     * Rule: All debits must come before all credits.
     *
     * Returns an array of row indices that are out of order:
     * - Credits that appear before the last debit (they're too early)
     * - Debits that appear after the first credit (they're too late)
     *
     * @param array $rows
     * @return array Array of row indices that are out of order
     */
    private function findOutOfOrderRows(array $rows): array
    {
        $outOfOrderRows = [];

        // Find the index of the first credit and last debit
        $firstCreditIndex = null;
        $lastDebitIndex = null;

        foreach ($rows as $index => $row) {
            $hasDebit = isset($row['debit']) && $row['debit'] !== '';
            $hasCredit = isset($row['credit']) && $row['credit'] !== '';

            if ($hasDebit) {
                $lastDebitIndex = $index;
            }
            if ($hasCredit && $firstCreditIndex === null) {
                $firstCreditIndex = $index;
            }
        }

        // If there's no mixing (all debits before all credits), no rows are out of order
        if ($firstCreditIndex === null || $lastDebitIndex === null || $lastDebitIndex < $firstCreditIndex) {
            return [];
        }

        // There's an order violation: some debit comes after some credit
        // Mark the specific rows that are out of order
        foreach ($rows as $index => $row) {
            $hasDebit = isset($row['debit']) && $row['debit'] !== '';
            $hasCredit = isset($row['credit']) && $row['credit'] !== '';

            // A credit is out of order if it appears before the last debit
            if ($hasCredit && $index < $lastDebitIndex) {
                $outOfOrderRows[] = $index;
            }

            // A debit is out of order if it appears after the first credit
            if ($hasDebit && $index > $firstCreditIndex) {
                $outOfOrderRows[] = $index;
            }
        }

        return array_unique($outOfOrderRows);
    }

    /**
     * Helper method to parse amount strings that may contain commas
     *
     * @param mixed $value
     * @return float
     */
    private function parseAmount($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        // Convert to string and remove commas
        $sanitized = str_replace(',', '', (string)$value);

        return floatval($sanitized);
    }
}
