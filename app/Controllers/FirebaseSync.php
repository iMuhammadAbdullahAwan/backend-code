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
        log_message('info', 'Starting Firebase sensor sync');
        // Check optional sync secret
        $secretCheck = $this->checkSyncSecret();
        if ($secretCheck !== true) {
            return $secretCheck;
        }

        log_message('info', 'Starting Firebase sensor sync');
        $sensors = $this->firebaseService->fetchSensorData();
        $count = is_array($sensors) ? count($sensors) : 0;
        log_message('info', 'Fetched sensors from Firebase: ' . $count);

        // Debug endpoint: return raw payload when ?debug=1 is provided
        if ($this->request->getGet('debug')) {
            return $this->response->setJSON(['payload' => $sensors, 'count' => $count]);
        }

        $synced = 0;

        foreach ($sensors as $deviceId => $readings) {
            // Upsert device
            if (!$this->deviceModel->where('device_id', $deviceId)->first()) {
                $this->deviceModel->insert([
                    'device_id' => $deviceId,
                    'status' => 'active',
                ]);
                log_message('info', "Inserted new device: {$deviceId}");
            }

            try {
                if (is_string($readings)) {
                    log_message('info', "Device {$deviceId} readings (string)");
                    $this->insertReading($deviceId, $readings);
                    $synced++;
                } else if (isset($readings['current']) || isset($readings['voltage'])) {
                    log_message('info', "Device {$deviceId} readings (single)");
                    $this->insertReading($deviceId, $readings);
                    $synced++;
                } else if (is_array($readings)) {
                    // In case it's an array of historical readings for the device
                    foreach ($readings as $r) {
                        if (is_array($r) && (isset($r['current']) || isset($r['voltage']) || is_string($r))) {
                            $this->insertReading($deviceId, $r);
                            $synced++;
                        } else {
                            log_message('debug', "Skipping non-reading entry for {$deviceId}");
                        }
                    }
                } else {
                    log_message('debug', "No readable readings for device {$deviceId}");
                }
            } catch (\Exception $e) {
                log_message('error', 'Error inserting reading for ' . $deviceId . ': ' . $e->getMessage());
            }
        }

        return $this->response->setJSON(['synced' => $synced, 'status' => 'ok']);
    }

    private function checkSyncSecret()
    {
        $required = env('SYNC_SECRET');
        if (empty($required)) {
            return true;
        }

        $provided = $this->request->getGet('secret') ?? $this->request->getHeaderLine('X-Sync-Secret');
        if ($provided === $required) {
            return true;
        }

        return $this->response->setStatusCode(403)->setJSON(['status' => 'forbidden']);
    }

    private function insertReading($deviceId, $data)
    {
        log_message('debug', "insertReading called for {$deviceId} with type=" . gettype($data));

        // Normalize incoming payload which may be an array or a plain string
        if (is_string($data)) {
            $parsed = [];
            // Collapse whitespace and newlines
            $s = preg_replace('/\s+/', ' ', trim($data));
            // Match key:value pairs like "key: 12.3" or "key 12.3"
            if (preg_match_all('/([a-zA-Z_]+)\s*:?\s*([-+]?[0-9]*\.?[0-9]+)/', $s, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $key = strtolower($m[1]);
                    $value = (float) $m[2];
                    $parsed[$key] = $value;
                }
            }
            $data = $parsed;
        }

        if (is_array($data)) {
            log_message('debug', 'Parsed reading for ' . $deviceId . ': ' . json_encode($data));
        } else {
            log_message('debug', 'Reading for ' . $deviceId . ' is empty after parse');
        }

        $current = (float) ($data['current'] ?? 0);
        $voltage = (float) ($data['voltage'] ?? 0);
        $powerWatt = $current * $voltage;

        // Some payloads may provide 'power' or 'kwh' or 'energy'
        $energy = isset($data['energy']) ? (float) $data['energy'] : null;
        $kwh = isset($data['kwh']) ? (float) $data['kwh'] : null;
        $powerRaw = isset($data['power']) ? (float) $data['power'] : null;

        $insertData = [
            'device_id'   => $deviceId,
            'current'     => $current,
            'voltage'     => $voltage,
            'temperature' => $data['temp'] ?? $data['temperature'] ?? 0,
            'power_watt'  => $powerWatt,
            'energy'      => $energy,
            'kwh'         => $kwh,
            'power'       => $powerRaw,
            'recorded_at' => $data['recorded_at'] ?? date('Y-m-d H:i:s'),
        ];

        $result = $this->sensorReadingModel->insert($insertData);
        if ($result) {
            log_message('info', 'Inserted reading id=' . $result . ' for device ' . $deviceId);
        } else {
            log_message('error', 'Failed to insert reading for device ' . $deviceId . ' data=' . json_encode($insertData));
        }
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
