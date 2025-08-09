<?php

namespace App\Traits;

use Google\Client as Google_Client;

trait GoogleApiTrait
{
    /**
     * Creates and configures a new Google Client instance.
     *
     * @return Google_Client
     */
    protected function createGoogleClient(): Google_Client
    {
        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config(env('USER_REDIRECT_URL', 'services.google.redirect_url')));
        $client->setScopes([
            'profile',
            'https://www.googleapis.com/auth/chat.spaces',
            'https://www.googleapis.com/auth/chat.messages',
            'https://www.googleapis.com/auth/chat.memberships',
        ]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }
}
