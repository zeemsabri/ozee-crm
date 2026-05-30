<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Invite</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; background-color: {{ config('branding.branding.background_color', '#f4f4f4') }}; color: {{ config('branding.branding.text_color_primary', '#333333') }}; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .header { background-color: {{ config('branding.branding.brand_primary_color', '#3490dc') }}; color: #ffffff; padding: 24px 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 6px 0 0; opacity: 0.85; font-size: 14px; }
        .content-body { padding: 30px; line-height: 1.6; color: {{ config('branding.branding.text_color_secondary', '#555555') }}; }
        .content-body p { margin-bottom: 15px; }
        .custom-message { background-color: #f9fafb; border-left: 4px solid {{ config('branding.branding.brand_primary_color', '#3490dc') }}; padding: 14px 18px; border-radius: 4px; margin-bottom: 20px; font-style: italic; }
        .button { display: inline-block; background-color: {{ config('branding.branding.brand_primary_color', '#3490dc') }}; color: #ffffff !important; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; }
        .footer { background-color: {{ config('branding.branding.background_color', '#f4f4f4') }}; color: {{ config('branding.branding.text_color_secondary', '#888888') }}; text-align: center; padding: 20px; font-size: 12px; border-top: 1px solid {{ config('branding.branding.border_color', '#e5e7eb') }}; }
    </style>
</head>
<body>
<div class="email-container">
    <div class="header">
        <h1>{{ config('branding.company.name') }}</h1>
        <p>Project Invitation</p>
    </div>
    <div class="content-body">
        <p>As-Salamu Alaykum {{ $recipientName }},</p>
        <p>You have been invited to view and submit a proposal for the following project:</p>
        <p><strong style="font-size:18px;">{{ $project->name }}</strong></p>
        @if($project->description)
            <p>{{ \Illuminate\Support\Str::limit($project->description, 200) }}</p>
        @endif
        @if($customMessage)
            <div class="custom-message">{{ $customMessage }}</div>
        @endif
        <p style="text-align:center; margin: 28px 0;">
            <a href="{{ $shareUrl }}" class="button" target="_blank">View Project &amp; Submit Proposal</a>
        </p>
        <p style="font-size:13px; color:#999;">If you are not interested, you may ignore this email. The link will remain active as long as the project is open.</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('branding.company.name') }}. All rights reserved.</p>
        @if(config('branding.company.address'))
            <p>{{ config('branding.company.address') }}</p>
        @endif
    </div>
</div>
<img src="{{ $trackingUrl }}" alt="" width="1" height="1" style="display:none;" />
</body>
</html>
