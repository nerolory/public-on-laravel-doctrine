{{--
    Скрытая форма действия модерации над одной записью.
    Поля $fields (имя => значение) добавляются как hidden-поля.
--}}
<form id="{{ $formId }}" method="post" action="{{ $action }}">@csrf
    <input type="hidden" name="ids[]" value="{{ $id }}">
    @foreach ($fields as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
</form>
