<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    /**
     * Get a setting value by key, with default fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = static::where('key', $key)->first();
        } catch (\Throwable $e) {
            return $default;
        }

        if (! $setting) {
            return $default;
        }

        if ($setting->is_encrypted && ! empty($setting->value)) {
            try {
                return Crypt::decryptString($setting->value);
            } catch (\Exception $e) {
                return $default;
            }
        }

        $decoded = json_decode((string) $setting->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value;
    }

    /**
     * Set a setting key and value.
     */
    public static function set(string $key, mixed $value, bool $encrypt = false): self
    {
        $storedValue = is_array($value) ? json_encode($value) : (string) $value;

        if ($encrypt && ! empty($storedValue)) {
            $storedValue = Crypt::encryptString($storedValue);
        }

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'is_encrypted' => $encrypt,
            ]
        );
    }

    /**
     * Delete a setting key.
     */
    public static function forget(string $key): bool
    {
        return (bool) static::where('key', $key)->delete();
    }
}
