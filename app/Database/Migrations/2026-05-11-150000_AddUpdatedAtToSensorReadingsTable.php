<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUpdatedAtToSensorReadingsTable extends Migration
{
    public function up()
    {
        $fields = [
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        $this->forge->addColumn('sensor_readings', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('sensor_readings', ['updated_at']);
    }
}
