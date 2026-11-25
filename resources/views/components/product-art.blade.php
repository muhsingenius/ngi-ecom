@props(['name'])

{{-- Original flat product illustrations. Each carries a small sunburst mark standing in for a customer's print. --}}
@php
    $mark = '<g class="mark" fill="#fff" stroke="#fff" stroke-width="3" stroke-linecap="round"><circle r="9" stroke="none"/><path d="M0-18v-6M0 18v6M-18 0h-6M18 0h6M-13-13l-4-4M13 13l4 4M-13 13l-4 4M13-13l4-4" fill="none"/></g>';
@endphp

<svg {{ $attributes }} viewBox="0 0 200 200" fill="none" role="img" aria-label="{{ ucfirst($name) }} illustration">
    @switch($name)
        @case('tshirt')
            <path d="M70 28 30 48 10 88l30 12 12-14v90h96V86l12 14 30-12-20-40-40-20c-6 14-18 20-30 20S76 42 70 28Z" fill="#2a4bf0" />
            <path d="M70 28c6 14 18 20 30 20s24-6 30-20" stroke="#1f38c4" stroke-width="5" fill="none" />
            <g transform="translate(100 112) scale(1.2)">{!! $mark !!}</g>
            @break

        @case('hoodie')
            <path d="M62 40c0-18 18-28 38-28s38 10 38 28l32 18 16 60-28 8-10-18v74H52v-74l-10 18-28-8 16-60 32-18Z" fill="#ffb020" />
            <path d="M80 40c0 12 10 22 20 22s20-10 20-22c0-10-10-16-20-16S80 30 80 40Z" fill="#c97f00" />
            <path d="M72 138h56l8 24H64l8-24Z" fill="#f29b00" />
            <g transform="translate(100 102) scale(0.95)">{!! $mark !!}</g>
            @break

        @case('mug')
            <path d="M140 72h12a20 20 0 0 1 0 44h-12" stroke="#d9dce6" stroke-width="12" stroke-linecap="round" />
            <rect x="48" y="50" width="96" height="108" rx="12" fill="#fff" stroke="#d9dce6" stroke-width="3" />
            <ellipse cx="96" cy="50" rx="48" ry="7" fill="#eceef5" stroke="#d9dce6" stroke-width="3" />
            <g transform="translate(96 106) scale(1.35)"><g class="mark" fill="#2a4bf0" stroke="#2a4bf0" stroke-width="3" stroke-linecap="round"><circle r="9" stroke="none"/><path d="M0-18v-6M0 18v6M-18 0h-6M18 0h6M-13-13l-4-4M13 13l4 4M-13 13l-4 4M13-13l4-4" fill="none"/></g></g>
            @break

        @case('cap')
            <path d="M38 124c0-46 28-72 64-72s58 28 58 72H38Z" fill="#12141c" />
            <path d="M36 122h112c28 0 40 12 22 18H58c-24 0-34-8-22-18Z" fill="#2a2f3f" />
            <circle cx="98" cy="52" r="6" fill="#2a2f3f" />
            <g transform="translate(98 94) scale(0.8)">{!! $mark !!}</g>
            @break

        @case('tote')
            <path d="M74 74V58a26 26 0 0 1 52 0v16" stroke="#cdbb97" stroke-width="8" stroke-linecap="round" />
            <path d="M42 70h116l8 112H34l8-112Z" fill="#efe3c8" />
            <g transform="translate(100 128) scale(1.3)"><g class="mark" fill="#12141c" stroke="#12141c" stroke-width="3" stroke-linecap="round"><circle r="9" stroke="none"/><path d="M0-18v-6M0 18v6M-18 0h-6M18 0h6M-13-13l-4-4M13 13l4 4M-13 13l-4 4M13-13l4-4" fill="none"/></g></g>
            @break

        @case('stickers')
            <circle cx="80" cy="104" r="50" fill="#2a4bf0" />
            <rect x="96" y="40" width="72" height="72" rx="16" fill="#ffb020" transform="rotate(12 132 76)" />
            <circle cx="140" cy="146" r="30" fill="#12141c" />
            <g transform="translate(80 104)">{!! $mark !!}</g>
            @break
    @endswitch
</svg>
