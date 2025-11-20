<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kfa_master extends Model
{
    use HasFactory;

    protected $table = 'kfa_master';

    protected $fillable = [
        'id',
        'code_kfa',
        'nama_kfa',
        'type',
        'status',
        'harga_obat',
        'waktu_update',
        'berat_bersih_uom',
        'volume_uom',
        'uom',
        'product_template',
        'ingredients',
        'dose_per_unit',
        'dosis_form',
        'tags'
    ];
    public $timestamps = true;
}
