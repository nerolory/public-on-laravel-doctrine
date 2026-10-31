<div class="space-y-5">
    @include('admin.partials.flash')
    @include('admin.partials.create-button', ['href' => $createUrl, 'label' => __('admin.sections.create')])
    @if ($sections->isNotEmpty())
        <x-moonshine::table>
            <x-slot:thead><tr>
                <th>{{ __('admin.sections.columns.name') }}</th>
                <th>{{ __('admin.sections.columns.slug') }}</th>
                <th>{{ __('admin.sections.columns.articles') }}</th>
                <th><span class="sr-only">{{ __('admin.common.actions') }}</span></th>
            </tr></x-slot:thead>
            <x-slot:tbody>
                @foreach ($sections as $section)
                    <tr>
                        <td><span class="{{ $section->levelClass }}">{{ $section->displayName }}</span></td>
                        <td><code>{{ $section->slug }}</code></td>
                        <td>{{ $section->articleCount }}</td>
                        <td><div class="article-admin__action-buttons">
                            @include('admin.partials.icon-link', [
                                'href' => $section->editUrl,
                                'label' => __('admin.sections.edit_named', ['name' => $section->name]),
                                'title' => __('admin.sections.edit'),
                                'icon' => 'pencil-square',
                            ])
                            @include('admin.partials.icon-action-form', [
                                'action' => route('admin.sections.destroy', $section->id),
                                'method' => 'DELETE',
                                'buttonClass' => 'article-admin__icon article-admin__icon--danger',
                                'icon' => 'trash',
                                'ariaLabel' => __('admin.sections.delete_named', ['name' => $section->name]),
                                'title' => $section->deleteTitle,
                                'confirm' => $section->deleteConfirm,
                                'disabled' => ! $section->deletable,
                            ])
                        </div></td>
                    </tr>
                @endforeach
            </x-slot:tbody>
        </x-moonshine::table>
    @else <p>{{ __('admin.sections.empty') }}</p> @endif
</div>
