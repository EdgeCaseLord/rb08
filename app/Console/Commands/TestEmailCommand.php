<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestEmailCommand extends Command
{
    protected $signature = 'test:email {email=daniel@pixelhoch.de}';
    protected $description = 'Test email sending functionality';

    public function handle()
    {
        $email = $this->argument('email');

        $this->info("Testing email to: {$email}");
        $this->info("Current mail driver: " . config('mail.default'));
        $this->info("Mail log channel: " . config('mail.mailers.log.channel'));

        try {
            Log::channel('email')->info('🧪 TEST EMAIL - Starting email test', [
                'to' => $email,
                'mail_driver' => config('mail.default'),
                'timestamp' => now()->toISOString(),
            ]);

            Mail::send([], [], function ($message) use ($email) {
                $message->to($email)
                    ->subject('Test Email from Laravel App')
                    ->html('<h1>Test Email</h1><p>This is a test email to verify email functionality.</p>');
            });

            $this->info('✅ Email sent successfully!');
            $this->info('Check the email log at: storage/logs/email.log');

            Log::channel('email')->info('✅ TEST EMAIL - Sent successfully', [
                'to' => $email,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            $this->error('❌ Failed to send email: ' . $e->getMessage());

            Log::channel('email')->error('❌ TEST EMAIL - Failed to send', [
                'to' => $email,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }
}
