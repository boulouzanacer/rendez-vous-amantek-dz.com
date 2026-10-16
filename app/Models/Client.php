<?php

namespace App\Models;

use App\Enums\ClientType;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'type', 'phone', 'phone_alt', 'email',
    'address', 'commune', 'wilaya', 'notes', 'created_by',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(Installation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('phone_alt', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function location(): string
    {
        return collect([$this->commune, $this->wilaya])
            ->filter()
            ->implode(', ');
    }

    /**
     * Montant total des devis acceptés par le client.
     */
    public function contractedAmount(): float
    {
        return (float) $this->quotes()->where('status', 'accepte')->sum('total');
    }

    /**
     * Total déjà encaissé auprès du client.
     */
    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * Reste à encaisser sur les devis acceptés.
     */
    public function balanceDue(): float
    {
        return round(max($this->contractedAmount() - $this->paidAmount(), 0), 2);
    }
}
