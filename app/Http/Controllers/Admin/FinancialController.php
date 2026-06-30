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

    public function showBill(int $id): Response
    {
        $bill = \App\Models\Bill::with([
            'contractor',
            'project',
            'expendable',
            'transactionType',
            'paymentDetail',
            'approvalInstance.steps.actedBy',
            'approvalInstance.steps.approverRole',
            'approvalInstance.steps.approverUser',
        ])->findOrFail($id);
        $transactionTypes = \App\Models\TransactionType::query()
            ->select(['id', 'name', 'xero_account_code'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Financials/BillDetails', [
            'bill' => $bill,
            'transaction_types' => $transactionTypes,
        ]);
    }

    public function invoices(): Response
    {
        return Inertia::render('Admin/Financials/Invoices');
    }

    public function projectServices(): Response
    {
        return Inertia::render('Admin/Financials/ProjectServices');
    }

    public function showInvoice(int $id): Response
    {
        $invoice = \App\Models\Invoice::with(['client', 'project.clients', 'invoiceItems.projectService', 'comments.user', 'files'])->findOrFail($id);
        return Inertia::render('Admin/Financials/InvoiceDetails', [
            'invoice' => $invoice,
        ]);
    }

    public function xeroSync(): Response
    {
        return Inertia::render('Admin/Financials/XeroInvoiceSync');
    }
}
