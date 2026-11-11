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

    /**
     * Mutator untuk memastikan kolom response_body selalu berupa JSON valid pada PostgreSQL.
     */
    public function setResponseBodyAttribute($value)
    {
        if (is_array($value) || is_object($value)) {
            $this->attributes['response_body'] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                json_decode($trimmed);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->attributes['response_body'] = $trimmed;
                    return;
                }
            }
            $this->attributes['response_body'] = json_encode(['raw' => $value], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->attributes['response_body'] = json_encode(['raw' => (string) $value]);
    }
}
