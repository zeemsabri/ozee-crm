<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal Accepted</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; background-color: {{ config('branding.branding.background_color', '#f4f4f4') }}; color: {{ config('branding.branding.text_color_primary', '#333333') }}; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .header { background-color: #10b981; color: #ffffff; padding: 24px 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 6px 0 0; opacity: 0.9; font-size: 14px; }
        .content-body { padding: 30px; line-height: 1.6; color: {{ config('branding.branding.text_color_secondary', '#555555') }}; }
        .content-body p { margin-bottom: 15px; }
        .info-box { background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 14px 18px; border-radius: 4px; margin: 20px 0; }
        .info-box p { margin: 4px 0; font-size: 14px; color: #166534; }
        .button { display: inline-block; background-color: {{ config('branding.branding.brand_primary_color', '#3490dc') }}; color: #ffffff !important; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; }
        .footer { background-color: {{ config('branding.branding.background_color', '#f4f4f4') }}; color: {{ config('branding.branding.text_color_secondary', '#888888') }}; text-align: center; padding: 20px; font-size: 12px; border-top: 1px solid {{ config('branding.branding.border_color', '#e5e7eb') }}; }
    </style>
</head>
<body>
<div class="email-container">
    <div class="header">
        <h1>{{ config('branding.company.name') }}</h1>
        <p>Proposal Accepted!</p>
    </div>
    <div class="content-body">
        <p>Hello {{ $user->name ?? 'there' }},</p>
        <p>Great news! Your proposal for the project <strong>{{ $project->name ?? 'Project' }}</strong> has been <strong>ACCEPTED</strong>.</p>
        
        <div class="info-box">
            <p><strong>Proposal Name:</strong> {{ $proposal->name }}</p>
            <p><strong>Amount:</strong> {{ $proposal->currency }} {{ number_format($proposal->amount, 2) }}</p>
            @if(!empty($reason))
                <p><strong>Notes:</strong> {{ $reason }}</p>
            @endif
        </div>

        @if(!empty($shareUrl))
            <p style="text-align:center; margin: 28px 0;">
                <a href="{{ $shareUrl }}" class="button" target="_blank">View Project Page</a>
            </p>
        @endif

        <p>Thank you for your submission. We look forward to working with you!</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('branding.company.name') }}. All rights reserved.</p>
    </div>
</div>
</body>
</html>
