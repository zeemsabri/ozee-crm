# Google Authentication Flow Fix

## Issue Description
The application was using `GoogleAuthController` for user login instead of the newly created `GoogleUserAuthController`. The `GoogleAuthController` is intended for application-level authentication, not user-level authentication.

## Solution
We made the following changes to fix the issue:

1. **Updated GoogleAuthController to use Inertia**
   - Added Inertia import to GoogleAuthController
   - Updated handleGoogleCallback method to use Inertia::render for GoogleAuthSuccess component
   - Updated method signature to reflect the correct return type

2. **Created a Test Page**
   - Created a blade view for testing both authentication flows
   - Added a route to access the test page

3. **Verified Authentication Flows**
   - Application-level authentication (GoogleAuthController)
     - Uses `/google/redirect` and `/google/callback` routes
     - Stores tokens in a file (google_tokens.json)
     - Shows the GoogleAuthSuccess page after authentication
   - User-level authentication (GoogleUserAuthController)
     - Uses `/user/google/redirect` and `/user/google/callback` routes
     - Stores tokens in the database (google_accounts table)
     - Redirects to the dashboard with a success message

## Expected Behavior
- When a user connects their Google account through the GoogleAccountPrompt, they should be directed to the user-level authentication flow using GoogleUserAuthController
- The application-level authentication using GoogleAuthController should remain unchanged and is used for application-wide Google API access

## Testing
To test the authentication flows:
1. Visit `/test-google-auth` in your browser
2. Click on the "Test App Authentication" button to test the application-level flow
3. Click on the "Test User Authentication" button to test the user-level flow
4. Verify that each flow uses the correct controller and behaves as expected

## Files Changed
- `/app/Http/Controllers/GoogleAuthController.php`
- `/routes/web.php`
- Created `/resources/views/test-google-auth.blade.php`
