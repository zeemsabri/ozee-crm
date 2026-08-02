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
        $projects = Project::with([
            'invoices' => function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
                }
            },
            'invoices.transactions',
            'bills' => function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
                }
            },
            'bills.transactions',
            'expendable' => function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
                }
            },
            'expendable.bills'
        ])->get();

        $projectHealthCards = [];
        $totalInvoicedRevenueAud = 0.0;
        $totalBillsLoggedAud = 0.0;
        $totalCashRevenueAud = 0.0;
        $totalCashExpensesAud = 0.0;
        $paidInvoicesAndBills = [];

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

            $baseCurrency = config('services.default_currency', 'AUD');

            $projectInvoicedAud = $this->calculateTotalInBase($invoices, 'total_amount', 'currency', $baseCurrency);
            $projectBillsAud = $this->calculateTotalInBase($bills, 'amount', 'currency', $baseCurrency);
            
            // For expendables not covered by bills
            $projectExpendableAud = $this->calculateTotalInBase(
                $expendables->filter(fn($e) => $e->bills->isEmpty() && $e->status->value === \App\Enums\ProjectExpendableStatus::Accepted->value), 
                'amount', 
                'currency',
                $baseCurrency
            );

            $projectTotalCostsAud = $projectBillsAud + $projectExpendableAud;
            
            // Cash basis
            $projectCashInAud = 0.0;
            foreach ($invoices as $invoice) {
                $invoicePaidTx = $invoice->transactions->where('is_paid', true);
                if ($invoicePaidTx->isNotEmpty()) {
                    $paidAmount = 0.0;
                    foreach ($invoicePaidTx as $tx) {
                        $amountAud = $this->convertToAud((float)$tx->amount, $tx->currency ?? $baseCurrency, $tx);
                        $paidAmount += $amountAud;
                        $projectCashInAud += $amountAud;
                    }
                    $paidInvoicesAndBills[] = [
                        'type' => 'invoice',
                        'id' => $invoice->id,
                        'reference' => $invoice->invoice_number,
                        'project_name' => $project->name,
                        'amount_aud' => round($paidAmount, 2),
                        'date' => $invoice->created_at->format('Y-m-d'),
                    ];
                }
            }
            
            $projectCashOutAud = 0.0;
            foreach ($bills as $bill) {
                $billPaidTx = $bill->transactions->where('is_paid', true);
                if ($billPaidTx->isNotEmpty()) {
                    $paidAmount = 0.0;
                    foreach ($billPaidTx as $tx) {
                        $amountAud = $this->convertToAud((float)$tx->amount, $tx->currency ?? $baseCurrency, $tx);
                        $paidAmount += $amountAud;
                        $projectCashOutAud += $amountAud;
                    }
                    $paidInvoicesAndBills[] = [
                        'type' => 'bill',
                        'id' => $bill->id,
                        'reference' => 'Bill #' . $bill->id,
                        'project_name' => $project->name,
                        'amount_aud' => round($paidAmount, 2),
                        'date' => $bill->created_at->format('Y-m-d'),
                    ];
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

        $baseCurrency = config('services.default_currency', 'AUD');
        
        return [
            'overview' => [
                'total_invoiced_revenue_aud' => round($totalInvoicedRevenueAud, 2),
                'total_bills_logged_aud' => round($totalBillsLoggedAud, 2),
                'net_accrual_profit_aud' => round($totalInvoicedRevenueAud - $totalBillsLoggedAud, 2),
                'total_cash_revenue_aud' => round($totalCashRevenueAud, 2),
                'total_cash_expenses_aud' => round($totalCashExpensesAud, 2),
                'net_cash_profit_aud' => round($totalCashRevenueAud - $totalCashExpensesAud, 2),
                'base_currency' => $baseCurrency,
            ],
            'projects' => $projectHealthCards,
            'paid_activities' => collect($paidInvoicesAndBills)->sortByDesc('date')->values()->toArray(),
        ];
    }

    /**
     * Get upcoming bills and expected invoices for the Cash Management timeline.
     *
     * @return array
     */
    public function getCashManagementTimeline(): array
    {
        $baseCurrency = config('services.default_currency', 'AUD');

        $upcomingBills = Bill::with(['project', 'contractor'])
            ->whereIn('status', [\App\Enums\BillStatus::Approved, \App\Enums\BillStatus::PartialPaid])
            ->whereNotNull('due_date')
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($bill) use ($baseCurrency) {
                return [
                    'type' => 'bill',
                    'id' => $bill->id,
                    'project' => $bill->project->name ?? 'Unknown',
                    'amount' => $bill->amount,
                    'currency' => $bill->currency,
                    'amount_aud' => round($this->convertToAud((float)$bill->amount, $bill->currency ?? $baseCurrency, $bill), 2),
                    'due_date' => $bill->due_date->format('Y-m-d'),
                    'status' => $bill->status->value,
                ];
            });

        $expectedInvoices = Invoice::with(['project', 'client'])
            ->whereIn('status', ['authorised', 'sent', 'partial_paid']) // based on mapSalesInvoiceStatus
            ->get()
            ->map(function ($invoice) use ($baseCurrency) {
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
                    'amount_aud' => round($this->convertToAud((float)$invoice->total_amount, $invoice->currency ?? $baseCurrency, $invoice), 2),
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

    private function calculateTotalInBase(Collection $items, string $amountField, string $currencyField, string $baseCurrency): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $amount = (float) $item->{$amountField};
            $currency = $item->{$currencyField} ?? $baseCurrency;
            $total += $this->convertToAud($amount, $currency, $item);
        }
        return $total;
    }

    /**
     * Convert an amount to AUD using historical rate if available, or fallback.
     */
    private function convertToAud(float $amount, string $currency, $record = null): float
    {
        $currency = strtoupper($currency);
        if ($currency === 'AUD') {
            return $amount;
        }

        // a) The historical rate locked on the record's transaction date.
        if ($record instanceof \App\Models\Transaction) {
            if ($record->exchange_rate && $record->exchange_rate > 0) {
                $targetCurrency = 'AUD';
                if ($record->bill) {
                    $targetCurrency = $record->bill->currency ?? 'AUD';
                } elseif ($record->invoice) {
                    $targetCurrency = $record->invoice->currency ?? 'AUD';
                }

                $targetAmount = $amount / (float) $record->exchange_rate;

                if (strtoupper($targetCurrency) === 'AUD') {
                    return $targetAmount;
                }

                return $this->convertSafe($targetAmount, $targetCurrency, 'AUD');
            }
        }

        // b) Fallback: $amount * (1 / rate_to_usd_for_item_currency) * rate_to_usd_for_aud
        return $this->convertSafe($amount, $currency, 'AUD');
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
