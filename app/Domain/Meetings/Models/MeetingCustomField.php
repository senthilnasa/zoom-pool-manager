<?php

namespace App\Domain\Meetings\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Symfony\Component\Uid\Ulid;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $field_key
 * @property string $field_type
 * @property array<int, string>|null $options
 * @property string|null $placeholder
 * @property string|null $help_text
 * @property string|null $default_value
 * @property bool $is_required
 * @property bool $is_active
 * @property int $display_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MeetingCustomField extends Model
{
    protected $table = 'meeting_custom_fields';

    protected $fillable = [
        'public_id',
        'name',
        'field_key',
        'field_type',
        'options',
        'placeholder',
        'help_text',
        'default_value',
        'is_required',
        'is_active',
        'display_order',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (MeetingCustomField $field): void {
            if (empty($field->public_id)) {
                $field->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Scope to active fields ordered by display_order.
     *
     * @param  Builder<MeetingCustomField>  $query
     * @return Builder<MeetingCustomField>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('display_order')->orderBy('name');
    }
}
