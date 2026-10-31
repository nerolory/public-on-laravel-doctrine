<div class="article-admin">
    @include('admin.partials.flash')

    @component('admin.partials.page-header', [
        'heading' => __('admin.articles.heading'),
        'intro' => __('admin.articles.subheading'),
    ])
        @include('admin.partials.create-button', ['href' => $createUrl, 'label' => __('admin.articles.create')])
    @endcomponent

    @if ($articles->isNotEmpty())
        <form method="post" action="{{ route('admin.articles.moderate') }}"
            data-confirm-delete="{{ __('admin.articles.confirm_bulk_delete') }}"
            data-moderation-bulk>
            @csrf
            <input type="hidden" name="reason">
            <div class="article-admin__toolbar">
                <label class="article-admin__select-all">
                    <x-moonshine::form.input type="checkbox" data-bulk-select-all />
                    <span>{{ __('admin.articles.select_all') }}</span>
                </label>
                @include('admin.partials.article-bulk-actions', [
                    'id' => 'article-bulk-action',
                    'name' => 'action',
                    'actions' => $bulkActions,
                ])
            </div>
            <x-moonshine::table class="article-admin__table">
                <x-slot:thead><tr>
                    <th><span class="sr-only">{{ __('admin.articles.select') }}</span></th>
                    <th>{{ __('admin.articles.column_article') }}</th>
                    <th>{{ __('admin.articles.column_author') }}</th>
                    <th>{{ __('admin.articles.column_status') }}</th>
                    <th><span class="sr-only">{{ __('admin.common.actions') }}</span></th>
                </tr></x-slot:thead>
                <x-slot:tbody>
                    @foreach ($articles as $article)
                        <tr class="article-admin__row">
                            <td class="article-admin__check">
                                <x-moonshine::form.input
                                    class="article-select"
                                    type="checkbox"
                                    name="ids[]"
                                    value="{{ $article->id }}"
                                    data-bulk-item
                                    aria-label="{{ __('admin.articles.select_named', ['title' => $article->title]) }}"
                                />
                            </td>
                            <td class="article-admin__content">
                                <div class="article-admin__text">
                                    <strong title="{{ $article->title }}">{{ $article->title }}</strong>
                                    <p>{{ $article->excerpt }}</p>
                                </div>
                            </td>
                            <td class="article-admin__author" data-label="{{ __('admin.articles.column_author') }}">
                                {{ $article->authorName }}
                                @if ($article->sections !== '')<br><small>{{ $article->sections }}</small>@endif
                                <br><small>{{ __('admin.articles.views', ['count' => $article->viewCount]) }}</small>
                            </td>
                            <td class="article-admin__status" data-label="{{ __('admin.articles.column_status') }}">
                                @include('admin.partials.moderation-status', [
                                    'formId' => 'status-article-'.$article->id,
                                    'ariaLabel' => __('admin.articles.status_named', ['title' => $article->title]),
                                    'status' => $article->status,
                                    'options' => $article->statusOptions,
                                    'rejectionReason' => $article->rejectionReason,
                                    'saveLabel' => __('admin.articles.save_status'),
                                ])
                            </td>
                            <td class="article-admin__actions">
                                <div class="article-admin__action-buttons">
                                    @include('admin.partials.icon-link', [
                                        'href' => $article->editUrl,
                                        'label' => __('admin.articles.edit_named', ['title' => $article->title]),
                                        'title' => __('admin.common.edit'),
                                        'icon' => 'pencil-square',
                                    ])
                                    <button class="article-admin__icon article-admin__icon--danger"
                                        type="submit"
                                        form="delete-article-{{ $article->id }}"
                                        aria-label="{{ __('admin.articles.delete_named', [
                                            'title' => $article->title,
                                        ]) }}"
                                        title="{{ __('admin.common.delete') }}"
                                        data-confirm="{{ __('admin.articles.confirm_delete') }}"
                                    ><x-moonshine::icon icon="trash" /></button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:tbody>
            </x-moonshine::table>
            <div class="article-admin__toolbar article-admin__toolbar--bottom">
                @include('admin.partials.article-bulk-actions', [
                    'id' => 'article-bulk-action-bottom',
                    'name' => null,
                    'actions' => $bulkActions,
                ])
            </div>
        </form>
        @include('admin.partials.moderation-forms', [
            'items' => $articles,
            'idKey' => 'id',
            'action' => route('admin.articles.moderate'),
            'forms' => [
                ['prefix' => 'status-article', 'fields' => ['reason' => '']],
                ['prefix' => 'delete-article', 'fields' => ['action' => 'delete']],
            ],
            'reasonLength' => $reasonLength,
            'reasonRows' => $reasonRows,
        ])
    @else
        @include('admin.partials.empty-state', ['message' => __('admin.articles.empty')])
    @endif
</div>
