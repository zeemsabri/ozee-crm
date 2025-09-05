<?php

// config/forms.php

/**
 * Defines the structure for various forms used in the application.
 * This centralized approach makes it easy to manage form fields and properties.
 */

return [

    'contact_form' => [
        'title' => 'Get in Touch',
        'description' => 'We\'re excited to learn more about your project. Please fill out the form below, and one of our specialists will contact you shortly.',
        'fields' => [
            ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'placeholder' => 'e.g., Jane Doe', 'required' => true],
            ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'placeholder' => 'e.g., jane.doe@example.com', 'required' => true],
            ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'placeholder' => 'e.g., 0412 345 678', 'required' => false],
            ['name' => 'company_name', 'label' => 'Company Name', 'type' => 'text', 'placeholder' => 'e.g., Future Co', 'required' => false],
            ['name' => 'abn', 'label' => 'ABN', 'type' => 'text', 'placeholder' => 'e.g., 50 123 456 789', 'required' => false],
            ['name' => 'address', 'label' => 'Address', 'type' => 'text', 'placeholder' => 'e.g., 123 Example St, Sydney NSW 2000', 'required' => false],
            ['name' => 'message', 'label' => 'Your Message', 'type' => 'textarea', 'placeholder' => 'Tell us a bit about your project or requirements...', 'required' => true],
        ],
        'submit_button_text' => 'Send Inquiry',
    ],

];
