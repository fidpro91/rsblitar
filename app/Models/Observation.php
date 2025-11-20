<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Observation extends Model
{
    use HasFactory;

    protected $table = 'observation';

    protected $fillable = [
        'id',
        'visit_id',
        'observation_name',
        'uuid_observation',
        'result',
        'vital_id'
    ];

    public $timestamps = true;

    public function vital()
    {
        return $this->belongsTo(Ms_vital_sign::class, 'vital_id', 'id');
    }
}
