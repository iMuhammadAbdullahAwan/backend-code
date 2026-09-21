<?php

use App\Models\BillingTariffModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Focused tests for BillingTariffModel::calculateCost()'s progressive slab
 * pricing. calculateCost() takes only its two parameters and never touches
 * the database, so these are pure unit tests.
 *
 * @internal
 */
final class BillingTariffModelCalculateCostTest extends CIUnitTestCase
{
    private BillingTariffModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new BillingTariffModel();
    }

    /**
     * The tariff documented in BACKEND_BILLING_BUDGET_REPORT.md and used
     * throughout the app's planning docs: 0-100, 101-300, 301+.
     */
    private function exampleTariff(): array
    {
        return [
            'tariff_type' => 'slab',
            'slabs' => [
                ['min_kwh' => 0, 'max_kwh' => 100, 'rate' => 22.0],
                ['min_kwh' => 101, 'max_kwh' => 300, 'rate' => 32.5],
                ['min_kwh' => 301, 'max_kwh' => null, 'rate' => 45.0],
            ],
        ];
    }

    // ---- Example tariff, full sweep ----

    public function testZeroUnits(): void
    {
        $this->assertSame(0.0, $this->model->calculateCost(0, $this->exampleTariff()));
    }

    public function test50Units(): void
    {
        $this->assertSame(1100.0, $this->model->calculateCost(50, $this->exampleTariff()));
    }

    public function test100UnitsExactBoundary(): void
    {
        $this->assertSame(2200.0, $this->model->calculateCost(100, $this->exampleTariff()));
    }

    public function test101Units(): void
    {
        $this->assertEqualsWithDelta(2232.5, $this->model->calculateCost(101, $this->exampleTariff()), 0.001);
    }

    public function test200Units(): void
    {
        $this->assertSame(5450.0, $this->model->calculateCost(200, $this->exampleTariff()));
    }

    public function test299Units(): void
    {
        // 100@22 + 199@32.5 = 2200 + 6467.5 = 8667.5
        $this->assertEqualsWithDelta(8667.5, $this->model->calculateCost(299, $this->exampleTariff()), 0.001);
    }

    public function test300UnitsExactBoundary(): void
    {
        // Must land entirely within the second bracket: 100@22 + 200@32.5.
        // Before the fix this incorrectly spilled 1 unit into the 45 rate.
        $this->assertSame(8700.0, $this->model->calculateCost(300, $this->exampleTariff()));
    }

    public function test301UnitsExactBoundary(): void
    {
        // First unit of the open-ended third bracket.
        $this->assertSame(8745.0, $this->model->calculateCost(301, $this->exampleTariff()));
    }

    public function test360Units(): void
    {
        $this->assertSame(11400.0, $this->model->calculateCost(360, $this->exampleTariff()));
    }

    // ---- Multiple bounded slabs: protects every boundary, not just one ----

    public function testMultipleBoundedSlabsRespectEveryInclusiveBoundary(): void
    {
        $tariff = [
            'tariff_type' => 'slab',
            'slabs' => [
                ['min_kwh' => 0, 'max_kwh' => 100, 'rate' => 10.0],
                ['min_kwh' => 101, 'max_kwh' => 200, 'rate' => 20.0],
                ['min_kwh' => 201, 'max_kwh' => 300, 'rate' => 30.0],
                ['min_kwh' => 301, 'max_kwh' => null, 'rate' => 40.0],
            ],
        ];

        // Exactly on every boundary: each bracket must hold exactly 100 units.
        $this->assertSame(1000.0, $this->model->calculateCost(100, $tariff));   // 100@10
        $this->assertSame(3000.0, $this->model->calculateCost(200, $tariff));   // 100@10 + 100@20
        $this->assertSame(6000.0, $this->model->calculateCost(300, $tariff));   // 100@10 + 100@20 + 100@30
        $this->assertSame(6040.0, $this->model->calculateCost(301, $tariff));   // + 1@40
        $this->assertSame(10000.0, $this->model->calculateCost(400, $tariff));  // + 100@40
    }

    // ---- No open-ended top slab: must never silently underbill ----

    public function testIncompleteSlabConfigurationThrowsInsteadOfUnderbilling(): void
    {
        $tariff = [
            'tariff_type' => 'slab',
            'slabs' => [
                ['min_kwh' => 0, 'max_kwh' => 100, 'rate' => 22.0],
                ['min_kwh' => 101, 'max_kwh' => 300, 'rate' => 32.5],
                // No max_kwh = null slab to absorb consumption beyond 300.
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->model->calculateCost(301, $tariff);
    }

    public function testIncompleteSlabConfigurationExactlyAtCeilingDoesNotThrow(): void
    {
        $tariff = [
            'tariff_type' => 'slab',
            'slabs' => [
                ['min_kwh' => 0, 'max_kwh' => 100, 'rate' => 22.0],
                ['min_kwh' => 101, 'max_kwh' => 300, 'rate' => 32.5],
            ],
        ];

        // Exactly at the defined ceiling: fully priced, no exception.
        $this->assertSame(8700.0, $this->model->calculateCost(300, $tariff));
    }

    public function testCorruptedSlabConfigurationThrows(): void
    {
        $tariff = [
            'tariff_type' => 'slab',
            'slabs' => [
                ['min_kwh' => 0, 'max_kwh' => 300, 'rate' => 22.0],
                ['min_kwh' => 101, 'max_kwh' => 100, 'rate' => 32.5], // max_kwh < previousMax
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Corrupted slab configuration: negative slab capacity detected.');
        $this->model->calculateCost(301, $tariff);
    }

    // ---- min_kwh is a label only; it must not affect pricing ----

    public function testFirstSlabMinKwhOneProducesSameResultAsZero(): void
    {
        $tariffStartingAtZero = $this->exampleTariff();
        $tariffStartingAtOne = $this->exampleTariff();
        $tariffStartingAtOne['slabs'][0]['min_kwh'] = 1;

        foreach ([50, 100, 101, 300, 360] as $units) {
            $this->assertSame(
                $this->model->calculateCost($units, $tariffStartingAtZero),
                $this->model->calculateCost($units, $tariffStartingAtOne),
                "min_kwh must not affect pricing for {$units} units"
            );
        }
    }

    // ---- Flat tariff is unaffected by the slab fix ----

    public function testFlatTariffUnaffected(): void
    {
        $tariff = ['tariff_type' => 'flat', 'rate_per_kwh' => 32.5, 'slabs' => null];

        $this->assertSame(5850.0, $this->model->calculateCost(180, $tariff));
    }
}
