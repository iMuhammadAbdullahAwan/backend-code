<?php

namespace App\Controllers\Api;

use App\Models\SensorReadingModel;
use App\Models\BillPredictionModel;
use App\Models\BillingTariffModel;
use App\Services\GeminiService;
use App\Services\BillingForecastService;

class BillController extends BaseApiController
{
    protected $sensorReadingModel;
    protected $billPredictionModel;
    protected $billingTariffModel;
    protected $geminiService;
    protected $billingForecastService;

    public function __construct()
    {
        $this->sensorReadingModel = new SensorReadingModel();
        $this->billPredictionModel = new BillPredictionModel();
        $this->billingTariffModel = new BillingTariffModel();
        $this->geminiService = new GeminiService();
        $this->billingForecastService = new BillingForecastService();
    }

    public function predictBill($deviceId)
    {
        $readings = $this->sensorReadingModel
            ->where('device_id', $deviceId)
            ->orderBy('recorded_at', 'DESC')
            ->findAll(30);

        if (!$readings) return $this->errorResponse('No sensor data found', 404);

        $simpleData = array_map(function ($r) {
            return [
                'current' => $r['current'],
                'voltage' => $r['voltage'],
                'temperature' => $r['temperature']
            ];
        }, $readings);

        $prediction = $this->geminiService->generateBillPrediction($deviceId, $simpleData);
        $tariff = $this->billingTariffModel->getTariffForDevice($deviceId);

        $predictedKwh = (float) ($prediction['predicted_kwh'] ?? 0);
        try {
            $predictedCost = $this->billingTariffModel->calculateCost($predictedKwh, $tariff);
        } catch (\RuntimeException $e) {
            log_message('error', 'Bill prediction cost calculation failed for device=' . $deviceId . ' error=' . $e->getMessage());
            return $this->errorResponse('Unable to calculate bill: ' . $e->getMessage(), 500);
        }

        $month = date('Y-m');
        $data = [
            'device_id' => $deviceId,
            'month' => $month,
            'predicted_kwh' => $predictedKwh,
            'predicted_cost' => $predictedCost,
            'currency' => $tariff['currency'],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        try {
            $id = $this->billPredictionModel->insert($data);
            if ($id) {
                log_message('info', 'Bill prediction inserted id=' . $id . ' device=' . $deviceId);
                $data['id'] = $id;
            } else {
                log_message('error', 'Bill prediction insert failed for device=' . $deviceId . ' data=' . json_encode($data));
            }
        } catch (\Exception $e) {
            log_message('error', 'Bill prediction insert exception for device=' . $deviceId . ' error=' . $e->getMessage());
        }

        return $this->successResponse(array_merge($data, [
            'rate_per_kwh' => (float) $tariff['rate_per_kwh'],
            'summary' => $prediction['summary'] ?? '',
        ]));
    }

    public function getConfig($deviceId)
    {
        $tariff = $this->billingTariffModel->getTariffForDevice($deviceId);

        return $this->successResponse([
            'device_id'                => $deviceId,
            'rate_per_kwh'             => (float) $tariff['rate_per_kwh'],
            'currency'                 => $tariff['currency'],
            'currency_symbol'          => $tariff['currency_symbol'],
            'billing_cycle_start_day'  => (int) $tariff['billing_cycle_start_day'],
            'tariff_type'              => $tariff['tariff_type'],
            'slabs'                    => $tariff['slabs'],
            'last_updated'             => $tariff['updated_at'],
        ]);
    }

    /**
     * Deterministic month-to-date billing + end-of-cycle forecast.
     *
     * Unlike predictBill(), this endpoint never calls Gemini: mtd_units and
     * predicted_units come from SUM(sensor_readings.kwh) over the resolved
     * billing period, and mtd_bill/predicted_bill are both computed by
     * BillingTariffModel::calculateCost() (the same, unmodified tariff
     * engine predictBill() uses) — predicted_bill is calculated from
     * predicted_units directly, never by scaling mtd_bill, so progressive
     * slab tariffs are priced correctly.
     */
    public function getMtd($deviceId)
    {
        try {
            $data = $this->billingForecastService->forecast($deviceId);
        } catch (\RuntimeException $e) {
            log_message('error', 'MTD forecast calculation failed for device=' . $deviceId . ' error=' . $e->getMessage());
            return $this->errorResponse('Unable to calculate bill: ' . $e->getMessage(), 500);
        }

        return $this->successResponse($data);
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
