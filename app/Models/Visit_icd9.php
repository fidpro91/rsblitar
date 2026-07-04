<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit_icd9 extends Model
{
    use HasFactory;

    protected $table = 'visit_icd9';
    protected $primaryKey = 'id';
    protected $fillable = [
        'visit_id',
        'uuid',
        'icd_code',
        'icd_name',
        'srv_id'
    ];
    public $timestamps = false;

    public function visit()
    {
        return $this->belongsTo(Visit_encounter::class, 'visit_id', 'visit_id');
    }
}
