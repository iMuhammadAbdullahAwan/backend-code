<?php

use App\Models\DeviceModel;
use App\Models\SensorReadingModel;
use App\Controllers\FirebaseSync;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class FirebaseSyncDeduplicationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    private DeviceModel $deviceModel;
    private SensorReadingModel $sensorReadingModel;
    private FirebaseSync $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deviceModel = new DeviceModel();
        $this->sensorReadingModel = new SensorReadingModel();
        $this->controller = new FirebaseSync();

        $this->deviceModel->insert([
            'device_id' => 'energy',
            'status' => 'active'
        ]);
    }

    public function testInsertsGenuineNewReading()
    {
        // First reading
        $this->invokeInsertReading('energy', [
            'current' => 1.5,
            'voltage' => 220.0,
            'temp' => 30.0,
            'power_watt' => 330.0,
            'kwh' => 0.05
        ]);

        $this->assertSame(1, $this->sensorReadingModel->countAllResults());
        
        // Second reading - values changed
        $this->invokeInsertReading('energy', [
            'current' => 1.6,
            'voltage' => 221.0,
            'temp' => 30.5,
            'power_watt' => 353.6,
            'kwh' => 0.06
        ]);

        $this->assertSame(2, $this->sensorReadingModel->countAllResults());
    }

    public function testDeduplicatesCompletelyIdenticalPayloadWithoutTimestamp()
    {
        $payload = [
            'current' => 0.0,
            'voltage' => 187.3,
            'temp' => 28.9,
            'power' => 0.0,
            'kwh' => 0.0
        ];

        // First reading
        $this->invokeInsertReading('energy', $payload);
        $this->assertSame(1, $this->sensorReadingModel->countAllResults());

        // Identical reading later (cron running but device offline)
        $this->invokeInsertReading('energy', $payload);
        $this->assertSame(1, $this->sensorReadingModel->countAllResults());
    }

    public function testDoesNotDeduplicateIdenticalPayloadWithNewHardwareTimestamp()
    {
        // First reading
        $this->invokeInsertReading('energy', [
            'current' => 0.0,
            'voltage' => 187.3,
            'temp' => 28.9,
            'kwh' => 0.0,
            'recorded_at' => '2026-09-22 10:00:00'
        ]);
        $this->assertSame(1, $this->sensorReadingModel->countAllResults());

        // Identical values, BUT new timestamp (genuine idle device heartbeat)
        $this->invokeInsertReading('energy', [
            'current' => 0.0,
            'voltage' => 187.3,
            'temp' => 28.9,
            'kwh' => 0.0,
            'recorded_at' => '2026-09-22 10:01:00'
        ]);
        $this->assertSame(2, $this->sensorReadingModel->countAllResults());
    }

    public function testDeduplicatesPayloadThatOnlyDiffersBeyondStoredPrecision()
    {
        // Stored columns are DECIMAL(10,4); Firebase's live float carries
        // more precision than that (ADC/sensor jitter in the 5th decimal
        // place and beyond). Two readings that are identical once rounded
        // to the column's precision must still be treated as duplicates,
        // otherwise every cron tick (every minute, per CRON_SETUP.md)
        // inserts a fresh row forever even though nothing really changed.
        $this->invokeInsertReading('energy', [
            'current' => 0.52,
            'voltage' => 197.79,
            'temp' => 26.2,
            'kwh' => 0.06,
        ]);
        $this->assertSame(1, $this->sensorReadingModel->countAllResults());

        // Same values, but with ADC-level jitter below the DB column's
        // DECIMAL(10,4) precision (mirrors a real observed live payload of
        // voltage 202.60001 vs a stored 202.6000).
        $this->invokeInsertReading('energy', [
            'current' => 0.52,
            'voltage' => 197.790004,
            'temp' => 26.199997,
            'kwh' => 0.0600004,
        ]);
        $this->assertSame(1, $this->sensorReadingModel->countAllResults());
    }

    public function testDeduplicatesStaleHardwareTimestamp()
    {
        // First reading
        $this->invokeInsertReading('energy', [
            'current' => 1.5,
            'voltage' => 220.0,
            'temp' => 30.0,
            'kwh' => 0.05,
            'recorded_at' => '2026-09-22 10:00:00'
        ]);
        $this->assertSame(1, $this->sensorReadingModel->countAllResults());

        // Same payload, same timestamp
        $this->invokeInsertReading('energy', [
            'current' => 1.5,
            'voltage' => 220.0,
            'temp' => 30.0,
            'kwh' => 0.05,
            'recorded_at' => '2026-09-22 10:00:00'
        ]);
        $this->assertSame(1, $this->sensorReadingModel->countAllResults());
    }

    private function invokeInsertReading($deviceId, $data)
    {
        $reflection = new \ReflectionMethod($this->controller, 'insertReading');
        $reflection->setAccessible(true);
        $reflection->invoke($this->controller, $deviceId, $data);
    }
}
