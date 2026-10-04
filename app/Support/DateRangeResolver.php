<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Resolves a report's date filter from the request — either a shortcut
 * (This Month / Previous Month / This Year / Past Year) or an explicit
 * date_from / date_to range.
 *
 * Used by the Payment History report in three places (see the warning atop
 * Manager\ReportExportController): the on-screen list, the inline PDF
 * preview, and the PDF download. All three call this one method so a filter
 * picked on screen is guaranteed to still be applied to whatever gets
 * exported — sharing this is what keeps that guarantee true.
 */
class DateRangeResolver
{
    public const SHORTCUTS = [
        'this_month' => 'This Month',
        'prev_month' => 'Previous Month',
        'this_year'  => 'This Year',
        'last_year'  => 'Past Year',
    ];

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}|null [$start, $end, $label], or null if no date filter is active.
     */
    public static function resolve(Request $request): ?array
    {
        $from = $request->query('date_from');
        $to   = $request->query('date_to');

        // An explicit custom range always wins over a shortcut, so switching
        // a "from" date doesn't require also clearing a leftover ?range=.
        if ($from || $to) {
            $start = $from ? Carbon::parse($from)->startOfDay() : Carbon::createFromDate(2000, 1, 1)->startOfDay();
            $end   = $to ? Carbon::parse($to)->endOfDay() : Carbon::today()->endOfDay();

            $label = ($from ? Carbon::parse($from)->format('M d, Y') : 'the beginning')
                . ' – ' . ($to ? Carbon::parse($to)->format('M d, Y') : 'today');

            return [$start, $end, $label];
        }

        $today = Carbon::today();

        switch ($request->query('range')) {
            case 'this_month':
                return [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), self::SHORTCUTS['this_month']];

            case 'prev_month':
                $prev = $today->copy()->subMonthNoOverflow();
                return [$prev->copy()->startOfMonth(), $prev->copy()->endOfMonth(), self::SHORTCUTS['prev_month']];

            case 'this_year':
                return [$today->copy()->startOfYear(), $today->copy()->endOfYear(), self::SHORTCUTS['this_year']];

            case 'last_year':
                $prevYear = $today->copy()->subYearNoOverflow();
                return [$prevYear->copy()->startOfYear(), $prevYear->copy()->endOfYear(), self::SHORTCUTS['last_year']];

            default:
                return null;
        }
    }
}
