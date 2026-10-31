<form method="post" action="{{ route('admin.settings.sitemap') }}" class="space-y-2">
    @csrf
    <p>
        {{ __('settings.sitemap.hint') }}
        <a href="{{ route('sitemap') }}" target="_blank" rel="noopener">/sitemap.xml</a>
    </p>
    <button type="submit" class="btn btn-secondary">{{ __('settings.sitemap.button') }}</button>
</form>
