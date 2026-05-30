<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #333333; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .header { background-color: {{ config('branding.branding.brand_primary_color', '#3490dc') }}; color: #ffffff; padding: 24px 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .content-body { padding: 30px; line-height: 1.6; color: #555555; }
        .otp-box { background-color: #f9fafb; border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0; }
        .otp-code { font-size: 40px; font-weight: 700; letter-spacing: 12px; color: #111827; }
        .footer { background-color: #f4f4f4; text-align: center; padding: 20px; font-size: 12px; color: #888888; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
<div class="email-container">
    <div class="header">
        <h1>{{ config('branding.company.name') }}</h1>
    </div>
    <div class="content-body">
        <p>As-Salamu Alaykum,</p>
        <p>You requested a verification code to submit a proposal for <strong>{{ $projectName }}</strong>.</p>
        <p>Use the code below. It expires in <strong>10 minutes</strong>.</p>
        <div class="otp-box">
            <div class="otp-code">{{ $otp }}</div>
        </div>
        <p style="font-size:13px; color:#999;">If you did not request this, please ignore this email.</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('branding.company.name') }}. All rights reserved.</p>
        <p>{{ config('branding.company.address') }}</p>
    </div>
</div>
</body>
</html>
