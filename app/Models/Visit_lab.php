<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit_lab extends Model
{
    use HasFactory;

    protected $table = 'visit_lab';

    protected $fillable = [
        'visit_id',
        'nama_pemeriksaan',
        'dokter_lab',
        'kode_dokter_lab',
        'tgl_periksa',
        'tgl_ambil_sample',
        'tgl_selesai',
        'dokter_pengirim',
        'kode_pengirim',
        'hasil_lab',
        'map_pemeriksaan_id',
        'map_specimen_id',
        'uuid_specimen',
        'uuid_obs',
        'uuid_diagnostic',
        'uuid_servicereq',
    ];

    public $timestamps = false;

    public function pemeriksaan()
    {
        return $this->belongsTo(Ms_pemeriksaan::class, 'map_pemeriksaan_id');
    }
    public function specimen()
    {
        return $this->belongsTo(Ms_specimen::class, 'map_specimen_id');
    }
}
