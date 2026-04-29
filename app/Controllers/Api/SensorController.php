<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\SensorModel;
use CodeIgniter\HTTP\ResponseInterface;

class SensorController extends BaseController
{
    protected $sensorModel;

    public function __construct()
    {
        $this->sensorModel = new SensorModel();
    }

    /**
     * Store sensor data
     * POST /api/sensor
     */
    public function store(): ResponseInterface
    {
        // Get JSON input
        $input = $this->request->getJSON();

        // Prepare data
        $data = [
            'device_id' => $input->device_id ?? null,
            'current'   => $input->current ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Validate data
        if (!$this->sensorModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $this->sensorModel->errors(),
                ]);
        }

        // Insert data
        if ($this->sensorModel->insert($data)) {
            return $this->response
                ->setStatusCode(201)
                ->setJSON([
                    'status' => 'success',
                    'message' => 'Sensor data recorded successfully',
                ]);
        }

        return $this->response
            ->setStatusCode(500)
            ->setJSON([
                'status' => 'error',
                'message' => 'Failed to insert sensor data',
            ]);
    }

    /**
     * Get latest sensor data
     * GET /api/sensor/latest
     * GET /api/sensor/latest/{device_id}
     */
    public function latest($device_id = null): ResponseInterface
    {
        if ($device_id === null) {
            // Return latest record overall
            $record = $this->sensorModel
                ->orderBy('id', 'DESC')
                ->first();
        } else {
            // Return latest record for specific device
            $record = $this->sensorModel
                ->where('device_id', $device_id)
                ->orderBy('id', 'DESC')
                ->first();
        }

        if ($record === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'No sensor data found',
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => 'success',
                'data'    => $record,
            ]);
    }
}
