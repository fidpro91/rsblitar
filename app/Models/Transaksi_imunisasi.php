<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaksi_imunisasi extends Model
{
    use HasFactory;
    protected $table = 'transaksi_imunisasi';
    protected $primaryKey = 'id_transaksi';
    protected $fillable = [
        'visit_id',
        'tanggal_lahir',
        'nama_orang_tua',
        'no_hp',
        'id_vaksin',
        'tanggal_imunisasi',
        'dosis_ke',
        'nomor_batch',
        'tanggal_kadaluarsa',
        'cara_pemberian',
        'lokasi_suntikan',
        'reaksi',
        'catatan',
        'status_imunisasi',
        'uuid_imunisasi',
        'created_at'
    ];

    public function vaksin()
    {
        return $this->belongsTo(Ms_vaksin::class, 'id_vaksin', 'id_vaksin');
    }

    public function encounter()
    {
        return $this->belongsTo(Visit_encounter::class, 'visit_id', 'visit_id');
    }
}
