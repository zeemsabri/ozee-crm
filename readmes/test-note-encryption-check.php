<?php

// Test script to check if notes are being saved without encryption

require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProjectNote;
use Illuminate\Support\Facades\Crypt;

echo "=== Checking Project Notes Encryption ===\n\n";

// Get the 10 most recent notes
$notes = ProjectNote::orderBy('id', 'desc')->limit(10)->get();

echo 'Found '.$notes->count()." recent notes.\n\n";

foreach ($notes as $note) {
    echo "Note ID: {$note->id}\n";
    echo "Created at: {$note->created_at}\n";
    echo 'Has chat_message_id: '.($note->chat_message_id ? "Yes ({$note->chat_message_id})" : 'No')."\n";

    // Check if content appears to be encrypted
    $contentPreview = test - note - encryption - check.phpsubstr($note->content, 0, 50).(strlen($note->content) > 50 ? '...' : '');
    echo "Content preview: {$contentPreview}\n";

    // Simple heuristic to check if content might be encrypted
    $appearsEncrypted = (
        strpos($note->content, 'eyJ') === 0 && // Laravel encryption typically starts with this
        strpos($note->content, ':') !== false && // Contains colons
        strlen($note->content) > 100 // Encrypted content is usually long
    );

    echo 'Appears encrypted: '.($appearsEncrypted ? 'Yes' : 'No')."\n";

    // Try to decrypt
    try {
        $decrypted = Crypt::decryptString($note->content);
        echo "Decryption: SUCCESS\n";
        echo 'Decrypted content: '.substr($decrypted, 0, 50).(strlen($decrypted) > 50 ? '...' : '')."\n";
    } catch (\Exception $e) {
        echo "Decryption: FAILED\n";
        echo "Error: {$e->getMessage()}\n";
    }

    echo "\n";
}

echo "=== Check completed ===\n";
