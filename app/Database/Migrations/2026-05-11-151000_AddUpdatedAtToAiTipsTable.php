<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUpdatedAtToAiTipsTable extends Migration
{
    public function up()
    {
        $fields = [
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        $this->forge->addColumn('ai_tips', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('ai_tips', ['updated_at']);
    }
}
