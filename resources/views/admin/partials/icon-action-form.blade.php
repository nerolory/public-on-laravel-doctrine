<form method="post" action="{{ $action }}">
    @csrf
    @if ($method !== null)
        @method($method)
    @endif
    <button class="{{ $buttonClass }}"
        type="submit"
        @disabled($disabled)
        aria-label="{{ $ariaLabel }}"
        title="{{ $title }}"
        @if ($confirm !== null) data-confirm="{{ $confirm }}" @endif><x-moonshine::icon :icon="$icon" /></button>
</form>
