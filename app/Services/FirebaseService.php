<?php

namespace App\Services;

class FirebaseService
{
    protected $client;
    protected $dbUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->client = \Config\Services::curlrequest();
        $this->dbUrl = rtrim(env('FIREBASE_DATABASE_URL'), '/');
        $this->apiKey = env('FIREBASE_API_KEY');
    }

    public function fetchSensorData(): array
    {
        try {
            $url = $this->dbUrl . '/sensors.json';
            if (!empty($this->apiKey)) {
                $url .= '?auth=' . $this->apiKey;
            }
            $response = $this->client->get($url);
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true) ?: [];
            }
        } catch (\Exception $e) {
            log_message('error', 'Firebase Fetch Sensors Error: ' . $e->getMessage());
        }
        return [];
    }

    public function fetchDevices(): array
    {
        try {
            $url = $this->dbUrl . '/devices.json';
            if (!empty($this->apiKey)) {
                $url .= '?auth=' . $this->apiKey;
            }
            $response = $this->client->get($url);
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true) ?: [];
            }
        } catch (\Exception $e) {
            log_message('error', 'Firebase Fetch Devices Error: ' . $e->getMessage());
        }
        return [];
    }
}
