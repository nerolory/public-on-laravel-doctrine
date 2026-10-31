{{-- Окно причины отклонения для списков модерации (статьи, альбомы). Логика — frontend/editor/admin/moderation.ts. --}}
<dialog id="reject-dialog" class="article-admin__dialog" aria-labelledby="reject-dialog-title">
    <form method="dialog">
        <h3 id="reject-dialog-title">{{ __('admin.articles.reject_title') }}</h3>
        <label for="reject-reason">{{ __('admin.articles.reason_prompt') }}</label>
        <textarea id="reject-reason" rows="{{ $reasonRows }}" maxlength="{{ $reasonLength }}" required></textarea>
        <div class="article-admin__dialog-buttons">
            <button class="btn btn-secondary" value="cancel" formnovalidate>{{ __('admin.articles.cancel') }}</button>
            <button class="btn btn-error" value="confirm">{{ __('admin.articles.actions.reject') }}</button>
        </div>
    </form>
</dialog>
