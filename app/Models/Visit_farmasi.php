<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Codec\TimestampFirstCombCodec;

class Visit_farmasi extends Model
{
    use HasFactory;

    protected $table = 'visit_farmasi';
    protected $fillable = [
        'item_id_simrs',
        'item_id_kfa',
        'visit_id',
        'sale_qty',
        'racikan',
        'dosis',
        'waktu_resep_dibuat',
        'waktu_resep_diterima',
        'waktu_resep_diproses',
        'waktu_diserahkan',
        'waktu_selesai',
        'dokter_peresep',
        'kode_dokter',
        'unit_name',
        'uuid_unit',
        'uuid_med',
        'uuid_med_request',
        'uuid_med_dispen',
        'sale_num'
    ];
    public $timestamps = false;


    public function ms_kfa()
    {
        return $this->belongsTo(Kfa_master::class, 'item_id_kfa', 'code_kfa');
    }
}
