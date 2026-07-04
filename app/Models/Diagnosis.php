<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diagnosis extends Model
{
    use HasFactory;

    protected $table = 'diagnosis';
    protected $primaryKey = 'id';

    protected $fillable = [
        'visit_id',
        'uuid',
        'rank',
        'code',
        'dx_name',
        'srv_id'
    ];

    public $timestamps = true;
}
