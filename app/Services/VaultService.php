<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;

class VaultService
{
    /**
     * Derive a 32-character key from a PIN and Salt.
     */
    public function deriveKey(string $pin, string $salt): string
    {
        // High iterations (10,000+) slow down brute-force attempts
        return hash_pbkdf2("sha256", $pin, $salt, 10000, 32);
    }

    /**
     * Encrypt data using a PIN and Salt.
     */
    public function encrypt(string $value, string $pin, string $salt): string
    {
        $key = $this->deriveKey($pin, $salt);
        $encrypter = new Encrypter($key, 'AES-256-CBC');
        
        return $encrypter->encrypt($value);
    }

    /**
     * Decrypt data using a PIN and Salt.
     */
    public function decrypt(string $payload, string $pin, string $salt): ?string
    {
        try {
            $key = $this->deriveKey($pin, $salt);
            $encrypter = new Encrypter($key, 'AES-256-CBC');
            
            return $encrypter->decrypt($payload);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate a unique salt.
     */
    public function generateSalt(): string
    {
        return Str::random(32);
    }
}
