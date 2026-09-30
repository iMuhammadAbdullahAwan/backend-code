<?php

use App\Models\DeviceModel;
use App\Models\SensorReadingModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * End-to-end coverage for GET /devices/{device_id}/status: verifies the
 * connection_status/is_online/last_seen fields added on top of the existing
 * device/latest_reading response.
 *
 * @internal
 */
final class DeviceControllerStatusTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh = true;
    // null = migrate all namespaces, since devices/sensor_readings live under App.
    protected $namespace = null;

    private DeviceModel $deviceModel;
    private SensorReadingModel $sensorReadingModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deviceModel = new DeviceModel();
        $this->sensorReadingModel = new SensorReadingModel();
    }

    private function insertDevice(string $deviceId): void
    {
        $this->deviceModel->insert([
            'device_id'   => $deviceId,
            'device_name' => 'Main Meter',
            'location'    => 'Main Panel',
            'status'      => 'active',
        ]);
    }

    private function insertReading(string $deviceId, string $recordedAt): array
    {
        $reading = [
            'device_id'   => $deviceId,
            'current'     => 0.0,
            'voltage'     => 187.3,
            'temperature' => 28.9,
            'power_watt'  => 0.0,
            'recorded_at' => $recordedAt,
        ];
        $this->sensorReadingModel->insert($reading);

        return $reading;
    }

    // ---- TEST 1: fresh reading ----

    public function testFreshReadingReportsOnline(): void
    {
        $this->insertDevice('DEV_FRESH');
        $recordedAt = date('Y-m-d H:i:s', time() - 10); // 10s old
        $this->insertReading('DEV_FRESH', $recordedAt);

        $result = $this->get('api/devices/DEV_FRESH/status');
        $result->assertOK();
        $body = json_decode($result->getJSON(), true);

        $this->assertSame('online', $body['data']['connection_status']);
        $this->assertTrue($body['data']['is_online']);
        $this->assertNotNull($body['data']['latest_reading']);
        $this->assertSame($recordedAt, $body['data']['last_seen']);
    }

    // ---- TEST 2: stale reading ----

    public function testStaleReadingReportsOfflineWithoutAlteringTheReading(): void
    {
        $this->insertDevice('DEV_STALE');
        $recordedAt = date('Y-m-d H:i:s', time() - 500); // well past 120s
        $this->insertReading('DEV_STALE', $recordedAt);

        $result = $this->get('api/devices/DEV_STALE/status');
        $result->assertOK();
        $body = json_decode($result->getJSON(), true);

        $this->assertSame('offline', $body['data']['connection_status']);
        $this->assertFalse($body['data']['is_online']);
        $this->assertSame($recordedAt, $body['data']['last_seen']);

        $latest = $body['data']['latest_reading'];
        $this->assertNotNull($latest);
        $this->assertEqualsWithDelta(187.3, (float) $latest['voltage'], 0.0001);
        $this->assertEqualsWithDelta(28.9, (float) $latest['temperature'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $latest['current'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $latest['power_watt'], 0.0001);
        $this->assertSame($recordedAt, $latest['recorded_at']);

        // The stored row itself must be untouched in the database too.
        $stored = $this->sensorReadingModel->where('device_id', 'DEV_STALE')->first();
        $this->assertEqualsWithDelta(187.3, (float) $stored['voltage'], 0.0001);
    }

    // ---- TEST 3: no reading ----

    public function testNoReadingReportsOfflineWithNullFields(): void
    {
        $this->insertDevice('DEV_NONE');

        $result = $this->get('api/devices/DEV_NONE/status');
        $result->assertOK();
        $body = json_decode($result->getJSON(), true);

        $this->assertSame('offline', $body['data']['connection_status']);
        $this->assertFalse($body['data']['is_online']);
        $this->assertNull($body['data']['latest_reading']);
        $this->assertNull($body['data']['last_seen']);
    }

    // ---- TEST 6: existing response compatibility ----

    public function testExistingDeviceAndLatestReadingFieldsArePreserved(): void
    {
        $this->insertDevice('DEV_COMPAT');
        $recordedAt = date('Y-m-d H:i:s', time() - 5);
        $this->insertReading('DEV_COMPAT', $recordedAt);

        $result = $this->get('api/devices/DEV_COMPAT/status');
        $result->assertOK();
        $body = json_decode($result->getJSON(), true);

        $this->assertArrayHasKey('device', $body['data']);
        $this->assertSame('DEV_COMPAT', $body['data']['device']['device_id']);
        $this->assertSame('active', $body['data']['device']['status']);

        $this->assertArrayHasKey('latest_reading', $body['data']);
        $this->assertArrayHasKey('recorded_at', $body['data']['latest_reading']);
        $this->assertArrayHasKey('voltage', $body['data']['latest_reading']);
    }

}
