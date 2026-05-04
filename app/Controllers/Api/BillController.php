<?php

namespace App\Controllers\Api;

use App\Models\SensorReadingModel;
use App\Models\BillPredictionModel;
use App\Services\GeminiService;

class BillController extends BaseApiController
{
    protected $sensorReadingModel;
    protected $billPredictionModel;
    protected $geminiService;

    public function __construct()
    {
        $this->sensorReadingModel = new SensorReadingModel();
        $this->billPredictionModel = new BillPredictionModel();
        $this->geminiService = new GeminiService();
    }

    public function predictBill($deviceId)
    {
        $readings = $this->sensorReadingModel
            ->where('device_id', $deviceId)
            ->orderBy('recorded_at', 'DESC')
            ->findAll(30);

        if (!$readings) return $this->errorResponse('No sensor data found', 404);

        $simpleData = array_map(function($r) {
            return [
                'current' => $r['current'],
                'voltage' => $r['voltage'],
                'temperature' => $r['temperature']
            ];
        }, $readings);

        $prediction = $this->geminiService->generateBillPrediction($deviceId, $simpleData);

        $month = date('Y-m');
        $data = [
            'device_id' => $deviceId,
            'month' => $month,
            'predicted_kwh' => $prediction['predicted_kwh'] ?? 0,
            'predicted_cost' => $prediction['predicted_cost'] ?? 0,
            'currency' => $prediction['currency'] ?? 'USD',
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        $this->billPredictionModel->insert($data);

        return $this->successResponse(array_merge($data, ['summary' => $prediction['summary'] ?? '']));
    }

    public function getBillHistory($deviceId)
    {
        $limit = $this->request->getGet('limit') ?: 12;
        $history = $this->billPredictionModel
            ->where('device_id', $deviceId)
            ->orderBy('generated_at', 'DESC')
            ->findAll($limit);

        return $this->successResponse($history);
    }

    public function getAllBills()
    {
        $db = \Config\Database::connect();
        $query = $db->query("SELECT t1.* FROM bill_predictions t1 JOIN (SELECT device_id, MAX(generated_at) as max_generated_at FROM bill_predictions GROUP BY device_id) t2 ON t1.device_id = t2.device_id AND t1.generated_at = t2.max_generated_at");
        return $this->successResponse($query->getResultArray());
    }
}
