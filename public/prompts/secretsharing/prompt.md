Technical Specification: Zero-Knowledge Client Credential Vault (Laravel)1. OverviewThe goal is to build a "Zero-Knowledge" storage system within the Laravel CRM. Clients provide login credentials encrypted with a user-defined PIN. The server must not store this PIN. Decryption is only possible when a staff member manually enters the PIN provided by the client.2. Core ArchitectureEncryption Standard: AES-256-CBC.Key Derivation: The encryption key is derived from the User's PIN + a unique Salt (User UUID) using pbkdf2.Persistence: Encrypted ciphertext is stored in the database with an expires_at timestamp.Cleanup: A scheduled task deletes expired records and notifies the client.3. Database SchemaCreate a new migration for client_vault_credentials:ColumnTypeDescriptionidUUID/IDPrimary Keyclient_idForeign KeyRelationship to users or clients tablelabelStringe.g., "Shopify Store Login"encrypted_usernameTextAES-256 ciphertextencrypted_passwordTextAES-256 ciphertextsaltStringUnique random string or Client UUID (used for PBKDF2)expires_atTimestampWhen the data should be auto-deletedlast_viewed_atTimestampFor audit trailing4. Implementation Details (Logic)A. Key Derivation FunctionThe developer should implement a helper or service method to generate the 32-byte key from the PIN. This prevents brute-force attacks on the 4-digit PIN./**
* Derive a 32-character key from a PIN and Salt.
  */
  private function deriveKey(string $pin, string $salt): string
  {
  // High iterations (10,000+) slow down brute-force attempts
  return hash_pbkdf2("sha256", $pin, $salt, 10000, 32);
  }
  B. Storing Credentials (Client Portal)Accept username, password, pin, and expiry_days.Generate/Retrieve the salt.Use a custom Illuminate\Encryption\Encrypter instance with the derived key.Save the ciphertext. Discard the PIN immediately.C. Viewing Credentials (Admin Side)Admin opens the "View Credential" modal.Form asks for the "Client PIN".Backend attempts decryption using the entered PIN.If successful:Log the access using Spatie Activity Log.Display plain text to Admin.If failure: Return "Invalid PIN" (Decryption will naturally fail/throw exception if the key is wrong).5. Automation & SecurityAutomatic Deletion (Cron)In app/Console/Kernel.php, create a schedule to prune expired entries:$schedule->call(function () {
  $expiredEntries = Vault::where('expires_at', '<=', now())->get();

  foreach ($expiredEntries as $entry) {
  // 1. Trigger Notification to Client
  Notification::send($entry->client, new CredentialsSafelyDeleted($entry->label));

       // 2. Delete Record
       $entry->delete();
  }
  })->everyFiveMinutes();
  Security GuardrailsNo Logging: Ensure the pin field is added to the $except array in app/Http/Middleware/TrustHosts.php or similar to prevent it appearing in server logs.Rate Limiting: Apply a strict rate limiter (e.g., 3 attempts per minute) on the "Unlock" form to prevent PIN guessing.Spatie Integration:activity()
  ->performedOn($credential)
  ->causedBy(auth()->user())
  ->log('Credential unlocked and viewed by team member.');
6. Success ConfirmationOnce a record is deleted (manually or automatically), the system sends a transactional email:Subject: "Security Update: Your shared credentials have been permanently deleted"Body: Confirming that the entry [Label] has been wiped from the database as per the expiry policy.
