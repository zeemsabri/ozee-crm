<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Task Management System - A comprehensive platform for managing projects, tasks, and team collaboration with email approval workflow.">
    <meta name="keywords" content="task management, project management, email approval, team collaboration">
    <meta name="author" content="OZEE CRM">
    <meta name="theme-color" content="#4f46e5">

    <!-- Open Graph / Social Media Meta Tags -->
    <meta property="og:title" content="Task Management System">
    <meta property="og:description" content="Streamline your project management and team collaboration with our comprehensive task management system.">
    <meta property="og:type" content="website">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <title inertia>{{ config('app.name', 'Task Management System') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Google Fonts Link for Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @routes
    @php
        // React pages are rendered server-side as Inertia::render('React/Name', ...)
        // and live under resources/js/ReactPages; everything else is still a Vue
        // page under resources/js/Pages. See resources/js/app.js for the client-side
        // half of this same check.
        $isReactPage = str_starts_with($page['component'], 'React/');
        $pageAsset = $isReactPage
            ? 'resources/js/ReactPages/' . substr($page['component'], strlen('React/')) . '.jsx'
            : 'resources/js/Pages/' . $page['component'] . '.vue';
    @endphp
    @viteReactRefresh
    @vite(['resources/js/app.js', $pageAsset])
    @inertiaHead
    <script>
        // Fix for share-modal.js addEventListener error and syntax errors
        window.addEventListener('DOMContentLoaded', function() {
            // Ensure any elements that might be null have a safety check before adding event listeners
            const originalAddEventListener = Element.prototype.addEventListener;
            Element.prototype.addEventListener = function() {
                if (this) {
                    return originalAddEventListener.apply(this, arguments);
                }
            };

            // Fix for "Invalid left-hand side in assignment" error
            // This script runs before other scripts and catches syntax errors
            window.onerror = function(message, source, lineno, colno, error) {
                if (message.includes('Invalid left-hand side in assignment')) {
                    console.warn('Caught syntax error: ' + message);
                    // Prevent the error from propagating
                    return true;
                }
            };
        });
    </script>
</head>
<body class="font-sans antialiased">
{{-- Migration notice: this CRM is being retired in favour of app.ozeeweb.com.au --}}
<div role="alert" style="position:sticky;top:0;z-index:9999;background:#b45309;color:#fff;padding:10px 16px;font-size:14px;line-height:1.4;">
    <strong>We're moving to <a href="https://app.ozeeweb.com.au" style="color:#fff;text-decoration:underline;">app.ozeeweb.com.au</a>.</strong>
    This CRM is being retired — please use the new app for all work and only use this one if absolutely necessary.
    {{-- Staff only: clients use magic links and never authenticate on the web guard --}}
    @auth
    <button type="button" onclick="document.getElementById('legacy-status').showModal()" style="margin-left:6px;background:#fff;color:#b45309;border:0;border-radius:4px;padding:2px 10px;font-weight:600;cursor:pointer;">
        What still works here?
    </button>
    @endauth
</div>
@auth
<style>#legacy-status::backdrop{background:rgba(0,0,0,.5)}</style>
<dialog id="legacy-status" aria-labelledby="legacy-status-title" style="max-width:640px;width:calc(100% - 32px);border:0;border-radius:8px;padding:24px;color:#1f2937;font-size:14px;line-height:1.5;">
    <h2 id="legacy-status-title" style="font-size:18px;font-weight:700;margin:0 0 4px;">This CRM is now read-only</h2>
    <p style="margin:0 0 16px;color:#4b5563;">Since 26 Oct 2026, do all new work in <a href="https://app.ozeeweb.com.au" style="color:#b45309;text-decoration:underline;">app.ozeeweb.com.au</a>. If something here must be changed, ask a super admin.</p>

    <h3 style="font-size:15px;font-weight:700;margin:0 0 6px;color:#15803d;">Still working</h3>
    <ul style="margin:0 0 16px 20px;list-style:disc;">
        <li>Viewing everything: projects, tasks, clients, emails, documents and reports</li>
        <li>Invoices: create, edit, approve and send through Xero, reject, comment and void</li>
        <li>Linking clients to Xero contacts</li>
        <li>Reading the inbox (emails received up to 26 Oct 2026)</li>
        <li>Emails sent by client websites and apps, and Stripe payments</li>
        <li>The client portal: clients can still log in</li>
    </ul>

    <h3 style="font-size:15px;font-weight:700;margin:0 0 6px;color:#b91c1c;">Stopped</h3>
    <ul style="margin:0 0 20px 20px;list-style:disc;">
        <li>Creating or editing anything other than invoices: projects, tasks, notes, meeting minutes, milestones, clients, leads, emails, resources and settings</li>
        <li>Inbox actions: replying, notes, drafts and approvals</li>
        <li>New emails arriving in the inbox</li>
        <li>Workflow automations and scheduled tasks</li>
        <li>Automatic Xero sync: invoice payment status only updates when Xero notifies the CRM</li>
        <li>Daily currency rates, points and streaks, and automatic file cleanup</li>
        <li>Creating tasks from the desktop tracker</li>
    </ul>

    <form method="dialog" style="text-align:right;margin:0;">
        <button style="background:#b45309;color:#fff;border:0;border-radius:4px;padding:6px 16px;font-weight:600;cursor:pointer;">Got it</button>
    </form>
</dialog>
@endauth
@inertia

@if(isset($page) && ($page['component'] ?? '') === 'Welcome')
<footer class="py-4 bg-gray-100 text-center text-gray-600 text-sm mt-auto">
    <div class="container mx-auto">
        <p>&copy; {{ date('Y') }} {{ config('app.name', 'Task Management System') }}. All rights reserved. <a href="{{ url('/privacy-policy') }}" class="text-indigo-600 hover:text-indigo-800">Privacy Policy</a></p>
    </div>
</footer>
@endif
</body>
</html>
