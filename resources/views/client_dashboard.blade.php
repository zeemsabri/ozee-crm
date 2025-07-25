<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Client Dashboard - Access and review your project resources">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Client Dashboard') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Firebase Configuration -->
    <script>
        // These variables will be used by the client_dashboard.js script
        const __app_id = "{{ config('services.firebase.app_id', 'default-app-id') }}";
        const __firebase_config = JSON.stringify({
            apiKey: "{{ config('services.firebase.api_key', '') }}",
            authDomain: "{{ config('services.firebase.auth_domain', '') }}",
            projectId: "{{ config('services.firebase.project_id', '') }}",
            storageBucket: "{{ config('services.firebase.storage_bucket', '') }}",
            messagingSenderId: "{{ config('services.firebase.messaging_sender_id', '') }}",
            appId: "{{ config('services.firebase.app_id', '') }}"
        });
        const __initial_auth_token = "{{ $token ?? '' }}";
    </script>

    <!-- React and ReactDOM CDN -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>

    <!-- Firebase CDN -->
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-auth-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-firestore-compat.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div id="root">
        <!-- Loading state while React app initializes -->
        <div class="flex items-center justify-center min-h-screen">
            <div class="text-xl font-semibold text-gray-700">Loading Client Dashboard...</div>
        </div>
    </div>

    <!-- Load the client dashboard React application -->
    <script src="{{ asset('client_dashboard.js') }}" type="module"></script>
</body>
</html>
