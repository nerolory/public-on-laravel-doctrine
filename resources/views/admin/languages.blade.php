<div class="space-y-5">
    @include('admin.partials.flash')
    <p>{{ __('languages.intro') }} <a href="{{ $localizationSettingsUrl }}">{{ __('languages.mode_link') }}</a></p>
    @include('admin.partials.create-button', ['href' => $createUrl, 'label' => __('languages.create')])
    <x-moonshine::table>
        <x-slot:thead><tr>
            <th>{{ __('languages.fields.code') }}</th>
            <th>{{ __('languages.fields.name') }}</th>
            <th>{{ __('languages.fields.file') }}</th>
            <th>{{ __('languages.fields.status') }}</th>
            <th><span class="sr-only">{{ __('languages.actions') }}</span></th>
        </tr></x-slot:thead>
        <x-slot:tbody>
            @foreach ($languages as $language)
                <tr>
                    <td><code>{{ $language->code }}</code></td>
                    <td>{{ $language->name }}</td>
                    <td><code>lang/{{ $language->file }}</code></td>
                    <td>{{ $language->statusLabel }}</td>
                    <td><div class="article-admin__action-buttons">
                        @include('admin.partials.icon-link', [
                            'href' => $language->editUrl,
                            'label' => __('languages.edit_named', ['name' => $language->name]),
                            'title' => __('languages.edit'),
                            'icon' => 'pencil-square',
                        ])
                        @include('admin.partials.icon-action-form', [
                            'action' => route('admin.languages.destroy', $language->code),
                            'method' => 'DELETE',
                            'buttonClass' => 'article-admin__icon article-admin__icon--danger',
                            'icon' => 'trash',
                            'ariaLabel' => __('languages.delete_named', ['name' => $language->name]),
                            'title' => $language->deleteTitle,
                            'confirm' => __('languages.confirm_delete', ['name' => $language->name]),
                            'disabled' => ! $language->deletable,
                        ])
                    </div></td>
                </tr>
            @endforeach
        </x-slot:tbody>
    </x-moonshine::table>
</div>
