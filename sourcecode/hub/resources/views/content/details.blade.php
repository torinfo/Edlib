@php use App\Support\SessionScope; @endphp
@props(['version', 'explicitVersion' => false])
<x-layout no-header>
    <x-slot:title>{{ $version->title }}</x-slot:title>

    <x-slot:head>
        <x-oembed-links />
    </x-slot:head>

    <x-content.details.header :$version current="content" />

    <x-layout.sidebar-layout>
        <x-slot:main>
            <x-lti-launch :launch="$launch" log-to="#messages" class="w-100 border mb-2" />
        </x-slot:main>

        <x-slot:sidebar>
            <div class="d-flex flex-lg-column gap-2 mb-5 details-action-buttons">
                @can('use', $content)
                    <x-form action="{{ route('content.use', [$content]) }}">
                        <button class="btn btn-primary btn-lg d-flex gap-2 w-100">
                            <x-icon name="check-lg" />
                            <span class="flex-grow-1">{{ trans('messages.use-content')}}</span>
                        </button>
                    </x-form>
                @endcan

                @can('edit', $content)
                    <a href="{{ route('content.edit', [$content, $version]) }}" class="btn btn-secondary btn-lg d-flex gap-2">
                        <x-icon name="pencil" class="align-self-start" />
                        <span class="flex-grow-1 align-self-center">{{ trans('messages.edit')}}</span>
                    </a>
                @endcan

                @if (!$explicitVersion && $version->published)
                    <a
                        href="{{ route('content.share', [$content, SessionScope::TOKEN_PARAM => null]) }}"
                        class="btn btn-secondary d-flex gap-2 btn-lg share-button"
                        target="_blank"
                        data-share-success-message="{{ trans('messages.share-copied-url-success') }}"
                        data-share-failure-message="{{ trans('messages.share-copied-url-failed') }}"
                    >
                        <x-icon name="share" />
                        <span class="flex-grow-1 align-self-center">{{ trans('messages.share') }}</span>
                    </a>
                @endif

                @can('delete', $content)
                    <x-form action="{{ route('content.delete', [$content]) }}" method="DELETE">
                        <button
                            class="btn btn-outline-danger btn-lg d-flex gap-2 w-100 delete-content-button"
                            hx-delete="{{ route('content.delete', [$content]) }}"
                            hx-confirm="{{ trans('messages.confirm-delete-content') }}"
                        >
                            <x-icon name="x-lg" class="align-self-start" />
                            <span class="flex-grow-1 align-self-center">{{ trans('messages.delete-content') }}</span>
                        </button>
                    </x-form>
                @endcan
            </div>

            @can('edit', $content)
                <x-content.details.version-history :$version />
            @endcan

            @if (auth()->user()?->debug_mode ?? app()->hasDebugModeEnabled())
                <x-content.details.lti-params :$launch :$version />
                <x-content.details.messages id="messages" />
            @endif
        </x-slot:sidebar>
    </x-layout.sidebar-layout>
</x-layout>
