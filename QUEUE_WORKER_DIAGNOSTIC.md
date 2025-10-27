# Queue Worker Diagnostic & Fix Guide

## Current Issue
Queue worker daemon is running but not logging for a week, even after restart.

## Diagnostic Steps

### 1. Check Queue Worker Status
```bash
# Check if worker is actually processing jobs
sudo supervisorctl status daemon-498059

# Check the daemon log file
tail -f /home/forge/.forge/daemon-498059.log

# Check if there are any jobs in the queue
php artisan queue:monitor
```

### 2. Check Queue Configuration
```bash
# Check current queue connection
php artisan tinker
>>> config('queue.default')

# Check if jobs table exists and has data
php artisan tinker
>>> DB::table('jobs')->count()
>>> DB::table('failed_jobs')->count()
```

### 3. Test Queue Manually
```bash
# Test if queue worker can process jobs
php artisan queue:work --once --verbose

# Check for failed jobs
php artisan queue:failed
```

## Potential Issues & Fixes

### Issue 1: No Jobs Being Dispatched
**Symptoms**: Worker running but no jobs to process
**Check**:
```bash
# Look for job dispatching in logs
grep -r "dispatch\|queue" storage/logs/
```

### Issue 2: Database Connection Issues
**Symptoms**: Worker can't connect to database
**Fix**: Update queue configuration
```php
// In config/queue.php, ensure database connection is correct
'database' => [
    'driver' => 'database',
    'connection' => env('DB_CONNECTION', 'sqlite'), // Make sure this matches your DB
    'table' => 'jobs',
    'queue' => 'default',
    'retry_after' => 90,
],
```

### Issue 3: Logging Configuration
**Symptoms**: Jobs processing but not logging
**Fix**: Add verbose logging to queue worker
```bash
# Update supervisor config to include verbose flag
command=php /home/forge/myintest-rezepte.de/artisan queue:work --sleep=3 --tries=3 --verbose
```

### Issue 4: Memory/Timeout Issues
**Symptoms**: Worker crashes silently
**Fix**: Add memory and timeout limits
```bash
# Update supervisor config
command=php /home/forge/myintest-rezepte.de/artisan queue:work --sleep=3 --tries=3 --timeout=300 --memory=512
```

## Recommended Supervisor Configuration

```ini
[program:daemon-498059]
directory=/home/forge/myintest-rezepte.de/
command=php /home/forge/myintest-rezepte.de/artisan queue:work --sleep=3 --tries=3 --verbose --timeout=300 --memory=512
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
user=forge
numprocs=1
startsecs=1
redirect_stderr=true
stdout_logfile=/home/forge/.forge/daemon-498059.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=10
stopsignal=SIGTERM
stopasgroup=true
killasgroup=true
environment=LARAVEL_ENV="production"
```

## Debug Commands

### 1. Check Job Status
```bash
# See what's in the jobs table
php artisan tinker
>>> DB::table('jobs')->orderBy('created_at', 'desc')->limit(10)->get()

# Check failed jobs
>>> DB::table('failed_jobs')->orderBy('failed_at', 'desc')->limit(10)->get()
```

### 2. Test Job Dispatch
```bash
# Create a test job
php artisan tinker
>>> App\Jobs\CreateBookJob::dispatch(App\Models\User::first(), [1,2,3])

# Check if it appears in jobs table
>>> DB::table('jobs')->count()
```

### 3. Monitor Queue Processing
```bash
# Run worker manually with verbose output
php artisan queue:work --verbose --timeout=60

# Check Laravel logs
tail -f storage/logs/laravel.log | grep -i "queue\|job"
```

## Common Fixes

### Fix 1: Restart with New Configuration
```bash
# Stop the daemon
sudo supervisorctl stop daemon-498059

# Update supervisor config with verbose flag
sudo supervisorctl reread
sudo supervisorctl update

# Start the daemon
sudo supervisorctl start daemon-498059

# Check status
sudo supervisorctl status daemon-498059
```

### Fix 2: Clear Failed Jobs
```bash
# Clear all failed jobs
php artisan queue:flush

# Retry failed jobs
php artisan queue:retry all
```

### Fix 3: Check Database Permissions
```bash
# Ensure forge user can write to database
sudo -u forge php artisan migrate:status
```

## Monitoring Commands

### Real-time Monitoring
```bash
# Monitor daemon log
tail -f /home/forge/.forge/daemon-498059.log

# Monitor Laravel logs
tail -f storage/logs/laravel.log | grep -i "queue\|job\|CreateBookJob"

# Monitor system resources
htop
```

### Check Queue Health
```bash
# Check queue status
php artisan queue:monitor

# Check for stuck jobs
php artisan tinker
>>> DB::table('jobs')->where('created_at', '<', now()->subHours(1))->count()
```

## Next Steps
1. Run diagnostic commands to identify the issue
2. Update supervisor configuration with verbose logging
3. Test job dispatch manually
4. Monitor logs for job processing
5. Consider switching to Redis queue if database issues persist
