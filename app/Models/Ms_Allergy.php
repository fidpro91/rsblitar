<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ms_allergy extends Model
{
    use HasFactory;

    protected $table = 'ms_allergy';

    protected $fillable = [
        'substance_code',
        'substance_display',
        'category',
        'criticality',
        'description'
    ];

    public $timestamps = true;
}
