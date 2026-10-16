<?php

namespace App\Models;

use Database\Factories\QuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quote_id', 'position', 'designation', 'description',
    'unit', 'quantity', 'unit_price', 'total',
])]
class QuoteItem extends Model
{
    /** @use HasFactory<QuoteItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Calcule le total de la ligne (quantité × prix unitaire).
     */
    public function computeTotal(): void
    {
        $this->total = round((float) $this->quantity * (float) $this->unit_price, 2);
    }
}
