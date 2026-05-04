<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDevicesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'auto_increment' => true],
            'device_id'   => ['type' => 'VARCHAR', 'constraint' => 100, 'unique' => true],
            'device_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'location'    => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'status'      => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('devices', true);
    }

    public function down()
    {
        $this->forge->dropTable('devices', true);
    }
}
