@extends('docs.layout')

@php
    $x = $rtl ? [
        'title' => 'آزمایش API', 'lead' => 'درخواست واقعی به API بفرستید و پاسخ را ببینید، بدون نوشتن کد. کلید فقط از همین مرورگر به همین سرور ارسال می‌شود.',
        'key' => 'کلید API', 'keyHelp' => 'برای مسیرهای «نیازمند کلید». یک کلید آزمایشی بسازید؛ در هیچ‌جای دیگری ارسال یا ذخیره نمی‌شود.', 'remember' => 'در این تب یادآوری شود', 'send' => 'ارسال', 'sending' => 'در حال ارسال…', 'response' => 'پاسخ', 'curl' => 'معادل curl', 'copy' => 'کپی',
        'needKey' => 'این مسیر کلید لازم دارد. کلید را بالا وارد کنید.', 'params' => 'پارامترها', 'body' => 'بدنه (JSON)', 'required' => 'الزامی', 'secured' => 'نیازمند کلید', 'public' => 'بدون کلید', 'headers' => 'هدرها', 'error' => 'درخواست انجام نشد',
        'getKey' => 'کلید بسازید', 'ops' => 'عملیات', 'loading' => 'در حال بارگذاری…', 'spec' => 'فایل OpenAPI',
    ] : [
        'title' => 'API explorer', 'lead' => 'Send real requests to the API and see the answer, without writing code. Your key goes only from this browser to this server.',
        'key' => 'API key', 'keyHelp' => 'For the “needs a key” endpoints. Make a test key; it is never sent or stored anywhere else.', 'remember' => 'Remember in this tab', 'send' => 'Send', 'sending' => 'Sending…', 'response' => 'Response', 'curl' => 'curl equivalent', 'copy' => 'Copy',
        'needKey' => 'This endpoint needs a key. Enter it above.', 'params' => 'Parameters', 'body' => 'Body (JSON)', 'required' => 'required', 'secured' => 'needs a key', 'public' => 'no key', 'headers' => 'Headers', 'error' => 'The request failed',
        'getKey' => 'Create a key', 'ops' => 'Operations', 'loading' => 'Loading…', 'spec' => 'OpenAPI file',
    ];
@endphp


@section('content')
    <article class="docs-prose">
        <p class="text-sm text-muted">REST API</p>
        <h1>{{ $x['title'] }}</h1>
        <p class="docs-lead">{{ $x['lead'] }}</p>
    </article>

    <div id="explorer" class="mt-8" data-spec="{{ route('docs.openapi') }}" data-i18n='@json($x)' data-origin="{{ rtrim(config('app.url'), '/') }}" data-account-url="{{ url('/') }}">
        <p class="text-muted">{{ $x['loading'] }}</p>
    </div>
@endsection
