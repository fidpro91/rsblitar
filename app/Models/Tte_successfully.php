<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tte_successfully extends Model
{
    use HasFactory;
    protected $table = 'tte_successfully';

    protected $fillable = [
        'visit_id',
        'doc_id',
        'path_tte',
    ];
}
