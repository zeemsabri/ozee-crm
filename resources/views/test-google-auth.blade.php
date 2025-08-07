<!DOCTYPE html>
<html>
<head>
    <title>Google Auth Flow Test</title>
    <style>
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
    </style>
</head>
<body>
    <div class='container'>
        <h1>Google Authentication Flow Test</h1>

        <div class='info'>
            <p>This page helps test the different Google authentication flows in the application:</p>
            <ul>
                <li><strong>Application-level authentication</strong>: Uses GoogleAuthController and stores tokens in a file</li>
                <li><strong>User-level authentication</strong>: Uses GoogleUserAuthController and stores tokens in the database</li>
            </ul>
        </div>

        <div class='card'>
            <h2>Application-level Authentication</h2>
            <p>This flow is for application-wide Google API access (Gmail, Calendar, Drive). It uses GoogleAuthController and stores tokens in a file.</p>
            <a href="{{ url('/google/redirect') }}" class='button'>Test App Authentication</a>
        </div>

        <div class='card'>
            <h2>User-level Authentication</h2>
            <p>This flow is for user-specific Google API access (Chat). It uses GoogleUserAuthController and stores tokens in the database.</p>
            <a href="{{ url('/user/google/redirect') }}" class='button'>Test User Authentication</a>
        </div>

        <div class='warning'>
            <h3>Expected Behavior:</h3>
            <ul>
                <li>Application-level auth should show the GoogleAuthSuccess page after authentication</li>
                <li>User-level auth should redirect to the dashboard with a success message</li>
            </ul>
        </div>
    </div>
</body>
</html>
