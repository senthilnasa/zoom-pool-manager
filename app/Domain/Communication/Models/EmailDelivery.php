<?php

namespace App\Domain\Communication\Models;

use App\Domain\Meetings\Models\Meeting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Symfony\Component\Uid\Ulid;

/**
 * @property int $id
 * @property string $public_id
 * @property string $dedupe_key
 * @property int|null $meeting_id
 * @property string $recipient_email
 * @property string|null $recipient_name
 * @property string $template_key
 * @property string $subject
 * @property string|null $body_html
 * @property string|null $body_text
 * @property string $status
 * @property int $attempts
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Meeting|null $meeting
 */
class EmailDelivery extends Model
{
    protected $table = 'email_deliveries';

    protected $fillable = [
        'public_id',
        'dedupe_key',
        'meeting_id',
        'recipient_email',
        'recipient_name',
        'template_key',
        'subject',
        'body_html',
        'body_text',
        'status',
        'attempts',
        'error_message',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_id' => 'integer',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailDelivery $delivery) {
            if (empty($delivery->public_id)) {
                $delivery->public_id = strtolower((string) Ulid::generate());
            }
        });
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    /**
     * @param  Builder<EmailDelivery>  $query
     * @return Builder<EmailDelivery>
     */
    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', 'queued');
    }

    /**
     * @param  Builder<EmailDelivery>  $query
     * @return Builder<EmailDelivery>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * @param  Builder<EmailDelivery>  $query
     * @return Builder<EmailDelivery>
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }
}
