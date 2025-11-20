<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ms_pemeriksaan extends Model
{
    use HasFactory;

    protected $table = 'ms_pemeriksaan';

    protected $fillable = [
        'code_system',
        'code_value',
        'code_display',
        'category',
        'priority',
        'unit',
        'normal_low',
        'normal_high',
        'normal_text',
        'critical_low',
        'critical_high',
        'source',
    ];

    public $timestamps = true;
}
