<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notice->title }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: {{ config('branding.branding.background_color') }};
            color: {{ config('branding.branding.text_color_primary') }};
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            color: #ffffff;
            padding: 20px;
            text-align: center;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            /* Dynamic background color based on notice type */
            background-color: {{
                match($notice->type) {
                    'General' => '#3490dc',
                    'Updates' => '#38c172',
                    'Warning' => '#ffed4a',
                    'Final Notice' => '#e3342f',
                    default => '#3490dc'
                }
            }};
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content-body {
            padding: 30px;
            line-height: 1.6;
            color: {{ config('branding.branding.text_color_secondary') }};
        }
        .content-body p {
            margin-bottom: 15px;
        }
        .button {
            display: inline-block;
            background-color: #3490dc;
            color: #ffffff;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
        }
        .footer {
            background-color: {{ config('branding.branding.background_color') }};
            color: {{ config('branding.branding.text_color_secondary') }};
            text-align: center;
            padding: 20px;
            font-size: 12px;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            border-top: 1px solid {{ config('branding.branding.border_color') }};
        }
    </style>
</head>
<body>
<div class="email-container">
    @if ($notice->thumbnail_url_public)
        <img src="{{ $notice->thumbnail_url_public }}" alt="Notice banner" style="width:100%;display:block;object-fit:cover;" />
    @endif
    <div class="header">
        <h1>{{ $notice->title }}</h1>
    </div>
    <div class="content-body">
        <p>As-Salamu Alaykum {{ $name ?? 'User' }},</p>
        {!! nl2br($notice->description) !!}
        @if ($notice->url)
            <p>
                <a href="{{ $notice->url }}" class="button" target="_blank">View Notice</a>
            </p>
        @endif
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('branding.company.name') }}. All rights reserved.</p>
        @if(config('branding.signature.tagline'))
            <p style="color:#008000; margin:5px 0;">Please consider the environment before printing this email.</p>
        @endif
        @if(config('branding.reviewLink'))
            <p>How did I do? <a href="{{ config('branding.reviewLink') }}" target="_blank" class="text-link" style="color: {{ config('branding.branding.brand_primary_color') }}; font-weight: bold;">Leave a review!</a></p>
        @endif
        <p>{{ config('branding.company.address') }}</p>
    </div>
</div>
@if(isset($emailTrackingUrl))
    <img src="{{ $emailTrackingUrl }}" alt="" width="1" height="1" style="display: none;"/>
@endif
</body>
</html>
