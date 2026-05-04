<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBillPredictionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'auto_increment' => true],
            'device_id'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'month'          => ['type' => 'VARCHAR', 'constraint' => 20],
            'predicted_kwh'  => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true],
            'predicted_cost' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'currency'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'USD'],
            'generated_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('bill_predictions', true);
    }

    public function down()
    {
        $this->forge->dropTable('bill_predictions', true);
    }
}
