<?php

namespace App\Models;

use CodeIgniter\Model;

class SensorReadingModel extends Model
{
    protected $table = 'sensor_readings';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['device_id', 'current', 'voltage', 'temperature', 'power_watt', 'energy', 'kwh', 'power', 'recorded_at'];

    /**
     * Sums the per-reading `kwh` column for a device within an inclusive
     * recorded_at window. This is the single source of truth for "units
     * consumed" anywhere in the app: consumption is SUM(kwh), never a
     * meter-register delta, since sensor_readings has no previous/current
     * reading concept. SQL SUM() ignores NULL kwh values automatically, and
     * negative readings are summed as-is (not clamped) to preserve existing
     * data semantics.
     */
    public function sumKwhBetween(string $deviceId, string $from, string $to): float
    {
        $row = $this->selectSum('kwh')
            ->where('device_id', $deviceId)
            ->where('recorded_at >=', $from)
            ->where('recorded_at <=', $to)
            ->get()
            ->getRow();

        return ($row && $row->kwh !== null) ? (float) $row->kwh : 0.0;
    }
}
