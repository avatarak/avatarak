<div dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}" style="font-family:Tahoma,Arial,sans-serif">
    <p>{{ __('app.mail_body') }}</p>
    <p><a href="{{ $url }}">{{ $url }}</a></p>
    <p>{{ __('app.mail_ignore') }}</p>
</div>
