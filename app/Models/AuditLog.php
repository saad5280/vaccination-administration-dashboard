<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'action', 'auditable_type', 'auditable_id',
        'before_values', 'after_values', 'ip_address', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(Request $request, string $action, ?Model $subject = null, ?array $before = null, ?array $after = null): void
    {
        self::query()->create([
            'actor_id' => $request->user()?->getAuthIdentifier(),
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : (str_starts_with($action, 'auth.') ? 'authentication' : 'system'),
            'auditable_id' => $subject?->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);
    }
}
