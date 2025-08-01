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
        div, td {padding:0;}
        div {margin: 0 !important;}
    </style>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        table, td, div, h1, p {
            font-family: 'Inter', sans-serif;
            font-weight: 400;
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
            .signature-responsive td {
                display: block;
                width: 100% !important;
                text-align: center !important;
            }
            .signature-responsive td + td {
                padding-left: 0 !important;
                padding-top: 20px !important;
            }
            .company-logo {
                margin: 0 auto !important;
            }
        }
    </style>
</head>
<body style="margin:0;padding:0;word-spacing:normal;background-color:{{ $backgroundColor ?? '#f4f4f4' }};">
<div role="article" aria-roledescription="email" lang="en" style="text-size-adjust:100%;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;background-color:{{ $backgroundColor ?? '#f4f4f4' }};">
    <table role="presentation" style="width:100%;border:none;border-spacing:0;">
        <tr>
            <td align="center" style="padding:0;">
                <!-- Main Email Container -->
                <table role="presentation" style="width:95%;max-width:600px;border:none;border-spacing:0;text-align:left;background-color:#ffffff;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.05);margin-top:20px;margin-bottom:20px;">
                    <tr>
                        <td style="padding:0;font-size:24px;line-height:28px;font-weight:bold;text-align:center;background-color:{{ $brandPrimaryColor ?? '#5d50c6' }};border-top-left-radius:12px;border-top-right-radius:12px;color:#ffffff;padding:30px;">
                            <h1 style="margin:0;font-size:24px;line-height:28px;font-weight:bold;">
                                {{ $emailData['subject'] ?? 'Important Update' }}
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;font-size:16px;line-height:24px;color:{{ $textColorSecondary ?? '#555555' }};">
                            <!-- Main Body Content -->
                            {!! $bodyContent !!}

                            <!-- Dynamic Buttons or other Call-to-Actions -->
                            @if(isset($actionButtonUrl) && isset($actionButtonText))
                                <div style="text-align:center;margin:30px 0;">
                                    <a href="{{ $actionButtonUrl }}" target="_blank" style="background-color:{{ $brandPrimaryColor ?? '#5d50c6' }};color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;font-size:16px;display:inline-block;box-shadow:0 4px 8px rgba(0,0,0,0.1);">
                                        {{ $actionButtonText }}
                                    </a>
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;padding-top:0;">
                            <!-- Signature Block -->
                            <div style="font-family:'Inter',sans-serif;padding:20px;border:1px solid {{ $borderColor ?? '#e5e7eb' }};background-color:{{ $backgroundColor ?? '#f4f4f4' }};border-radius:8px;box-shadow:0 0 0 3px {{ $brandSecondaryColor ?? 'rgba(128, 90, 213, 0.1)' }};">
                                <table role="presentation" cellspacing="0" cellpadding="0" width="100%" class="signature-responsive">
                                    <tr>
                                        <td style="vertical-align:middle;padding-right:20px;">
                                            <p style="margin:0;font-size:18px;color:{{ $textColorPrimary ?? '#333333' }};">Best Regards</p>
                                            <p style="margin:0;font-size:18px;font-weight:bold;color:{{ $textColorPrimary ?? '#333333' }};">{{ $senderName }}</p>
                                            <p style="margin:5px 0 5px 0;font-size:14px;color:{{ $textColorSecondary ?? '#555555' }};">{{ $senderRole }}</p>
                                            <p style="margin:0 0 15px 0;font-size:12px;color:{{ $textColorSecondary ?? '#555555' }};">
                                                Your website and social media are like your home, <br> first impressions matter!
                                            </p>
                                            <div style="margin-top:15px;text-align:left;">
                                                <table role="presentation" cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        @if(isset($senderPhone))
                                                            <td style="padding-right:15px;font-size:13px;color:{{ $textColorSecondary ?? '#555555' }};">📞 {{ $senderPhone }}</td>
                                                        @endif
                                                        @if(isset($senderWebsite))
                                                            <td style="font-size:13px;">🌐 <a href="https://{{ $senderWebsite }}" target="_blank" style="color:{{ $brandPrimaryColor ?? '#5d50c6' }};text-decoration:none;">{{ $senderWebsite }}</a></td>
                                                        @endif
                                                    </tr>
                                                </table>
                                            </div>
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
                                        @if(isset($companyLogoUrl))
                                            <td style="padding-left:20px;vertical-align:middle;width:120px;text-align:right;">
                                                <img src="{{ $companyLogoUrl }}" alt="Company Logo" width="100" style="display:block;border-radius:8px;">
                                            </td>
                                        @endif
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 30px;font-size:12px;line-height:18px;color:{{ $textColorSecondary ?? '#555555' }};border-top:1px solid {{ $borderColor ?? '#e5e7eb' }};background-color:{{ $backgroundColor ?? '#f4f4f4' }};border-bottom-left-radius:12px;border-bottom-right-radius:12px;">
                            <p style="margin:0;">&copy; {{ date('Y') }} OZee Web & Digital. All rights reserved.</p>
                            <p style="margin:5px 0;">Powered by MMS IT & Web Solutions PTY Ltd.</p>
                            <p style="margin:5px 0;">Please consider the environment before printing this email.</p>
                            @if(isset($reviewLink))
                                <p style="margin:5px 0;">How did I do? <a href="{{ $reviewLink }}" target="_blank" style="color:{{ $brandPrimaryColor ?? '#5d50c6' }};text-decoration:none;font-weight:bold;">Leave a review!</a></p>
                            @endif
                            <p style="margin:5px 0;">Thornlie, WA, 6108 Country</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
