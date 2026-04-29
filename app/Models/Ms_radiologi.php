<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ms_radiologi extends Model
{
    use HasFactory;

    protected $table = 'ms_radiologi';
    protected $primaryKey = 'id';
    protected $timestamp = true;

    protected $fillable = [
        'loinc_code',
        'loinc_name',
        'modality',
        'body_part',
        'contrast',
        'is_active',
    ];
}
