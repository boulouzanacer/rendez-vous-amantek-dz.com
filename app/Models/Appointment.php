<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\Priority;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'reference', 'client_id', 'user_id', 'type', 'status', 'priority',
    'scheduled_at', 'duration_minutes', 'address', 'commune', 'wilaya',
    'description', 'internal_notes', 'cancelled_reason', 'completed_at',
    'reminder_sent_at', 'created_by',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AppointmentType::class,
            'status' => AppointmentStatus::class,
            'priority' => Priority::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $appointment): void {
            $appointment->reference ??= self::nextReference();
        });
    }

    /**
     * Génère la référence suivante au format RVD-AAAA-0001.
     */
    public static function nextReference(): string
    {
        $prefix = 'RVD-'.now()->year.'-';
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

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Les chantiers découlent des devis rattachés au rendez-vous.
     */
    public function installations(): HasManyThrough
    {
        return $this->hasManyThrough(Installation::class, Quote::class, 'appointment_id', 'quote_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (AppointmentStatus $status): string => $status->value,
            AppointmentStatus::openCases(),
        ));
    }

    public function scopeOnDay(Builder $query, CarbonInterface $day): Builder
    {
        return $query->whereBetween('scheduled_at', [
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
        ]);
    }

    public function scopeBetween(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query->whereBetween('scheduled_at', [$start, $end]);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>=', now());
    }

    public function scopeForTechnician(Builder $query, ?int $userId): Builder
    {
        return $query->when($userId, fn (Builder $query): Builder => $query->where('user_id', $userId));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('reference', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('address', 'like', "%{$term}%")
                    ->orWhereHas('client', fn (Builder $client) => $client
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%"));
            });
        });
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return $query->when(filled($status), fn (Builder $query): Builder => $query->where('status', $status));
    }

    public function scopeWithType(Builder $query, ?string $type): Builder
    {
        return $query->when(filled($type), fn (Builder $query): Builder => $query->where('type', $type));
    }

    public function endsAt(): CarbonInterface
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes);
    }

    public function timeRange(): string
    {
        return $this->scheduled_at->format('H:i').' – '.$this->endsAt()->format('H:i');
    }

    /**
     * Adresse du site : celle du rendez-vous, sinon celle du client.
     */
    public function siteAddress(): string
    {
        return $this->address ?: ($this->client?->address ?? '—');
    }

    public function siteLocation(): string
    {
        $location = collect([$this->commune, $this->wilaya])->filter()->implode(', ');

        return $location !== '' ? $location : ($this->client?->location() ?? '');
    }

    /**
     * Indique si le rendez-vous est en retard (heure dépassée et toujours ouvert).
     */
    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->scheduled_at->isPast();
    }
}
