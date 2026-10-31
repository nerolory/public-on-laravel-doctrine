{{--
    Скрытые формы модерации для каждой записи списка и окно причины отклонения.
    Для каждой записи выводится по одной форме на каждый элемент $forms.
--}}
@foreach ($items as $item)
    @foreach ($forms as $form)
        @include('admin.partials.moderation-form', [
            'formId' => $form['prefix'].'-'.$item->{$idKey},
            'action' => $action,
            'id' => $item->{$idKey},
            'fields' => $form['fields'],
        ])
    @endforeach
@endforeach
@include('admin.partials.reject-dialog', ['reasonLength' => $reasonLength, 'reasonRows' => $reasonRows])
