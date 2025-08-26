<?php

/**
 * Test script to verify the Google authentication flow
 *
 * This script helps verify that:
 * 1. Application-level authentication uses GoogleAuthController
 * 2. User-level authentication uses GoogleUserAuthController
 *
 * How to use:
 * 1. Visit /test-google-auth in your browser
 * 2. Click on the links to test different authentication flows
 * 3. Verify that the correct controllers are being used
 */

echo "<html><head><title>Google Auth Flow Test</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .container { max-width: 800px; margin: 0 auto; }
    .card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
    h1 { color: #333; }
    h2 { color: #555; }
    .button {
        display: inline-block;
        background-color: #4285f4;
        color: white;
        padding: 10px 20px;
        text-decoration: none;
        border-radius: 4px;
        margin-right: 10px;
        margin-bottom: 10px;
    }
    .button:hover { background-color: #3367d6; }
    .info { background-color: #f8f9fa; padding: 15px; border-left: 4px solid #4285f4; margin-bottom: 20px; }
    .warning { background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px; }
</style>";
echo "</head><body>";
echo "<div class='container'>";
echo "<h1>Google Authentication Flow Test</h1>";

echo "<div class='info'>
    <p>This page helps test the different Google authentication flows in the application:</p>
    <ul>
        <li><strong>Application-level authentication</strong>: Uses GoogleAuthController and stores tokens in a file</li>
        <li><strong>User-level authentication</strong>: Uses GoogleUserAuthController and stores tokens in the database</li>
    </ul>
</div>";

echo "<div class='card'>";
echo "<h2>Application-level Authentication</h2>";
echo "<p>This flow is for application-wide Google API access (Gmail, Calendar, Drive). It uses GoogleAuthController and stores tokens in a file.</p>";
echo "<a href='/google/redirect' class='button'>Test App Authentication</a>";
echo "</div>";

echo "<div class='card'>";
echo "<h2>User-level Authentication</h2>";
echo "<p>This flow is for user-specific Google API access (Chat). It uses GoogleUserAuthController and stores tokens in the database.</p>";
echo "<a href='/user/google/redirect' class='button'>Test User Authentication</a>";
echo "</div>";

echo "<div class='warning'>";
echo "<h3>Expected Behavior:</h3>";
echo "<ul>
    <li>Application-level auth should show the GoogleAuthSuccess page after authentication</li>
    <li>User-level auth should redirect to the dashboard with a success message</li>
</ul>";
echo "</div>";

echo "</div></body></html>";
