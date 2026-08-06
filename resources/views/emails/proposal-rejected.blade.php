<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal Status Update</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; background-color: {{ config('branding.branding.background_color', '#f4f4f4') }}; color: {{ config('branding.branding.text_color_primary', '#333333') }}; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .header { background-color: #ef4444; color: #ffffff; padding: 24px 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 6px 0 0; opacity: 0.9; font-size: 14px; }
        .content-body { padding: 30px; line-height: 1.6; color: {{ config('branding.branding.text_color_secondary', '#555555') }}; }
        .content-body p { margin-bottom: 15px; }
        .reason-box { background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 18px; border-radius: 4px; margin: 20px 0; }
        .reason-box p { margin: 4px 0; font-size: 14px; color: #991b1b; }
        .button { display: inline-block; background-color: {{ config('branding.branding.brand_primary_color', '#3490dc') }}; color: #ffffff !important; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; }
        .footer { background-color: {{ config('branding.branding.background_color', '#f4f4f4') }}; color: {{ config('branding.branding.text_color_secondary', '#888888') }}; text-align: center; padding: 20px; font-size: 12px; border-top: 1px solid {{ config('branding.branding.border_color', '#e5e7eb') }}; }
    </style>
</head>
<body>
<div class="email-container">
    <div class="header">
        <h1>{{ config('branding.company.name') }}</h1>
        <p>Proposal Status Update</p>
    </div>
    <div class="content-body">
        <p>Hello {{ $user->name ?? 'there' }},</p>
        <p>Your proposal <strong>{{ $proposal->name }}</strong> for the project <strong>{{ $project->name ?? 'Project' }}</strong> requires revisions or was not accepted at this time.</p>
        
        <div class="reason-box">
            <p><strong>Reason / Feedback:</strong></p>
            <p style="margin-top: 6px; font-style: italic;">{{ $reason }}</p>
        </div>

        <p>You can update and resubmit your proposal with revised details using the link below:</p>

        @if(!empty($shareUrl))
            <p style="text-align:center; margin: 28px 0;">
                <a href="{{ $shareUrl }}" class="button" target="_blank">Update &amp; Resubmit Proposal</a>
            </p>
        @endif

        <p>If you have any questions, please reach out to the project team.</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('branding.company.name') }}. All rights reserved.</p>
    </div>
</div>
</body>
</html>
