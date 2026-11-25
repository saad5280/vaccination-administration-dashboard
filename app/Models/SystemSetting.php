<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever('system-setting:'.$key, fn () => static::query()->where('key', $key)->first()?->value ?? $default);
    }

    public static function saveValue(string $key, mixed $value, ?int $updatedBy): self
    {
        $setting = static::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $updatedBy]);
        Cache::forget('system-setting:'.$key);

        return $setting;
    }
}
