<div class="article-admin statistics">
    @component('admin.partials.page-header', [
        'heading' => __('admin.statistics.title'),
        'intro' => __('admin.statistics.intro'),
    ])
        <nav class="statistics__periods" aria-label="{{ __('admin.statistics.period') }}">
            @foreach ($periods as $period)
                @include('admin.partials.switch-link', [
                    'href' => $period->url,
                    'label' => $period->label,
                    'active' => $period->active,
                ])
            @endforeach
        </nav>
    @endcomponent
    <dl class="statistics__totals">
        @foreach ($totals as $total)
            <div><dt>{{ $total->label }}</dt><dd>{{ $total->value }}</dd></div>
        @endforeach
    </dl>
</div>
