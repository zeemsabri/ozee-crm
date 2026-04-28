<?php

namespace App\Console\Commands;

use App\Models\ClientVaultCredential;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VaultPrune extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vault:prune';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Prune expired vault credentials';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expired = ClientVaultCredential::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();
        $count = $expired->count();

        foreach ($expired as $credential) {
            if ($credential->isClientSubmitted()) {
                $credential->forceDelete();
            } else {
                $credential->delete();
            }
        }

        if ($count > 0) {
            $this->info("Pruned {$count} expired vault credentials.");
            Log::info("Vault: Pruned {$count} expired credentials.");
        }
    }
}
