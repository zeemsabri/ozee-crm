<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StripeConfiguration;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StripeConfigurationController extends Controller
{
    /**
     * Display a listing of stripe configurations.
     */
    public function index()
    {
        $configurations = StripeConfiguration::latest()->get();

        return Inertia::render('Admin/StripeConfigurations/Index', [
            'configurations' => $configurations,
        ]);
    }

    /**
     * Store a newly created stripe configuration.
     */
    public function store(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'app_id' => 'required|string|max:255|unique:stripe_configurations,app_id',
            'stripe_secret_key' => 'required|string',
            'stripe_public_key' => 'required|string',
            'settings' => 'nullable|array',
        ]);

        StripeConfiguration::create($request->all());

        return back()->with('success', 'Stripe configuration created successfully.');
    }

    /**
     * Update the specified stripe configuration.
     */
    public function update(Request $request, StripeConfiguration $stripeConfiguration)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'app_id' => 'required|string|max:255|unique:stripe_configurations,app_id,' . $stripeConfiguration->id,
            'stripe_secret_key' => 'nullable|string',
            'stripe_public_key' => 'required|string',
            'settings' => 'nullable|array',
        ]);

        $data = $request->all();
        if (empty($data['stripe_secret_key'])) {
            unset($data['stripe_secret_key']);
        }

        $stripeConfiguration->update($data);

        return back()->with('success', 'Stripe configuration updated successfully.');
    }

    /**
     * Remove the specified stripe configuration.
     */
    public function destroy(StripeConfiguration $stripeConfiguration)
    {
        $stripeConfiguration->delete();

        return back()->with('success', 'Stripe configuration deleted successfully.');
    }
}
