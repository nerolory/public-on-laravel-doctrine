<div class="article-admin">
    @component('admin.partials.page-header', [
        'heading' => __('admin.conflicts.title'),
        'intro' => __('admin.conflicts.intro'),
    ])@endcomponent
    @include('admin.partials.conflict-table', [
        'title' => __('admin.conflicts.articles'),
        'header' => __('admin.conflicts.article'),
        'rows' => $articles,
    ])
    @include('admin.partials.conflict-table', [
        'title' => __('admin.conflicts.comments'),
        'header' => __('admin.conflicts.comment'),
        'rows' => $comments,
    ])
</div>
