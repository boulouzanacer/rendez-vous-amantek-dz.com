<?php

namespace App\Models;

use App\Enums\AuthorizationStatus;
use Database\Factories\AuthorizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'reference', 'client_id', 'status', 'wilaya', 'commune', 'address',
    'has_inside_cameras', 'has_outside_cameras', 'inside_cameras',
    'outside_cameras', 'notes', 'created_by',
])]
class Authorization extends Model
{
    /** @use HasFactory<AuthorizationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AuthorizationStatus::class,
            'has_inside_cameras' => 'boolean',
            'has_outside_cameras' => 'boolean',
            'inside_cameras' => 'array',
            'outside_cameras' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $authorization): void {
            $authorization->reference ??= self::nextReference();
        });
    }

    /**
     * Génère la référence suivante au format AUT-AAAA-0001.
     */
    public static function nextReference(): string
    {
        $prefix = 'AUT-'.now()->year.'-';
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Caméras intérieures déclarées (uniquement si le type est retenu).
     *
     * @return array<int, array{reference: string, quantity: int}>
     */
    public function insideList(): array
    {
        return $this->has_inside_cameras ? array_values((array) $this->inside_cameras) : [];
    }

    /**
     * Caméras extérieures déclarées (uniquement si le type est retenu).
     *
     * @return array<int, array{reference: string, quantity: int}>
     */
    public function outsideList(): array
    {
        return $this->has_outside_cameras ? array_values((array) $this->outside_cameras) : [];
    }

    public function cameraCount(): int
    {
        return collect($this->insideList())->sum('quantity')
            + collect($this->outsideList())->sum('quantity');
    }

    public function cameraSummary(): string
    {
        $parts = [];

        if ($this->insideList() !== []) {
            $parts[] = 'Intérieur : '.collect($this->insideList())->sum('quantity');
        }

        if ($this->outsideList() !== []) {
            $parts[] = 'Extérieur : '.collect($this->outsideList())->sum('quantity');
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    public function location(): string
    {
        return collect([$this->commune, $this->wilaya])->filter()->implode(', ');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            AuthorizationStatus::AutorisationRetenue->value,
            AuthorizationStatus::Annule->value,
            AuthorizationStatus::Refuse->value,
        ]);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('reference', 'like', "%{$term}%")
                    ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$term}%"));
            });
        });
    }
}
