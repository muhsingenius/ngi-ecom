<x-mail::message>
# {{ $headline }}

Order **{{ $order->number }}**

@foreach ($lines as $line)
{{ $line }}

@endforeach
<x-mail::table>
| Item | Qty | Total |
| :--- | :-: | ----: |
@foreach ($order->lines as $line)
| {{ $line->product_name }} ({{ $line->quality }}) | {{ $line->quantity }} | {{ \App\Support\Money::format(\App\Support\Money::toMinor($line->line_total), $order->currency) }} |
@endforeach
@if ((float) $order->delivery_fee > 0)
| Delivery ({{ $order->zone_name }}) | | {{ \App\Support\Money::format(\App\Support\Money::toMinor($order->delivery_fee), $order->currency) }} |
@endif
| **Total** | | **{{ \App\Support\Money::format(\App\Support\Money::toMinor($order->total), $order->currency) }}** |
</x-mail::table>

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
