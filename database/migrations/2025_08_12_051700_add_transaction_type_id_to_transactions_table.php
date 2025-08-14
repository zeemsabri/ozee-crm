<?php

use App\Models\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('transaction_type_id')->nullable()->after('type')->constrained('transaction_types')->nullOnDelete();
        });

        // Ensure the default type exists for backfill
        $name = 'Initial Agreed Amount';
        $slug = Str::slug($name);
        $type = TransactionType::firstOrCreate(['slug' => $slug], [
            'name' => $name,
        ]);

        // Backfill: for parent transactions (where transaction_id is null), set transaction_type_id to Initial Agreed Amount
        DB::table('transactions')
            ->whereNull('transaction_id')
            ->update(['transaction_type_id' => $type->id]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_type_id');
        });
    }
};
