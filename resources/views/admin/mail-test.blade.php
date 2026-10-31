<form method="post" action="{{ route('admin.settings.mail-test') }}" class="space-y-2">
    @csrf
    <p>{{ __('mail.test.hint', ['email' => $email]) }}</p>
    <button type="submit" class="btn btn-secondary">{{ __('mail.test.button') }}</button>
</form>
