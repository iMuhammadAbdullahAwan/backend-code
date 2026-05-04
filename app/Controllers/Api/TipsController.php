<?php

namespace App\Controllers\Api;

use App\Models\SensorReadingModel;
use App\Models\AiTipModel;
use App\Services\GeminiService;

class TipsController extends BaseApiController
{
    protected $sensorReadingModel;
    protected $aiTipModel;
    protected $geminiService;

    public function __construct()
    {
        $this->sensorReadingModel = new SensorReadingModel();
        $this->aiTipModel = new AiTipModel();
        $this->geminiService = new GeminiService();
    }

    public function getTips($deviceId)
    {
        $readings = $this->sensorReadingModel
            ->where('device_id', $deviceId)
            ->orderBy('recorded_at', 'DESC')
            ->findAll(10);

        if (!$readings) return $this->errorResponse('No sensor data found', 404);

        $simpleData = array_map(function($r) {
            return [
                'current' => $r['current'],
                'voltage' => $r['voltage'],
                'temperature' => $r['temperature']
            ];
        }, $readings);

        $tips = $this->geminiService->generateEnergyTips($deviceId, $simpleData);

        $savedTips = [];
        foreach ($tips as $t) {
            $data = [
                'device_id' => $deviceId,
                'tip_text' => $t['tip'] ?? '',
                'category' => $t['category'] ?? 'general',
                'generated_at' => date('Y-m-d H:i:s'),
            ];
            $this->aiTipModel->insert($data);
            $savedTips[] = $data;
        }

        return $this->successResponse($savedTips);
    }

    public function getTipsHistory($deviceId)
    {
        $limit = $this->request->getGet('limit') ?: 20;
        $history = $this->aiTipModel
            ->where('device_id', $deviceId)
            ->orderBy('generated_at', 'DESC')
            ->findAll($limit);

        return $this->successResponse($history);
    }

    public function getAllLatestTips()
    {
        $db = \Config\Database::connect();
        $query = $db->query("SELECT t1.* FROM ai_tips t1 JOIN (SELECT device_id, MAX(generated_at) as max_generated_at FROM ai_tips GROUP BY device_id) t2 ON t1.device_id = t2.device_id AND t1.generated_at = t2.max_generated_at");
        return $this->successResponse($query->getResultArray());
    }
}
