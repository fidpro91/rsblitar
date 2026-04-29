<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ms_vaksin extends Model
{
    use HasFactory;
    protected $table = 'ms_vaksin';
    protected $primaryKey = 'id_vaksin';
    protected $fillable = [
        'kode_vaksin',
        'nama_vaksin',
        'jenis_vaksin',
        'kategori_vaksin',
        'produsen',
        'keterangan',
        'status_aktif',
        'created_at',
        'reason_code',
        'reason_display',
        'timing_code',
        'timing_display',
    ];

    public function transaksi_imunisasi()
    {
        return $this->hasMany(Transaksi_imunisasi::class, 'id_vaksin', 'id_vaksin');
    }
}
