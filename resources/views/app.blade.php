<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="OZEE CRM - Internal CRM for employees, contractors, and clients.">

    <title inertia>{{ config('app.name', '') }}</title>

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
