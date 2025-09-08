<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'A message from OZee Web & Digital' }}</title>
    {{-- Styles are identical to your existing email_template.blade.php --}}
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; background-color: {{ config('branding.branding.background_color', '#f4f4f4') }}; color: {{ config('branding.branding.text_color_primary', '#333') }}; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        .header { background-color: {{ config('branding.branding.brand_primary_color') }}; color: #ffffff; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .header h1 { margin: 0; font-size: 24px; }
        .content-body { padding: 30px; line-height: 1.6; color: {{ config('branding.branding.text_color_secondary', '#4a5568') }}; }
        .content-body p { margin-bottom: 15px; }
        .cta-button { background-color: {{ config('branding.branding.brand_primary_color') }}; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 8px rgba(0,0,0,0.1); margin-top: 10px; }
        .footer { background-color: {{ config('branding.branding.background_color', '#f9fafb') }}; color: {{ config('branding.branding.text_color_secondary', '#4a5568') }}; text-align: center; padding: 20px; font-size: 12px; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; border-top: 1px solid {{ config('branding.branding.border_color', '#e5e7eb') }}; }
        .signature-block { font-family: 'Inter', sans-serif; padding: 20px; border: 1px solid {{ config('branding.branding.border_color', '#e5e7eb') }}; margin-top: 30px; background-color: {{ config('branding.branding.background_color', '#f9fafb') }}; border-radius: 8px; overflow: hidden; box-shadow: 0 0 0 3px {{ config('branding.branding.brand_secondary_color', '#fbbc05') }}; }
        .signature-block p { margin: 0; }
        .social-icons img { vertical-align: middle; }
        .company-logo { display: block; border-radius: 8px; margin-left: auto; }
        .text-link { text-decoration: none; }
    </style>
</head>
<body>
<div class="email-container">
    <div class="header">
        <h1>{{ $emailData['subject'] ?? 'Important Update' }}</h1>
    </div>
    <div class="content-body">
        {{-- Data from AI response will populate this section --}}
        <p>{{ $bodyContent->greeting ?? 'Hi there,' }}</p>

        @if(isset($bodyContent->paragraphs) && is_array($bodyContent->paragraphs))
            @foreach($bodyContent->paragraphs as $paragraph)
                <p>{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
        @endif

        @if(isset($bodyContent->call_to_action?->text) && isset($bodyContent->call_to_action?->link))
            <a href="{{ $bodyContent['call_to_action']['link'] }}" class="cta-button">
                {{ $bodyContent['call_to_action']['text'] }}
            </a>
        @endif

        <!-- Signature Block (Now dynamically populated from config) -->
        <div class="signature-block">
            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td colspan="2" style="vertical-align: top; padding-bottom: 15px;">
                        <p style="margin: 0; font-size: 18px; color: {{ config('branding.branding.text_color_primary') }}; line-height: 1.2;">Best Regards</p>
                        <p style="margin: 0; font-size: 18px; font-weight: bold; color: {{ config('branding.branding.text_color_primary') }}; line-height: 1.2;">{{ $senderName ?? config('branding.company.name') }}</p>
                        <p style="margin: 5px 0 5px 0; font-size: 14px; color: {{ config('branding.branding.text_color_secondary') }}; line-height: 1.2;">{{ $senderRole ?? 'The Team' }}</p>
                        @if(config('branding.signature.tagline'))
                            <p style="margin: 0 0 15px 0; font-size: 12px; color: {{ config('branding.branding.text_color_secondary') }}; line-height: 1.4;">
                                {!! config('branding.signature.tagline') !!}
                            </p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align: top; width: 65%;">
                        <p style="margin: 0; font-size: 13px; color: {{ config('branding.branding.text_color_secondary') }};">📞 <a href="tel:{{ config('branding.company.phone') }}" class="text-link" style="color: {{ config('branding.branding.text_color_secondary') }};">{{ config('branding.company.phone') }}</a></p>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: {{ config('branding.branding.text_color_secondary') }};">🌐 <a href="https://{{ config('branding.company.website') }}" target="_blank" class="text-link" style="color: {{ config('branding.branding.brand_primary_color') }};">{{ config('branding.company.website') }}</a></p>
                        @if(config('branding.social_icons'))
                            <div style="margin-top: 15px; text-align: left;" class="social-icons">
                                @foreach(config('branding.social_icons') as $social)
                                    <a href="{{ $social['url'] }}" target="_blank" style="display: inline-block; margin-right: 8px; color: {{ config('branding.branding.brand_primary_color') }}; text-decoration: none;">
                                        <img src="{{ asset($social['iconUrl']) }}"
                                             alt="{{ $social['name'] }}" style="vertical-align: middle;" width="24"
                                             height="24">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    @if(config('branding.company.logo_url'))
                        <td style="padding-left: 20px; vertical-align: middle; width: 35%; text-align: right;">
                            <img src="{{ asset(config('branding.company.logo_url')) }}" alt="{{ config('branding.company.name') }} Logo" width="150" class="company-logo">
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('branding.company.name') }}. All rights reserved.</p>
        <p>Powered by MMS IT & Web Solutions PTY Ltd.</p>
        <p style="color:#008000; margin:5px 0;">Please consider the environment before printing this email.</p>
        @if(config('branding.reviewLink'))
            <p>How did we do? <a href="{{ config('branding.reviewLink') }}" target="_blank" class="text-link" style="color: {{ config('branding.branding.brand_primary_color') }}; font-weight: bold;">Leave a review!</a></p>
        @endif
        <p>{{ config('branding.company.address') }}</p>
    </div>
</div>
@if(isset($emailTrackingUrl))
    <img src="{{ $emailTrackingUrl }}" alt="" width="1" height="1" style="display: none;"/>
@endif
</body>
</html>

