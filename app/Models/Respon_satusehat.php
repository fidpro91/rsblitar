<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Respon_satusehat extends Model
{
    use HasFactory;

    protected $table = 'respon_satusehat';
    protected $primaryKey = 'id_respon';
    
    protected $fillable = [
        'id_respon',
        'status',
        'resourcetype',
        'resourceid',
        'metode',
        'tgl_kirim',
        'pasien_id',
        'visit_id',
        'respon_all',
    ];
    public $timestamps = true;

    protected $casts = [
        'resourceid' => 'string',
        'tgl_kirim' => 'datetime',
        'respon_all' => 'array',
    ];
}
