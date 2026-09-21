<?php

use App\Models\BillingTariffModel;
use App\Models\SensorReadingModel;
use App\Services\BillingForecastService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class BillingForecastServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    // null = migrate all namespaces (equivalent to `spark migrate --all`),
    // since billing_tariffs/sensor_readings live under App, not Tests\Support.
    protected $namespace = null;

    private SensorReadingModel $sensorReadingModel;
    private BillingTariffModel $billingTariffModel;
    private BillingForecastService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sensorReadingModel = new SensorReadingModel();
        $this->billingTariffModel = new BillingTariffModel();
        $this->service = new BillingForecastService();
    }

    private function now(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC'));
    }

    private function insertReading(string $deviceId, string $recordedAt, ?float $kwh, float $current = 1.0, float $voltage = 220.0): void
    {
        $this->sensorReadingModel->insert([
            'device_id'   => $deviceId,
            'current'     => $current,
            'voltage'     => $voltage,
            'temperature' => 25.0,
            'power_watt'  => $current * $voltage,
            'kwh'         => $kwh,
            'recorded_at' => $recordedAt,
        ]);
    }

    private function insertFlatTariff(string $deviceId, float $rate = 10.0, int $startDay = 1): void
    {
        $this->billingTariffModel->insert([
            'device_id'               => $deviceId,
            'rate_per_kwh'            => $rate,
            'currency'                => 'PKR',
            'currency_symbol'         => 'Rs.',
            'billing_cycle_start_day' => $startDay,
            'tariff_type'             => 'flat',
            'slabs'                   => null,
        ]);
    }

    private function insertSlabTariff(string $deviceId, array $slabs, int $startDay = 1): void
    {
        $this->billingTariffModel->insert([
            'device_id'               => $deviceId,
            'rate_per_kwh'            => 0,
            'currency'                => 'PKR',
            'currency_symbol'         => 'Rs.',
            'billing_cycle_start_day' => $startDay,
            'tariff_type'             => 'slab',
            'slabs'                   => json_encode($slabs),
        ]);
    }

    // ---- Consumption aggregation (SensorReadingModel::sumKwhBetween) ----

    public function testNoReadingsGivesZeroUnits(): void
    {
        $this->insertFlatTariff('DEV_NONE');

        $result = $this->service->forecast('DEV_NONE', $this->now('2026-09-15 12:00:00'));

        $this->assertSame(0.0, $result['mtd_units']);
        $this->assertSame(0.0, $result['predicted_units']);
    }

    public function testOneReading(): void
    {
        $this->insertFlatTariff('DEV_ONE', 10.0);
        $this->insertReading('DEV_ONE', '2026-09-05 10:00:00', 5.0);

        $result = $this->service->forecast('DEV_ONE', $this->now('2026-09-15 12:00:00'));

        $this->assertSame(5.0, $result['mtd_units']);
    }

    public function testMultipleReadingsSumTogether(): void
    {
        $this->insertFlatTariff('DEV_MULTI', 10.0);
        $this->insertReading('DEV_MULTI', '2026-09-01 08:00:00', 2.5);
        $this->insertReading('DEV_MULTI', '2026-09-05 08:00:00', 3.5);
        $this->insertReading('DEV_MULTI', '2026-09-10 08:00:00', 4.0);

        $result = $this->service->forecast('DEV_MULTI', $this->now('2026-09-15 12:00:00'));

        $this->assertSame(10.0, $result['mtd_units']);
    }

    public function testDecimalKwhIsPreserved(): void
    {
        $this->insertFlatTariff('DEV_DEC', 10.0);
        $this->insertReading('DEV_DEC', '2026-09-05 08:00:00', 1.2345);
        $this->insertReading('DEV_DEC', '2026-09-06 08:00:00', 2.3333);

        $result = $this->service->forecast('DEV_DEC', $this->now('2026-09-15 12:00:00'));

        $this->assertEqualsWithDelta(3.5678, $result['mtd_units'], 0.0005);
    }

    public function testMultipleReadingsOnSameDayAreSummed(): void
    {
        $this->insertFlatTariff('DEV_SAMEDAY', 10.0);
        $this->insertReading('DEV_SAMEDAY', '2026-09-05 08:00:00', 1.0);
        $this->insertReading('DEV_SAMEDAY', '2026-09-05 12:00:00', 1.5);
        $this->insertReading('DEV_SAMEDAY', '2026-09-05 20:00:00', 2.0);

        $result = $this->service->forecast('DEV_SAMEDAY', $this->now('2026-09-15 12:00:00'));

        $this->assertSame(4.5, $result['mtd_units']);
    }

    public function testNullKwhIsExcludedFromSum(): void
    {
        $this->insertFlatTariff('DEV_NULL', 10.0);
        $this->insertReading('DEV_NULL', '2026-09-05 08:00:00', 5.0);
        $this->insertReading('DEV_NULL', '2026-09-06 08:00:00', null);

        $result = $this->service->forecast('DEV_NULL', $this->now('2026-09-15 12:00:00'));

        $this->assertSame(5.0, $result['mtd_units']);
    }

    public function testReadingsOutsideBillingPeriodAreExcluded(): void
    {
        $this->insertFlatTariff('DEV_OUT', 10.0);
        $this->insertReading('DEV_OUT', '2026-08-25 08:00:00', 100.0); // previous cycle
        $this->insertReading('DEV_OUT', '2026-09-05 08:00:00', 5.0);   // current cycle

        $result = $this->service->forecast('DEV_OUT', $this->now('2026-09-15 12:00:00'));

        $this->assertSame(5.0, $result['mtd_units']);
    }

    public function testDeviceIsolation(): void
    {
        $this->insertFlatTariff('DEV_A', 10.0);
        $this->insertFlatTariff('DEV_B', 10.0);
        $this->insertReading('DEV_A', '2026-09-05 08:00:00', 50.0);
        $this->insertReading('DEV_B', '2026-09-05 08:00:00', 999.0);

        $result = $this->service->forecast('DEV_A', $this->now('2026-09-15 12:00:00'));

        $this->assertSame(50.0, $result['mtd_units']);
    }

    // ---- MTD billing ----

    public function testMtdBillFlatTariff(): void
    {
        $this->insertFlatTariff('DEV_FLAT', 32.5);
        $this->insertReading('DEV_FLAT', '2026-09-05 08:00:00', 180.0);

        $result = $this->service->forecast('DEV_FLAT', $this->now('2026-09-15 12:00:00'));

        $this->assertSame(180.0, $result['mtd_units']);
        $this->assertEqualsWithDelta(5850.0, $result['mtd_bill'], 0.01);
    }

    public function testMtdBillZeroConsumption(): void
    {
        $this->insertFlatTariff('DEV_ZERO', 32.5);

        $result = $this->service->forecast('DEV_ZERO', $this->now('2026-09-15 12:00:00'));

        $this->assertSame(0.0, $result['mtd_units']);
        $this->assertSame(0.0, $result['mtd_bill']);
        $this->assertSame(0.0, $result['predicted_bill']);
    }

    public function testMtdBillProgressiveSlabTariff(): void
    {
        $this->insertSlabTariff('DEV_SLAB', [
            ['min_kwh' => 0, 'max_kwh' => 100, 'rate' => 22.0],
            ['min_kwh' => 101, 'max_kwh' => 300, 'rate' => 32.5],
            ['min_kwh' => 301, 'max_kwh' => null, 'rate' => 45.0],
        ]);
        $this->insertReading('DEV_SLAB', '2026-09-05 08:00:00', 180.0);

        $result = $this->service->forecast('DEV_SLAB', $this->now('2026-09-15 12:00:00'));

        // 100 units @ 22 + 80 units @ 32.5 = 2200 + 2600 = 4800
        $this->assertEqualsWithDelta(4800.0, $result['mtd_bill'], 0.01);
    }

    // ---- Forecast / average daily consumption ----

    public function testAvgDailyConsumptionAndForecastAtDayFifteen(): void
    {
        $this->insertFlatTariff('DEV_FORECAST', 32.5, 1);
        $this->insertReading('DEV_FORECAST', '2026-09-03 08:00:00', 180.0);

        $result = $this->service->forecast('DEV_FORECAST', $this->now('2026-09-15 12:00:00'));

        $this->assertSame(15, $result['elapsed_days']);
        $this->assertSame(15, $result['remaining_days']);
        $this->assertSame(30, $result['total_days']);
        $this->assertEqualsWithDelta(12.0, $result['avg_daily_units'], 0.001);
        $this->assertEqualsWithDelta(360.0, $result['predicted_units'], 0.001);
    }

    public function testForecastOnFirstDayOfCycle(): void
    {
        $this->insertFlatTariff('DEV_DAY1', 10.0, 1);
        $this->insertReading('DEV_DAY1', '2026-09-01 06:00:00', 6.0);

        $result = $this->service->forecast('DEV_DAY1', $this->now('2026-09-01 12:00:00'));

        $this->assertSame(1, $result['elapsed_days']);
        $this->assertEqualsWithDelta(6.0, $result['avg_daily_units'], 0.001);
        $this->assertEqualsWithDelta(6.0 * 30, $result['predicted_units'], 0.001);
    }

    public function testForecastOnLastDayOfCycleEqualsMtd(): void
    {
        $this->insertFlatTariff('DEV_LASTDAY', 10.0, 1);
        $this->insertReading('DEV_LASTDAY', '2026-09-10 06:00:00', 300.0);

        $result = $this->service->forecast('DEV_LASTDAY', $this->now('2026-09-30 23:00:00'));

        $this->assertSame(0, $result['remaining_days']);
        $this->assertEqualsWithDelta($result['mtd_units'], $result['predicted_units'], 0.0001);
        $this->assertEqualsWithDelta($result['mtd_bill'], $result['predicted_bill'], 0.01);
        $this->assertSame(0.0, $result['remaining_estimated_bill']);
    }

    public function testForecastZeroConsumptionStaysZero(): void
    {
        $this->insertFlatTariff('DEV_ZEROFC', 10.0, 1);

        $result = $this->service->forecast('DEV_ZEROFC', $this->now('2026-09-15 12:00:00'));

        $this->assertSame(0.0, $result['avg_daily_units']);
        $this->assertSame(0.0, $result['predicted_units']);
        $this->assertSame(0.0, $result['predicted_bill']);
    }

    public function testForecastFlatTariffBillIsRateTimesPredictedUnits(): void
    {
        $this->insertFlatTariff('DEV_FLATFC', 32.5, 1);
        $this->insertReading('DEV_FLATFC', '2026-09-03 08:00:00', 180.0);

        $result = $this->service->forecast('DEV_FLATFC', $this->now('2026-09-15 12:00:00'));

        $this->assertEqualsWithDelta(360.0 * 32.5, $result['predicted_bill'], 0.01);
    }

    /**
     * The critical regression guard: with a progressive slab tariff,
     * predicted_bill for double the units must NOT equal double the MTD
     * bill. It must be calculateCost() run on predicted_units directly.
     */
    public function testForecastProgressiveSlabIsNotLinearExtrapolation(): void
    {
        // Same 3-slab tariff as testMtdBillProgressiveSlabTariff, with an
        // open-ended top slab so all predicted units get priced.
        $this->insertSlabTariff('DEV_SLABFC', [
            ['min_kwh' => 0, 'max_kwh' => 100, 'rate' => 22.0],
            ['min_kwh' => 101, 'max_kwh' => 300, 'rate' => 32.5],
            ['min_kwh' => 301, 'max_kwh' => null, 'rate' => 45.0],
        ], 1);
        // 180 units by day 15 of a 30-day cycle -> predicted 360 units.
        $this->insertReading('DEV_SLABFC', '2026-09-03 08:00:00', 180.0);

        $result = $this->service->forecast('DEV_SLABFC', $this->now('2026-09-15 12:00:00'));

        $this->assertEqualsWithDelta(180.0, $result['mtd_units'], 0.001);
        $this->assertEqualsWithDelta(360.0, $result['predicted_units'], 0.001);

        // mtd_bill = calculateCost(180): 100@22 + 80@32.5 = 2200 + 2600 = 4800
        $this->assertEqualsWithDelta(4800.0, $result['mtd_bill'], 0.01);

        // predicted_bill = calculateCost(360): 100@22 + 200@32.5 + 60@45
        // = 2200 + 6500 + 2700 = 11400.0 (inclusive-bracket slab pricing,
        // fixed in BillingTariffModel::calculateCost())
        $this->assertEqualsWithDelta(11400.0, $result['predicted_bill'], 0.01);

        // The bug this test exists to catch: naive linear extrapolation
        // would produce mtd_bill * 2 = 9600, which is WRONG for a
        // progressive slab tariff and does not match the real engine output.
        $this->assertNotEqualsWithDelta($result['mtd_bill'] * 2, $result['predicted_bill'], 0.01);
    }

    /**
     * Regression proving the calculateCost() slab fix flows through
     * unchanged MTD/forecast orchestration: mtd_units=300 lands exactly on
     * the second bracket's ceiling and predicted_units=360 lands 60 units
     * into the third. Neither BillingPeriodResolver nor
     * BillingForecastService needed any change for this to be correct.
     */
    public function testMtdAndForecastRegressionAfterSlabFix(): void
    {
        $this->insertSlabTariff('DEV_SLABREG', [
            ['min_kwh' => 0, 'max_kwh' => 100, 'rate' => 22.0],
            ['min_kwh' => 101, 'max_kwh' => 300, 'rate' => 32.5],
            ['min_kwh' => 301, 'max_kwh' => null, 'rate' => 45.0],
        ], 1);
        $this->insertReading('DEV_SLABREG', '2026-09-05 08:00:00', 300.0);

        // Day 25 of a 30-day cycle: elapsed=25, remaining=5.
        // avg_daily = 300/25 = 12; predicted = 300 + 12*5 = 360.
        $result = $this->service->forecast('DEV_SLABREG', $this->now('2026-09-25 12:00:00'));

        $this->assertSame(25, $result['elapsed_days']);
        $this->assertSame(5, $result['remaining_days']);
        $this->assertEqualsWithDelta(300.0, $result['mtd_units'], 0.001);
        $this->assertEqualsWithDelta(360.0, $result['predicted_units'], 0.001);

        $this->assertSame(8700.0, $result['mtd_bill']);
        $this->assertSame(11400.0, $result['predicted_bill']);
    }

    public function testRemainingEstimatedBillIsDifferenceAndNotNegative(): void
    {
        $this->insertFlatTariff('DEV_REMAIN', 32.5, 1);
        $this->insertReading('DEV_REMAIN', '2026-09-03 08:00:00', 180.0);

        $result = $this->service->forecast('DEV_REMAIN', $this->now('2026-09-15 12:00:00'));

        $this->assertEqualsWithDelta($result['predicted_bill'] - $result['mtd_bill'], $result['remaining_estimated_bill'], 0.01);
        $this->assertGreaterThanOrEqual(0.0, $result['remaining_estimated_bill']);
    }

    // ---- Tariff fallback ----

    public function testFallsBackToGlobalDefaultTariffWhenDeviceHasNone(): void
    {
        // The billing_tariffs migration itself always seeds one global
        // default row (device_id IS NULL). Update it rather than inserting
        // a second NULL-device_id row, since SQL unique constraints treat
        // multiple NULLs as distinct and would make the fallback ambiguous.
        $globalTariff = $this->billingTariffModel->where('device_id', null)->first();
        $this->billingTariffModel->update($globalTariff['id'], ['rate_per_kwh' => 15.0]);

        $this->insertReading('DEV_NOTARIFF', '2026-09-05 08:00:00', 10.0);

        $result = $this->service->forecast('DEV_NOTARIFF', $this->now('2026-09-15 12:00:00'));

        $this->assertSame('PKR', $result['currency']);
        $this->assertEqualsWithDelta(150.0, $result['mtd_bill'], 0.01);
    }
}
