<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit_alergy extends Model
{
    use HasFactory;

    protected $table = 'visit_alergy';

    protected $fillable = [
        'visit_id',
        'allergy_id',
        'tanggal_alergi',
        'note',
        'uuid_allergy'
    ];

    public $timestamps = true;

    public function master()
    {
        return $this->belongsTo(Ms_allergy::class, 'allergy_id', 'id');
    }
}
