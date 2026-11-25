<x-mail::message>
# New print job

Order **{{ $order->number }}** is ready for production.

- Items: {{ $order->quantity }}
- Due: {{ $order->due_date?->format('D j M Y') ?? 'as soon as possible' }}
- {{ $order->isDelivery() ? 'Delivery: '.$order->zone_name : 'Pickup: '.$order->pickup_name }}

<x-mail::button :url="$url">
Open the job sheet
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
