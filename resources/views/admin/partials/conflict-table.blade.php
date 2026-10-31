@include('admin.partials.section-title', ['title' => $title])
@if ($rows->isNotEmpty())
    <x-moonshine::table class="article-admin__table">
        <x-slot:thead><tr>
            <th>{{ $header }}</th>
            <th>{{ __('admin.conflicts.positive') }}</th>
            <th>{{ __('admin.conflicts.negative') }}</th>
            <th>{{ __('admin.conflicts.score') }}</th>
        </tr></x-slot:thead>
        <x-slot:tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>
                        @if ($row->excerpt === null)
                            <a href="{{ $row->articleUrl }}"
                                target="_blank"
                                rel="noopener">{{ $row->articleTitle }}</a>
                        @else
                            <span>{{ $row->excerpt }}</span><br>
                            <small>
                                <a href="{{ $row->articleUrl }}"
                                    target="_blank"
                                    rel="noopener">{{ $row->articleTitle }}</a>
                            </small>
                        @endif
                    </td>
                    <td>{{ $row->positive }}</td>
                    <td>{{ $row->negative }}</td>
                    <td><strong>{{ $row->conflict }}</strong></td>
                </tr>
            @endforeach
        </x-slot:tbody>
    </x-moonshine::table>
@else
    @include('admin.partials.empty-state', ['message' => __('admin.conflicts.empty')])
@endif
