@props(['status'])

<span {{ $attributes->merge(['class' => 'badge-'.$status->color()]) }}>{{ $status->label() }}</span>
