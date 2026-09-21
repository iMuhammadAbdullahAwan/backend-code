<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBillingTariffsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'auto_increment' => true],
            'device_id'              => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'unique' => true],
            'rate_per_kwh'           => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'currency'               => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'PKR'],
            'currency_symbol'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'Rs.'],
            'billing_cycle_start_day' => ['type' => 'TINYINT', 'constraint' => 2, 'default' => 1],
            'tariff_type'            => ['type' => 'ENUM', 'constraint' => ['flat', 'slab'], 'default' => 'flat'],
            'slabs'                  => ['type' => 'TEXT', 'null' => true],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('billing_tariffs', true);

        // Global default tariff (device_id = NULL) used when a device has no
        // device-specific tariff row of its own.
        $this->db->table('billing_tariffs')->insert([
            'device_id'               => null,
            'rate_per_kwh'            => 32.50,
            'currency'                => 'PKR',
            'currency_symbol'         => 'Rs.',
            'billing_cycle_start_day' => 1,
            'tariff_type'             => 'flat',
            'slabs'                   => null,
            'created_at'              => date('Y-m-d H:i:s'),
            'updated_at'              => date('Y-m-d H:i:s'),
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('billing_tariffs', true);
    }
}
