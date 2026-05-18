<?php

namespace App\Controllers\Api;

use App\Models\DeviceModel;
use App\Models\SensorReadingModel;
use App\Models\AiTipModel;
use App\Models\BillPredictionModel;
use Dompdf\Dompdf;
use Dompdf\Options;

class DeviceController extends BaseApiController
{
    protected $deviceModel;
    protected $sensorReadingModel;
    protected $aiTipModel;
    protected $billPredictionModel;

    public function __construct()
    {
        $this->deviceModel = new DeviceModel();
        $this->sensorReadingModel = new SensorReadingModel();
        $this->aiTipModel = new AiTipModel();
        $this->billPredictionModel = new BillPredictionModel();
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

    public function exportData($deviceId)
    {
        $json = $this->request->getJSON();
        $type = $this->request->getVar('type') ?? ($json->type ?? null);
        $format = $this->request->getVar('format') ?? ($json->format ?? null);
        $startDate = $this->request->getVar('start_date') ?? ($json->start_date ?? null);
        $endDate = $this->request->getVar('end_date') ?? ($json->end_date ?? null);

        if (!$type || !$format) {
            return $this->response->setJSON([
                "status" => "error",
                "message" => "Missing required parameters: type and format.",
                "code" => 400
            ])->setStatusCode(400);
        }

        $validTypes = ['consumption', 'billing', 'alerts'];
        $validFormats = ['csv', 'pdf'];

        if (!in_array($type, $validTypes) || !in_array($format, $validFormats)) {
            return $this->response->setJSON([
                "status" => "error",
                "message" => "Invalid type or format parameter.",
                "code" => 400
            ])->setStatusCode(400);
        }

        // Check if device exists
        $device = $this->deviceModel->where('device_id', $deviceId)->first();
        if (!$device) {
            return $this->response->setJSON([
                "status" => "error",
                "message" => "No data found for this device or date range.",
                "code" => 404
            ])->setStatusCode(404);
        }

        // Fetch data based on type
        $data = [];
        if ($type === 'consumption') {
            $query = $this->sensorReadingModel->where('device_id', $deviceId);
            if (!empty($startDate)) {
                $query->where('recorded_at >=', $startDate . ' 00:00:00');
            }
            if (!empty($endDate)) {
                $query->where('recorded_at <=', $endDate . ' 23:59:59');
            }
            $data = $query->orderBy('recorded_at', 'ASC')->findAll();
        } elseif ($type === 'alerts') {
            $query = $this->aiTipModel->where('device_id', $deviceId);
            if (!empty($startDate)) {
                $query->where('generated_at >=', $startDate . ' 00:00:00');
            }
            if (!empty($endDate)) {
                $query->where('generated_at <=', $endDate . ' 23:59:59');
            }
            $data = $query->orderBy('generated_at', 'ASC')->findAll();
        } elseif ($type === 'billing') {
            $query = $this->billPredictionModel->where('device_id', $deviceId);
            if (!empty($startDate)) {
                $query->where('generated_at >=', $startDate . ' 00:00:00');
            }
            if (!empty($endDate)) {
                $query->where('generated_at <=', $endDate . ' 23:59:59');
            }
            $data = $query->orderBy('generated_at', 'ASC')->findAll();
        }

        if (empty($data)) {
            return $this->response->setJSON([
                "status" => "error",
                "message" => "No data found for this device or date range.",
                "code" => 404
            ])->setStatusCode(404);
        }

        if ($format === 'csv') {
            $this->response->setHeader('Content-Type', 'text/csv');
            $this->response->setHeader('Content-Disposition', 'attachment; filename="smart_energy_report.csv"');
            $this->response->sendHeaders();

            $fp = fopen('php://output', 'w');

            if ($type === 'consumption') {
                fputcsv($fp, ['Timestamp', 'Voltage (V)', 'Current (A)', 'Power (W)', 'Temperature (°C)']);
                foreach ($data as $row) {
                    fputcsv($fp, [
                        $row['recorded_at'] ?? '',
                        $row['voltage'] ?? 0,
                        $row['current'] ?? 0,
                        $row['power_watt'] ?? 0,
                        $row['temperature'] ?? 0
                    ]);
                }
            } elseif ($type === 'alerts') {
                fputcsv($fp, ['Generated At', 'Category', 'Alert Message']);
                foreach ($data as $row) {
                    fputcsv($fp, [
                        $row['generated_at'] ?? '',
                        $row['category'] ?? '',
                        $row['tip_text'] ?? ''
                    ]);
                }
            } elseif ($type === 'billing') {
                fputcsv($fp, ['Month', 'Predicted kWh', 'Predicted Cost', 'Currency', 'Generated At']);
                foreach ($data as $row) {
                    fputcsv($fp, [
                        $row['month'] ?? '',
                        $row['predicted_kwh'] ?? 0,
                        $row['predicted_cost'] ?? 0,
                        $row['currency'] ?? 'USD',
                        $row['generated_at'] ?? ''
                    ]);
                }
            }

            fclose($fp);
            exit();
        } elseif ($format === 'pdf') {
            $this->response->setHeader('Content-Type', 'application/pdf');
            $this->response->setHeader('Content-Disposition', 'attachment; filename="smart_energy_invoice.pdf"');
            $this->response->sendHeaders();

            $html = $this->generatePdfHtml($device, $type, $data, $startDate, $endDate);

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $dompdf->stream("smart_energy_report.pdf", ["Attachment" => true]);
            exit();
        }
    }

    protected function generatePdfHtml($device, $type, $data, $startDate, $endDate)
    {
        $deviceName = htmlspecialchars($device['device_name'] ?? 'Unknown Device');
        $deviceId = htmlspecialchars($device['device_id'] ?? '');
        $location = htmlspecialchars($device['location'] ?? 'Not Specified');
        $reportDate = date('F j, Y, g:i a');
        $dateRangeStr = ($startDate ? $startDate : 'All Time') . ($endDate ? ' to ' . $endDate : '');

        $title = ucfirst($type) . " Report";
        if ($type === 'billing') {
            $title = "Electricity Invoice & Billing Report";
        } elseif ($type === 'alerts') {
            $title = "Smart Energy Alert Summary";
        } elseif ($type === 'consumption') {
            $title = "Energy Consumption Report";
        }

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>' . $title . '</title>
    <style>
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.5; font-size: 13px; margin: 20px; }
        .header { border-bottom: 2px solid #10b981; padding-bottom: 20px; margin-bottom: 25px; }
        .logo { font-size: 26px; font-weight: bold; color: #10b981; }
        .logo-sub { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .report-title { font-size: 22px; color: #111827; font-weight: bold; margin-top: 10px; }
        .meta-box { background-color: #f9fafb; border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
        .meta-table { width: 100%; font-size: 13px; }
        .meta-table td { padding: 4px 0; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        .table th { background-color: #10b981; color: #ffffff; padding: 12px 10px; text-align: left; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        .table td { padding: 12px 10px; border-bottom: 1px solid #e5e7eb; color: #374151; }
        .table tr:nth-child(even) { background-color: #f9fafb; }
        .summary-box { margin-top: 35px; background-color: #ecfdf5; border: 1px solid #a7f3d0; padding: 20px; border-radius: 8px; }
        .summary-title { font-size: 16px; font-weight: bold; color: #065f46; margin-bottom: 10px; border-bottom: 1px solid #a7f3d0; padding-bottom: 8px; }
        .summary-item { font-size: 14px; color: #047857; margin-bottom: 6px; }
        .summary-val { font-weight: bold; color: #065f46; }
        .footer { margin-top: 50px; text-align: center; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 20px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-alert { background-color: #fee2e2; color: #991b1b; }
        .badge-tip { background-color: #e0e7ff; color: #3730a3; }
        .badge-general { background-color: #f3f4f6; color: #374151; }
    </style>
</head>
<body>
    <div class="header">
        <table width="100%">
            <tr>
                <td>
                    <div class="logo">Smart Energy</div>
                    <div class="logo-sub">Intelligent Power Monitoring</div>
                </td>
                <td style="text-align: right;">
                    <div class="report-title">' . $title . '</div>
                    <div style="color: #6b7280; font-size: 12px; margin-top: 4px;">Generated: ' . $reportDate . '</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td width="20%"><strong>Device Name:</strong></td>
                <td width="30%">' . $deviceName . '</td>
                <td width="20%"><strong>Device ID:</strong></td>
                <td width="30%">' . $deviceId . '</td>
            </tr>
            <tr>
                <td><strong>Location:</strong></td>
                <td>' . $location . '</td>
                <td><strong>Date Filter:</strong></td>
                <td>' . htmlspecialchars($dateRangeStr) . '</td>
            </tr>
        </table>
    </div>';

        if ($type === 'billing') {
            $totalKwh = 0;
            $totalCost = 0;
            $currency = 'USD';

            $tableRows = '';
            foreach ($data as $row) {
                $kwh = (float)($row['predicted_kwh'] ?? 0);
                $cost = (float)($row['predicted_cost'] ?? 0);
                $curr = htmlspecialchars($row['currency'] ?? 'USD');
                $currency = $curr;
                $totalKwh += $kwh;
                $totalCost += $cost;

                $tableRows .= '<tr>
                    <td><strong>' . htmlspecialchars($row['month'] ?? '') . '</strong></td>
                    <td>' . number_format($kwh, 2) . ' kWh</td>
                    <td>' . $curr . ' ' . number_format($cost, 2) . '</td>
                    <td>' . htmlspecialchars($row['generated_at'] ?? '') . '</td>
                </tr>';
            }

            $html .= '<table class="table">
                <thead>
                    <tr>
                        <th>Billing Month</th>
                        <th>Consumption (kWh)</th>
                        <th>Estimated Cost</th>
                        <th>Calculated At</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $tableRows . '
                </tbody>
            </table>

            <div class="summary-box">
                <div class="summary-title">Billing Summary & Totals</div>
                <div class="summary-item">Total Energy Consumed / Estimated: <span class="summary-val">' . number_format($totalKwh, 2) . ' kWh</span></div>
                <div class="summary-item">Total Estimated Invoice Amount: <span class="summary-val">' . $currency . ' ' . number_format($totalCost, 2) . '</span></div>
            </div>';
        } elseif ($type === 'alerts') {
            $tableRows = '';
            foreach ($data as $row) {
                $cat = htmlspecialchars($row['category'] ?? 'general');
                $badgeClass = 'badge-general';
                if ($cat === 'alert' || $cat === 'warning' || $cat === 'high_usage') $badgeClass = 'badge-alert';
                elseif ($cat === 'tip' || $cat === 'recommendation' || $cat === 'saving') $badgeClass = 'badge-tip';

                $tableRows .= '<tr>
                    <td style="white-space: nowrap;">' . htmlspecialchars($row['generated_at'] ?? '') . '</td>
                    <td><span class="badge ' . $badgeClass . '">' . $cat . '</span></td>
                    <td>' . nl2br(htmlspecialchars($row['tip_text'] ?? '')) . '</td>
                </tr>';
            }

            $html .= '<table class="table">
                <thead>
                    <tr>
                        <th width="22%">Generated At</th>
                        <th width="18%">Category</th>
                        <th width="60%">Alert / Recommendation Summary</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $tableRows . '
                </tbody>
            </table>
            
            <div class="summary-box">
                <div class="summary-title">Alert Overview</div>
                <div class="summary-item">Total Alerts / Recommendations Logged: <span class="summary-val">' . count($data) . ' items</span></div>
                <div style="font-size: 12px; color: #047857; margin-top: 8px;">Note: Recommendations are generated by our AI analysis engine to help optimize device energy efficiency.</div>
            </div>';
        } elseif ($type === 'consumption') {
            $totalPower = 0;
            $totalTemp = 0;
            $totalVoltage = 0;
            $totalCurrent = 0;
            $count = count($data);

            $tableRows = '';
            foreach ($data as $row) {
                $v = (float)($row['voltage'] ?? 0);
                $c = (float)($row['current'] ?? 0);
                $p = (float)($row['power_watt'] ?? 0);
                $t = (float)($row['temperature'] ?? 0);

                $totalVoltage += $v;
                $totalCurrent += $c;
                $totalPower += $p;
                $totalTemp += $t;

                $tableRows .= '<tr>
                    <td style="white-space: nowrap;">' . htmlspecialchars($row['recorded_at'] ?? '') . '</td>
                    <td>' . number_format($v, 1) . ' V</td>
                    <td>' . number_format($c, 2) . ' A</td>
                    <td><strong>' . number_format($p, 1) . ' W</strong></td>
                    <td>' . number_format($t, 1) . ' °C</td>
                </tr>';
            }

            $avgVoltage = $count > 0 ? $totalVoltage / $count : 0;
            $avgCurrent = $count > 0 ? $totalCurrent / $count : 0;
            $avgPower = $count > 0 ? $totalPower / $count : 0;
            $avgTemp = $count > 0 ? $totalTemp / $count : 0;

            $html .= '<table class="table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Voltage (V)</th>
                        <th>Current (A)</th>
                        <th>Power (W)</th>
                        <th>Temperature (&deg;C)</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $tableRows . '
                </tbody>
            </table>

            <div class="summary-box">
                <div class="summary-title">Consumption Metrics Summary</div>
                <table width="100%" style="font-size: 13px; color: #047857;">
                    <tr>
                        <td width="50%">Average Power: <span class="summary-val">' . number_format($avgPower, 1) . ' W</span></td>
                        <td width="50%">Average Voltage: <span class="summary-val">' . number_format($avgVoltage, 1) . ' V</span></td>
                    </tr>
                    <tr>
                        <td style="padding-top: 8px;">Average Current: <span class="summary-val">' . number_format($avgCurrent, 2) . ' A</span></td>
                        <td style="padding-top: 8px;">Average Operating Temp: <span class="summary-val">' . number_format($avgTemp, 1) . ' &deg;C</span></td>
                    </tr>
                </table>
            </div>';
        }

        $html .= '
    <div class="footer">
        Smart Energy Mobile App &bull; Confidential Energy Data Report &bull; Page 1 of 1
    </div>
</body>
</html>';

        return $html;
    }
}

