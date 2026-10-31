<a href="{{ $href }}"
    @class(['btn', 'btn-primary' => $active, 'btn-secondary' => ! $active])
    @if ($active) aria-current="page" @endif>{{ $label }}</a>
