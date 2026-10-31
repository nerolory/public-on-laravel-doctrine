<div class="dashboard-metrics">
    <div class="dashboard-metrics__cards">
        @include('admin.partials.metric-card', [
            'label' => __('admin.dashboard.articles'),
            'value' => $metrics->articles->total,
        ])
        @include('admin.partials.metric-card', [
            'label' => __('admin.dashboard.users'),
            'value' => $metrics->users->total,
        ])
        @include('admin.partials.metric-card', [
            'label' => __('admin.dashboard.unpublished'),
            'value' => $metrics->articles->unpublished,
        ])
        @include('admin.partials.metric-card', [
            'label' => __('admin.dashboard.banned'),
            'value' => $metrics->users->banned,
        ])
    </div>
    <section class="dashboard-metrics__top">
        <h2>{{ __('admin.dashboard.top_authors') }}</h2>
        <p>{{ __('admin.dashboard.top_authors_note') }}</p>
        @forelse ($metrics->topAuthors as $author)
            <div class="dashboard-metrics__author">
                <span>{{ $author->name }}</span><strong>{{ $author->articleCount }}</strong>
            </div>
        @empty
            <p>{{ __('admin.dashboard.no_articles') }}</p>
        @endforelse
    </section>
    <p class="dashboard-metrics__note">{{ __('admin.dashboard.refresh_note') }}</p>
</div>
