<x-moonshine::form.select
    :native="true"
    class="article-admin__status-select article-admin__status-select--{{ $status }}"
    name="action"
    form="{{ $formId }}"
    aria-label="{{ $ariaLabel }}"
    data-current="{{ $status }}"
    data-moderation-status
><x-slot:options>
    @foreach ($options as $option)
        <option value="{{ $option->value }}" @selected($option->selected)>{{ $option->label }}</option>
    @endforeach
</x-slot:options></x-moonshine::form.select>
