<?php

namespace App\Controllers\Api;

use App\Models\SensorReadingModel;
use App\Models\DeviceModel;

class SensorController extends BaseApiController
{
    protected $sensorReadingModel;
    protected $deviceModel;

    public function __construct()
    {
        $this->sensorReadingModel = new SensorReadingModel();
        $this->deviceModel = new DeviceModel();
    }

    public function getLatestAll()
    {
        $devices = $this->deviceModel->findAll();
        $data = [];
        foreach ($devices as $d) {
            $reading = $this->sensorReadingModel
                ->where('device_id', $d['device_id'])
                ->orderBy('recorded_at', 'DESC')
                ->first();
            if ($reading) $data[] = $reading;
        }
        return $this->successResponse($data);
    }

    public function getLatest($deviceId)
    {
        $reading = $this->sensorReadingModel
            ->where('device_id', $deviceId)
            ->orderBy('recorded_at', 'DESC')
            ->first();
        if (!$reading) return $this->errorResponse('Reading not found', 404);

        return $this->successResponse($reading);
    }

    public function getHistory($deviceId)
    {
        $from = $this->request->getGet('from');
        $to = $this->request->getGet('to');
        $limit = $this->request->getGet('limit') ?: 100;

        $builder = $this->sensorReadingModel->where('device_id', $deviceId);
        if ($from) $builder->where('recorded_at >=', $from . ' 00:00:00');
        if ($to) $builder->where('recorded_at <=', $to . ' 23:59:59');

        $readings = $builder->orderBy('recorded_at', 'DESC')->findAll($limit);
        return $this->successResponse($readings);
    }

    public function getStats($deviceId)
    {
        $period = $this->request->getGet('period') ?: 'daily';
        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) {
            return $this->errorResponse('Invalid period. Use daily, weekly or monthly.', 400);
        }

        $buckets = match ($period) {
            'daily'   => $this->dailyBuckets(),
            'weekly'  => $this->weeklyBuckets(),
            'monthly' => $this->monthlyBuckets(),
        };

        $rows = $this->aggregateRows($deviceId, $period);

        $series = [];
        $totalKwh = 0.0;
        $activeVoltages = [];
        $activeCurrents = [];
        $activeTemps = [];

        foreach ($buckets as $bucket) {
            $row = $rows[$bucket['key']] ?? null;

            $kwh = $row ? round((float) $row['kwh'], 2) : 0.00;
            $voltage = $row ? round((float) $row['voltage'], 2) : 0.00;
            $current = $row ? round((float) $row['current'], 2) : 0.00;
            $temperature = $row ? round((float) $row['temperature'], 2) : 0.00;

            $series[] = [
                'label'       => $bucket['label'],
                'date'        => $bucket['date'],
                'kwh'         => $kwh,
                'voltage'     => $voltage,
                'current'     => $current,
                'temperature' => $temperature,
            ];

            $totalKwh += $kwh;
            if ($row !== null) {
                $activeVoltages[] = $voltage;
                $activeCurrents[] = $current;
                $activeTemps[] = $temperature;
            }
        }

        $intervalCount = count($series);

        $averages = [
            'avg_kwh'         => $intervalCount > 0 ? round($totalKwh / $intervalCount, 2) : 0.00,
            'avg_voltage'     => count($activeVoltages) > 0 ? round(array_sum($activeVoltages) / count($activeVoltages), 2) : 0.00,
            'avg_current'     => count($activeCurrents) > 0 ? round(array_sum($activeCurrents) / count($activeCurrents), 2) : 0.00,
            'avg_temperature' => count($activeTemps) > 0 ? round(array_sum($activeTemps) / count($activeTemps), 2) : 0.00,
            'total_kwh'       => round($totalKwh, 2),
        ];

        return $this->successResponse([
            'period'    => $period,
            'averages'  => $averages,
            'series'    => $series,
        ]);
    }

    /**
     * Runs the grouped SQL aggregation for the given period and returns rows
     * keyed by the same bucket key produced by {daily,weekly,monthly}Buckets().
     */
    private function aggregateRows(string $deviceId, string $period): array
    {
        $db = \Config\Database::connect();

        if ($period === 'daily') {
            [$start, $end] = $this->currentWeekBounds();
            $query = $db->query(
                "SELECT DATE(recorded_at) AS bucket_key,
                        SUM(kwh) AS kwh, AVG(voltage) AS voltage,
                        AVG(current) AS current, AVG(temperature) AS temperature
                 FROM sensor_readings
                 WHERE device_id = ? AND recorded_at >= ? AND recorded_at <= ?
                 GROUP BY DATE(recorded_at)",
                [$deviceId, $start, $end]
            );
        } elseif ($period === 'weekly') {
            $year = date('Y');
            $month = date('n');
            $query = $db->query(
                "SELECT FLOOR((DAY(recorded_at) - 1) / 7) + 1 AS bucket_key,
                        SUM(kwh) AS kwh, AVG(voltage) AS voltage,
                        AVG(current) AS current, AVG(temperature) AS temperature
                 FROM sensor_readings
                 WHERE device_id = ? AND YEAR(recorded_at) = ? AND MONTH(recorded_at) = ?
                 GROUP BY bucket_key",
                [$deviceId, $year, $month]
            );
        } else {
            $year = date('Y');
            $query = $db->query(
                "SELECT MONTH(recorded_at) AS bucket_key,
                        SUM(kwh) AS kwh, AVG(voltage) AS voltage,
                        AVG(current) AS current, AVG(temperature) AS temperature
                 FROM sensor_readings
                 WHERE device_id = ? AND YEAR(recorded_at) = ?
                 GROUP BY bucket_key",
                [$deviceId, $year]
            );
        }

        $rows = [];
        foreach ($query->getResultArray() as $row) {
            $rows[(string) $row['bucket_key']] = $row;
        }

        return $rows;
    }

    private function currentWeekBounds(): array
    {
        $today = new \DateTime();
        $dayOfWeek = (int) $today->format('N'); // 1 (Mon) .. 7 (Sun)
        $monday = (clone $today)->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);
        $sunday = (clone $monday)->modify('+6 days')->setTime(23, 59, 59);

        return [$monday->format('Y-m-d H:i:s'), $sunday->format('Y-m-d H:i:s')];
    }

    private function dailyBuckets(): array
    {
        $today = new \DateTime();
        $dayOfWeek = (int) $today->format('N');
        $monday = (clone $today)->modify('-' . ($dayOfWeek - 1) . ' days');

        $buckets = [];
        for ($i = 0; $i < 7; $i++) {
            $date = (clone $monday)->modify("+{$i} days");
            $buckets[] = [
                'key'   => $date->format('Y-m-d'),
                'label' => $date->format('D'),
                'date'  => $date->format('Y-m-d'),
            ];
        }

        return $buckets;
    }

    private function weeklyBuckets(): array
    {
        $year = (int) date('Y');
        $month = (int) date('n');
        $daysInMonth = (int) date('t');
        $weekCount = (int) ceil($daysInMonth / 7);

        $buckets = [];
        for ($week = 1; $week <= $weekCount; $week++) {
            $startDay = ($week - 1) * 7 + 1;
            $buckets[] = [
                'key'   => (string) $week,
                'label' => "Week {$week}",
                'date'  => sprintf('%04d-%02d-%02d', $year, $month, $startDay),
            ];
        }

        return $buckets;
    }

    private function monthlyBuckets(): array
    {
        $year = (int) date('Y');

        $buckets = [];
        for ($month = 1; $month <= 12; $month++) {
            $buckets[] = [
                'key'   => (string) $month,
                'label' => date('M', mktime(0, 0, 0, $month, 1, $year)),
                'date'  => sprintf('%04d-%02d-01', $year, $month),
            ];
        }

        return $buckets;
    }

    /**
     * Insert a test reading (debug only).
     * Accepts JSON body or inserts a default sample for device 'energy'.
     */
    public function insertTestReading()
    {
        $required = env('SYNC_SECRET');
        if (!empty($required)) {
            $provided = $this->request->getGet('secret') ?? $this->request->getHeaderLine('X-Sync-Secret');
            if ($provided !== $required) {
                return $this->errorResponse('forbidden', 403);
            }
        }

        $input = $this->request->getJSON(true) ?: [];

        $deviceId = $input['device_id'] ?? 'energy';
        $current = isset($input['current']) ? (float) $input['current'] : 0.0;
        $voltage = isset($input['voltage']) ? (float) $input['voltage'] : 3.97;
        $temperature = isset($input['temperature']) ? (float) $input['temperature'] : (isset($input['temp']) ? (float)$input['temp'] : 32.8);
        $kwh = isset($input['kwh']) ? (float) $input['kwh'] : 0.02;
        $power = isset($input['power']) ? (float) $input['power'] : 0.0;
        $powerWatt = $current * $voltage;

        $insertData = [
            'device_id' => $deviceId,
            'current' => $current,
            'voltage' => $voltage,
            'temperature' => $temperature,
            'power_watt' => $powerWatt,
            'energy' => $input['energy'] ?? null,
            'kwh' => $kwh,
            'power' => $power,
            'recorded_at' => $input['recorded_at'] ?? date('Y-m-d H:i:s'),
        ];

        try {
            $id = $this->sensorReadingModel->insert($insertData);
            if ($id) return $this->successResponse(['inserted_id' => $id]);
            return $this->errorResponse('Insert failed', 500);
        } catch (\Exception $e) {
            return $this->errorResponse('Insert error: ' . $e->getMessage(), 500);
        }
    }
}
