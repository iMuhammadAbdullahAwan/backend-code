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
            $paths = ['/sensors.json', '/energy.json', '/.json'];
            $data = [];
            foreach ($paths as $p) {
                $url = $this->dbUrl . $p;
                if (!empty($this->apiKey)) {
                    $url .= '?auth=' . $this->apiKey;
                }
                log_message('debug', 'FirebaseService: fetching ' . $url);
                $response = $this->client->get($url);
                if ($response->getStatusCode() === 200) {
                    $decoded = json_decode($response->getBody(), true) ?: [];
                    if (!empty($decoded)) {
                        $data = $decoded;
                        log_message('debug', 'FirebaseService: received payload from ' . $url . ' count=' . (is_array($decoded) ? count($decoded) : 0));
                        break;
                    } else {
                        log_message('debug', 'FirebaseService: empty payload at ' . $url);
                    }
                }
            }

            // Normalize cases where the DB root is a single reading object
            // e.g. { "current": 0, "kwh": 0.02, ... } -> treat as device 'energy'
            if (is_array($data)) {
                $hasReadingKeys = false;
                foreach (['current', 'voltage', 'temp', 'temperature', 'kwh', 'power'] as $k) {
                    if (array_key_exists($k, $data)) {
                        $hasReadingKeys = true;
                        break;
                    }
                }

                if ($hasReadingKeys) {
                    return ['energy' => $data];
                }

                return $data;
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
