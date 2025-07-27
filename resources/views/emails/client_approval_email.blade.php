<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailData['subject'] ?? 'Client Email' }}</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #333;
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
            background-color: {{ $brandPrimaryColor }}; /* Use brand primary color */
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
            color: {{ $textColorSecondary }}; /* Use secondary text color */
        }
        .content-body p {
            margin-bottom: 15px;
        }
        .footer {
            background-color: {{ $backgroundColor }}; /* Use background color */
            color: {{ $textColorSecondary }}; /* Use secondary text color */
            text-align: center;
            padding: 20px;
            font-size: 12px;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            border-top: 1px solid {{ $borderColor }}; /* Use border color */
        }
        .button-container {
            text-align: center;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: {{ $brandPrimaryColor }}; /* Use brand primary color */
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        /* Styles specifically for the signature block */
        .signature-block {
            font-family: 'Inter', sans-serif;
            padding: 20px;
            border: 1px solid {{ $borderColor }};
            margin-top: 30px;
            background-color: {{ $backgroundColor }};
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 0 3px {{ $brandSecondaryColor }};
        }
        .signature-block p {
            margin: 0; /* Reset paragraph margins within signature */
        }
        .signature-block .social-icons img {
            vertical-align: middle;
        }
        .signature-block .company-logo {
            display: block;
            border-radius: 8px;
            margin-left: auto;
        }
    </style>
</head>
<body>
<div class="email-container">
    <div class="header">
        <h1>{{ $emailData['subject'] ?? 'Important Update' }}</h1>
    </div>
    <div class="content-body">

        {!! $bodyContent !!} {{-- Render the HTML body from your payload --}}

        <p>We appreciate your business.</p>

        {{-- START OF INCLUDED SIGNATURE --}}
        <div class="signature-block">
            <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                <tr>
                    <td style="vertical-align: middle;">
                        <p style="margin: 0; font-size: 18px; color: {{ $textColorPrimary }};">Best Regards</p>
                        <p style="margin: 0; font-size: 18px; font-weight: bold; color: {{ $textColorPrimary }};">{{ $senderName }}</p>
                        <p style="margin: 5px 0 5px 0; font-size: 14px; color: {{ $textColorSecondary }};">{{ $senderRole }}</p>
                        <p style="margin: 0 0 15px 0; font-size: 12px; color: {{ $textColorSecondary }};">
                            Your website and social media are like your home, <br> first impressions matter!
                        </p>
                        <table role="presentation" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="padding-right: 15px;">
                                    <p style="margin: 0; font-size: 13px; color: {{ $textColorSecondary }};">📞 {{ $senderPhone }}</p>
                                    <p style="margin: 5px 0 0 0; font-size: 13px; color: {{ $textColorSecondary }};">🌐 <a href="https://{{ $senderWebsite }}" target="_blank" style="color: {{ $brandPrimaryColor }}; text-decoration: none;">{{ $senderWebsite }}</a></p>
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top: 15px; text-align: left;" class="social-icons">
                            <a href="https://www.facebook.com/yourprofile" target="_blank" style="display: inline-block; margin-right: 8px; color: {{ $brandPrimaryColor }}; text-decoration: none;">
                                <img src="{{ $facebookIconUrl }}" alt="Facebook" style="vertical-align: middle;">
                            </a>
                            <a href="https://twitter.com/yourhandle" target="_blank" style="display: inline-block; margin-right: 8px; color: {{ $brandPrimaryColor }}; text-decoration: none;">
                                <img src="{{ $twitterIconUrl }}" alt="Twitter" style="vertical-align: middle;">
                            </a>
                            <a href="https://www.linkedin.com/in/yourprofile" target="_blank" style="display: inline-block; margin-right: 8px; color: {{ $brandPrimaryColor }}; text-decoration: none;">
                                <img src="{{ $linkedinIconUrl }}" alt="LinkedIn" style="vertical-align: middle;">
                            </a>
                            <a href="https://www.instagram.com/yourprofile" target="_blank" style="display: inline-block; margin-right: 8px; color: {{ $brandPrimaryColor }}; text-decoration: none;">
                                <img src="{{ $instagramIconUrl }}" alt="Instagram" style="vertical-align: middle;">
                            </a>
                        </div>
                    </td>
                    <td style="padding-left: 20px; vertical-align: middle; width: 120px; text-align: right;">
                        <img src="{{ $companyLogoUrl }}" alt="Company Logo" width="100" class="company-logo">
                    </td>
                </tr>
            </table>


        </div>
        {{-- END OF INCLUDED SIGNATURE --}}
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} OZee Web & Digital. All rights reserved.</p>
        <p>Powered by MMS IT & Web Solutions PTY Ltd.</p>
        <p>Please consider the environment before printing this email.</p>
        <p>How did I do? <a href="https://www.example.com/review" target="_blank" style="color: {{ $brandPrimaryColor }}; text-decoration: none; font-weight: bold;">Leave a review!</a></p>
        <p>Thornlie, WA, 6108 Country</p>
    </div>
</div>
</body>
</html>
