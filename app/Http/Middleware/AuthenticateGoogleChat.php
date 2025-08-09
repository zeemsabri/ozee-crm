<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Google\Client as GoogleClient;
use App\Traits\GoogleApiTrait; // Use the trait to create the client
class AuthenticateGoogleChat
{

    use GoogleApiTrait;

    public function handle(Request $request, Closure $next)
    {
        // 1. Check if a user is authenticated in your application.
        // This is safe to do in middleware as it runs after the authentication process.
        if (!Auth::check()) {
            return redirect()->route('google.redirect'); // Or some other login route
        }

        $user = Auth::user();

        //Temporary turned off for testing
//        // 2. Fetch the user's Google token data from the database.
//        // Assume you have `google_access_token` and `google_refresh_token` columns on your `users` table.
//        if (!$user->google_access_token) {
//            return redirect()->route('google.redirect');
//        }

        // 3. Create a new Google Client instance using the shared trait method.
        $client = $this->createGoogleClient();

        // 4. Set the access token on the client.
        $client->setAccessToken($user->googleAccount()->first()?->tokens);;

        // 5. Check if the token has expired and refresh it if necessary.
        if ($client->isAccessTokenExpired()) {
            try {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

                // Update the user's tokens in the database with the new ones.
                $account = $user->googleAccount()->firstOrUpdate([
                    'access_token' => $client->getAccessToken()['access_token'],
                    'created'   =>  $client->getAccessToken()['created'],
                    'refresh_token' => $client->getRefreshToken()
                ]);

                $client->setAccessToken($account->tokens);

            } catch (\Exception $e) {

                dd($e->getMessage());
                return redirect()->route('google.redirect')->with('error', 'Google session expired. Please log in again.');
            }
        }

        // 6. Bind the fully authenticated client to the service container.
        // This makes it available for injection into any other class (like your service).
        app()->instance(GoogleClient::class, $client);

        return $next($request);
    }
}
