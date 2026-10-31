{{--
    Выбор массового действия. Верхний список отправляет форму (name="action"),
    нижний синхронизируется с ним модулем bulk-selection.
--}}
<label class="article-admin__bulk-label" for="{{ $id }}">{{ __('admin.articles.bulk_action') }}</label>
<x-moonshine::form.select
    :native="true"
    class="article-admin__bulk-select"
    id="{{ $id }}"
    :name="$name"
    data-bulk-action
    disabled
    required
><x-slot:options>
    <option value="" selected disabled>{{ __('admin.articles.choose_action') }}</option>
    @foreach ($actions as $action)
        <option value="{{ $action->value }}">{{ $action->label }}</option>
    @endforeach
</x-slot:options></x-moonshine::form.select>
<button class="btn btn-secondary" type="submit" data-bulk-submit disabled>{{ __('admin.articles.apply') }}</button>
