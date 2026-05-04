<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    public function run()
    {
        // 1. Devices
        $devices = [
            [
                'device_id'   => 'DEV001',
                'device_name' => 'Living Room AC',
                'location'    => 'Living Room',
                'status'      => 'active',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ],
            [
                'device_id'   => 'DEV002',
                'device_name' => 'Kitchen Refrigerator',
                'location'    => 'Kitchen',
                'status'      => 'active',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ],
            [
                'device_id'   => 'DEV003',
                'device_name' => 'Main Water Heater',
                'location'    => 'Basement',
                'status'      => 'inactive',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('devices')->ignore(true)->insertBatch($devices);

        // 2. Sensor Readings (30 per device for realistic charts)
        $readings = [];
        $now = time();
        foreach (['DEV001', 'DEV002'] as $deviceId) {
            for ($i = 0; $i < 30; $i++) {
                $current = rand(10, 80) / 10; // 1.0 to 8.0 Amps
                $voltage = 220.0 + (rand(-50, 50) / 10); // 215.0 to 225.0 Volts
                $readings[] = [
                    'device_id'   => $deviceId,
                    'current'     => $current,
                    'voltage'     => $voltage,
                    'temperature' => rand(200, 350) / 10, // 20.0 to 35.0 C
                    'power_watt'  => $current * $voltage,
                    'recorded_at' => date('Y-m-d H:i:s', $now - ($i * 3600)), // Every hour back
                    'created_at'  => date('Y-m-d H:i:s'),
                ];
            }
        }
        $this->db->table('sensor_readings')->insertBatch($readings);

        // 3. Bill Predictions
        $predictions = [
            [
                'device_id'      => 'DEV001',
                'month'          => date('Y-m'),
                'predicted_kwh'  => 120.5,
                'predicted_cost' => 36.15,
                'currency'       => 'USD',
                'generated_at'   => date('Y-m-d H:i:s'),
                'created_at'     => date('Y-m-d H:i:s'),
            ],
            [
                'device_id'      => 'DEV002',
                'month'          => date('Y-m'),
                'predicted_kwh'  => 85.2,
                'predicted_cost' => 25.56,
                'currency'       => 'USD',
                'generated_at'   => date('Y-m-d H:i:s'),
                'created_at'     => date('Y-m-d H:i:s'),
            ]
        ];
        $this->db->table('bill_predictions')->insertBatch($predictions);

        // 4. AI Tips
        $tips = [
            [
                'device_id'    => 'DEV001',
                'tip_text'     => 'Your AC is running slightly hotter than usual. Please check the filters.',
                'category'     => 'maintenance alert',
                'generated_at' => date('Y-m-d H:i:s'),
                'created_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'device_id'    => 'DEV001',
                'tip_text'     => 'Increasing your AC set temperature by 1 degree can save 5% on cooling costs.',
                'category'     => 'energy saving',
                'generated_at' => date('Y-m-d H:i:s'),
                'created_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'device_id'    => 'DEV002',
                'tip_text'     => 'Ensure there is adequate space around your refrigerator coils for optimal efficiency.',
                'category'     => 'energy saving',
                'generated_at' => date('Y-m-d H:i:s'),
                'created_at'   => date('Y-m-d H:i:s'),
            ]
        ];
        $this->db->table('ai_tips')->insertBatch($tips);
    }
}
