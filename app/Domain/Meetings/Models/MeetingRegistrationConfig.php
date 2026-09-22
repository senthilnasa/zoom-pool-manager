<?php

namespace App\Domain\Meetings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRegistrationConfig extends Model
{
    protected $table = 'meeting_registrations_config';

    protected $fillable = [
        'meeting_id',
        'approval_type',
        'questions',
    ];

    protected $casts = [
        'questions' => 'json',
    ];

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
