<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUpdatedAtToBillPredictionsTable extends Migration
{
    public function up()
    {
        $fields = [
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        $this->forge->addColumn('bill_predictions', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('bill_predictions', ['updated_at']);
    }
}
