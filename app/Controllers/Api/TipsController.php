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

        $simpleData = array_map(function ($r) {
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
            try {
                $id = $this->aiTipModel->insert($data);
                if ($id) {
                    log_message('info', 'AI tip inserted id=' . $id . ' device=' . $deviceId);
                    $data['id'] = $id;
                    $savedTips[] = $data;
                } else {
                    log_message('error', 'AI tip insert returned false for device=' . $deviceId . ' data=' . json_encode($data));
                }
            } catch (\Exception $e) {
                log_message('error', 'AI tip insert exception for device=' . $deviceId . ' error=' . $e->getMessage());
            }
        }

        log_message('info', 'Generated ' . count($savedTips) . ' tips for device ' . $deviceId);

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

    /**
     * Generate tips on-demand for a device and store them.
     */
    public function generateTips($deviceId)
    {
        $required = env('SYNC_SECRET');
        if (!empty($required)) {
            $provided = $this->request->getGet('secret') ?? $this->request->getHeaderLine('X-Sync-Secret');
            if ($provided !== $required) {
                return $this->errorResponse('forbidden', 403);
            }
        }

        $readings = $this->sensorReadingModel
            ->where('device_id', $deviceId)
            ->orderBy('recorded_at', 'DESC')
            ->findAll(20);

        if (!$readings) return $this->errorResponse('No sensor data found', 404);

        $simpleData = array_map(function ($r) {
            return [
                'current' => $r['current'],
                'voltage' => $r['voltage'],
                'temperature' => $r['temperature']
            ];
        }, $readings);

        $tips = $this->geminiService->generateEnergyTips($deviceId, $simpleData);

        log_message('info', 'Generating tips for ' . $deviceId . ' - received ' . count($tips) . ' suggestions from Gemini');

        $savedTips = [];
        foreach ($tips as $t) {
            $data = [
                'device_id' => $deviceId,
                'tip_text' => $t['tip'] ?? '',
                'category' => $t['category'] ?? 'general',
                'generated_at' => date('Y-m-d H:i:s'),
            ];
            try {
                $id = $this->aiTipModel->insert($data);
                if ($id) {
                    log_message('info', 'AI tip inserted id=' . $id . ' device=' . $deviceId);
                    $data['id'] = $id;
                    $savedTips[] = $data;
                } else {
                    log_message('error', 'AI tip insert returned false for device=' . $deviceId . ' data=' . json_encode($data));
                }
            } catch (\Exception $e) {
                log_message('error', 'AI tip insert exception for device=' . $deviceId . ' error=' . $e->getMessage());
            }
        }

        log_message('info', 'Saved ' . count($savedTips) . ' tips for device ' . $deviceId);

        return $this->successResponse($savedTips);
    }
}
