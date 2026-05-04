<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSensorReadingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'auto_increment' => true],
            'device_id'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'current'     => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true],
            'voltage'     => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true],
            'temperature' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true],
            'power_watt'  => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true],
            'recorded_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('sensor_readings', true);
    }

    public function down()
    {
        $this->forge->dropTable('sensor_readings', true);
    }
}
