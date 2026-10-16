<?php

namespace App\Models;

use Database\Factories\InstallationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'installation_id', 'designation', 'serial_number', 'quantity', 'unit', 'notes',
])]
class InstallationItem extends Model
{
    /** @use HasFactory<InstallationItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }
}
