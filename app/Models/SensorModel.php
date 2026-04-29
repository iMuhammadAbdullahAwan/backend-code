<?php

namespace App\Models;

use CodeIgniter\Model;

class SensorModel extends Model
{
    protected $table      = 'sensor_data';
    protected $primaryKey = 'id';

    protected $useAutoIncrement = true;

    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'device_id',
        'current',
        'created_at',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'device_id' => 'required|string|max_length[50]',
        'current'   => 'required|numeric',
    ];

    protected $validationMessages = [
        'device_id' => [
            'required'     => 'Device ID is required',
            'max_length'   => 'Device ID must not exceed 50 characters',
        ],
        'current' => [
            'required' => 'Current value is required',
            'numeric'  => 'Current must be a numeric value',
        ],
    ];
}
