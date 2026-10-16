<?php

namespace App\Models;

use App\Enums\InstallationStatus;
use Carbon\CarbonInterface;
use Database\Factories\InstallationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'reference', 'client_id', 'quote_id', 'user_id', 'title', 'status',
    'scheduled_at', 'started_at', 'completed_at', 'site_address',
    'commune', 'wilaya', 'description', 'notes', 'created_by',
])]
class Installation extends Model
{
    /** @use HasFactory<InstallationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InstallationStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $installation): void {
            $installation->reference ??= self::nextReference();
        });
    }

    /**
     * Génère la référence suivante au format INT-AAAA-0001.
     */
    public static function nextReference(): string
    {
        $prefix = 'INT-'.now()->year.'-';
        $last = self::withTrashed()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = $last ? ((int) mb_substr($last, mb_strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InstallationItem::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['planifie', 'en_cours', 'suspendu']);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('reference', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%")
                    ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$term}%"));
            });
        });
    }

    public function scopeOnDay(Builder $query, CarbonInterface $day): Builder
    {
        return $query->whereBetween('scheduled_at', [
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
        ]);
    }

    /**
     * Durée planifiée en heures (nuit si l'intervention traverse minuit).
     */
    public function durationInHours(): ?float
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return round($this->started_at->diffInMinutes($this->completed_at) / 60, 1);
    }
}
