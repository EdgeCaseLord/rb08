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
            Mail::send([], [], function ($message) use ($email) {
                $message->to($email)
                    ->subject('Test Email from Laravel App')
                    ->html('<h1>Test Email</h1><p>This is a test email to verify email functionality.</p>');
            });

            $this->info('Email sent successfully!');
            $this->info('Check the email log at: storage/logs/email.log');

        } catch (\Exception $e) {
            $this->error('Failed to send email: ' . $e->getMessage());
            Log::error('Email test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
