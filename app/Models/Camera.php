<?php

namespace App\Models;

use Database\Factories\CameraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reference', 'brand', 'is_active'])]
class Camera extends Model
{
    /** @use HasFactory<CameraFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function label(): string
    {
        return $this->brand
            ? $this->brand.' — '.$this->reference
            : (string) $this->reference;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('reference');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('reference', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%");
            });
        });
    }
}
