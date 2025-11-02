<?php

// Script to fix project notes that have encryption issues after adding chat_message_id

require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProjectNote;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

echo "=== Project Notes Encryption Fix Script ===\n\n";

// Function to check and fix notes
function fixNoteEncryption()
{
    // Get all notes
    $notes = ProjectNote::all();

    echo 'Found '.$notes->count()." notes in the database.\n\n";

    $fixedCount = 0;
    $errorCount = 0;

    foreach ($notes as $note) {
        echo "Checking note ID: {$note->id}\n";
        echo 'Has chat_message_id: '.($note->chat_message_id ? 'Yes' : 'No')."\n";

        try {
            // Try to decrypt the content
            $decryptedContent = Crypt::decryptString($note->content);
            echo "Decryption: SUCCESS\n";
            echo 'Content: '.substr($decryptedContent, 0, 30).(strlen($decryptedContent) > 30 ? '...' : '')."\n";
        } catch (\Exception $e) {
            echo "Decryption: FAILED\n";
            echo "Error: {$e->getMessage()}\n";
            $errorCount++;

            // Check if the content is already a placeholder
            if ($note->content === '[Encrypted content could not be decrypted]') {
                echo "Note already has placeholder content, skipping.\n";

                continue;
            }

            // Try to fix the note by setting a placeholder content
            try {
                // Store the original content for reference
                $originalContent = $note->content;

                // Set a placeholder content that's properly encrypted
                $note->content = Crypt::encryptString('[Original content was corrupted and could not be recovered]');
                $note->save();

                echo "Fixed note with placeholder content.\n";
                $fixedCount++;

                // Log the fix
                Log::info('Fixed corrupted note content', [
                    'note_id' => $note->id,
                    'original_content' => $originalContent,
                ]);
            } catch (\Exception $fixError) {
                echo "Failed to fix note: {$fixError->getMessage()}\n";
                Log::error('Failed to fix corrupted note', [
                    'note_id' => $note->id,
                    'error' => $fixError->getMessage(),
                ]);
            }
        }

        echo "\n";
    }

    echo "Summary:\n";
    echo '- Total notes: '.$notes->count()."\n";
    echo "- Notes with decryption errors: $errorCount\n";
    echo "- Notes fixed: $fixedCount\n";

    return true;
}

// Run the fix function
try {
    fixNoteEncryption();
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Stack trace: {$e->getTraceAsString()}\n";
}
