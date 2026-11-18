@extends('mail.layout', ['title' => __('app.notice_'.$kind.'_title'), 'preheader' => __('app.notice_'.$kind.'_subject', ['app' => config('brand.name')])])
@section('content')
    <p style="margin:0;">{{ __('app.notice_'.$kind.'_body', ['reason' => $context['reason'] ?? '']) }}</p>

    @if(! empty($context['when']) || ! empty($context['device']) || ! empty($context['ip']))
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0 0;width:100%;background:#F3F5FA;border-radius:10px;font-size:14px;">
            @foreach([[__('app.notice_when'), $context['when'] ?? null], [__('app.notice_device'), $context['device'] ?? null], ['IP', $context['ip'] ?? null]] as [$label, $value])
                @if($value)<tr><td style="padding:8px 14px;color:#6B7280;white-space:nowrap;">{{ $label }}</td><td dir="ltr" style="padding:8px 14px;font-weight:600;text-align:{{ app()->getLocale() === 'fa' ? 'right' : 'left' }};">{{ $value }}</td></tr>@endif
            @endforeach
        </table>
    @endif

    @if(in_array($kind, ['new_device', 'password_changed', 'two_factor_disabled']))
        <p style="margin:18px 0 0;padding:12px 14px;background:#FFF7ED;border-radius:10px;color:#9A3412;font-size:14px;">{{ __('app.notice_not_you') }}</p>
    @endif
@endsection
