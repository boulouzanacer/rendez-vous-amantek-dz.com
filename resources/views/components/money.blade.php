@props([
    'amount' => 0,
    'format' => 'format',
])

@php
    use App\Support\Money;

    $value = match ($format) {
        'short' => Money::short($amount),
        'compact' => Money::compact($amount),
        default => Money::format($amount),
    };
@endphp

<span {{ $attributes }}>{{ $value }}</span>
