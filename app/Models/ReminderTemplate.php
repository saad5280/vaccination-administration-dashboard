<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReminderTemplate extends Model
{
    protected $fillable = ['name', 'channel', 'language', 'body', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ReminderRule::class, 'template_id');
    }
}
