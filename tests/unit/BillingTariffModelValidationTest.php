<?php

use App\Models\BillingTariffModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class BillingTariffModelValidationTest extends CIUnitTestCase
{
    private BillingTariffModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new class extends BillingTariffModel {
            // Expose the protected method for testing
            public function testValidateSlabConfig(array $data): array
            {
                return $this->validateSlabConfig($data);
            }
        };
    }

    public function testValidProgressiveSlabs(): void
    {
        $data = [
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'max_kwh' => 100, 'rate' => 22.0],
                    ['min_kwh' => 101, 'max_kwh' => 300, 'rate' => 32.5],
                    ['min_kwh' => 301, 'max_kwh' => null, 'rate' => 45.0],
                ],
            ]
        ];

        $result = $this->model->testValidateSlabConfig($data);
        $this->assertIsString($result['data']['slabs']);
    }

    public function testSingleOpenEndedSlab(): void
    {
        $data = [
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'max_kwh' => null, 'rate' => 22.0],
                ],
            ]
        ];

        $result = $this->model->testValidateSlabConfig($data);
        $this->assertIsString($result['data']['slabs']);
    }

    public function testInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slab configuration.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => '{ invalid json }',
            ]
        ]);
    }

    public function testEmptySlabsArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slab tariff must contain at least one slab.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [],
            ]
        ]);
    }

    public function testSlabsNotArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slab configuration.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => '{"min_kwh": 0, "max_kwh": null, "rate": 22}', // Not a json array
            ]
        ]);
    }

    public function testMissingRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slab configuration.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'max_kwh' => null],
                ],
            ]
        ]);
    }

    public function testNonNumericRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slab rate must be a non-negative number.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'max_kwh' => null, 'rate' => 'free'],
                ],
            ]
        ]);
    }

    public function testNegativeRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slab rate must be a non-negative number.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'max_kwh' => null, 'rate' => -5],
                ],
            ]
        ]);
    }

    public function testMissingMaxKwh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slab configuration.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'rate' => 10],
                ],
            ]
        ]);
    }

    public function testNonNumericMaxKwh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slab configuration.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'max_kwh' => 'lots', 'rate' => 10],
                ],
            ]
        ]);
    }

    public function testNegativeMaxKwh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slab configuration.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'max_kwh' => -100, 'rate' => 10],
                ],
            ]
        ]);
    }

    public function testUnorderedMaxKwh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slab maximum values must be strictly increasing.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'max_kwh' => 300, 'rate' => 10],
                    ['min_kwh' => 301, 'max_kwh' => 100, 'rate' => 20],
                    ['min_kwh' => 101, 'max_kwh' => null, 'rate' => 30],
                ],
            ]
        ]);
    }

    public function testDuplicateMaxKwh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slab maximum values must be strictly increasing.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'max_kwh' => 100, 'rate' => 10],
                    ['min_kwh' => 101, 'max_kwh' => 100, 'rate' => 20],
                    ['min_kwh' => 101, 'max_kwh' => null, 'rate' => 30],
                ],
            ]
        ]);
    }

    public function testFinalSlabHasNumericMaxKwh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slab tariff must end with an open-ended slab.');

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'max_kwh' => 100, 'rate' => 10],
                    ['min_kwh' => 101, 'max_kwh' => 200, 'rate' => 20],
                ],
            ]
        ]);
    }

    public function testNoOpenEndedFinalSlabMiddle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slab maximum values must be strictly increasing.'); // Or similar error

        $this->model->testValidateSlabConfig([
            'data' => [
                'tariff_type' => 'slab',
                'slabs' => [
                    ['min_kwh' => 0, 'max_kwh' => null, 'rate' => 10],
                    ['min_kwh' => 101, 'max_kwh' => 200, 'rate' => 20],
                ],
            ]
        ]);
    }
}
