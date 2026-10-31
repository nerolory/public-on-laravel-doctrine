<div class="article-admin">
    @include('admin.partials.flash')
    @component('admin.partials.page-header', [
        'heading' => __('admin.complaints.title'),
        'intro' => __('admin.complaints.intro'),
    ])@endcomponent
    @if ($groups->isNotEmpty())
        <x-moonshine::table class="article-admin__table">
            <x-slot:thead><tr>
                <th>{{ __('admin.complaints.target') }}</th>
                <th>{{ __('admin.complaints.count') }}</th>
                <th>{{ __('admin.complaints.reasons') }}</th>
                <th><span class="sr-only">{{ __('admin.common.actions') }}</span></th>
            </tr></x-slot:thead>
            <x-slot:tbody>
                @foreach ($groups as $group)
                    <tr>
                        <td>
                            <small>{{ __('admin.complaints.types.'.$group->target) }}</small><br>
                            @if ($group->labelUrl !== null)
                                <a href="{{ $group->labelUrl }}"
                                    target="_blank"
                                    rel="noopener">{{ $group->labelText }}</a>
                            @else
                                {{ $group->labelText }}
                            @endif
                            @foreach ($group->texts as $text)
                                <p class="article-admin__reason">
                                    {{ __('admin.complaints.quoted_text', ['text' => $text]) }}
                                </p>
                            @endforeach
                        </td>
                        <td><strong>{{ $group->total }}</strong></td>
                        <td>
                            @foreach ($group->reasonLines as $line)
                                {{ $line }}@unless ($loop->last)<br>@endunless
                            @endforeach
                        </td>
                        <td>
                            <div class="article-admin__action-buttons">
                                @include('admin.partials.complaint-action-form', [
                                    'target' => $group->target,
                                    'id' => $group->id,
                                    'action' => $hideAction,
                                    'label' => $group->hideLabel,
                                    'buttonClass' => 'btn btn-error',
                                    'confirm' => __('admin.complaints.confirm_hide'),
                                ])
                                @include('admin.partials.complaint-action-form', [
                                    'target' => $group->target,
                                    'id' => $group->id,
                                    'action' => $dismissAction,
                                    'label' => __('admin.complaints.dismiss'),
                                    'buttonClass' => 'btn btn-secondary',
                                    'confirm' => null,
                                ])
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:tbody>
        </x-moonshine::table>
    @else
        @include('admin.partials.empty-state', ['message' => __('admin.complaints.empty')])
    @endif
</div>
