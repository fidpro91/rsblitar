<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ms_specimen extends Model
{
    use HasFactory;

    protected $table = 'ms_specimen';

    protected $fillable = [
        'code_system',
        'code_value',
        'code_display',
        'container',
        'description',
        'source',
    ];

    public $timestamps = true;
}
