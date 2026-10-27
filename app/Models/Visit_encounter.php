<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit_encounter extends Model
{
    use HasFactory;

    protected $table = 'visit_encounter';
    protected $primaryKey = 'id';
    protected $fillable = [
        'visit_id',
        'no_ktp',
        'px_norm',
        'px_name',
        'unit_name',
        'idunitsatset',
        'dpjp_name',
        'kode_dokter',
        'tgl_kunjung',
        'tgl_dilayani',
        'tgl_selesai_dilayani',
        'tgl_pulang',
        'tipe_kunjungan',
        'is_send',
        'uuid_encounter',
        'kode_pasien',
        'instruksi_pulang',
        'uuid_composition',
        'uuid_clinicalimpresion',
        'srv_id'
    ];
    public $timestamps = true;

    public function diagnossis()
    {
        return $this->hasMany(Diagnosis::class, 'visit_id', 'visit_id')
            ->orderBy('rank', 'asc');
    }

    public function observation()
    {
        return $this->hasMany(Observation::class, 'visit_id', 'visit_id');
    }

    public function visit_allergy()
    {
        return $this->hasMany(Visit_alergy::class, 'visit_id', 'visit_id');
    }

    public function visit_lab()
    {
        return $this->hasMany(Visit_lab::class, 'visit_id', 'visit_id');
    }

    public function icd9()
    {
        return $this->hasMany(Visit_icd9::class, 'visit_id', 'visit_id');
    }

     public function visit_radiologi()
    {
        return $this->hasMany(Visit_radiologi::class, 'visit_id', 'visit_id');
    }

}
