<?php

namespace App\Models;

use CodeIgniter\Model;

class BillPredictionModel extends Model
{
    protected $table = 'bill_predictions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['device_id', 'month', 'predicted_kwh', 'predicted_cost', 'currency', 'generated_at', 'created_at', 'updated_at'];
}
