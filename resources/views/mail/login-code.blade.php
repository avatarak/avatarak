@extends('mail.layout', ['title' => __('app.mail_code_title'), 'preheader' => __('app.code_subject', ['code' => $code, 'app' => config('brand.name_en')])])
@section('content')
    <p style="margin:0;">{{ __('app.code_body', ['minutes' => $minutes]) }}</p>
    <p dir="ltr" style="margin:20px 0;padding:16px;background:#F3F5FA;border-radius:10px;text-align:center;font-family:'Courier New',monospace;font-size:34px;font-weight:700;letter-spacing:10px;color:#101517;">{{ $code }}</p>
    <p style="margin:0;color:#6B7280;font-size:13px;">{{ __('app.code_ignore') }}</p>
@endsection
