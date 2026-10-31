<x-moonshine::form :action="route('moonshine.logout')" :raw="true" class="px-4 py-3">
    <x-moonshine::form.input type="hidden" name="_method" value="DELETE" />
    <x-moonshine::form.button type="submit" class="btn btn-secondary">
        <x-moonshine::icon icon="arrow-right-start-on-rectangle" />
        {{ __('admin.common.logout') }}
    </x-moonshine::form.button>
</x-moonshine::form>
