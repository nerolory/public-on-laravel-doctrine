<div class="space-y-4">
    @include('admin.partials.flash')
    <nav class="flex flex-wrap gap-2" aria-label="{{ __('settings.groups_label') }}">
        @foreach ($tabs as $tab)
            @include('admin.partials.switch-link', [
                'href' => $tab->url,
                'label' => $tab->label,
                'active' => $tab->active,
            ])
        @endforeach
    </nav>
</div>
