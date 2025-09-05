@component('mail::message')
# New Lead Submitted

A new lead has been submitted from the public presenter.

@component('mail::panel')
- Name: {{ $lead->full_name ?: ($lead->first_name || $lead->last_name ? trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')) : '—') }}
- Email: {{ $lead->email ?? '—' }}
- Phone: {{ $lead->phone ?? '—' }}
- Company: {{ $lead->company ?? '—' }}
- Address: {{ $lead->address ?? '—' }}
- Notes: {{ $lead->notes ?? '—' }}
@endcomponent

@if(!empty($lead->metadata))
### Additional Details
@component('mail::panel')
@foreach($lead->metadata as $key => $val)
- {{ $key }}: {{ is_scalar($val) ? $val : json_encode($val) }}
@endforeach
@endcomponent
@endif

Thanks,
{{ config('app.name') }}
@endcomponent
