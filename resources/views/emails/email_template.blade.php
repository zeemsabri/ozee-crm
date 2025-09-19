<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailData['subject'] ?? 'Client Email' }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: {{ $backgroundColor ?? '#f4f4f4' }};
            color: {{ $textColorPrimary ?? '#333' }};
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
            background-color: {{ $brandPrimaryColor }};
            color: #ffffff;
            padding: 20px;
            text-align: center;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content-body {
            padding: 30px;
            line-height: 1.6;
            color: {{ $textColorSecondary ?? '#4a5568' }};
        }
        .content-body p {
            margin-bottom: 15px;
        }
        .footer {
            background-color: {{ $backgroundColor ?? '#f9fafb' }};
            color: {{ $textColorSecondary ?? '#4a5568' }};
            text-align: center;
            padding: 20px;
            font-size: 12px;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            border-top: 1px solid {{ $borderColor ?? '#e5e7eb' }};
        }
        .signature-block {
            font-family: 'Inter', sans-serif;
            padding: 20px;
            border: 1px solid {{ $borderColor ?? '#e5e7eb' }};
            margin-top: 30px;
            background-color: {{ $backgroundColor ?? '#f9fafb' }};
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 0 3px {{ $brandSecondaryColor ?? '#fbbc05' }};
        }
        .signature-block p {
            margin: 0;
        }
        .social-icons img {
            vertical-align: middle;
        }
        .company-logo {
            display: block;
            border-radius: 8px;
            margin-left: auto;
        }
        .text-link {
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="email-container">
    @if(isset($show_signature) && $show_signature === true)
    <div class="header">
        <h1>{{ $emailData['subject'] ?? 'Important Update' }}</h1>
    </div>
    @endif
    <div class="content-body">
        {!! $bodyContent !!}

    @if(isset($show_signature) && $show_signature === true)
        <!-- Signature Block -->
        <div class="signature-block">
            <table role="presentation">
                <tr>
                    <td colspan="2" style="vertical-align: top;">
                        <p style="margin: 0; font-size: 18px; color: {{ $textColorPrimary }}; line-height: 1.2;">Best Regards</p>
                        <p style="margin: 0; font-size: 18px; font-weight: bold; color: {{ $textColorPrimary }}; line-height: 1.2;">{{ $senderName }}</p>
                        <p style="margin: 5px 0 5px 0; font-size: 14px; color: {{ $textColorSecondary }}; line-height: 1.2;">{{ $senderRole }}</p>
                        @if(isset($signatureTagline))
                            <p style="margin: 0 0 15px 0; font-size: 12px; color: {{ $textColorSecondary }}; line-height: 1.4;">
                                {!! $signatureTagline !!}
                            </p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align: top; width: 65%;">
                        <p style="margin: 0; font-size: 13px; color: {{ $textColorSecondary }};">📞 <a href="tel:{{ $senderPhone }}" class="text-link" style="color: {{ $textColorSecondary }};">{{ $senderPhone }}</a></p>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: {{ $textColorSecondary }};">🌐 <a href="https://{{ $senderWebsite }}" target="_blank" class="text-link" style="color: {{ $brandPrimaryColor }};">{{ $senderWebsite }}</a></p>
                        @if(isset($socialIcons))
                            <div style="margin-top: 15px; text-align: left;" class="social-icons">
                                @foreach($socialIcons as $social)
                                    <a href="{{ $social['url'] }}" target="_blank" style="display: inline-block; margin-right: 8px; color: {{ $brandPrimaryColor }}; text-decoration: none;">
                                        <img src="{{ config('app.url') . $social['iconUrl'] }}"
                                             alt="{{ $social['name'] }}" style="vertical-align: middle;" width="24"
                                             height="24">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    @if(isset($companyLogoUrl))
                        <td style="padding-left: 20px; vertical-align: middle; width: 35%; text-align: right;">
                            <img src="{{ $companyLogoUrl }}" alt="Company Logo" width="150" class="company-logo">
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} OZee Web & Digital. All rights reserved.</p>
        <p>Powered by MMS IT & Web Solutions PTY Ltd.</p>
        <p style="color:#008000; margin:5px 0;">Please consider the environment before printing this email.</p>
        @if(isset($reviewLink))
            <p>How did I do? <a href="{{ $reviewLink }}" target="_blank" class="text-link" style="color: {{ $brandPrimaryColor }}; font-weight: bold;">Leave a review!</a></p>
        @endif
        <p>{{ $companyAddress ?? 'Thornlie, WA, 6108 Australia' }}</p>
    </div>
    @endif
</div>
@if(isset($emailTrackingUrl))
    <img src="{{ $emailTrackingUrl }}" alt="" width="1" height="1" style="display: none;"/>
@endif
</body>
</html>
