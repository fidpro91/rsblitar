<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit_radiologi extends Model
{
    use HasFactory;

    protected $table = 'visit_radiologi';
    public $timestamps = false;
    protected $fillable = [
        'visit_id',
        'srv_id',
        'tanggal_order',
        'nama_pemeriksaan',
        'dokter_pengirim',
        'kode_dokter_pengirim',
        'tanggal_pemeriksaan',
        'tanggal_hasil',
        'dokter_radiologi',
        'kode_dokter_radiologi',
        'hasil_pemeriksaan',
        'uuid_service_request',
        'uuid_observation',
        'uuid_diagnostic_report',
        'code_map_rad',
        'acsn_number',
    ];
    
    public function ms_radiologi()
    {
        return $this->belongsTo(Ms_radiologi::class, 'code_map_rad', 'loinc_code');
    }
}
