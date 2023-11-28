<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\IndexContentRequest;
use App\Http\Requests\DeepLinkingReturnRequest;
use App\Lti\LtiLaunchBuilder;
use App\Models\Content;
use App\Models\ContentUserRole;
use App\Models\ContentVersion;
use App\Models\LtiTool;
use App\Models\LtiToolEditMode;
use Cerpus\EdlibResourceKit\Lti\Lti11\Mapper\DeepLinking\ContentItemsMapperInterface;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

use function assert;
use function is_string;
use function to_route;
use function view;

class ContentController extends Controller
{
    public function index(IndexContentRequest $request): View
    {
        $query = $request->validated('q', '');
        assert(is_string($query));

        $contents = Content::findShared($query);

        return view('content.index', [
            'contents' => $contents->paginate(),
            'query' => $query,
        ]);
    }

    public function mine(IndexContentRequest $request): View
    {
        $query = $request->validated('q', '');
        assert(is_string($query));

        $user = $this->getUser();
        $contents = Content::findForUser($user, $query);

        return view('content.mine', [
            'contents' => $contents->paginate(),
            'query' => $query,
        ]);
    }

    public function details(Content $content, LtiLaunchBuilder $launchBuilder): View
    {
        $version = $content->latestPublishedVersion()->firstOrFail();

        $tool = $version->tool;
        assert($tool instanceof LtiTool);

        $launchUrl = $version->lti_launch_url;
        assert(is_string($launchUrl));

        $launch = $launchBuilder
            ->withWidth(640)
            ->withHeight(480)
            ->toPresentationLaunch($tool, $launchUrl, $content->id);

        return view('content.details', [
            'content' => $content,
            'launch' => $launch,
        ]);
    }

    public function version(
        Content $content,
        ContentVersion $version,
        LtiLaunchBuilder $launchBuilder,
    ): View {
        $launchUrl = $version->lti_launch_url;
        assert(is_string($launchUrl));

        $tool = $version->tool;
        assert($tool instanceof LtiTool);

        $launch = $launchBuilder->toPresentationLaunch(
            $tool,
            $launchUrl,
            $version->id,
        );

        return view('content.details', [
            'content' => $content,
            'version' => $version,
            'launch' => $launch,
        ]);
    }

    public function embed(Content $content, LtiLaunchBuilder $launchBuilder): View
    {
        $tool = $content->latestPublishedVersion?->tool;
        assert($tool instanceof LtiTool);

        $launchUrl = $content->latestPublishedVersion?->lti_launch_url;
        assert(is_string($launchUrl));

        $launch = $launchBuilder
            ->withWidth(640)
            ->withHeight(480)
            ->toPresentationLaunch($tool, $launchUrl, $content->id . '/embed');

        return view('content.embed', [
            'content' => $content,
            'version' => $content->latestPublishedVersion,
            'launch' => $launch,
        ]);
    }

    public function create(): View
    {
        $tools = LtiTool::all();

        return view('content.create', [
            'types' => $tools,
        ]);
    }

    public function copy(Content $content): RedirectResponse
    {
        $user = $this->getUser();
        $copy = $content->createCopyBelongingTo($user);

        return to_route('content.index', [$copy->id]);
    }

    public function edit(Content $content, LtiLaunchBuilder $builder): View
    {
        $version = $content->latestPublishedVersion ?? abort(404);
        $tool = $version->tool ?? abort(404);

        $launchUrl = match ($tool->edit_mode) {
            LtiToolEditMode::Replace => $tool->creator_launch_url,
            LtiToolEditMode::DeepLinkingRequestToContentUrl => $version->lti_launch_url,
        };
        assert(is_string($launchUrl));

        $launch = $builder->toItemSelectionLaunch(
            $tool,
            $launchUrl,
            route('content.lti-update', [$tool, $content]),
        );

        return view('content.edit', [
            'content' => $content,
            'launch' => $launch,
        ]);
    }

    public function use(Content $content): View
    {
        $ltiRequest = $content->toItemSelectionRequest();

        return view('lti.redirect', [
            'url' => $ltiRequest->getUrl(),
            'method' => $ltiRequest->getMethod(),
            'parameters' => $ltiRequest->toArray(),
        ]);
    }

    public function launchCreator(LtiTool $tool, LtiLaunchBuilder $launchBuilder): View
    {
        $launch = $launchBuilder
            ->withWidth(640)
            ->withHeight(480)
            ->toItemSelectionLaunch(
                $tool,
                $tool->creator_launch_url,
                route('content.lti-store', [$tool]),
            );

        return view('content.launch-creator', [
            'tool' => $tool,
            'launch' => $launch,
        ]);
    }

    public function ltiStore(
        LtiTool $tool,
        DeepLinkingReturnRequest $request,
        ContentItemsMapperInterface $mapper,
    ): View {
        $item = $mapper->map($request->input('content_items'))[0];

        $content = DB::transaction(function () use ($item, $tool) {
            $title = $item->getTitle() ?? throw new Exception('Missing title');
            $url = $item->getUrl() ?? throw new Exception('Missing URL');

            $content = new Content();
            $content->save();

            $contentVersion = new ContentVersion();
            $contentVersion->title = $title;
            $contentVersion->lti_tool_id = $tool->id;
            $contentVersion->lti_launch_url = $url;
            $contentVersion->published = true; // TODO

            $content->users()->save($this->getUser(), [
                'role' => ContentUserRole::Owner,
            ]);

            $content->versions()->save($contentVersion);

            return $content;
        });
        assert($content instanceof Content);

        // return to platform consuming Edlib
        if ($request->session()->get('lti.lti_message_type') === 'ContentItemSelectionRequest') {
            $ltiRequest = $content->toItemSelectionRequest();

            return view('lti.redirect', [
                'url' => $ltiRequest->getUrl(),
                'method' => $ltiRequest->getMethod(),
                'parameters' => $ltiRequest->toArray(),
            ]);
        }

        // return to Edlib
        return view('lti.redirect', [
            'url' => route('content.details', $content),
            'method' => 'GET',
            'target' => '_parent',
        ]);
    }

    public function ltiUpdate(
        LtiTool $tool,
        Content $content,
        DeepLinkingReturnRequest $request,
        ContentItemsMapperInterface $mapper,
    ): View {
        $item = $mapper->map($request->input('content_items'))[0];

        DB::transaction(function () use ($content, $item, $tool): void {
            $title = $item->getTitle() ?? throw new Exception('Missing title');
            $url = $item->getUrl() ?? throw new Exception('Missing URL');

            $version = new ContentVersion();
            $version->lti_tool_id = $tool->id;
            $version->title = $title;
            $version->lti_launch_url = $url;
            $version->published = true; // TODO

            $content->versions()->save($version);
        });

        // return to platform consuming Edlib
        if (($request->attributes->get('lti')['lti_message_type'] ?? null) === 'ContentItemSelectionRequest') {
            $ltiRequest = $content->toItemSelectionRequest();

            return view('lti.redirect', [
                'url' => $ltiRequest->getUrl(),
                'method' => $ltiRequest->getMethod(),
                'parameters' => $ltiRequest->toArray(),
            ]);
        }

        // return to Edlib
        return view('lti.redirect', [
            'url' => route('content.details', $content),
            'method' => 'GET',
            'target' => '_parent',
        ]);
    }

    public function sitemap(): Response
    {
        $document = Content::generateSiteMap();

        return new Response($document->saveXML(), headers: [
            'Content-Type' => 'application/xml',
        ]);
    }
}
