<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailApp;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmailAppController extends Controller
{
    public function index()
    {
        $apps = EmailApp::with('templates:id,name')
            ->latest()
            ->get()
            ->map(function (EmailApp $app) {
                return [
                    'id' => $app->id,
                    'name' => $app->name,
                    'slug' => $app->slug,
                    'description' => $app->description,
                    'is_active' => $app->is_active,
                    'delivery_mode' => $app->delivery_mode,
                    'smtp_host' => $app->smtp_host,
                    'smtp_port' => $app->smtp_port,
                    'smtp_username' => $app->smtp_username,
                    'smtp_encryption' => $app->smtp_encryption,
                    'smtp_from_address' => $app->smtp_from_address,
                    'smtp_from_name' => $app->smtp_from_name,
                    'smtp_reply_to' => $app->smtp_reply_to,
                    'api_provider' => $app->api_provider,
                    'api_base_url' => $app->api_base_url,
                    'templates' => $app->templates,
                    'template_ids' => $app->templates->pluck('id')->all(),
                    'has_smtp_password' => ! empty($app->smtp_password),
                    'has_api_key' => ! empty($app->api_key),
                    'has_api_secret' => ! empty($app->api_secret),
                ];
            });

        $templates = EmailTemplate::select('id', 'name', 'slug')->orderBy('name')->get();

        return Inertia::render('Admin/EmailApps/Index', [
            'apps' => $apps,
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request));

        $app = EmailApp::create($this->payloadFromValidated($validated));
        $app->templates()->sync($validated['template_ids'] ?? []);

        return back()->with('success', 'Email app created successfully.');
    }

    public function update(Request $request, EmailApp $emailApp)
    {
        $validated = $request->validate($this->rules($request, $emailApp->id));

        $payload = $this->payloadFromValidated($validated, false);

        if (! $request->filled('smtp_password')) {
            unset($payload['smtp_password']);
        }

        if (! $request->filled('api_key')) {
            unset($payload['api_key']);
        }

        if (! $request->filled('api_secret')) {
            unset($payload['api_secret']);
        }

        $emailApp->update($payload);
        $emailApp->templates()->sync($validated['template_ids'] ?? []);

        return back()->with('success', 'Email app updated successfully.');
    }

    public function destroy(EmailApp $emailApp)
    {
        $emailApp->delete();

        return back()->with('success', 'Email app deleted successfully.');
    }

    private function rules(Request $request, ?int $id = null): array
    {
        $mode = $request->input('delivery_mode', 'smtp');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('email_apps', 'slug')->ignore($id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'delivery_mode' => ['required', Rule::in(['smtp', 'api'])],

            'smtp_host' => [Rule::requiredIf($mode === 'smtp'), 'nullable', 'string', 'max:255'],
            'smtp_port' => [Rule::requiredIf($mode === 'smtp'), 'nullable', 'integer', 'between:1,65535'],
            'smtp_username' => [Rule::requiredIf($mode === 'smtp'), 'nullable', 'string', 'max:255'],
            'smtp_password' => [$id ? 'nullable' : Rule::requiredIf($mode === 'smtp'), 'string'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'starttls'])],
            'smtp_from_address' => [Rule::requiredIf($mode === 'smtp'), 'nullable', 'email:rfc,dns'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'smtp_reply_to' => ['nullable', 'email:rfc,dns'],

            'api_provider' => [Rule::requiredIf($mode === 'api'), 'nullable', 'string', 'max:255'],
            'api_base_url' => ['nullable', 'url'],
            'api_key' => ['nullable', 'string'],
            'api_secret' => ['nullable', 'string'],

            'template_ids' => ['nullable', 'array'],
            'template_ids.*' => ['integer', 'exists:email_templates,id'],
        ];
    }

    private function payloadFromValidated(array $validated, bool $generateSlug = true): array
    {
        $payload = [
            'name' => $validated['name'],
            'slug' => ! empty($validated['slug'])
                ? Str::slug($validated['slug'])
                : ($generateSlug ? Str::slug($validated['name']) : null),
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'delivery_mode' => $validated['delivery_mode'],
            'smtp_host' => $validated['smtp_host'] ?? null,
            'smtp_port' => $validated['smtp_port'] ?? null,
            'smtp_username' => $validated['smtp_username'] ?? null,
            'smtp_password' => $validated['smtp_password'] ?? null,
            'smtp_encryption' => $validated['smtp_encryption'] ?? null,
            'smtp_from_address' => $validated['smtp_from_address'] ?? null,
            'smtp_from_name' => $validated['smtp_from_name'] ?? null,
            'smtp_reply_to' => $validated['smtp_reply_to'] ?? null,
            'api_provider' => $validated['api_provider'] ?? null,
            'api_base_url' => $validated['api_base_url'] ?? null,
            'api_key' => $validated['api_key'] ?? null,
            'api_secret' => $validated['api_secret'] ?? null,
        ];

        if (! $generateSlug && $payload['slug'] === null) {
            unset($payload['slug']);
        }

        return $payload;
    }
}
