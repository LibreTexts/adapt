<?php

namespace App\Console\Commands\OneTimers;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\DB;
use Throwable;

class fixMultipleChoiceQuestions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * php artisan fix:multipleChoiceQuestions 10444
     * php artisan fix:multipleChoiceQuestions 10444 --dry-run
     *
     * @var string
     */
    protected $signature = 'fix:multipleChoiceQuestions {course_id=10444} {--dry-run : Report what would change without writing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For a course\'s multiple_choice questions: sets randomizeOrder to "No" and centers every <img> found anywhere in the qti_json, then deletes seeds for every assignment that gets touched';

    private const IMAGE_ALIGN_CLASS = 'image-align-center';

    /** @var array<int, object|null> cache of question_revisions rows keyed by revision id */
    private array $revisionCache = [];

    /** @var array<int, object|null> cache of questions rows keyed by question id */
    private array $questionCache = [];

    /** @var array<int, int|null> cache of "latest revision id for a question_id" */
    private array $latestRevisionCache = [];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws Throwable
     */
    public function handle()
    {
        $courseId = (int)$this->argument('course_id');
        $dryRun = (bool)$this->option('dry-run');
        $prefix = $dryRun ? '[DRY RUN] ' : '';

        $this->info("{$prefix}Processing course_id={$courseId}");

        $stats = [
            'assignments_scanned' => 0,
            'assignment_questions_scanned' => 0,
            'revisions_updated' => 0,
            'questions_updated' => 0,
            'images_centered' => 0,
            'assignment_question_counts' => [], // assignment_id => number of questions changed
            'assignment_image_counts' => [],    // assignment_id => number of images centered
        ];

        try {
            DB::beginTransaction();

            $assignmentIds = DB::table('assignments')
                ->where('course_id', $courseId)
                ->pluck('id');

            $stats['assignments_scanned'] = $assignmentIds->count();

            if ($assignmentIds->isEmpty()) {
                $this->warn("No assignments found for course_id={$courseId}");
                DB::rollBack();
                return 0;
            }

            $assignmentQuestions = DB::table('assignment_question')
                ->whereIn('assignment_id', $assignmentIds)
                ->select('id', 'assignment_id', 'question_id', 'question_revision_id')
                ->get();

            $stats['assignment_questions_scanned'] = $assignmentQuestions->count();

            $touchedAssignmentIds = [];

            foreach ($assignmentQuestions as $aq) {
                $result = $this->processAssignmentQuestion($aq, $dryRun, $stats, $prefix);

                if ($result['changed']) {
                    $touchedAssignmentIds[$aq->assignment_id] = true;
                    $stats['assignment_question_counts'][$aq->assignment_id] =
                        ($stats['assignment_question_counts'][$aq->assignment_id] ?? 0) + 1;
                }

                if ($result['images'] > 0) {
                    $stats['assignment_image_counts'][$aq->assignment_id] =
                        ($stats['assignment_image_counts'][$aq->assignment_id] ?? 0) + $result['images'];
                }
            }

            $touchedAssignmentIds = array_keys($touchedAssignmentIds);
            sort($touchedAssignmentIds);

            $seedsQuery = DB::table('seeds')->whereIn('assignment_id', $touchedAssignmentIds);
            $seedsCount = empty($touchedAssignmentIds) ? 0 : $seedsQuery->count();

            $this->line("");
            $this->info(sprintf(
                '%s%d seed row(s) %s for %d touched assignment(s).',
                $prefix,
                $seedsCount,
                $dryRun ? 'would be deleted' : 'deleted',
                count($touchedAssignmentIds)
            ));

            if (!$dryRun && !empty($touchedAssignmentIds)) {
                $seedsQuery->delete();
            }

            $this->printSummary($stats, $seedsCount, $touchedAssignmentIds, $dryRun);
            $this->printCourseAssignmentBreakdown(
                $courseId,
                $stats['assignment_question_counts'],
                $stats['assignment_image_counts']
            );

            if ($dryRun) {
                // Belt-and-braces: nothing should have been written, but never persist a dry run.
                DB::rollBack();
            } else {
                DB::commit();
            }

            return 0;
        } catch (Exception $e) {
            DB::rollBack();
            $h = new Handler(app());
            $h->report($e);
            $this->error($e->getMessage());
            return 1;
        }
    }

    /**
     * Handle a single assignment_question row.
     *
     * @return array{changed: bool, images: int}
     */
    private function processAssignmentQuestion(object $aq, bool $dryRun, array &$stats, string $prefix): array
    {
        $questionId = $aq->question_id;
        $revisionId = $aq->question_revision_id;
        $changed = false;
        $images = 0;

        if ($revisionId) {
            $revision = $this->getRevision($revisionId);

            if ($revision && $revision->qti_json_type === 'multiple_choice') {
                $fixed = $this->applyFixes($revision->qti_json);

                if ($fixed !== null) {
                    $this->line(sprintf(
                        '%sassignment_question #%d (assignment %d): updating question_revision #%d (question #%d)%s',
                        $prefix,
                        $aq->id,
                        $aq->assignment_id,
                        $revisionId,
                        $questionId,
                        $fixed['images_centered'] > 0 ? " [{$fixed['images_centered']} image(s) centered]" : ''
                    ));

                    if (!$dryRun) {
                        DB::table('question_revisions')->where('id', $revisionId)->update(['qti_json' => $fixed['json'], 'updated_at' => now()]);
                    }

                    $stats['revisions_updated']++;
                    $stats['images_centered'] += $fixed['images_centered'];
                    $changed = true;
                    $images += $fixed['images_centered'];

                    $latestRevisionId = $this->getLatestRevisionId($questionId);

                    if ($latestRevisionId && (int)$latestRevisionId === (int)$revisionId) {
                        $questionResult = $this->updateQuestionRow($questionId, $dryRun, $stats);

                        if ($questionResult['changed']) {
                            $this->line(sprintf(
                                '%s  -> revision #%d is the latest for question #%d, also updating the question row%s',
                                $prefix,
                                $revisionId,
                                $questionId,
                                $questionResult['images'] > 0 ? " [{$questionResult['images']} image(s) centered]" : ''
                            ));

                            $images += $questionResult['images'];
                        }
                    }
                }
            }

            return ['changed' => $changed, 'images' => $images];
        }

        // No revision reference on assignment_question -> the question row is the source of truth.
        $questionResult = $this->updateQuestionRow($questionId, $dryRun, $stats);

        if ($questionResult['changed']) {
            $this->line(sprintf(
                '%sassignment_question #%d (assignment %d): updating question #%d directly (no revision on record)%s',
                $prefix,
                $aq->id,
                $aq->assignment_id,
                $questionId,
                $questionResult['images'] > 0 ? " [{$questionResult['images']} image(s) centered]" : ''
            ));

            return ['changed' => true, 'images' => $questionResult['images']];
        }

        return ['changed' => false, 'images' => 0];
    }

    private function getRevision(int $revisionId): ?object
    {
        if (!array_key_exists($revisionId, $this->revisionCache)) {
            $this->revisionCache[$revisionId] = DB::table('question_revisions')->where('id', $revisionId)->first();
        }

        return $this->revisionCache[$revisionId];
    }

    private function getQuestion(int $questionId): ?object
    {
        if (!array_key_exists($questionId, $this->questionCache)) {
            $this->questionCache[$questionId] = DB::table('questions')->where('id', $questionId)->first();
        }

        return $this->questionCache[$questionId];
    }

    private function getLatestRevisionId(int $questionId): ?int
    {
        if (!array_key_exists($questionId, $this->latestRevisionCache)) {
            $this->latestRevisionCache[$questionId] = DB::table('question_revisions')
                ->where('question_id', $questionId)
                ->max('id');
        }

        return $this->latestRevisionCache[$questionId];
    }

    /**
     * Update the base `questions` row for $questionId if it is multiple_choice.
     *
     * @return array{changed: bool, images: int}
     */
    private function updateQuestionRow(int $questionId, bool $dryRun, array &$stats): array
    {
        $question = $this->getQuestion($questionId);

        if (!$question || $question->qti_json_type !== 'multiple_choice') {
            return ['changed' => false, 'images' => 0];
        }

        $fixed = $this->applyFixes($question->qti_json);

        if ($fixed === null) {
            return ['changed' => false, 'images' => 0];
        }

        if (!$dryRun) {
            DB::table('questions')->where('id', $questionId)->update(['qti_json' => $fixed['json'], 'updated_at'=>now()]);
        }

        // Keep the cache in sync in case this question is hit again later in the run.
        $question->qti_json = $fixed['json'];
        $question->updated_at = now();
        $this->questionCache[$questionId] = $question;

        $stats['questions_updated']++;
        $stats['images_centered'] += $fixed['images_centered'];

        return ['changed' => true, 'images' => $fixed['images_centered']];
    }

    /**
     * Decode qti_json, force randomizeOrder to "No", center every <img> found anywhere in the
     * payload (prompt, answer choices, feedback, etc.), and re-encode.
     *
     * Returns null if the json is empty or can't be decoded, so callers can skip it safely.
     *
     * @return array{json: string, images_centered: int}|null
     */
    private function applyFixes(?string $qtiJson): ?array
    {
        if (empty($qtiJson)) {
            return null;
        }

        $decoded = json_decode($qtiJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        $decoded['randomizeOrder'] = 'No';

        $imagesCentered = 0;
        $decoded = $this->centerAllImages($decoded, $imagesCentered);

        return [
            'json' => json_encode($decoded),
            'images_centered' => $imagesCentered,
        ];
    }

    /**
     * Recursively walk an arbitrary decoded JSON structure and run any string value that
     * contains an <img> tag through centerImagesInHtml(), regardless of which key it lives under
     * (prompt, simpleChoice values, feedback, hint, etc.).
     */
    private function centerAllImages($data, int &$imagesCentered)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->centerAllImages($value, $imagesCentered);
            }

            return $data;
        }

        if (is_string($data) && stripos($data, '<img') !== false) {
            return $this->centerImagesInHtml($data, $imagesCentered);
        }

        return $data;
    }

    /**
     * Ensure every <img> in an HTML fragment sits inside a <p class="image-align-center">.
     * If the <img> is already wrapped in a <p>, the class is merged in (no duplicates).
     * Otherwise the <img> is wrapped in a brand-new <p class="image-align-center">.
     */
    private function centerImagesInHtml(string $html, int &$imagesCentered): string
    {
        if (stripos($html, '<img') === false) {
            return $html;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);

        // Wrap in a throwaway container so loadHTML doesn't inject <html><body> we'd have to strip,
        // and force UTF-8 so multi-byte characters in the fragment survive the round trip.
        $wrapped = '<?xml encoding="utf-8" ?><div id="__root__">' . $html . '</div>';
        $loaded = $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        if (!$loaded) {
            return $html;
        }

        $xpath = new DOMXPath($dom);
        $images = $xpath->query('//img');

        foreach ($images as $img) {
            $parent = $img->parentNode;

            if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'p') {
                $this->addClass($parent, self::IMAGE_ALIGN_CLASS);
            } else {
                $wrapper = $dom->createElement('p');
                $wrapper->setAttribute('class', self::IMAGE_ALIGN_CLASS);
                $parent->insertBefore($wrapper, $img);
                $wrapper->appendChild($img); // moves the existing <img> node into the wrapper
            }

            $imagesCentered++;
        }

        $container = $dom->getElementById('__root__');

        if (!$container) {
            return $html;
        }

        $innerHtml = '';
        foreach ($container->childNodes as $child) {
            $innerHtml .= $dom->saveHTML($child);
        }

        return $innerHtml;
    }

    private function addClass(DOMElement $element, string $class): void
    {
        $existing = $element->getAttribute('class');
        $classes = $existing !== '' ? preg_split('/\s+/', trim($existing)) : [];

        if (!in_array($class, $classes, true)) {
            $classes[] = $class;
            $element->setAttribute('class', implode(' ', $classes));
        }
    }

    private function printSummary(array $stats, int $seedsCount, array $touchedAssignmentIds, bool $dryRun): void
    {
        $prefix = $dryRun ? '[DRY RUN] ' : '';

        $this->line("");
        $this->info($prefix . 'Summary');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Assignments scanned (in course)', $stats['assignments_scanned']],
                ['Assignment_question rows scanned', $stats['assignment_questions_scanned']],
                ['Question_revisions updated', $stats['revisions_updated']],
                ['Questions updated', $stats['questions_updated']],
                ['Images centered', $stats['images_centered']],
                ['Assignments touched', count($touchedAssignmentIds)],
                ['Seeds ' . ($dryRun ? 'that would be deleted' : 'deleted'), $seedsCount],
            ]
        );

        if (!empty($touchedAssignmentIds)) {
            $this->line("");
            $this->info('Touched assignment IDs: ' . implode(', ', $touchedAssignmentIds));
        }
    }

    /**
     * Prints Course name -> Assignment name -> number of questions updated / images centered,
     * for every assignment that had at least one change.
     *
     * @param array<int, int> $assignmentQuestionCounts assignment_id => count of questions changed
     * @param array<int, int> $assignmentImageCounts assignment_id => count of images centered
     */
    private function printCourseAssignmentBreakdown(
        int   $courseId,
        array $assignmentQuestionCounts,
        array $assignmentImageCounts
    ): void
    {
        $this->line("");
        $this->info('Breakdown by course / assignment');

        if (empty($assignmentQuestionCounts)) {
            $this->line('  (no assignments were touched)');
            return;
        }

        $courseName = DB::table('courses')->where('id', $courseId)->value('name') ?? "(course #{$courseId})";

        $assignmentNames = DB::table('assignments')
            ->whereIn('id', array_keys($assignmentQuestionCounts))
            ->pluck('name', 'id');

        $rows = [];
        $totalQuestions = 0;
        $totalImages = 0;

        foreach ($assignmentQuestionCounts as $assignmentId => $count) {
            $imageCount = $assignmentImageCounts[$assignmentId] ?? 0;

            $rows[] = [
                $courseName,
                $assignmentNames[$assignmentId] ?? "(assignment #{$assignmentId})",
                $count,
                $imageCount,
            ];

            $totalQuestions += $count;
            $totalImages += $imageCount;
        }

        // Sort by assignment name for readability.
        usort($rows, fn($a, $b) => strcmp($a[1], $b[1]));

        $rows[] = ['', 'TOTAL', $totalQuestions, $totalImages];

        $this->table(['Course', 'Assignment', 'Questions Updated', 'Images Centered'], $rows);
    }
}
