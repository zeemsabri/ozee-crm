<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'A message from OZee Web & Digital' }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; background-color: {{ config('branding.branding.background_color', '#f4f4f4') }}; color: {{ config('branding.branding.text_color_primary', '#333') }}; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }

        /* --- RECOMMENDATION 1: Header Removed --- */
        /* The formal header block has been removed to make the email feel more personal from the very first line. */

        .content-body { padding: 30px; line-height: 1.6; color: {{ config('branding.branding.text_color_secondary', '#4a5568') }}; }
        .content-body p { margin-bottom: 15px; }
        .cta-button { background-color: {{ config('branding.branding.brand_primary_color') }}; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 8px rgba(0,0,0,0.1); margin-top: 10px; }
        .footer { background-color: {{ config('branding.branding.background_color', '#f9fafb') }}; color: {{ config('branding.branding.text_color_secondary', '#4a5568') }}; text-align: center; padding: 20px; font-size: 12px; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; border-top: 1px solid {{ config('branding.branding.border_color', '#e5e7eb') }}; }
        .text-link { text-decoration: none; }

        /* --- RECOMMENDATION 2: Simplified Signature Style --- */
        .signature p { margin: 0; line-height: 1.5; }
    </style>
</head>
<body>
<div class="email-container">
    {{-- The branded header is intentionally removed for the initial cold outreach --}}

    <div class="content-body">
        {{-- AI-generated content starts here, feeling personal from the first line --}}
        <p>{{ $bodyContent->greeting ?? 'Hi there,' }}</p>

        @if(isset($bodyContent->paragraphs) && is_array($bodyContent->paragraphs))
            @foreach($bodyContent->paragraphs as $paragraph)
                {{-- The nl2br and e() functions ensure formatting is respected and content is safe --}}
                <p>{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
        @endif

        @if(isset($bodyContent->call_to_action?->text) && isset($bodyContent->call_to_action?->link))
            <a href="{{ $bodyContent->call_to_action->link }}" class="cta-button">
                {{ $bodyContent->call_to_action->text }}
            </a>
        @endif

        <!-- RECOMMENDATION 2: Simplified and Personal Signature -->
        <!-- This signature block is designed to look more like a personal, typed signature -->
        <!-- rather than a corporate block, enhancing the one-to-one feel of the email. -->
        <div class="signature" style="margin-top: 30px;">
            <p style="color: {{ config('branding.branding.text_color_secondary', '#4a5568') }};">All the best,</p>
            <p style="font-weight: bold; color: {{ config('branding.branding.text_color_primary') }};">{{ $senderName ?? config('branding.company.name') }}</p>
            <p style="color: {{ config('branding.branding.text_color_secondary', '#4a5568') }};">{{ $senderRole ?? 'Digital Strategist' }}</p>
            <p style="margin-top: 10px; font-size: 13px; color: {{ config('branding.branding.text_color_secondary') }};">
                <b style="color: {{ config('branding.branding.brand_primary_color') }};">P:</b> <a href="tel:{{ config('branding.company.phone') }}" class="text-link" style="color: {{ config('branding.branding.text_color_secondary') }};">{{ config('branding.company.phone') }}</a>
            </p>
            <p style="font-size: 13px; color: {{ config('branding.branding.text_color_secondary') }};">
                <b style="color: {{ config('branding.branding.brand_primary_color') }};">W:</b> <a href="https://{{ config('branding.company.website') }}" target="_blank" class="text-link" style="color: {{ config('branding.branding.brand_primary_color') }};">{{ config('branding.company.website') }}</a>
            </p>
        </div>
    </div>
    <div class="footer">
        {{-- The footer remains for professionalism and legal compliance --}}
        <p>&copy; {{ date('Y') }} {{ config('branding.company.name') }}. All rights reserved.</p>
        <p>{{ config('branding.company.address') }}</p>
        @if(config('branding.reviewLink'))
            <p>How did we do? <a href="{{ config('branding.reviewLink') }}" target="_blank" class="text-link" style="color: {{ config('branding.branding.brand_primary_color') }}; font-weight: bold;">Leave a review!</a></p>
        @endif
    </div>
</div>
@if(isset($emailTrackingUrl))
    <img src="{{ $emailTrackingUrl }}" alt="" width="1" height="1" style="display: none;"/>
@endif
</body>
</html>
