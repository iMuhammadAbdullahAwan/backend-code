<?php

namespace App\Models;

use CodeIgniter\Model;

class SensorReadingModel extends Model
{
    protected $table = 'sensor_readings';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['device_id', 'current', 'voltage', 'temperature', 'power_watt', 'energy', 'kwh', 'power', 'recorded_at'];
}
