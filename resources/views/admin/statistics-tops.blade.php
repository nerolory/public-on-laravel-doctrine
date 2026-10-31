<div class="article-admin statistics">
    <div class="statistics__tops">
        @include('admin.partials.statistics-top', [
            'title' => __('admin.statistics.top_articles'),
            'rows' => $topArticles,
        ])
        @include('admin.partials.statistics-top', [
            'title' => __('admin.statistics.top_referrers'),
            'rows' => $topReferrers,
        ])
    </div>
</div>
