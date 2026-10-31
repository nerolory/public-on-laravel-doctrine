<div class="space-y-5">
    @include('admin.partials.flash')
    <p>{{ __('legal.admin.intro') }}</p>
    <p>
        {{ __('legal.admin.current_region', ['region' => $currentRegionLabel]) }}
        <a href="{{ $regionSettingsUrl }}">{{ __('legal.admin.change_region') }}</a>
    </p>
    <x-moonshine::table>
        <x-slot:thead><tr>
            <th>{{ __('legal.admin.document') }}</th>
            <th>{{ __('legal.admin.region') }}</th>
            <th>{{ __('legal.admin.current') }}</th>
            <th><span class="sr-only">{{ __('admin.common.actions') }}</span></th>
        </tr></x-slot:thead>
        <x-slot:tbody>
            @foreach ($rows as $row)
                <tr>
                    <td><strong>{{ $row->typeLabel }}</strong></td>
                    <td>{{ $row->regionLabel }}</td>
                    <td><span>{{ $row->versionLabel }}</span></td>
                    <td><div class="article-admin__action-buttons legal-documents__actions">
                        @include('admin.partials.icon-link', [
                            'href' => $row->editUrl,
                            'label' => __('legal.admin.edit_named', [
                                'document' => $row->typeLabel,
                                'region' => $row->regionLabel,
                            ]),
                            'title' => __('legal.admin.edit'),
                            'icon' => 'pencil-square',
                        ])
                    </div></td>
                </tr>
            @endforeach
        </x-slot:tbody>
    </x-moonshine::table>
</div>
