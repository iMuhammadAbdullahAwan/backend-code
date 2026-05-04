<?php

namespace App\Controllers\Api;

use App\Models\DeviceModel;
use App\Models\SensorReadingModel;

class DeviceController extends BaseApiController
{
    protected $deviceModel;
    protected $sensorReadingModel;

    public function __construct()
    {
        $this->deviceModel = new DeviceModel();
        $this->sensorReadingModel = new SensorReadingModel();
    }

    public function getAllDevices()
    {
        $devices = $this->deviceModel->findAll();
        return $this->successResponse($devices, 'Devices retrieved successfully');
    }

    public function getDevice($deviceId)
    {
        $device = $this->deviceModel->where('device_id', $deviceId)->first();
        if (!$device) return $this->errorResponse('Device not found', 404);
        
        return $this->successResponse($device);
    }

    public function getDeviceStatus($deviceId)
    {
        $device = $this->deviceModel->where('device_id', $deviceId)->first();
        if (!$device) return $this->errorResponse('Device not found', 404);

        $latestReading = $this->sensorReadingModel
            ->where('device_id', $deviceId)
            ->orderBy('recorded_at', 'DESC')
            ->first();

        $data = [
            'device' => $device,
            'latest_reading' => $latestReading
        ];
        return $this->successResponse($data);
    }
}
