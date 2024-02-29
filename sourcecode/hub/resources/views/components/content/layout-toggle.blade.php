@props(['current'])

<div class="d-sm-block position-absolute end-0 layout-toggle">
    <x-form method="POST" action="{{ route('content.layout') }}">
        <x-form.button
            class="fs-5 py-1 px-2 btn-secondary border-0"
            :title="$current === 'grid' ? trans('messages.result-list') : trans('messages.result-grid')"
            aria-description="{{ trans('messages.result-list-desc') }}"
        >
            @if ($current === 'grid')
                <x-icon name="list" />
            @else
                <x-icon name="grid-fill" />
            @endif
        </x-form.button>
    </x-form>
</div>
