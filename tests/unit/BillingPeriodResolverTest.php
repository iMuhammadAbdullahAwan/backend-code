<?php

use App\Services\BillingPeriodResolver;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class BillingPeriodResolverTest extends CIUnitTestCase
{
    private BillingPeriodResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new BillingPeriodResolver();
    }

    private function now(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC'));
    }

    public function testStartDayOneGivesCalendarMonth(): void
    {
        $period = $this->resolver->resolve(1, $this->now('2026-09-15 10:00:00'));

        $this->assertSame('2026-09-01', $period->start->format('Y-m-d'));
        $this->assertSame('2026-09-30', $period->endDateString());
        $this->assertSame(15, $period->elapsedDays);
        $this->assertSame(15, $period->remainingDays);
        $this->assertSame(30, $period->totalDays);
    }

    public function testStartDayFifteenCrossesMonthBoundary(): void
    {
        // Today (Sept 20) is after the 15th, so the cycle is Sep15 -> Oct14.
        $period = $this->resolver->resolve(15, $this->now('2026-09-20 08:00:00'));

        $this->assertSame('2026-09-15', $period->start->format('Y-m-d'));
        $this->assertSame('2026-10-14', $period->endDateString());
    }

    public function testStartDayFifteenBeforeAnchorUsesPreviousMonth(): void
    {
        // Today (Sept 10) is before the 15th, so we're still in the
        // Aug15 -> Sep14 cycle.
        $period = $this->resolver->resolve(15, $this->now('2026-09-10 08:00:00'));

        $this->assertSame('2026-08-15', $period->start->format('Y-m-d'));
        $this->assertSame('2026-09-14', $period->endDateString());
    }

    public function testStartDayOnAnchorDayStartsNewCycle(): void
    {
        $period = $this->resolver->resolve(15, $this->now('2026-09-15 00:00:00'));

        $this->assertSame('2026-09-15', $period->start->format('Y-m-d'));
        $this->assertSame(1, $period->elapsedDays);
    }

    public function testStartDayThirtyOneClampsInThirtyDayMonth(): void
    {
        // September only has 30 days, so the Sept anchor clamps to Sep 30.
        // On Sep 20 we're still in the Aug31 -> Sep29 cycle.
        $period = $this->resolver->resolve(31, $this->now('2026-09-20 00:00:00'));

        $this->assertSame('2026-08-31', $period->start->format('Y-m-d'));
        $this->assertSame('2026-09-29', $period->endDateString());
    }

    public function testStartDayThirtyOneOnClampedAnchorDay(): void
    {
        // Sep 30 is the clamped anchor for a "31" cycle in a 30-day month.
        $period = $this->resolver->resolve(31, $this->now('2026-09-30 12:00:00'));

        $this->assertSame('2026-09-30', $period->start->format('Y-m-d'));
        // Next anchor clamps to Oct 31 (October has 31 days).
        $this->assertSame('2026-10-30', $period->endDateString());
    }

    public function testFebruaryNonLeapYearClampsStartDayTwentyNine(): void
    {
        // 2026 is not a leap year (Feb has 28 days).
        $period = $this->resolver->resolve(29, $this->now('2026-02-20 00:00:00'));

        $this->assertSame('2026-01-29', $period->start->format('Y-m-d'));
        $this->assertSame('2026-02-27', $period->endDateString());
    }

    public function testFebruaryLeapYearClampsStartDayThirty(): void
    {
        // 2028 is a leap year (Feb has 29 days).
        $period = $this->resolver->resolve(30, $this->now('2028-02-20 00:00:00'));

        $this->assertSame('2028-01-30', $period->start->format('Y-m-d'));
        $this->assertSame('2028-02-28', $period->endDateString());
    }

    public function testThirtyOneDayMonth(): void
    {
        $period = $this->resolver->resolve(1, $this->now('2026-10-31 23:00:00'));

        $this->assertSame('2026-10-01', $period->start->format('Y-m-d'));
        $this->assertSame('2026-10-31', $period->endDateString());
        $this->assertSame(31, $period->elapsedDays);
        $this->assertSame(0, $period->remainingDays);
        $this->assertSame(31, $period->totalDays);
    }

    public function testLastDayOfCycleHasZeroRemainingDays(): void
    {
        $period = $this->resolver->resolve(1, $this->now('2026-04-30 23:59:00'));

        $this->assertSame(0, $period->remainingDays);
        $this->assertSame(30, $period->elapsedDays);
    }

    public function testFirstDayOfCycleHasOneElapsedDay(): void
    {
        $period = $this->resolver->resolve(1, $this->now('2026-04-01 00:05:00'));

        $this->assertSame(1, $period->elapsedDays);
        $this->assertSame(29, $period->remainingDays);
    }
}
