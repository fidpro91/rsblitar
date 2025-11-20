<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configs extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $table = 'configs';
    protected $fillable = [
        'id',
        'secret_key',
        'kode',
        'client_key',
        'url',
        'kode_organization',
        'tipe'
    ];
}
