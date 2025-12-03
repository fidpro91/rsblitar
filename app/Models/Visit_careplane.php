<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit_careplane extends Model
{
    use HasFactory;

    protected $table = 'visit_careplane';
    protected $primaryKey = 'id';

    protected $fillable = [
        'visit_id',
        'kondisi_pulang',
        'alasan_pulang',
        'keterangan',
        'uuid_careplane'

    ];
     public $timestamps = false;

    public function visit_encounter()
    {
        return $this->belongsTo(Visit_encounter::class, 'visit_id', 'visit_id');
    }
}
