<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit_quisioner extends Model
{
    use HasFactory;
    protected $table = 'visit_quisioner';
    protected $primaryKey = 'id_quisioner';
    protected $fillable = [
        'visit_id',
        'kode_apoteker',
        'nama_apoteker',
        'tgl_quisioner',
        'data_quisioner',
        'uuid_quisioner',
        'srv_id'
    ];

    protected $casts = [
        'data_quisioner' => 'array',

    ];
    public $timestamps = false;

    public function visit_encounter()
    {
        return $this->belongsTo(Visit_encounter::class, 'visit_id', 'visit_id');
    }
}
