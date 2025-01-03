<x-layout>
    <x-slot:title>{{ trans('messages.contexts-for-lti-platform', ['platform' => $platform->name]) }}</x-slot:title>

    <p><a href="{{ route('admin.lti-platforms.index') }}">Back to LTI platforms</a></p>

    @if (count($platform->contexts) > 0)
        <ul>
            @foreach ($platform->contexts as $context)
                <li>{{ $context->name }}</li>
            @endforeach
        </ul>
    @endif

    @if (count($available_contexts) > 0)
        <x-form action="{{ route('admin.lti-platforms.add-context', [$platform]) }}" method="PUT">
            <x-form.field
                name="context"
                type="select"
                emptyOption
                required
                :label="trans('messages.context')"
                :options="$available_contexts"
            />

            <x-form.button class="btn-primary">{{ trans('messages.add') }}</x-form.button>
        </x-form>
    @else
        <p>{{ trans('messages.no-available-contexts-to-add') }}</p>
    @endif
</x-layout>
