<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier bill uploaded</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; background-color: {{ config('branding.branding.background_color', '#f4f4f4') }}; color: {{ config('branding.branding.text_color_primary', '#333333') }}; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .header { background-color: {{ config('branding.branding.brand_primary_color', '#3490dc') }}; color: #ffffff; padding: 24px 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 6px 0 0; opacity: 0.9; font-size: 14px; }
        .content-body { padding: 30px; line-height: 1.6; color: {{ config('branding.branding.text_color_secondary', '#555555') }}; }
        .content-body p { margin-bottom: 15px; }
        .info-box { background-color: #f8fafc; border-left: 4px solid {{ config('branding.branding.brand_primary_color', '#3490dc') }}; padding: 14px 18px; border-radius: 4px; margin: 20px 0; }
        .info-box p { margin: 4px 0; font-size: 14px; color: {{ config('branding.branding.text_color_primary', '#333333') }}; }
        .todo { background-color: #fff8e0; border-left: 4px solid #eaaa15; padding: 14px 18px; border-radius: 4px; margin: 20px 0; }
        .todo p { margin: 4px 0; font-size: 14px; color: #7a5b00; }
        .button { display: inline-block; background-color: {{ config('branding.branding.brand_primary_color', '#3490dc') }}; color: #ffffff !important; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; }
        .footer { background-color: {{ config('branding.branding.background_color', '#f4f4f4') }}; color: {{ config('branding.branding.text_color_secondary', '#888888') }}; text-align: center; padding: 20px; font-size: 12px; border-top: 1px solid {{ config('branding.branding.border_color', '#e5e7eb') }}; }
    </style>
</head>
<body>
<div class="email-container">
    <div class="header">
        <h1>{{ config('branding.company.name') }}</h1>
        <p>A supplier uploaded a bill</p>
    </div>
    <div class="content-body">
        <p>
            {{ $contractor?->name ?: ($contractor?->email ?? 'A supplier') }} has uploaded a bill against
            <strong>{{ $project->name ?? 'a project' }}</strong> through their project share link.
        </p>

        <div class="info-box">
            <p><strong>Bill:</strong> {{ $bill->bill_number }}@if($bill->reference_number) &middot; {{ $bill->reference_number }}@endif</p>
            <p><strong>Amount:</strong> {{ $bill->currency }} {{ number_format((float) $bill->amount, 2) }}</p>
            @if($expendable)
                <p><strong>Against contract:</strong> {{ $expendable->name }} ({{ $expendable->expendable_number }})</p>
            @endif
            @if($bill->due_date)
                <p><strong>Due:</strong> {{ $bill->due_date->format('d M Y') }}</p>
            @endif
            <p><strong>Status:</strong> Pending approval</p>
        </div>

        <div class="todo">
            <p><strong>Needs your input before it can be approved:</strong></p>
            <p>Xero account code, tax type and transaction type &mdash; the supplier can't supply these.</p>
            @if(empty($contractor?->xero_contact_id))
                <p>{{ $contractor?->name ?: 'This supplier' }} isn't linked to a Xero contact yet, which will block approval.</p>
            @endif
        </div>

        <p style="text-align:center; margin: 28px 0;">
            <a href="{{ $billsUrl }}" class="button" target="_blank">Open bills</a>
        </p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('branding.company.name') }}. All rights reserved.</p>
    </div>
</div>
</body>
</html>
