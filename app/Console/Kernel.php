<?php

namespace App\Console;

use App\Jobs\LogFromCRONJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\OneTimers\storeQuestions::class,
        Commands\H5P\storeH5P::class,
        Commands\OneTimers\storeWebwork::class,
        Commands\Database\DbBackup::class,
        Commands\Notifications\sendAssignmentDueReminderEmails::class,
        Commands\dataShopToS3::class

    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        $schedule->command('process:autoRelease')->everyMinute();


        if (env('APP_ENV') === 'local') {
            $schedule->command('check:repeatedAssignmentGroups')->dailyAt('12:00');

        }


        if (env('APP_ENV') !== 'local') {
            $schedule->command('notify:LatestErrors')->everyFiveMinutes();
            //$schedule->command('retry:FailedGradePassbacks')->daily();
            $schedule->command('get:editorImageHandles')->hourly();
            $schedule->command('get:emptyParagraphNonTechnologyHtml')->hourly();

        }

        /*if (env('APP_ENV') !== 'dev') {
            $schedule->command('import:allH5P', ['minutes', '5'])->everyMinute();
        }*/

        $schedule->command('remove:NoRoles')->everyMinute();
        if (env('APP_ENV') === 'production') {
            $schedule->command('delete:cloudFlareVideosMarkedForDeletion')->everyThirtyMinutes();
            $schedule->command('fix:BadTranscriptionQuestionMediaUploads')->everyThirtyMinutes();
            $schedule->command('fix:DiscussionComment502Errors')->everyFiveMinutes();
            $schedule->command('remove:pendingCourseInvitationsFromClosedCourses')->daily();
            //$schedule->command('flashcards:process-tts-batch --batch=50')->everyFiveMinutes();
            $schedule->command('auto:registerAndEnrollForNonLMSOnlyCourse')->daily();
            $schedule->command('send:frameworkItemsByQuestionUpdates')->daily();
            $schedule->command('notify:failedTranscriptions')->hourly();
            $schedule->command('get:slowDatabaseQueriesSummary')->daily();
            $schedule->command('remove:oldSubmissionConfirmations')->daily();
            $schedule->command('email:studentsWithSubmissions')->everyMinute();
            $schedule->command('cache:IMathSolutions')->everyMinute();
            $schedule->command('delete:OldFCMTokens')
                ->cron('0 0 1 */2 *'); // midnight on the 1st, every 2 months

            $schedule->command('email:submissionFeedbackSummary')->daily();
            $schedule->command('email:pendingQuestionRevisionNotifications')->daily();
            $schedule->command('cache:Metrics')->daily();
            $schedule->command('notify:instructorCanvasMaxAttemptsError')->hourly();

            $schedule->command('passback:manualPendingScores')->everyMinute();
            $schedule->command('notify:LmsMaximumNumberOfAttemptsError')->everyFiveMinutes();
            $schedule->command('passback:pendingScores')->everyMinute();

            $schedule->command('get:NonTechnologiesWithNullNonTechnologyHtml')->hourly();
            $schedule->command('get:nullLmsGradePassbacks')->hourly();
            $schedule->command('get:badWebworks')->everySixHours();

            $schedule->command('get:nonAdaptQuestions')->everySixHours();
            $schedule->command('notification:sendAssignmentDueReminderEmails')->everyMinute();

            $schedule->command('remove:oldCurrentQuestionEditors')->everyMinute();
            $schedule->command('remove:pendingQuestionOwnershipTransfers')->daily();
            $schedule->command('remove:unenrolledTestingStudents')->daily();

            $schedule->command('notify:BetaCourseApprovals')->daily();

            $schedule->command('email:instructorsWithConcludedCourses')->daily();
            $schedule->command('remove:localQtiFiles')->weekly();

            $schedule->command('check:nullH5pTypes')->daily();
            $schedule->command('save:dataShopsToS3')->daily();
            $schedule->command('import:dataShopsFromS3')->daily();

            /* grader notifications */
            $schedule->command('notify:gradersForDueAssignments')->hourly();
            $schedule->command('notify:gradersForLateSubmissions')->daily();
            $schedule->command('notify:gradersReminders')->daily();
            /* end grader notifications */
            $schedule->command('check:AssignTos')->twiceDaily();
            $schedule->command('remove:oldValidationCodes')->everyMinute();
            $schedule->command('find:accents')->daily();

            $schedule->command('find:richTextError')->twiceDaily();

        }

        if (env('APP_ENV') === 'dev') {
            $schedule->command('s3:backup')->daily();
            $schedule->command('fix:unprocessedTranscriptions')->everyMinute();
        }

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
