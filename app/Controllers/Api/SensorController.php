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

        $readings = $this->sensorReadingModel->where('device_id', $deviceId)->findAll();
        if (!$readings) return $this->errorResponse('No readings found', 404);

        $currents = array_column($readings, 'current');
        $voltages = array_column($readings, 'voltage');
        $temps = array_column($readings, 'temperature');
        $powers = array_column($readings, 'power_watt');

        $stats = [
            'period' => $period,
            'count' => count($readings),
            'avg_current' => count($currents) > 0 ? array_sum($currents) / count($currents) : 0,
            'max_current' => count($currents) > 0 ? max($currents) : 0,
            'min_current' => count($currents) > 0 ? min($currents) : 0,
            'avg_voltage' => count($voltages) > 0 ? array_sum($voltages) / count($voltages) : 0,
            'avg_temperature' => count($temps) > 0 ? array_sum($temps) / count($temps) : 0,
            'avg_power' => count($powers) > 0 ? array_sum($powers) / count($powers) : 0,
            'max_power' => count($powers) > 0 ? max($powers) : 0,
        ];

        return $this->successResponse($stats);
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
