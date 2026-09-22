<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Symfony\Component\Uid\Ulid;

/**
 * @property int $id
 * @property string $public_id
 * @property string $key
 * @property string $name
 * @property string $subject_template
 * @property string $body_html_template
 * @property string $body_text_template
 * @property array<int, string>|null $available_variables
 * @property bool $is_active
 * @property string $locale
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $fillable = [
        'public_id',
        'key',
        'name',
        'subject_template',
        'body_html_template',
        'body_text_template',
        'available_variables',
        'is_active',
        'locale',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailTemplate $template) {
            if (empty($template->public_id)) {
                $template->public_id = strtolower((string) Ulid::generate());
            }
        });
    }

    /**
     * @param  Builder<EmailTemplate>  $query
     * @return Builder<EmailTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
