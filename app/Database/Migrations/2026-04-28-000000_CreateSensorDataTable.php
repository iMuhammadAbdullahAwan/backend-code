<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSensorDataTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'device_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'current' => [
                'type' => 'FLOAT',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', false, true);
        $this->forge->createTable('sensor_data');
    }

    public function down()
    {
        $this->forge->dropTable('sensor_data');
    }
}
