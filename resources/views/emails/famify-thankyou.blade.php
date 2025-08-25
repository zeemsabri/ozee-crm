<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You</title>
</head>
<body style="margin:0;padding:16px;font-family:Arial,Helvetica,sans-serif;background:#f9fafb;color:#111827;">
    <div style="max-width:640px;margin:auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;background:#f3f4f6;">
            <h2 style="margin:0;font-size:18px;">Thank you for contacting Famify Hub</h2>
        </div>
        <div style="padding:20px;">
            <p style="margin:0 0 12px;">@if(!empty($name))Hi {{ $name }},@else Hi there,@endif</p>

            @if(($userType ?? '') === 'Parent')
                <p style="margin:0 0 12px;">Thanks for reaching out to Famify Hub. We're excited to help you create a safer, healthier digital experience for your family.</p>
                @if(!empty($parentGoal) || !empty($childAge))
                    <p style="margin:0 0 12px;">
                        @if(!empty($parentGoal))We noted your goal: <strong>{{ $parentGoal }}</strong>.@endif
                        @if(!empty($childAge)) {{ !empty($parentGoal) ? ' ' : '' }}Child age: <strong>{{ $childAge }}</strong>.@endif
                    </p>
                @endif
                <p style="margin:0 0 12px;">Our team will review your submission and get back to you shortly with the next steps.</p>
            @elseif(($userType ?? '') === 'Content Creator')
                <p style="margin:0 0 12px;">Thanks for getting in touch. We're thrilled you're interested in partnering with Famify Hub.</p>
                @if(!empty($creatorGoal))
                    <p style="margin:0 0 12px;">We noted your goal: <strong>{{ $creatorGoal }}</strong>.</p>
                @endif
                <p style="margin:0 0 12px;">We'll be in touch soon with more details about collaboration opportunities and next steps.</p>
            @else
                <p style="margin:0 0 12px;">Thanks for contacting us. We'll review your message and get back to you shortly.</p>
            @endif

            <p style="margin:16px 0 0;color:#6b7280;font-size:12px;">— Famify Hub Team<br/>© {{ $year }} Famify Hub</p>
        </div>
    </div>
</body>
</html>
