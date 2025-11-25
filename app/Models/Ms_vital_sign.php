<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ms_vital_sign extends Model
{
    use HasFactory;
    protected $table = 'ms_vital_sign';

    protected $fillable = [
        'code',
        'name',
        'display',
        'sumber',
        'satuan',
        'code_satuan',
    ];
}
