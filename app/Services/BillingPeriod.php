<?php

namespace App\Services;

/**
 * Value object describing the billing cycle that "now" falls into, as
 * resolved by BillingPeriodResolver. All dates are UTC.
 */
class BillingPeriod
{
    public function __construct(
        public readonly \DateTimeImmutable $start,
        public readonly \DateTimeImmutable $end,
        public readonly \DateTimeImmutable $now,
        public readonly int $elapsedDays,
        public readonly int $remainingDays,
        public readonly int $totalDays
    ) {
    }

    public function startDateTimeString(): string
    {
        return $this->start->format('Y-m-d H:i:s');
    }

    public function endDateString(): string
    {
        return $this->end->format('Y-m-d');
    }

    public function nowDateTimeString(): string
    {
        return $this->now->format('Y-m-d H:i:s');
    }

    public function todayDateString(): string
    {
        return $this->now->format('Y-m-d');
    }
}
