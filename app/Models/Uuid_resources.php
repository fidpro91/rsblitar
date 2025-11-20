<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Uuid_resources extends Model
{
    use HasFactory;

    protected $table = 'uuid_resources';
    protected $fillable = [
        'visit_id',
        'local_uuid',
        'resource_type'
    ];
    public $timestamps = true;
    public static function createuuid($visit_id, $resource_type)
    {
        $uuid = Str::uuid()->toString();

        return self::create([
            'visit_id' => $visit_id,
            'local_uuid' => $uuid,
            'resource_type' => $resource_type
        ]);
    }
}
