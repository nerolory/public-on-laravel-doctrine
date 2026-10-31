<form method="post" action="{{ route('admin.complaints.resolve') }}">@csrf
    <input type="hidden" name="target" value="{{ $target }}">
    <input type="hidden" name="id" value="{{ $id }}">
    <input type="hidden" name="action" value="{{ $action }}">
    <button class="{{ $buttonClass }}" type="submit"
        @if ($confirm !== null) data-confirm="{{ $confirm }}" @endif>{{ $label }}</button>
</form>
