<?php

namespace App\Services;

class GeminiService
{
    protected $client;
    protected $apiKey;
    protected $model;

    public function __construct()
    {
        $this->client = \Config\Services::curlrequest();
        $this->apiKey = env('GEMINI_API_KEY');
        $this->model = env('GEMINI_MODEL') ?: 'gemini-2.5-pro';
    }

    private function callGemini(string $prompt): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ]
        ];

        try {
            $response = $this->client->post($url, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $body = json_decode($response->getBody(), true);
                return $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
            }
        } catch (\Exception $e) {
            log_message('error', 'Gemini API Error: ' . $e->getMessage());
        }

        return '';
    }

    public function generateBillPrediction(string $deviceId, array $recentReadings): array
    {
        $readingsJson = json_encode($recentReadings);
        $prompt = "You are an energy analyst AI. Based on the following sensor readings 
         for device {$deviceId}, predict the electricity bill for this month.
         Readings (current in Amps, voltage in Volts, temperature in °C):
         {$readingsJson}
         
         Return ONLY valid JSON:
         {
           \"predicted_kwh\": 0.0,
           \"predicted_cost\": 0.0,
           \"currency\": \"USD\",
           \"summary\": \"One sentence explanation\"
         }";

        $result = $this->callGemini($prompt);

        if (!empty($result)) {
            $result = str_replace(['```json', '```'], '', $result);
            $parsed = json_decode(trim($result), true);
            if ($parsed) {
                return $parsed;
            }
        }

        return [
            'predicted_kwh' => 0.0,
            'predicted_cost' => 0.0,
            'currency' => 'USD',
            'summary' => 'Unable to generate prediction.'
        ];
    }

    public function generateEnergyTips(string $deviceId, array $recentReadings): array
    {
        $readingsJson = json_encode($recentReadings);
        $prompt = "You are an energy efficiency AI assistant. Analyze the following 
         sensor readings for device {$deviceId} and provide 3 practical 
         energy-saving tips or maintenance alerts.
         Readings: {$readingsJson}
         
         Return ONLY valid JSON array:
         [
           { \"tip\": \"tip text here\", \"category\": \"energy saving|maintenance|alert\" }
         ]";

        $result = $this->callGemini($prompt);

        if (!empty($result)) {
            $result = str_replace(['```json', '```'], '', $result);
            $parsed = json_decode(trim($result), true);
            if ($parsed && is_array($parsed)) {
                return $parsed;
            }
        }

        return [];
    }
}
