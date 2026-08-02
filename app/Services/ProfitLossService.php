<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectExpendable;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ProfitLossService
{
    public function __construct(
        private readonly CurrencyConversionService $currencyService
    ) {}

    /**
     * Get the aggregated CEO dashboard data for a given date range.
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     */
    public function getDashboardData(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $projects = Project::with(['invoices.transactions', 'bills.transactions', 'expendable'])->get();

        $projectHealthCards = [];
        $totalInvoicedRevenueAud = 0.0;
        $totalBillsLoggedAud = 0.0;
        $totalCashRevenueAud = 0.0;
        $totalCashExpensesAud = 0.0;

        foreach ($projects as $project) {
            $invoices = $project->invoices;
            if ($startDate && $endDate) {
                $invoices = $invoices->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
            }

            $bills = $project->bills;
            if ($startDate && $endDate) {
                $bills = $bills->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
            }
            
            $expendables = $project->expendable;
            if ($startDate && $endDate) {
                $expendables = $expendables->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
            }

            $projectInvoicedAud = $this->calculateTotalInAud($invoices, 'total_amount', 'currency');
            $projectBillsAud = $this->calculateTotalInAud($bills, 'amount', 'currency');
            
            // For expendables not covered by bills
            $projectExpendableAud = $this->calculateTotalInAud(
                $expendables->filter(fn($e) => $e->bills->isEmpty() && $e->status->value === \App\Enums\ProjectExpendableStatus::Accepted->value), 
                'amount', 
                'currency'
            );

            $projectTotalCostsAud = $projectBillsAud + $projectExpendableAud;
            
            // Cash basis
            $projectCashInAud = 0.0;
            foreach ($invoices as $invoice) {
                foreach ($invoice->transactions->where('is_paid', true) as $tx) {
                    $projectCashInAud += $this->convertSafe((float)$tx->amount, $tx->currency ?? 'AUD', 'AUD');
                }
            }
            
            $projectCashOutAud = 0.0;
            foreach ($bills as $bill) {
                foreach ($bill->transactions->where('is_paid', true) as $tx) {
                    $projectCashOutAud += $this->convertSafe((float)$tx->amount, $tx->currency ?? 'AUD', 'AUD');
                }
            }

            $totalInvoicedRevenueAud += $projectInvoicedAud;
            $totalBillsLoggedAud += $projectTotalCostsAud;
            $totalCashRevenueAud += $projectCashInAud;
            $totalCashExpensesAud += $projectCashOutAud;

            if ($projectInvoicedAud > 0 || $projectTotalCostsAud > 0) {
                $netProfit = $projectInvoicedAud - $projectTotalCostsAud;
                $margin = $projectInvoicedAud > 0 ? ($netProfit / $projectInvoicedAud) * 100 : 0;
                
                $unpaidBalanceAlert = false;
                if ($projectBillsAud > $projectCashOutAud) {
                    $unpaidBalanceAlert = true;
                }

                $projectHealthCards[] = [
                    'project' => $project->only(['id', 'name']),
                    'invoiced_aud' => round($projectInvoicedAud, 2),
                    'costs_aud' => round($projectTotalCostsAud, 2),
                    'net_profit_aud' => round($netProfit, 2),
                    'margin_percent' => round($margin, 2),
                    'unpaid_balance_alert' => $unpaidBalanceAlert,
                    'cash_in_aud' => round($projectCashInAud, 2),
                    'cash_out_aud' => round($projectCashOutAud, 2),
                ];
            }
        }

        return [
            'overview' => [
                'total_invoiced_revenue_aud' => round($totalInvoicedRevenueAud, 2),
                'total_bills_logged_aud' => round($totalBillsLoggedAud, 2),
                'net_accrual_profit_aud' => round($totalInvoicedRevenueAud - $totalBillsLoggedAud, 2),
                'total_cash_revenue_aud' => round($totalCashRevenueAud, 2),
                'total_cash_expenses_aud' => round($totalCashExpensesAud, 2),
                'net_cash_profit_aud' => round($totalCashRevenueAud - $totalCashExpensesAud, 2),
            ],
            'projects' => $projectHealthCards,
        ];
    }

    /**
     * Get upcoming bills and expected invoices for the Cash Management timeline.
     *
     * @return array
     */
    public function getCashManagementTimeline(): array
    {
        $upcomingBills = Bill::with(['project', 'contractor'])
            ->whereIn('status', [\App\Enums\BillStatus::Approved, \App\Enums\BillStatus::PartialPaid])
            ->whereNotNull('due_date')
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($bill) {
                return [
                    'type' => 'bill',
                    'id' => $bill->id,
                    'project' => $bill->project->name ?? 'Unknown',
                    'amount' => $bill->amount,
                    'currency' => $bill->currency,
                    'amount_aud' => round($this->convertSafe((float)$bill->amount, $bill->currency ?? 'AUD', 'AUD'), 2),
                    'due_date' => $bill->due_date->format('Y-m-d'),
                    'status' => $bill->status->value,
                ];
            });

        $expectedInvoices = Invoice::with(['project', 'client'])
            ->whereIn('status', ['authorised', 'sent', 'partial_paid']) // based on mapSalesInvoiceStatus
            ->get()
            ->map(function ($invoice) {
                // Since user requested due_date is added to invoices, we check if it exists.
                // If not, we fallback to created_at + 30 days or +5 days as mentioned in the note,
                // "new invoice generated should be paid immidiately so we can safely consider it late after 5 days"
                $dueDate = isset($invoice->due_date) && $invoice->due_date 
                    ? Carbon::parse($invoice->due_date) 
                    : $invoice->created_at->addDays(5);
                    
                $daysSinceGeneration = $invoice->created_at->diffInDays(now());

                return [
                    'type' => 'invoice',
                    'id' => $invoice->id,
                    'project' => $invoice->project->name ?? 'Unknown',
                    'amount' => $invoice->total_amount,
                    'currency' => $invoice->currency,
                    'amount_aud' => round($this->convertSafe((float)$invoice->total_amount, $invoice->currency ?? 'AUD', 'AUD'), 2),
                    'due_date' => $dueDate->format('Y-m-d'),
                    'days_since_generation' => $daysSinceGeneration,
                    'status' => $invoice->status,
                ];
            })
            ->sortBy('due_date')
            ->values();

        return [
            'upcoming_bills' => $upcomingBills,
            'expected_invoices' => $expectedInvoices,
        ];
    }

    private function calculateTotalInAud(Collection $items, string $amountField, string $currencyField): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $amount = (float) $item->{$amountField};
            $currency = $item->{$currencyField} ?? 'AUD';
            $total += $this->convertSafe($amount, $currency, 'AUD');
        }
        return $total;
    }

    private function convertSafe(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($amount == 0) return 0.0;
        try {
            return $this->currencyService->convert($amount, $fromCurrency, $toCurrency);
        } catch (\Exception $e) {
            return $amount; // fallback
        }
    }
}
