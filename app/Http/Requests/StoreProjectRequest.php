<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if the user can manage projects
        return Auth::user()->hasPermission('create_projects');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Determine if the request is JSON or FormData
        $isJsonRequest = $this->isJson() || $this->header('Content-Type') === 'application/json';

        // Check for file uploads to better detect FormData requests
        $hasFileUploads = $this->hasFile('logo') || $this->hasFile('documents');
        if ($hasFileUploads) {
            $isJsonRequest = false;
        }

        // Basic validation rules for project creation
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
            'social_media_link' => 'nullable|url',
            'preferred_keywords' => 'nullable|string',
            'google_chat_id' => 'nullable|string|max:255',
            'status' => 'required|in:active,completed,on_hold,archived',
            'project_type' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'google_drive_link' => 'nullable|url',
//            'payment_type' => 'required|in:one_off,monthly',
        ];

        // Add file validation rules only for non-JSON requests
        if (!$isJsonRequest) {
            $rules['logo'] = 'nullable|image|max:2048';
            $rules['documents.*'] = 'nullable|file|mimes:pdf,doc,docx,jpg,png|max:10240';
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Handle FormData with JSON strings for arrays and objects
        if (!$this->isJson() && ($this->header('Content-Type') === 'multipart/form-data' || $this->hasFile('logo') || $this->hasFile('documents'))) {
            // Parse JSON strings in FormData for array/object fields
            $fields = ['services', 'service_details', 'transactions', 'notes'];
            foreach ($fields as $field) {
                if ($this->has($field) && is_string($this->input($field))) {
                    try {
                        $this->merge([$field => json_decode($this->input($field), true)]);
                    } catch (\Exception $e) {
                        // If JSON decoding fails, leave the field as is
                    }
                }
            }
        }
    }
}
