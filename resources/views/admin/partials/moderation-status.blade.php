{{--
    Статус записи модерации с причиной отклонения.
    Кнопка сохранения в noscript выводится, когда задан $saveLabel.
--}}
@include('admin.partials.status-select', [
    'formId' => $formId,
    'ariaLabel' => $ariaLabel,
    'status' => $status,
    'options' => $options,
])
@isset($saveLabel)
    <noscript><button class="btn btn-secondary" type="submit" form="{{ $formId }}">{{ $saveLabel }}</button></noscript>
@endisset
@if ($rejectionReason !== null)
    <p class="article-admin__reason"><span>{{ __('admin.articles.reason_label') }}</span> {{ $rejectionReason }}</p>
@endif
