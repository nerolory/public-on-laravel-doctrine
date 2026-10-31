<div class="article-admin">
    @include('admin.partials.flash')

    @component('admin.partials.page-header', [
        'heading' => __('admin.albums.heading'),
        'intro' => __('admin.albums.subheading'),
    ])@endcomponent

    @if ($albums->isNotEmpty())
        <x-moonshine::table class="article-admin__table">
            <x-slot:thead><tr>
                <th>{{ __('admin.albums.column_album') }}</th>
                <th>{{ __('admin.articles.column_author') }}</th>
                <th>{{ __('admin.articles.column_status') }}</th>
                <th><span class="sr-only">{{ __('admin.common.actions') }}</span></th>
            </tr></x-slot:thead>
            <x-slot:tbody>
                @foreach ($albums as $album)
                    <tr class="article-admin__row">
                        <td class="article-admin__content">
                            <div class="article-admin__text">
                                <strong title="{{ $album->title }}">{{ $album->title }}</strong>
                                <p>{{ $album->photosLabel }}</p>
                            </div>
                        </td>
                        <td class="article-admin__author" data-label="{{ __('admin.articles.column_author') }}">
                            {{ $album->ownerName }}
                        </td>
                        <td class="article-admin__status" data-label="{{ __('admin.articles.column_status') }}">
                            @include('admin.partials.moderation-status', [
                                'formId' => 'status-album-'.$album->uuid,
                                'ariaLabel' => __('admin.albums.status_named', ['title' => $album->title]),
                                'status' => $album->status,
                                'options' => $album->statusOptions,
                                'rejectionReason' => $album->rejectionReason,
                            ])
                        </td>
                        <td class="article-admin__actions">
                            <div class="article-admin__action-buttons">
                                @include('admin.partials.icon-link', [
                                    'href' => $album->openUrl,
                                    'label' => __('admin.albums.open_named', ['title' => $album->title]),
                                    'title' => __('admin.albums.open'),
                                    'icon' => 'eye',
                                    'external' => true,
                                ])
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:tbody>
        </x-moonshine::table>
        @include('admin.partials.moderation-forms', [
            'items' => $albums,
            'idKey' => 'uuid',
            'action' => route('admin.albums.moderate'),
            'forms' => [['prefix' => 'status-album', 'fields' => ['reason' => '']]],
            'reasonLength' => $reasonLength,
            'reasonRows' => $reasonRows,
        ])
    @else
        @include('admin.partials.empty-state', ['message' => __('admin.albums.empty')])
    @endif
</div>
