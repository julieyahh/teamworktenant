<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * The payment calendar: rent is raised on the 1st and due on the 10th, every
 * month, for every tenant (config/billing.php → payment_days).
 *
 * Pure date arithmetic, no database, so every rule here is cheap to test and
 * the same answer comes out of the nightly command, approval, the payments
 * screen and the dashboard.
 *
 * A RENT PERIOD always ends on the next "opens" day (the 1st). A lease that
 * starts on the 1st gets whole calendar months from day one. A lease that
 * starts on the 17th gets a short first period, the 17th to the 1st, and then
 * whole months. Leases created before v19 ran from their own start date, so
 * their next period is short in exactly the same way, which is how they join
 * the calendar without a gap or an overlap.
 */
class RentCalendar
{
    /** The day each month's rent is raised (1 = the 1st). */
    public static function opensOnDay(): int
    {
        return min(28, max(1, (int) config('billing.payment_days.opens_on_day', 1)));
    }

    /** The last day to pay without a penalty (10 = the 10th). */
    public static function dueOnDay(): int
    {
        return min(28, max(1, (int) config('billing.payment_days.due_on_day', 10)));
    }

    public static function minNoticeDays(): int
    {
        return max(0, (int) config('billing.payment_days.min_notice_days', 5));
    }

    /**
     * The end of the rent period that starts on $start: the next opens-day
     * strictly after it. Exclusive, like every period_end in rent_charges.
     */
    public static function periodEnd(Carbon $start): Carbon
    {
        $start = $start->copy()->startOfDay();
        $day   = self::opensOnDay();

        $candidate = $start->copy()->startOfMonth()->day($day);

        if ($candidate->lte($start)) {
            $candidate = $start->copy()->startOfMonth()->addMonthNoOverflow()->day($day);
        }

        return $candidate;
    }

    /** Is the period starting here a whole month (starts on the opens-day)? */
    public static function isWholeMonth(Carbon $start): bool
    {
        return (int) $start->day === self::opensOnDay();
    }

    /**
     * The fraction of a month's rent a period starting on $start costs.
     * 1.0 for a whole month; days/days-in-month for a short one.
     */
    public static function fractionOfMonth(Carbon $start): float
    {
        if (self::isWholeMonth($start) || ! config('billing.rent.prorate_partial_month', true)) {
            return 1.0;
        }

        $start = $start->copy()->startOfDay();
        $end   = self::periodEnd($start);

        // The month the short period sits in — measured against the month
        // that the period is part of, so 17 Feb → 1 Mar is x/28 and
        // 17 Mar → 1 Apr is x/31.
        $monthStart  = $end->copy()->subMonthNoOverflow();
        $daysInMonth = (int) $monthStart->diffInDays($end);
        $days        = (int) $start->diffInDays($end);

        if ($daysInMonth <= 0) {
            return 1.0;
        }

        return min(1.0, $days / $daysInMonth);
    }

    /** What a period starting on $start costs at $monthlyRent. */
    public static function amountFor(Carbon $start, float $monthlyRent, int $precision = 2): float
    {
        return round($monthlyRent * self::fractionOfMonth($start), $precision);
    }

    /**
     * The first due-day (the 10th) on or after $from + the minimum notice.
     *
     *   period starting 1 Oct  → 10 Oct
     *   period starting 8 Oct  → 10 Nov   (2 days' notice is too little)
     *   period starting 17 Oct → 10 Nov
     */
    public static function nextDueDate(Carbon $from, ?int $minNotice = null): Carbon
    {
        $earliest = $from->copy()->startOfDay()->addDays($minNotice ?? self::minNoticeDays());
        $day      = self::dueOnDay();

        $candidate = $earliest->copy()->startOfMonth()->day($day);

        if ($candidate->lt($earliest)) {
            $candidate = $earliest->copy()->startOfMonth()->addMonthNoOverflow()->day($day);
        }

        return $candidate;
    }

    /** When the rent for a period starting on $start has to be paid by. */
    public static function rentDueDate(Carbon $start): Carbon
    {
        return self::nextDueDate($start);
    }

    /**
     * Default due date for a utility bill.
     *
     * The due-day of the month AFTER the month being billed — a "September
     * 2026" bill is due 10 October — unless that is already too close or
     * past, in which case the next due-day that gives proper notice.
     */
    public static function billDueDate(?string $billingMonth, ?Carbon $issuedOn = null): Carbon
    {
        $issuedOn = ($issuedOn ?? Carbon::today())->copy()->startOfDay();
        $earliest = self::nextDueDate($issuedOn);

        $month = self::parseBillingMonth($billingMonth);

        if (! $month) {
            return $earliest;
        }

        $candidate = $month->copy()->addMonthNoOverflow()->day(self::dueOnDay());

        return $candidate->lt($earliest) ? $earliest : $candidate;
    }

    /**
     * "2026-09", "September 2026", "Sep 2026", "09/2026" → the 1st of that
     * month. Null when it cannot be read as a month.
     */
    public static function parseBillingMonth(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m', 'F Y', 'M Y', 'm/Y', 'F, Y', 'M, Y', 'Y F'] as $format) {
            try {
                $parsed = Carbon::createFromFormat('!' . $format, $value);

                if ($parsed !== false && $parsed->format($format) === $value) {
                    return $parsed->startOfMonth();
                }
            } catch (\Throwable $e) {
                // try the next format
            }
        }

        // Case-insensitive last resort for "september 2026" and the like.
        try {
            $parsed = Carbon::parse('1 ' . $value);

            return $parsed->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The one spelling every billing month is stored and compared in:
     * "September 2026". Anything unreadable is kept as typed, trimmed, so it
     * still compares equal to itself.
     */
    public static function normaliseBillingMonth(?string $value): string
    {
        $month = self::parseBillingMonth($value);

        return $month ? $month->format('F Y') : trim((string) $value);
    }

    /**
     * Where today sits in the payment cycle, for the tenant dashboard.
     *
     * @return array{open:bool, opens:Carbon, closes:Carbon, days_left:int}
     */
    public static function window(?Carbon $today = null): array
    {
        $today  = ($today ?? Carbon::today())->copy()->startOfDay();
        $opens  = $today->copy()->startOfMonth()->day(self::opensOnDay());
        $closes = $today->copy()->startOfMonth()->day(self::dueOnDay());

        if ($closes->lt($opens)) {
            $closes->addMonthNoOverflow();
        }

        if ($today->gt($closes)) {
            $opens  = $opens->copy()->addMonthNoOverflow();
            $closes = $closes->copy()->addMonthNoOverflow();
        }

        $open = $today->betweenIncluded($opens, $closes);

        return [
            'open'      => $open,
            'opens'     => $opens,
            'closes'    => $closes,
            'days_left' => $open ? (int) $today->diffInDays($closes) : 0,
        ];
    }

    /** "1st–10th", for labels. */
    public static function label(): string
    {
        return self::ordinal(self::opensOnDay()) . '–' . self::ordinal(self::dueOnDay());
    }

    public static function ordinal(int $n): string
    {
        if (in_array($n % 100, [11, 12, 13], true)) {
            return $n . 'th';
        }

        return $n . (['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'][$n % 10]);
    }
}
