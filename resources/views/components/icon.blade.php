@props(['name'])

<svg {{ $attributes->class('size-5 shrink-0') }} fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
    @switch($name)
        @case('upload')
            <path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M4 16v2.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V16" />
            @break
        @case('type')
            <path d="M5 7V5h14v2M12 5v14m-3 0h6" />
            @break
        @case('eye')
            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
            <circle cx="12" cy="12" r="3" />
            @break
        @case('pencil')
            <path d="m4 20 1-4L16.5 4.5a2 2 0 0 1 3 3L8 19l-4 1Zm9-13 3 3" />
            @break
        @case('star')
            <path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.8 6.8 19.6l1-5.8-4.3-4.1 5.9-.9L12 3.5Z" />
            @break
        @case('truck')
            <path d="M3 6.5h11v9H3zM14 9.5h4l3 3v3h-7M7 18.5a1.5 1.5 0 1 0 0-.01M17 18.5a1.5 1.5 0 1 0 0-.01" />
            @break
        @case('bolt')
            <path d="M13 3 5 13.5h6L10 21l9-11h-6.2L13 3Z" />
            @break
        @case('shield')
            <path d="M12 3.5 5 6v5.5c0 4.4 2.9 7.6 7 9 4.1-1.4 7-4.6 7-9V6l-7-2.5Zm-3 8.5 2.2 2.2L15.5 10" />
            @break
        @case('check')
            <path d="m5 12.5 4.5 4.5L19 7.5" />
            @break
        @case('box')
            <path d="m12 3 8 4v10l-8 4-8-4V7l8-4Zm0 8 8-4m-8 4v10m0-10L4 7" />
            @break
        @case('chat')
            <path d="M4 5.5h16v10H9.5L5 19v-3.5H4z" />
            @break
        @case('arrow')
            <path d="M5 12h14m0 0-5-5m5 5-5 5" />
            @break
        @case('user')
            <circle cx="12" cy="8.5" r="3.5" />
            <path d="M5 20c.8-3.6 3.5-5.5 7-5.5s6.2 1.9 7 5.5" />
            @break
        @case('bag')
            <path d="M5.5 8h13l-1 12h-11l-1-12Zm3.5 0V7a3 3 0 0 1 6 0v1" />
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break
        @case('close')
            <path d="m6 6 12 12M18 6 6 18" />
            @break
    @endswitch
</svg>
