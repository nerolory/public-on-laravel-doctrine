<div class="roles">
    <p>{{ __('admin.roles.intro') }}</p>
    <dl>
        @foreach ($roles as $role)
            <dt><strong>{{ __('roles.'.$role) }}</strong></dt><dd>{{ __('admin.roles.descriptions.'.$role) }}</dd>
        @endforeach
    </dl>
    <p>{{ __('admin.roles.outro') }}</p>
</div>
