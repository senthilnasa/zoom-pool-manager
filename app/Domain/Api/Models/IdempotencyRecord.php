<?php

namespace App\Domain\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property int|null $user_id
 * @property int|null $api_key_id
 * @property string $route
 * @property string $request_hash
 * @property int $response_status
 * @property array<string, mixed>|null $response_headers
 * @property string $response_body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class IdempotencyRecord extends Model
{
    protected $table = 'idempotency_records';

    protected $fillable = [
        'key',
        'user_id',
        'api_key_id',
        'route',
        'request_hash',
        'response_status',
        'response_headers',
        'response_body',
    ];

    protected $casts = [
        'response_headers' => 'array',
        'response_status' => 'integer',
    ];
}
