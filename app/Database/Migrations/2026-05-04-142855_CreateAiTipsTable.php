<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAiTipsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'auto_increment' => true],
            'device_id'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'tip_text'     => ['type' => 'TEXT', 'null' => true],
            'category'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'generated_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('ai_tips', true);
    }

    public function down()
    {
        $this->forge->dropTable('ai_tips', true);
    }
}
