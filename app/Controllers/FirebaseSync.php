<?php

namespace App\Controllers;

use App\Services\FirebaseService;
use App\Models\DeviceModel;
use App\Models\SensorReadingModel;

class FirebaseSync extends BaseController
{
    protected $firebaseService;
    protected $deviceModel;
    protected $sensorReadingModel;

    public function __construct()
    {
        $this->firebaseService = new FirebaseService();
        $this->deviceModel = new DeviceModel();
        $this->sensorReadingModel = new SensorReadingModel();
    }

    public function syncSensors()
    {
        $sensors = $this->firebaseService->fetchSensorData();
        $synced = 0;

        foreach ($sensors as $deviceId => $readings) {
            // Upsert device
            if (!$this->deviceModel->where('device_id', $deviceId)->first()) {
                $this->deviceModel->insert([
                    'device_id' => $deviceId,
                    'status' => 'active',
                ]);
            }

            if (isset($readings['current']) || isset($readings['voltage'])) {
                $this->insertReading($deviceId, $readings);
                $synced++;
            } else if (is_array($readings)) {
                // In case it's an array of historical readings for the device
                foreach ($readings as $r) {
                    if (is_array($r) && (isset($r['current']) || isset($r['voltage']))) {
                        $this->insertReading($deviceId, $r);
                        $synced++;
                    }
                }
            }
        }

        return $this->response->setJSON(['synced' => $synced, 'status' => 'ok']);
    }

    private function insertReading($deviceId, $data)
    {
        $current = $data['current'] ?? 0;
        $voltage = $data['voltage'] ?? 0;
        $powerWatt = $current * $voltage;

        $this->sensorReadingModel->insert([
            'device_id'   => $deviceId,
            'current'     => $current,
            'voltage'     => $voltage,
            'temperature' => $data['temperature'] ?? 0,
            'power_watt'  => $powerWatt,
            'recorded_at' => $data['recorded_at'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function syncDevices()
    {
        $devices = $this->firebaseService->fetchDevices();
        $synced = 0;

        foreach ($devices as $deviceId => $deviceData) {
            // If deviceData is not an array, maybe the format is just a list
            if (!is_array($deviceData)) {
                continue;
            }
            
            // Sometimes Firebase array vs object diffs give integer keys
            $realDeviceId = $deviceData['device_id'] ?? $deviceId;
            
            $existing = $this->deviceModel->where('device_id', $realDeviceId)->first();
            $data = [
                'device_id'   => $realDeviceId,
                'device_name' => $deviceData['device_name'] ?? null,
                'location'    => $deviceData['location'] ?? null,
                'status'      => $deviceData['status'] ?? 'active',
            ];

            if ($existing) {
                $this->deviceModel->update($existing['id'], $data);
            } else {
                $this->deviceModel->insert($data);
            }
            $synced++;
        }

        return $this->response->setJSON(['synced' => $synced, 'status' => 'ok']);
    }
}
