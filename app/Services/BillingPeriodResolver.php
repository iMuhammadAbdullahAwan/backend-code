<?php

namespace App\Services;

/**
 * Resolves which billing cycle "now" falls into, given a device tariff's
 * `billing_cycle_start_day` (1-31).
 *
 * Billing timezone: all dates are computed against explicit UTC
 * (\DateTimeZone('UTC')), regardless of the PHP process's ini timezone or
 * the MySQL server's session timezone. This matches the app's declared (but
 * previously unenforced) App::$appTimezone = 'UTC' and is scoped to billing
 * only — no application-wide timezone change is made. sensor_readings rows
 * are compared against these UTC-computed boundary strings as-is.
 *
 * Clamping rule for impossible dates: if the configured start day does not
 * exist in a given month (e.g. day 31 in a 30-day month, or day 29/30/31 in
 * February), the cycle anchor for that month is clamped to that month's
 * last day. This is deterministic and applied consistently both when
 * resolving the start of the current cycle and the start of the next cycle
 * (which also defines the current cycle's end, one day earlier).
 */
class BillingPeriodResolver
{
    private \DateTimeZone $timezone;

    public function __construct()
    {
        $this->timezone = new \DateTimeZone('UTC');
    }

    public function resolve(int $billingCycleStartDay, ?\DateTimeImmutable $now = null): BillingPeriod
    {
        $now = $now !== null
            ? $now->setTimezone($this->timezone)
            : new \DateTimeImmutable('now', $this->timezone);

        $startDay = max(1, min(31, $billingCycleStartDay));

        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');

        $anchorThisMonth = $this->anchorDate($year, $month, $startDay);

        if ($now >= $anchorThisMonth) {
            $billingStart = $anchorThisMonth;
            [$nextYear, $nextMonth] = $this->shiftMonth($year, $month, 1);
            $billingEnd = $this->anchorDate($nextYear, $nextMonth, $startDay)->modify('-1 day');
        } else {
            [$prevYear, $prevMonth] = $this->shiftMonth($year, $month, -1);
            $billingStart = $this->anchorDate($prevYear, $prevMonth, $startDay);
            $billingEnd = $anchorThisMonth->modify('-1 day');
        }

        $today = $now->setTime(0, 0, 0);

        $elapsedDays = $this->dateDiffInDays($billingStart, $today) + 1;
        $remainingDays = $this->dateDiffInDays($today, $billingEnd);
        $totalDays = $this->dateDiffInDays($billingStart, $billingEnd) + 1;

        return new BillingPeriod(
            $billingStart,
            $billingEnd,
            $now,
            max(0, $elapsedDays),
            max(0, $remainingDays),
            $totalDays
        );
    }

    private function daysInMonth(int $year, int $month): int
    {
        return (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    }

    private function anchorDate(int $year, int $month, int $startDay): \DateTimeImmutable
    {
        $day = min($startDay, $this->daysInMonth($year, $month));

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $this->timezone);
    }

    /**
     * Shifts a (year, month) pair by $delta months without the overflow
     * bugs of DateTime::modify('+1 month') (e.g. Jan 31 + 1 month naturally
     * rolling into March).
     */
    private function shiftMonth(int $year, int $month, int $delta): array
    {
        $index = ($year * 12 + ($month - 1)) + $delta;
        $newYear = intdiv($index, 12);
        $newMonth = $index % 12 + 1;

        return [$newYear, $newMonth];
    }

    private function dateDiffInDays(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $from->setTime(0, 0, 0)->diff($to->setTime(0, 0, 0))->format('%r%a');
    }
}
