<?php

use App\Libraries\DeviceConnectionStatus;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * DeviceConnectionStatus::resolve() takes only its parameters and never
 * touches the database, so these are pure unit tests.
 *
 * @internal
 */
final class DeviceConnectionStatusTest extends CIUnitTestCase
{
    private const TIMEOUT = 120;

    // Fixed "now" so age math is deterministic regardless of wall-clock time.
    private const NOW = 1_800_000_000; // 2027-01-15 08:00:00 UTC

    private function reading(array $overrides = []): array
    {
        return array_merge([
            'voltage'     => '187.3000',
            'temperature' => '28.9000',
            'current'     => '0.0000',
            'power_watt'  => '0.0000',
            'recorded_at' => '2026-09-21 18:14:18',
        ], $overrides);
    }

    // ---- TEST 1: fresh reading ----

    public function testFreshReadingIsOnline(): void
    {
        $recordedAt = self::NOW - 30; // 30s old, well within the 120s timeout
        $reading = $this->reading(['recorded_at' => date('Y-m-d H:i:s', $recordedAt)]);

        $result = DeviceConnectionStatus::resolve($reading, self::TIMEOUT, self::NOW);

        $this->assertSame('online', $result['connection_status']);
        $this->assertTrue($result['is_online']);
        $this->assertSame($reading['recorded_at'], $result['last_seen']);
    }

    // ---- TEST 2: stale reading ----

    public function testStaleReadingIsOfflineButReadingValuesAreUntouched(): void
    {
        $recordedAt = self::NOW - 500; // well past the 120s timeout
        $reading = $this->reading(['recorded_at' => date('Y-m-d H:i:s', $recordedAt)]);

        $result = DeviceConnectionStatus::resolve($reading, self::TIMEOUT, self::NOW);

        $this->assertSame('offline', $result['connection_status']);
        $this->assertFalse($result['is_online']);
        $this->assertSame($reading['recorded_at'], $result['last_seen']);

        // The reading array itself must be untouched by resolve() — it is
        // the caller's job to keep passing the original row through.
        $this->assertSame('187.3000', $reading['voltage']);
        $this->assertSame('28.9000', $reading['temperature']);
        $this->assertSame('0.0000', $reading['current']);
        $this->assertSame('0.0000', $reading['power_watt']);
    }

    // ---- TEST 3: no reading ----

    public function testNoReadingIsOffline(): void
    {
        $result = DeviceConnectionStatus::resolve(null, self::TIMEOUT, self::NOW);

        $this->assertSame('offline', $result['connection_status']);
        $this->assertFalse($result['is_online']);
        $this->assertNull($result['last_seen']);
    }

    public function testReadingWithoutRecordedAtIsOffline(): void
    {
        $reading = $this->reading(['recorded_at' => null]);

        $result = DeviceConnectionStatus::resolve($reading, self::TIMEOUT, self::NOW);

        $this->assertSame('offline', $result['connection_status']);
        $this->assertFalse($result['is_online']);
        $this->assertNull($result['last_seen']);
    }

    // ---- TEST 4: exact 120s boundary ----

    public function testExactlyAtTimeoutIsOnline(): void
    {
        $recordedAt = self::NOW - self::TIMEOUT; // exactly 120s old
        $reading = $this->reading(['recorded_at' => date('Y-m-d H:i:s', $recordedAt)]);

        $result = DeviceConnectionStatus::resolve($reading, self::TIMEOUT, self::NOW);

        // age <= timeout => online (documented boundary behavior)
        $this->assertSame('online', $result['connection_status']);
        $this->assertTrue($result['is_online']);
    }

    public function testOneSecondPastTimeoutIsOffline(): void
    {
        $recordedAt = self::NOW - self::TIMEOUT - 1; // 121s old
        $reading = $this->reading(['recorded_at' => date('Y-m-d H:i:s', $recordedAt)]);

        $result = DeviceConnectionStatus::resolve($reading, self::TIMEOUT, self::NOW);

        $this->assertSame('offline', $result['connection_status']);
        $this->assertFalse($result['is_online']);
    }

    // ---- TEST 5: configurable timeout (not a controller magic number) ----

    public function testTimeoutIsCallerSuppliedNotHardcoded(): void
    {
        $recordedAt = self::NOW - 200; // 200s old
        $reading = $this->reading(['recorded_at' => date('Y-m-d H:i:s', $recordedAt)]);

        // Offline under the default 120s timeout...
        $default = DeviceConnectionStatus::resolve($reading, 120, self::NOW);
        $this->assertSame('offline', $default['connection_status']);

        // ...but online once the caller supplies a larger configured timeout,
        // proving the threshold is a parameter, not baked into the helper.
        $wider = DeviceConnectionStatus::resolve($reading, 300, self::NOW);
        $this->assertSame('online', $wider['connection_status']);
    }

    // ---- Malformed timestamp must not crash ----

    public function testMalformedTimestampFailsSafeToOffline(): void
    {
        $reading = $this->reading(['recorded_at' => 'not-a-real-timestamp']);

        $result = DeviceConnectionStatus::resolve($reading, self::TIMEOUT, self::NOW);

        $this->assertSame('offline', $result['connection_status']);
        $this->assertFalse($result['is_online']);
        $this->assertSame('not-a-real-timestamp', $result['last_seen']);
    }
}
