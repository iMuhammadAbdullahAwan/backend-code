<?php

namespace App\Models;

use CodeIgniter\Model;

class AiTipModel extends Model
{
    protected $table = 'ai_tips';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['device_id', 'tip_text', 'category', 'generated_at', 'created_at', 'updated_at'];
}
