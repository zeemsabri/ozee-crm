<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Magic Link for {{ $project->name }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 20px;
        }
        h1 {
            color: #2d3748;
            font-size: 24px;
            margin-bottom: 20px;
        }
        p {
            margin-bottom: 16px;
        }
        .button {
            display: inline-block;
            background-color: #4f46e5;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 4px;
            font-weight: 600;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #4338ca;
        }
        .footer {
            margin-top: 40px;
            font-size: 14px;
            color: #6b7280;
            text-align: center;
        }
        .expiration {
            font-style: italic;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Magic Link for {{ $project->name }}</h1>
    </div>

    <p>Hello,</p>

    <p>You've been sent a magic link to access the project <strong>{{ $project->name }}</strong>.</p>

    <p>Click the button below to access the project:</p>

    <div style="text-align: center;">
        <a href="{{ $url }}" class="button">Access Project</a>
    </div>

    <p>Or copy and paste this URL into your browser:</p>
    <p style="word-break: break-all;">{{ $url }}</p>

    <p class="expiration">This magic link will expire in 24 hours.</p>

    <p>If you didn't request this link, you can safely ignore this email.</p>

    <div class="footer">
        <p>&copy; {{ date('Y') }} Your Company. All rights reserved.</p>
    </div>
</body>
</html>
