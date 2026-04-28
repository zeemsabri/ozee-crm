<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE client_vault_credentials MODIFY expires_at TIMESTAMP NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE client_vault_credentials ALTER COLUMN expires_at DROP NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('UPDATE client_vault_credentials SET expires_at = NOW() WHERE expires_at IS NULL');
            DB::statement('ALTER TABLE client_vault_credentials MODIFY expires_at TIMESTAMP NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('UPDATE client_vault_credentials SET expires_at = NOW() WHERE expires_at IS NULL');
            DB::statement('ALTER TABLE client_vault_credentials ALTER COLUMN expires_at SET NOT NULL');
        }
    }
};
