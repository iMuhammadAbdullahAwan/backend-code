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
     * Consumption for a device within an inclusive recorded_at window. This
     * is the single source of truth for "units consumed" anywhere in the
     * app.
     *
     * `kwh` is the hardware's cumulative energy register (it only ever
     * grows, e.g. 0.00 -> 0.11 over many days for a light load), NOT a
     * per-reading delta — the sync cron inserts a row roughly every minute
     * regardless of whether consumption actually changed, so SUM(kwh) across
     * those rows counts the same near-static register value hundreds of
     * times a day. Consumption must instead be the DELTA of that register:
     * (last reading in the window) - (last known reading before the window
     * started). If no reading exists before the window, the window's own
     * first reading is used as the baseline (nothing is known about
     * consumption before the device started reporting).
     *
     * A negative delta means the hardware's register was reset (e.g. power
     * loss) partway through the window; in that case the register's value
     * since the reset is used as-is rather than subtracting a larger prior
     * baseline into a nonsensical negative total.
     */
    public function kwhDeltaBetween(string $deviceId, string $from, string $to): float
    {
        $latest = $this->select('kwh')
            ->where('device_id', $deviceId)
            ->where('recorded_at >=', $from)
            ->where('recorded_at <=', $to)
            ->where('kwh IS NOT NULL')
            ->orderBy('recorded_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        if (!$latest) {
            return 0.0;
        }

        $latestKwh = (float) $latest['kwh'];

        $baseline = $this->select('kwh')
            ->where('device_id', $deviceId)
            ->where('recorded_at <', $from)
            ->where('kwh IS NOT NULL')
            ->orderBy('recorded_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        if (!$baseline) {
            $baseline = $this->select('kwh')
                ->where('device_id', $deviceId)
                ->where('recorded_at >=', $from)
                ->where('recorded_at <=', $to)
                ->where('kwh IS NOT NULL')
                ->orderBy('recorded_at', 'ASC')
                ->orderBy('id', 'ASC')
                ->first();
        }

        $baselineKwh = $baseline ? (float) $baseline['kwh'] : $latestKwh;

        $delta = $latestKwh - $baselineKwh;

        return $delta >= 0 ? $delta : $latestKwh;
    }
}
