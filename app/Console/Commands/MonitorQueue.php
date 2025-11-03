<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorQueue extends Command
{
    protected $signature = 'queue:monitor-status';
    protected $description = 'Monitor queue status and health';

    public function handle()
    {
        $this->info('Queue Status Monitor');
        $this->info('==================');

        // Check jobs table
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $this->info("Pending Jobs: {$pendingJobs}");
        $this->info("Failed Jobs: {$failedJobs}");

        if ($pendingJobs > 0) {
            $this->info("\nRecent Jobs:");
            $recentJobs = DB::table('jobs')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'queue', 'payload', 'created_at']);

            foreach ($recentJobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobName = $payload['displayName'] ?? 'Unknown';
                $this->info("- {$jobName} (Queue: {$job->queue}, Created: {$job->created_at})");
            }
        }

        if ($failedJobs > 0) {
            $this->warn("\nRecent Failed Jobs:");
            $recentFailed = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(3)
                ->get(['id', 'queue', 'payload', 'failed_at', 'exception']);

            foreach ($recentFailed as $job) {
                $payload = json_decode($job->payload, true);
                $jobName = $payload['displayName'] ?? 'Unknown';
                $this->warn("- {$jobName} (Failed: {$job->failed_at})");
                $this->warn("  Exception: " . substr($job->exception, 0, 100) . "...");
            }
        }

        // Check for stuck jobs (older than 1 hour)
        $stuckJobs = DB::table('jobs')
            ->where('created_at', '<', now()->subHour())
            ->count();

        if ($stuckJobs > 0) {
            $this->error("\n⚠️  {$stuckJobs} jobs are stuck (older than 1 hour)");
            $this->info("Consider running: php artisan queue:retry all");
        }

        // Check job batches
        $batchCount = DB::table('job_batches')->count();
        if ($batchCount > 0) {
            $this->info("\nJob Batches: {$batchCount}");
        }

        $this->info("\nQueue Health: " . ($pendingJobs < 100 && $failedJobs < 10 ? "✅ Good" : "⚠️  Needs Attention"));

        return 0;
    }
}
