<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEnergyFieldsToSensorReadingsTable extends Migration
{
    public function up()
    {
        $fields = [
            'energy' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true],
            'kwh'    => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true],
            'power'  => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true],
        ];

        $this->forge->addColumn('sensor_readings', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('sensor_readings', ['energy', 'kwh', 'power']);
    }
}
