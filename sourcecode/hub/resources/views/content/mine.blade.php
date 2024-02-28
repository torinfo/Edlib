<x-layout>
    <x-slot:title>{{ trans('messages.my-content') }}</x-slot:title>

    <div
        hx-get="{{route('content.mine', \Illuminate\Support\Facades\URL::getRequest()->query())}}"
        hx-trigger="load"
        id="content"
    >
        <x-content.search
            :$query
            :$language
            :$languageOptions
            :$sortBy
            :$sortOptions
        />
        <div class="spinner-border text-info" role="status">
            <span class="visually-hidden">{{ trans('messages.loading') }}</span>
        </div>
    </div>
</x-layout>
