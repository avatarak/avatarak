<div dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}" style="font-family:Tahoma,Arial,sans-serif">
    <p>{{ __('app.code_body', ['minutes' => $minutes]) }}</p>
    <p dir="ltr" style="font-size:32px;letter-spacing:8px;font-weight:bold;margin:16px 0">{{ $code }}</p>
    <p>{{ __('app.code_ignore') }}</p>
</div>
