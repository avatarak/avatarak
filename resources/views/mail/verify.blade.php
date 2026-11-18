@extends('mail.layout', ['title' => __('app.mail_verify_title'), 'preheader' => __('app.mail_body'), 'button' => ['url' => $url, 'label' => __('app.mail_verify_button')]])
@section('content')
    <p style="margin:0;">{{ __('app.mail_body') }}</p>
    <p style="margin:16px 0 0;color:#6B7280;font-size:13px;">{{ __('app.mail_ignore') }}</p>
@endsection
