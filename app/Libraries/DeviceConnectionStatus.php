<?php

namespace App\Libraries;

/**
 * Derives a device's online/offline connection state from the age of its
 * latest sensor reading. This is a pure calculation over the reading array
 * already fetched by the caller — it never queries the database and never
 * mutates the reading, so historical sensor_readings rows and the
 * devices.status enum are both left untouched.
 */
class DeviceConnectionStatus
{
    /**
     * @param array<string, mixed>|null $latestReading The newest sensor_readings row for the device, or null if none exists.
     * @param int                        $timeoutSeconds Max age (in seconds) for a reading to be considered fresh.
     * @param int|null                   $now Current unix timestamp; defaults to time(). Overridable for tests.
     *
     * @return array{connection_status: string, is_online: bool, last_seen: string|null}
     */
    public static function resolve(?array $latestReading, int $timeoutSeconds, ?int $now = null): array
    {
        $offline = ['connection_status' => 'offline', 'is_online' => false, 'last_seen' => null];

        if (!$latestReading || empty($latestReading['recorded_at'])) {
            return $offline;
        }

        $lastSeen = $latestReading['recorded_at'];
        $recordedAt = strtotime($lastSeen);

        // Malformed/unparseable timestamp: fail safe to offline rather than
        // crash the endpoint, but still surface the raw value as last_seen
        // since it is the last known reading regardless of format.
        if ($recordedAt === false) {
            $offline['last_seen'] = $lastSeen;

            return $offline;
        }

        $now ??= time();
        $isOnline = ($now - $recordedAt) <= $timeoutSeconds;

        return [
            'connection_status' => $isOnline ? 'online' : 'offline',
            'is_online' => $isOnline,
            'last_seen' => $lastSeen,
        ];
    }
}
