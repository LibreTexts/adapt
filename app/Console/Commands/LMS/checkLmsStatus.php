<?php

namespace App\Console\Commands\LMS;

use App\Exceptions\Handler;
use App\LmsStatusCheck;
use App\LmsStatusProbe;
use App\Mail\LmsStatusChanged;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class checkLmsStatus extends Command
{
    /**
     * @var string
     */
    protected $signature = 'check:lmsStatus
                            {--no-email : Record results but do not send the status-change email}
                            {--no-retry : Do not retry "down" results before recording them}
                            {--test-email : Send the alert email now, whether or not anything changed (subject starts with [TEST])}';

    /**
     * @var string
     */
    protected $description = "Checks every active LTI registration (Canvas, Brightspace, Blackboard, Moodle) with ADAPT's own LTI key, records up/down/auth_error, and emails when a school's status changes.";

    /**
     * @param LmsStatusProbe $probe
     * @return int
     */
    public function handle(LmsStatusProbe $probe): int
    {
        try {
            $registrations = $probe->registrations();
            if ($registrations->isEmpty()) {
                $this->info('No active LTI registrations to check.');
                return 0;
            }

            $previous = $this->latestStatuses();
            $results = $probe->probeAll($registrations, $this->option('no-retry') ? 0 : 5);
            $checkedAt = Carbon::now();

            $rows = [];
            $changes = [];
            foreach ($registrations as $registration) {
                $result = $results[$registration->id];
                $rows[] = array_merge($result, [
                    'lti_registration_id' => $registration->id,
                    'checked_at' => $checkedAt,
                ]);
                $before = $previous[$registration->id] ?? null;
                // No alert the first time a school is ever checked: there's nothing to compare with.
                if ($before && $before !== $result['status']) {
                    $changes[] = $this->change($registration, $result, $before);
                }
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                LmsStatusCheck::insert($chunk);
            }

            $retentionHours = max(1, (int)config('myconfig.lms_status_retention_hours', 72));
            LmsStatusCheck::where('checked_at', '<', $checkedAt->copy()->subHours($retentionHours))->delete();

            $this->printSummary($registrations, $results, $changes);

            if ($this->option('test-email')) {
                // Show whatever currently isn't up, as if it had just changed, so the email has realistic content.
                $sample = $changes;
                if (!$sample) {
                    foreach ($registrations as $registration) {
                        $result = $results[$registration->id];
                        if ($result['status'] !== LmsStatusCheck::UP) {
                            $sample[] = $this->change($registration, $result, $previous[$registration->id] ?? LmsStatusCheck::UP);
                        }
                    }
                }
                $this->sendEmails($sample, $results, $registrations, true);
            } elseif ($changes && !$this->option('no-email')) {
                $this->sendEmails($changes, $results, $registrations, false);
            }
            return 0;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $this->error($e->getMessage());
            return 1;
        }
    }

    private function change($registration, array $result, string $from): array
    {
        return array_merge($result, [
            'name' => $registration->name,
            'lms' => LmsStatusProbe::LMS_LABELS[$registration->lms] ?? $registration->lms,
            'base_url' => $registration->base_url,
            'from' => $from,
        ]);
    }

    /**
     * Each registration's most recent recorded status.
     *
     * @return array lti_registration_id => status
     */
    private function latestStatuses(): array
    {
        $latest = DB::table('lms_status_checks')
            ->select('lti_registration_id', DB::raw('MAX(checked_at) AS checked_at'))
            ->groupBy('lti_registration_id');

        return DB::table('lms_status_checks')
            ->joinSub($latest, 'latest', function ($join) {
                $join->on('lms_status_checks.lti_registration_id', '=', 'latest.lti_registration_id')
                    ->on('lms_status_checks.checked_at', '=', 'latest.checked_at');
            })
            ->pluck('lms_status_checks.status', 'lms_status_checks.lti_registration_id')
            ->all();
    }

    /**
     * @param array $changes
     * @param array $results
     * @param $registrations
     * @param bool $isTest
     */
    private function sendEmails(array $changes, array $results, $registrations, bool $isTest): void
    {
        $userIds = array_filter(array_map('intval', explode(',', (string)config('myconfig.lms_status_alert_user_ids'))));
        $recipients = DB::table('users')
            ->whereIn('id', $userIds ?: [0])
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->values()
            ->all();
        if (!$recipients) {
            $this->warn('There is nobody to email: no users found for LMS_STATUS_ALERT_USER_IDS (default 1,5).');
            return;
        }

        $counts = array_count_values(array_column($results, 'status'));
        $byLms = [];
        foreach ($registrations as $registration) {
            $label = LmsStatusProbe::LMS_LABELS[$registration->lms] ?? $registration->lms;
            $byLms[$label] = $byLms[$label] ?? ['total' => 0, 'up' => 0];
            $byLms[$label]['total']++;
            if ($results[$registration->id]['status'] === LmsStatusCheck::UP) {
                $byLms[$label]['up']++;
            }
        }
        ksort($byLms);

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new LmsStatusChanged($changes, [
                    'total' => count($results),
                    'up' => $counts[LmsStatusCheck::UP] ?? 0,
                    'down' => $counts[LmsStatusCheck::DOWN] ?? 0,
                    'auth_error' => $counts[LmsStatusCheck::AUTH_ERROR] ?? 0,
                    'by_lms' => $byLms,
                ], $isTest));
            } catch (Exception $e) {
                $h = new Handler(app());
                $h->report($e);
                $this->error("Could not email $email: " . $e->getMessage());
            }
        }
        $this->info(($isTest ? 'Test email sent' : 'Emailed') . ' to: ' . implode(', ', $recipients) . " (mailer: " . config('mail.default') . ')');
    }

    private function printSummary($registrations, array $results, array $changes): void
    {
        $this->table(['ID', 'LMS', 'School', 'Status', 'HTTP', 'ms', 'Error'],
            $registrations->map(function ($registration) use ($results) {
                $r = $results[$registration->id];
                return [$registration->id, LmsStatusProbe::LMS_LABELS[$registration->lms] ?? $registration->lms,
                    $registration->name, $r['status'], $r['http_code'], $r['response_ms'], $r['error']];
            })->all());
        $counts = array_count_values(array_column($results, 'status'));
        $this->info(sprintf('%d checked: %d up, %d down, %d auth errors. %d status change(s).',
            count($results), $counts['up'] ?? 0, $counts['down'] ?? 0, $counts['auth_error'] ?? 0, count($changes)));
    }
}
