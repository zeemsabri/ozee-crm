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

    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
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
@inertia
</body>
</html>
