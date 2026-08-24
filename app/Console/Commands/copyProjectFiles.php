<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class CopyProjectFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * php artisan project:copy-files
     *
     * @var string
     */
    protected $signature = 'project:copy-files
                            {--dest= : Destination directory (defaults to ~/Downloads/project_files)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find a specific set of project files anywhere in the app and copy them (flat) into a Downloads folder for re-upload';

    /**
     * The files we're looking for, matched anywhere under base_path()
     * (excluding vendor/node_modules/.git/storage).
     *
     * @var array<int, string>
     */
    protected array $files = [
        '2026_08_05_142749_add_learning_tree_to_assignment_question_learning_tree.php',
        '2026_08_13_110044_create_question_revision_propagations_table.php',
        'Assignment.php',
        'AssignmentQuestionLearningTree.php',
        'QuestionRevisionPropagation.php',
        'Submission.php',
        'AssignmentSyncQuestionController.php',
        'LearningTreeController.php',
        'LearningTreeNodeAssignmentQuestionController.php',
        'LearningTreeNodeController.php',
        'LearningTreeNodeSubmissionController.php',
        'QuestionController.php',
        'LearningTreeNodeAssignmentQuestionPolicy.php',
        'LearningTreePolicy.php',
        'SetAppVersionHeader.php',
        'api.php',
        'backfillLearningTreeQuestionRevisions.php',
        'backfillLearningTreeSnapshotHtml.php',
        'BackfillLearningTreeQuestionRevisionsTest.php',
        'LearningTreeAssignmentQuestionTest.php',
        'LearningTreeNeedsUpdateTest.php',
        'LearningTreeNodeTest.php',
        'LearningTreeQuestionRevisionSyncTest.php',
        'LearningTreeQuestionsViewTest.php',
        'LearningTreeStructuralAutoUpdateTest.php',
        'LearningTreeUpdateToLatestRevisionTest.php',
        'learning_trees.editor.vue',
        'LearningTreeProperties.vue',
        'QuestionRevisionDifferences.vue',
        'questions.view.vue',
        'UpdateLearningTreeRevision.vue',
    ];

    /**
     * Directories to skip while scanning the project.
     *
     * @var array<int, string>
     */
    protected array $excludedDirs = ['vendor', 'node_modules', '.git', 'storage', 'bootstrap/cache'];

    public function handle(): int
    {
        $destination = $this->option('dest') ?: $this->defaultDestination();

        if (! is_dir($destination) && ! mkdir($destination, 0755, true) && ! is_dir($destination)) {
            $this->error("Could not create destination directory: {$destination}");

            return 1;
        }

        $this->info('Searching under: '.base_path());
        $this->info('Copying into:    '.$destination);
        $this->line('');

        $index = $this->indexProjectFiles();

        $found = 0;
        $missing = [];
        $duplicates = [];

        foreach ($this->files as $filename) {
            $matches = $index[$filename] ?? [];

            if (empty($matches)) {
                $missing[] = $filename;

                continue;
            }

            copy($matches[0], $destination.DIRECTORY_SEPARATOR.$filename);
            $found++;

            if (count($matches) > 1) {
                $this->line("  copied: {$filename}  (multiple matches found, used first)");
                $duplicates[$filename] = $matches;
            } else {
                $this->line("  copied: {$filename}");
            }
        }

        $this->line('');
        $this->info('-----------------------------------------');
        $this->info("Done. Copied {$found} / ".count($this->files).' files.');
        $this->info("Output folder: {$destination}");

        if (! empty($missing)) {
            $this->line('');
            $this->warn('Not found ('.count($missing).'):');
            foreach ($missing as $m) {
                $this->line("  - {$m}");
            }
        }

        if (! empty($duplicates)) {
            $this->line('');
            $this->warn('Multiple matches found (double-check these):');
            foreach ($duplicates as $filename => $paths) {
                $this->line("  - {$filename}:");
                foreach ($paths as $path) {
                    $this->line("      {$path}");
                }
            }
        }

        return 0;
    }

    /**
     * Walk the project once and build an index of [filename => [full paths]]
     * for every file in $this->files, so we don't re-scan per file.
     *
     * @return array<string, array<int, string>>
     */
    protected function indexProjectFiles(): array
    {
        $wanted = array_fill_keys($this->files, true);
        $index = [];

        $finder = new Finder();
        $finder->files()
            ->in(base_path())
            ->exclude($this->excludedDirs)
            ->ignoreDotFiles(false)
            ->ignoreVCS(true);

        foreach ($finder as $file) {
            $name = $file->getFilename();

            if (isset($wanted[$name])) {
                $index[$name][] = $file->getRealPath();
            }
        }

        return $index;
    }

    protected function defaultDestination(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        return rtrim($home, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'Downloads'.DIRECTORY_SEPARATOR.'project_files';
    }
}
