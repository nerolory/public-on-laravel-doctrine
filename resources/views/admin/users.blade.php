<div class="user-admin">
    @include('admin.partials.flash')
    <div class="user-admin__header">
        <h2>{{ __('admin.users.heading') }}</h2>
        <p>{{ __('admin.users.subheading') }}</p>
    </div>
    @if ($users->isNotEmpty())
        <x-moonshine::table class="user-admin__table">
            <x-slot:thead><tr>
                <th>{{ __('admin.users.column_user') }}</th>
                <th>{{ __('admin.users.column_roles') }}</th>
                <th>{{ __('admin.users.column_status') }}</th>
                <th><span class="sr-only">{{ __('admin.common.actions') }}</span></th>
            </tr></x-slot:thead>
            <x-slot:tbody>
                @foreach ($users as $user)
                    <tr class="user-admin__row">
                        <td class="user-admin__identity">
                            <strong>{{ $user->name }}</strong><span>{{ $user->email }}</span>
                        </td>
                        <td class="user-admin__roles" data-label="{{ __('admin.users.column_roles') }}">
                            <span>{{ $user->rolesLabel }}</span>
                            @if ($user->manageable)
                                <details class="user-admin__role-menu">
                                    <summary>{{ __('admin.users.change_roles') }}</summary>
                                    <div class="user-admin__role-options">
                                        @foreach ($user->roleToggles as $toggle)
                                            <form method="post" action="{{ route('admin.users.role', $user->id) }}">
                                                @csrf
                                                <input type="hidden" name="role" value="{{ $toggle->code }}">
                                                <input type="hidden" name="operation" value="{{ $toggle->operation }}">
                                                <label class="user-admin__role-option">
                                                    <x-moonshine::form.input
                                                        type="checkbox"
                                                        :checked="$toggle->checked"
                                                        aria-label="{{ __('admin.users.toggle_aria', [
                                                            'label' => $toggle->label,
                                                            'name' => $user->name,
                                                        ]) }}"
                                                        data-auto-submit
                                                    />
                                                    <span>{{ $toggle->label }}</span>
                                                </label>
                                                <noscript>
                                                    <button
                                                        class="btn btn-secondary"
                                                        type="submit"
                                                    >{{ $toggle->submitLabel }}</button>
                                                </noscript>
                                            </form>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </td>
                        <td class="user-admin__status" data-label="{{ __('admin.users.column_status') }}">
                            @if ($user->banned)
                                <x-moonshine::badge color="red">{{ __('admin.users.banned') }}</x-moonshine::badge>
                            @else
                                <x-moonshine::badge color="green">{{ __('admin.users.active') }}</x-moonshine::badge>
                            @endif
                        </td>
                        <td class="user-admin__actions">
                            @if ($user->manageable)
                                <div class="user-admin__action-buttons">
                                    @include('admin.partials.icon-link', [
                                        'href' => $user->editUrl,
                                        'label' => __('admin.users.edit_named', ['name' => $user->name]),
                                        'title' => __('admin.common.edit'),
                                        'icon' => 'pencil-square',
                                    ])
                                    @include('admin.partials.icon-action-form', [
                                        'action' => route($user->banRoute, $user->id),
                                        'method' => null,
                                        'buttonClass' => 'article-admin__icon',
                                        'icon' => $user->banIcon,
                                        'ariaLabel' => $user->banAriaLabel,
                                        'title' => $user->banTitle,
                                        'confirm' => null,
                                        'disabled' => false,
                                    ])
                                    @include('admin.partials.icon-action-form', [
                                        'action' => route('admin.users.destroy', $user->id),
                                        'method' => 'DELETE',
                                        'buttonClass' => 'article-admin__icon article-admin__icon--danger',
                                        'icon' => 'trash',
                                        'ariaLabel' => __('admin.users.delete_named', ['name' => $user->name]),
                                        'title' => __('admin.common.delete'),
                                        'confirm' => __('admin.users.confirm_delete'),
                                        'disabled' => false,
                                    ])
                                </div>
                            @else
                                <span class="user-admin__unavailable">{{ __('admin.users.unavailable') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-slot:tbody>
        </x-moonshine::table>
    @else <p>{{ __('admin.users.empty') }}</p> @endif
</div>
