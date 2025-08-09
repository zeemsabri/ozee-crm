<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 p-8">
    <div class="max-w-3xl mx-auto bg-white shadow-md rounded-lg p-6">
        <h1 class="text-2xl font-bold text-slate-800 mb-4">Privacy Policy for OZee-crm-email</h1>
        <p class="text-sm text-slate-600 mb-6">Last Updated: {{ date('F j, Y') }}</p>

        <p class="text-slate-700 mb-4">
            OZee-crm-email ("we," "us," or "our") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our web application (the "Application") that integrates with Google services to allow users to send messages via Google Chat. Please read this Privacy Policy carefully. If you do not agree with the terms of this Privacy Policy, please do not access the Application.
        </p>

        <section class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800 mb-3">1. Information We Collect</h2>
            <p class="text-slate-700 mb-2">We collect information from you when you use the Application, including:</p>

            <h3 class="text-lg font-medium text-slate-700 mt-4 mb-2">a. Personal Information</h3>
            <ul class="list-disc list-inside text-slate-700 space-y-2">
                <li><strong>Google Account Information</strong>: When you log in to the Application using your Google account, we collect your Google account email address and basic profile information (e.g., name) as permitted by the scopes you authorize during the OAuth consent process.</li>
                <li><strong>User-Generated Content</strong>: Messages you send through the Application to Google Chat spaces are processed and transmitted to Google's servers.</li>
                <li><strong>Authentication Tokens</strong>: We collect and store OAuth access tokens and refresh tokens to authenticate your Google account and enable sending messages on your behalf.</li>
            </ul>

            <h3 class="text-lg font-medium text-slate-700 mt-4 mb-2">b. Non-Personal Information</h3>
            <ul class="list-disc list-inside text-slate-700 space-y-2">
                <li><strong>Usage Data</strong>: We may collect information about how you interact with the Application, such as pages visited, features used, and timestamps of actions.</li>
                <li><strong>Device and Log Information</strong>: We collect information about your device, including IP address, browser type, operating system, and other technical details, to ensure the Application functions properly.</li>
            </ul>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800 mb-3">2. How We Use Your Information</h2>
            <p class="text-slate-700">We use the information we collect for the following purposes:</p>
            <ul class="list-disc list-inside text-slate-700 space-y-2 mt-2">
                <li><strong>Authentication</strong>: To authenticate your Google account and obtain OAuth tokens to access the Google Chat API on your behalf.</li>
                <li><strong>Service Delivery</strong>: To process and send your messages to Google Chat spaces as requested.</li>
                <li><strong>Improving the Application</strong>: To analyze usage patterns and improve the functionality and user experience of the Application.</li>
                <li><strong>Security</strong>: To monitor and protect the security of the Application and prevent unauthorized access or fraudulent activity.</li>
                <li><strong>Compliance and Legal Obligations</strong>: To comply with applicable laws, regulations, or legal processes.</li>
            </ul>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800 mb-3">3. How We Share Your Information</h2>
            <p class="text-slate-700 mb-2">We do not sell, trade, or rent your personal information to third parties. We may share your information in the following circumstances:</p>
            <ul class="list-disc list-inside text-slate-700 space-y-2">
                <li><strong>With Google</strong>: Your Google account information and messages are shared with Google's servers to authenticate your account and send messages via the Google Chat API.</li>
                <li><strong>Service Providers</strong>: We may share your information with third-party service providers who assist us in operating the Application (e.g., hosting providers, analytics services), but only to the extent necessary to perform their services.</li>
                <li><strong>Legal Requirements</strong>: We may disclose your information if required by law, such as to comply with a subpoena, court order, or other legal process.</li>
                <li><strong>Business Transfers</strong>: If we are involved in a merger, acquisition, or sale of assets, your information may be transferred as part of that transaction.</li>
            </ul>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800 mb-3">4. How We Store and Secure Your Information</h2>
            <ul class="list-disc list-inside text-slate-700 space-y-2">
                <li><strong>Storage</strong>: Your Google OAuth tokens are securely stored in our database with encryption to protect them from unauthorized access. Messages sent via the Application are not stored by us after they are transmitted to Google Chat.</li>
                <li><strong>Security Measures</strong>: We implement industry-standard security measures, including encryption and access controls, to protect your data. However, no method of transmission over the internet or electronic storage is 100% secure, and we cannot guarantee absolute security.</li>
                <li><strong>Retention</strong>: We retain your OAuth tokens as long as your account is active or as needed to provide the Application's services. You can revoke access at any time (see Section 7). Usage data is retained for a limited period for analytics purposes, typically no longer than 12 months.</li>
            </ul>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800 mb-3">5. Your Choices and Rights</h2>
            <ul class="list-disc list-inside text-slate-700 space-y-2">
                <li><strong>Access and Control</strong>: You can revoke the Application's access to your Google account at any time by visiting your Google Account settings (<a href="https://myaccount.google.com/permissions" class="text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer">https://myaccount.google.com/permissions</a>) and removing the Application.</li>
                <li><strong>Data Access and Deletion</strong>: You may request access to or deletion of your personal information by contacting us at <a href="mailto:info@OZeeWeb.com.au" class="text-blue-600 hover:underline">info@OZeeWeb.com.au</a>. We will respond to your request within a reasonable timeframe, subject to legal obligations.</li>
                <li><strong>Opt-Out</strong>: If you no longer wish to use the Application, you can stop using it and revoke access as described above.</li>
            </ul>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800 mb-3">6. Third-Party Services</h2>
            <p class="text-slate-700">
                The Application integrates with Google services, including Google OAuth and the Google Chat API. Your interactions with these services are governed by Google's Privacy Policy (<a href="https://policies.google.com/privacy" class="text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer">https://policies.google.com/privacy</a>). We encourage you to review Google's policies to understand how they handle your data.
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800 mb-3">7. Revoking Access</h2>
            <p class="text-slate-700 mb-2">You can revoke the Application's access to your Google account at any time by:</p>
            <ol class="list-decimal list-inside text-slate-700 space-y-2">
                <li>Visiting <a href="https://myaccount.google.com/permissions" class="text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer">https://myaccount.google.com/permissions</a>.</li>
                <li>Locating "OZee-crm-email" in the list of third-party apps.</li>
                <li>Clicking "Remove Access."</li>
            </ol>
            <p class="text-slate-700 mt-2">Revoking access will prevent the Application from sending messages on your behalf or accessing your Google account data.</p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800 mb-3">8. Children's Privacy</h2>
            <p class="text-slate-700">
                The Application is not intended for use by individuals under the age of 13. We do not knowingly collect personal information from children under 13. If you believe we have collected such information, please contact us immediately at <a href="mailto:info@OZeeWeb.com.au" class="text-blue-600 hover:underline">info@OZeeWeb.com.au</a>, and we will take steps to delete it.
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800 mb-3">9. International Users</h2>
            <p class="text-slate-700">
                The Application is hosted in the United States. If you access the Application from outside the United States, your data may be transferred to, stored, and processed in the United States, where data protection laws may differ from those in your country. By using the Application, you consent to this transfer.
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800 mb-3">10. Changes to This Privacy Policy</h2>
            <p class="text-slate-700">
                We may update this Privacy Policy from time to time to reflect changes in our practices or legal requirements. We will notify you of material changes by posting the updated policy on this page with a revised "Last Updated" date. We encourage you to review this policy periodically.
            </p>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800 mb-3">11. Contact Us</h2>
            <p class="text-slate-700">
                If you have any questions, concerns, or requests regarding this Privacy Policy or our data practices, please contact us at:
            </p>
            <ul class="list-disc list-inside text-slate-700 space-y-2 mt-2">
                <li><strong>Email</strong>: <a href="mailto:info@OZeeWeb.com.au" class="text-blue-600 hover:underline">Info@OZeeWeb.com.au</a></li>
                <li><strong>Address</strong>: 27 Forest Cr, Thornlie WA, 6108, Austrlia</li>
            </ul>
        </section>

        <p class="text-slate-700 text-sm mt-6">
            This Privacy Policy is effective as of {{ date('F j, Y') }}.
        </p>
    </div>
</body>
</html>
