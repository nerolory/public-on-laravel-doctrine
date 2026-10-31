<div class="article-admin">
    @component('admin.partials.page-header', [
        'heading' => __('admin.analytics.title'),
        'intro' => __('admin.analytics.intro'),
    ])@endcomponent
    @forelse ($reports as $section)
        @include('admin.partials.section-title', ['title' => $section->title])
        @if ($section->report->error !== null)
            <x-moonshine::alert type="error">
                {{ __('admin.analytics.error', ['error' => $section->report->error]) }}
            </x-moonshine::alert>
        @else
            <p>
                {{ __('admin.analytics.totals', [
                    'visits' => $section->report->visits,
                    'users' => $section->report->users,
                    'pageviews' => $section->report->pageviews,
                ]) }}
            </p>
            <x-moonshine::table class="article-admin__table">
                <x-slot:thead><tr>
                    <th>{{ __('admin.analytics.date') }}</th>
                    <th>{{ __('admin.analytics.visits') }}</th>
                    <th>{{ __('admin.analytics.users') }}</th>
                    <th>{{ __('admin.analytics.pageviews') }}</th>
                </tr></x-slot:thead>
                <x-slot:tbody>
                    @foreach ($section->report->days as $day)
                        <tr>
                            <td>{{ $day->date }}</td>
                            <td>{{ $day->visits }}</td>
                            <td>{{ $day->users }}</td>
                            <td>{{ $day->pageviews }}</td>
                        </tr>
                    @endforeach
                </x-slot:tbody>
            </x-moonshine::table>
        @endif
    @empty
        @include('admin.partials.empty-state', ['message' => __('admin.analytics.empty')])
    @endforelse
</div>
