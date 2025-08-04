<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $emailData['subject'] ?? 'Client Email' }}</title>
    <!--[if mso]>
    <style type="text/css">
        table {border-collapse: collapse; border-spacing: 0; border: none; margin: 0;}
        div, td {padding:0;font-family:'Inter',sans-serif;}
        div {margin: 0 !important;}
    </style>
    <![endif]-->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        table, td, div, h1, h2, p {
            font-family: 'Inter', sans-serif;
            font-weight: 400;
        }
        body {
            margin: 0;
            padding: 0;
            word-spacing: normal;
            background-color: {{ $backgroundColor ?? '#f4f7f9' }};
            color: {{ $textColorSecondary ?? '#555555' }};
        }
        a {
            text-decoration: none;
        }
        .btn-primary:hover {
            background-color: {{ $brandSecondaryColor ?? '#3f35a0' }} !important;
        }
        .text-link:hover {
            text-decoration: underline !important;
        }
        @media screen and (max-width: 600px) {
            .full-width-mobile {
                width: 100% !important;
                max-width: 100% !important;
            }
            .padding-mobile {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
            .header-text h1 {
                font-size: 20px !important;
            }
        }
    </style>
</head>
<body style="margin:0;padding:0;word-spacing:normal;background-color:{{ $backgroundColor ?? '#f4f7f9' }};">
<div role="article" aria-roledescription="email" lang="en" style="text-size-adjust:100%;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;background-color:{{ $backgroundColor ?? '#f4f7f9' }};">
    <table role="presentation" style="width:100%;border:none;border-spacing:0;">
        <tr>
            <td align="center" style="padding:0;">
                <!-- Main Email Container -->
                <table role="presentation" style="width:95%;max-width:600px;border:none;border-spacing:0;text-align:left;background-color:#ffffff;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,0.08);margin-top:24px;margin-bottom:24px;">
                    <!-- Header Section with Gradient -->
                    <tr>
                        <td align="center" style="padding:40px 30px;font-size:24px;line-height:28px;font-weight:bold;color:#ffffff;border-top-left-radius:16px;border-top-right-radius:16px;
                            background-color: {{ $brandPrimaryColor ?? '#5d50c6' }};">
                            <h1 style="margin:0;font-size:24px;line-height:28px;font-weight:700;">
                                {{ $emailData['subject'] ?? 'Important Update' }}
                            </h1>
                        </td>
                    </tr>
                    <!-- Main Body Content -->
                    <tr>
                        <td style="padding:30px;font-size:16px;line-height:24px;color:{{ $textColorSecondary ?? '#555555' }};">
                            <!-- Dynamic body content from Blade -->
                            {!! $bodyContent !!}

                            <!-- Dynamic Buttons or other Call-to-Actions -->
                            @if(isset($actionButtonUrl) && isset($actionButtonText))
                                <div style="text-align:center;margin:30px 0;">
                                    <a href="{{ $actionButtonUrl }}" target="_blank" class="btn-primary" style="background-color:{{ $brandPrimaryColor ?? '#5d50c6' }};color:#ffffff;text-decoration:none;padding:15px 30px;border-radius:10px;font-weight:bold;font-size:16px;display:inline-block;box-shadow:0 6px 15px rgba(0,0,0,0.15);">
                                        {{ $actionButtonText }}
                                    </a>
                                </div>
                            @endif
                        </td>
                    </tr>
                    <!-- Signature Block -->
                    <tr>
                        <td style="padding:0 30px 30px 30px;">
                            <div style="font-family:'Inter',sans-serif;padding:24px;border:1px solid {{ $borderColor ?? '#e5e7eb' }};background-color:{{ $backgroundColor ?? '#f9fafb' }};border-radius:12px;
                                box-shadow: 0 0 0 3px {{ $brandSecondaryColor ?? '#fbbc05' }};">
                                <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                                    <tr>
                                        <!-- Sender Information -->
                                        <td style="vertical-align:middle;padding-right:20px;">
                                            <p style="margin:0 0 8px 0;font-size:18px;color:{{ $textColorPrimary ?? '#333333' }};font-weight:600;">Best Regards,</p>
                                            <p style="margin:0;font-size:16px;font-weight:600;color:{{ $textColorPrimary ?? '#333333' }};">{{ $senderName }}</p>
                                            <p style="margin:5px 0;font-size:14px;color:{{ $textColorSecondary ?? '#555555' }};">{{ $senderRole }}</p>

                                            @if(isset($signatureTagline))
                                                <p style="margin: 0 0 15px 0; font-size: 12px; color: {{ $textColorSecondary ?? '#555555' }};">
                                                    {!! $signatureTagline !!}
                                                </p>
                                            @endif

                                            <!-- Contact Links -->
                                            <div style="margin-top:15px;text-align:left;">
                                                <table role="presentation" cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        @if(isset($senderPhone))
                                                            <td style="padding-right:15px;font-size:13px;color:{{ $textColorSecondary ?? '#555555' }};">📞 <a href="tel:{{ $senderPhone }}" class="text-link" style="color:{{ $textColorSecondary ?? '#555555' }};text-decoration:none;">{{ $senderPhone }}</a></td>
                                                        @endif
                                                        @if(isset($senderWebsite))
                                                            <td style="font-size:13px;">🌐 <a href="https://{{ $senderWebsite }}" target="_blank" class="text-link" style="color:{{ $brandPrimaryColor ?? '#5d50c6' }};text-decoration:none;">{{ $senderWebsite }}</a></td>
                                                        @endif
                                                    </tr>
                                                </table>
                                            </div>

                                            <!-- Social Icons -->
                                            @if(isset($socialIcons))
                                                <div style="margin-top:15px;text-align:left;">
                                                    @foreach($socialIcons as $social)
                                                        <a href="{{ $social['url'] }}" target="_blank" style="display:inline-block;margin-right:8px;color:{{ $brandPrimaryColor ?? '#5d50c6' }};text-decoration:none;">
                                                            <img src="{{ $social['iconUrl'] }}" alt="{{ $social['name'] }}" width="24" height="24" style="vertical-align:middle;border-radius:4px;">
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <!-- Company Logo (if available) -->
                                        @if(isset($companyLogoUrl))
                                            <td style="padding-left:20px;vertical-align:middle;width:200px;text-align:right;">
                                                <img src="{{ $companyLogoUrl }}" alt="Company Logo" width="200" style="display:block;border-radius:8px;">
                                            </td>
                                        @endif
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <!-- Footer Section -->
                    <tr>
                        <td align="center" style="padding:20px 30px;font-size:12px;line-height:18px;color:{{ $textColorSecondary ?? '#888888' }};border-top:1px solid {{ $borderColor ?? '#e5e7eb' }};background-color:{{ $backgroundColor ?? '#f9fafb' }};border-bottom-left-radius:16px;border-bottom-right-radius:16px;">
                            <p style="margin:0;">&copy; {{ date('Y') }} OZee Web & Digital. All rights reserved.</p>
                            <p style="margin:5px 0;">Powered by MMS IT & Web Solutions PTY Ltd.</p>
                            <p style="color:#008000; margin:5px 0;">Please consider the environment before printing this email.</p>
                            @if(isset($reviewLink))
                                <p style="margin:5px 0;">How did I do? <a href="{{ $reviewLink }}" target="_blank" class="text-link" style="color:{{ $brandPrimaryColor ?? '#5d50c6' }};text-decoration:none;font-weight:bold;">Leave a review!</a></p>
                            @endif
                            <p style="margin:5px 0;">{{ $companyAddress ?? 'Thornlie, WA, 6108 Australia' }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
