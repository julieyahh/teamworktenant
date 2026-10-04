<?php

namespace App\Services;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The invoice for an accepted payment (v19).
 *
 * Built from what the system actually recorded when the payment was applied,
 * never recomputed from today's balances:
 *
 *   Utilities     one line per bill the money landed on (bill_payment_allocations),
 *                 plus any part that went to credit.
 *   Rent          one line; rent comes off the lease balance as a lump.
 *   Advance Rent  what went to arrears and what was held ahead, from the
 *                 advance_months / advance_amount written on the payment.
 *   Deposit       one line.
 *
 * Used by the tenant (Tenant\PaymentController@invoice) and the office
 * (Manager\PaymentRecordController@invoice) so both see the same document.
 */
class InvoiceService
{
    /**
     * @return array{lines: array<int,array{description:string, detail:?string, amount:float}>, total:float}
     */
    public function lines(Payment $payment): array
    {
        $payment->loadMissing(['lease.unit', 'liveAllocations.bill']);

        $total = round((float) $payment->pay_amount, 2);
        $room  = $payment->lease?->room_no;
        $lines = [];

        switch ($payment->payment_for) {
            case 'Utilities':
                $applied = 0.0;

                foreach ($payment->liveAllocations as $alloc) {
                    $bill = $alloc->bill;

                    $lines[] = [
                        'description' => trim(($bill->billing_type ?? $payment->utility_type ?? 'Utility') . ' bill — ' . ($bill->billing_month ?? '')),
                        'detail'      => $bill
                            ? 'Bill #' . $bill->id
                              . ($bill->kwh_reading ? ' · ' . number_format((float) $bill->kwh_reading, 2) . ' kWh' : '')
                              . ($bill->due_date ? ' · due ' . $bill->due_date->format('M d, Y') : '')
                            : null,
                        'amount'      => round((float) $alloc->amount, 2),
                    ];

                    $applied = round($applied + (float) $alloc->amount, 2);
                }

                $rest = round($total - $applied, 2);

                if ($rest >= 0.01) {
                    $lines[] = [
                        'description' => 'Held as credit on your account',
                        'detail'      => 'More than the open ' . strtolower((string) ($payment->utility_type ?: 'utility'))
                            . ' bills at the time; applied to your next bill.',
                        'amount'      => $rest,
                    ];
                }

                if (! $lines) {
                    $lines[] = ['description' => 'Utilities' . ($payment->utility_type ? ' — ' . $payment->utility_type : ''), 'detail' => null, 'amount' => $total];
                }
                break;

            case 'Advance Rent':
                $held    = round((float) ($payment->advance_amount ?? 0), 2);
                $arrears = round($total - $held, 2);
                $months  = (int) ($payment->advance_months ?? 0);

                if ($arrears >= 0.01) {
                    $lines[] = ['description' => 'Rent and penalties already due', 'detail' => 'Settled first', 'amount' => $arrears];
                }

                $lines[] = [
                    'description' => 'Rent paid in advance',
                    'detail'      => $months > 0
                        ? $months . ' month' . ($months === 1 ? '' : 's') . ' ahead, held against future rent only'
                        : 'Held against future rent only',
                    'amount'      => $held > 0 ? $held : $total,
                ];
                break;

            case 'Deposit':
                $lines[] = ['description' => 'Security deposit', 'detail' => $room ? 'Room ' . $room : null, 'amount' => $total];
                break;

            default:
                $lines[] = [
                    'description' => ($payment->payment_for ?: 'Rent') . ($room ? ' — Room ' . $room : ''),
                    'detail'      => 'Applied to outstanding rent, then to any late penalty',
                    'amount'      => $total,
                ];
        }

        return ['lines' => $lines, 'total' => $total];
    }

    public function filename(Payment $payment): string
    {
        return $payment->invoiceNumber() . '.pdf';
    }

    public function download(Payment $payment)
    {
        $payment->loadMissing(['tenant', 'lease.unit']);

        $pdf = Pdf::loadView('invoices.payment', [
            'payment'  => $payment,
            'invoice'  => $this->lines($payment),
            'business' => config('invoice'),
        ])->setPaper('a4');

        return $pdf->download($this->filename($payment));
    }
}
