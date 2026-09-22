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
