<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Jobs\CreateBookJob;
use App\Models\User;

class TestQueueWorker extends Command
{
    protected $signature = 'test:queue-worker';
    protected $description = 'Test queue worker functionality';

    public function handle()
    {
        $this->info('Testing Queue Worker...');

        // 1. Check queue configuration
        $this->info('Queue Connection: ' . config('queue.default'));
        $this->info('Database Connection: ' . config('database.default'));

        // 2. Check jobs table
        try {
            $jobCount = DB::table('jobs')->count();
            $failedCount = DB::table('failed_jobs')->count();
            $this->info("Jobs in queue: {$jobCount}");
            $this->info("Failed jobs: {$failedCount}");
        } catch (\Exception $e) {
            $this->error("Database error: " . $e->getMessage());
            return 1;
        }

        // 3. Test job dispatch
        try {
            $user = User::first();
            if (!$user) {
                $this->error('No users found in database');
                return 1;
            }

            $this->info("Dispatching test job for user: {$user->name}");
            CreateBookJob::dispatch($user, [1, 2, 3]);

            // Check if job was added
            $newJobCount = DB::table('jobs')->count();
            $this->info("Jobs after dispatch: {$newJobCount}");

            if ($newJobCount > $jobCount) {
                $this->info('✅ Job dispatch successful');
            } else {
                $this->error('❌ Job dispatch failed');
            }

        } catch (\Exception $e) {
            $this->error("Job dispatch error: " . $e->getMessage());
            return 1;
        }

        // 4. Test logging
        Log::info('Queue worker test completed', [
            'timestamp' => now(),
            'job_count' => $jobCount,
            'failed_count' => $failedCount
        ]);

        $this->info('✅ Queue worker test completed');
        $this->info('Check storage/logs/laravel.log for detailed logs');

        return 0;
    }
}
