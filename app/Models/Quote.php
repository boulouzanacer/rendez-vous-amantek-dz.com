<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\QuoteStatus;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'number', 'client_id', 'appointment_id', 'issued_at', 'valid_until', 'status',
    'subtotal', 'discount_type', 'discount_value', 'discount_amount',
    'tax_rate', 'tax_amount', 'total', 'notes', 'terms', 'created_by',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'discount_type' => DiscountType::class,
            'issued_at' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $quote): void {
            $quote->number ??= self::nextNumber();
        });
    }

    /**
     * Génère le numéro suivant au format DEV-AAAA-0001.
     */
    public static function nextNumber(): string
    {
        $prefix = 'DEV-'.now()->year.'-';
        $last = self::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) mb_substr($last, mb_strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(Installation::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('number', 'like', "%{$term}%")
                    ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$term}%"));
            });
        });
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return $query->when(filled($status), fn (Builder $query): Builder => $query->where('status', $status));
    }

    /**
     * Recalcule le sous-total, la remise, la TVA et le total à partir des lignes.
     */
    public function recalculateTotals(): void
    {
        $subtotal = round((float) $this->items()->sum('total'), 2);

        $discount = match ($this->discount_type) {
            DiscountType::Percent => round($subtotal * ((float) $this->discount_value / 100), 2),
            DiscountType::Fixed => round((float) $this->discount_value, 2),
            null => 0.0,
        };

        $discount = min($discount, $subtotal);
        $taxable = $subtotal - $discount;
        $tax = round($taxable * ((float) $this->tax_rate / 100), 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => round($taxable + $tax, 2),
        ])->save();
    }

    public function isExpired(): bool
    {
        return $this->valid_until->isPast() && $this->status === QuoteStatus::Envoye;
    }

    public function daysRemaining(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->valid_until, false);
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function balanceDue(): float
    {
        return round(max((float) $this->total - $this->paidAmount(), 0), 2);
    }
}
