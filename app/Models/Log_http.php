<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log_http extends Model
{
    use HasFactory;
    protected $table = 'log_http';

    protected $fillable = [
        'service_name',
        'endpoint_url',
        'http_method',
        'response_code',
        'response_body',
        'status',
        'fk_id',
        'response_message',
    ];
}
