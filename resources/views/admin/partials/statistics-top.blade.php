<section>
    @include('admin.partials.section-title', ['title' => $title])
    @forelse ($rows as $row)
        <p>@if ($row->url !== null)<a href="{{ $row->url }}" target="_blank"
                rel="noopener">{{ $row->label }}</a>@else{{ $row->label }}@endif{{
                __('admin.statistics.row_count', ['count' => $row->count]) }}</p>
    @empty
        @include('admin.partials.empty-state', ['message' => __('admin.statistics.empty')])
    @endforelse
</section>
