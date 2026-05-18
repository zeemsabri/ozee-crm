<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('Admin/Financials/Dashboard');
    }

    public function bills(): Response
    {
        return Inertia::render('Admin/Financials/Bills');
    }

    public function invoices(): Response
    {
        return Inertia::render('Admin/Financials/Invoices');
    }

    public function showInvoice(int $id): Response
    {
        $invoice = \App\Models\Invoice::with(['client', 'project', 'invoiceItems.projectService', 'comments.user', 'files'])->findOrFail($id);
        return Inertia::render('Admin/Financials/InvoiceDetails', [
            'invoice' => $invoice,
        ]);
    }
}
