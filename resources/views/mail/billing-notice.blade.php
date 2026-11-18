@extends('mail.layout', ['title' => __('app.billing_'.$kind.'_title', ['plan' => $d['plan_name'] ?? '']), 'preheader' => __('app.billing_'.$kind.'_subject', ['app' => config('brand.name'), 'plan' => $d['plan_name'] ?? '']), 'button' => ['url' => url('/'), 'label' => __('app.billing_open')]])
@section('content')
    <p style="margin:0;">{{ __('app.billing_'.$kind.'_body', ['plan' => $d['plan_name'] ?? '', 'until' => $d['until'] ?? '']) }}</p>
    @if($kind === 'receipt')
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0 0;width:100%;background:#F3F5FA;border-radius:10px;font-size:14px;">
            @foreach([[__('app.billing_plan'), $d['plan_name'] ?? ''], [__('app.billing_amount'), $d['amount'] ?? ''], [__('app.billing_until'), $d['until'] ?? ''], [__('app.billing_ref'), $d['ref_id'] ?? '']] as [$label, $value])
                @if($value !== '')<tr><td style="padding:8px 14px;color:#6B7280;white-space:nowrap;">{{ $label }}</td><td style="padding:8px 14px;font-weight:600;">{{ $value }}</td></tr>@endif
            @endforeach
        </table>
    @endif
@endsection
