<?php

namespace App\Console\Commands;

use App\Http\Controllers\EmailReceiveController;
use Illuminate\Console\Command;

class FetchEmails extends Command
{
    /**
     * The name and signature of the console command.
     *Listed Gmail messages
     *
     * @var string
     */
    protected $signature = 'emails:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch emails from Gmail and store them in the database';

    /**
     * The EmailTestController instance.
     *
     * @var \App\Http\Controllers\EmailReceiveController
     */
    protected $emailController;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(EmailReceiveController $emailController)
    {
        parent::__construct();
        $this->emailController = $emailController;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Fetching emails...');

        try {
            $response = $this->emailController->receiveEmails();
            $data = json_decode($response->getContent(), true);

            $this->info('Successfully fetched emails: '.($data['count_fetched'] ?? 0));

            if (isset($data['summary_of_emails']) && ! empty($data['summary_of_emails'])) {
                $this->table(
                    ['ID', 'Gmail Message ID', 'From', 'Subject', 'Date', 'Status'],
                    array_map(function ($email) {
                        return [
                            $email['id'],
                            $email['gmail_message_id'],
                            $email['from'],
                            $email['subject'],
                            $email['date'],
                            $email['status'],
                        ];
                    }, $data['summary_of_emails'])
                );
            }

            return 0; // Success
        } catch (\Exception $e) {
            $this->error('Failed to fetch emails: '.$e->getMessage());

            return 1; // Error
        }
    }
}
