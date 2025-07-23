<?php

namespace App\Console\Commands;

use App\Http\Controllers\EmailTestController;
use Illuminate\Console\Command;

class FetchEmails extends Command
{
    /**
     * The name and signature of the console command.
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
     * @var \App\Http\Controllers\EmailTestController
     */
    protected $emailController;

    /**
     * Create a new command instance.
     *
     * @param  \App\Http\Controllers\EmailTestController  $emailController
     * @return void
     */
    public function __construct(EmailTestController $emailController)
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
            $response = $this->emailController->receiveTestEmails();
            $data = json_decode($response->getContent(), true);

            $this->info('Successfully fetched emails: ' . ($data['count_fetched'] ?? 0));

            if (isset($data['summary_of_emails']) && !empty($data['summary_of_emails'])) {
                $this->table(
                    ['ID', 'Gmail Message ID', 'From', 'Subject', 'Date'],
                    array_map(function ($email) {
                        return [
                            $email['id'],
                            $email['gmail_message_id'],
                            $email['from'],
                            $email['subject'],
                            $email['date'],
                        ];
                    }, $data['summary_of_emails'])
                );
            }

            return 0; // Success
        } catch (\Exception $e) {
            $this->error('Failed to fetch emails: ' . $e->getMessage());
            return 1; // Error
        }
    }
}
