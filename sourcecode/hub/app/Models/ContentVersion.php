<?php

declare(strict_types=1);

namespace App\Models;

use App\Events\ContentVersionDeleting;
use App\Events\ContentVersionSaving;
use App\Lti\LtiLaunch;
use App\Lti\LtiLaunchBuilder;
use App\Support\HasUlidsFromCreationDate;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use function is_string;
use function url;

class ContentVersion extends Model
{
    use HasFactory;
    use HasUlidsFromCreationDate;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'language_iso_639_3' => 'und',
        'published' => true,
        'max_score' => '0.00',
        'min_score' => '0.00',
    ];

    protected $casts = [
        'published' => 'boolean',
        'max_score' => 'decimal:2',
        'min_score' => 'decimal:2',
    ];

    /** @var string[] */
    protected $touches = [
        'content',
    ];

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'deleting' => ContentVersionDeleting::class,
        'saving' => ContentVersionSaving::class,
    ];

    /**
     * @param string[] $claims
     */
    public function toLtiLaunch(array $claims = []): LtiLaunch
    {
        $launch = app()->make(LtiLaunchBuilder::class)
            ->withClaim('resource_link_title', $this->getTitle());

        foreach ($claims as $name => $value) {
            $launch = $launch->withClaim((string) $name, $value);
        }

        if ($launch->getClaim('resource_link_id') === null) {
            // LTI spec says: "This is an opaque unique identifier that the
            // [platform] guarantees will be unique within the [platform] for
            // every placement of the link". Using the URL should be sufficient
            // to provide that guarantee.
            $launch = $launch->withClaim('resource_link_id', url()->current());
        }

        $tool = $this->tool;
        assert($tool instanceof LtiTool);

        $url = $this->lti_launch_url;
        assert(is_string($url));

        return $launch->toPresentationLaunch($this, $url);
    }

    public function getTitle(): string
    {
        return $this->title
            ?? throw new DomainException('The content version has no title');
    }

    /**
     * @return BelongsTo<User, self>
     */
    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /**
     * @return BelongsTo<Content, self>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * @return BelongsTo<Upload, self>
     */
    public function icon(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'icon_upload_id');
    }

    /**
     * @return BelongsTo<LtiTool, self>
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(LtiTool::class, 'lti_tool_id');
    }

    /**
     * @return BelongsToMany<Tag>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withPivot('verbatim_name');
    }

    /**
     * Get a list of tags in their "prefix:name" or "name" representation.
     * @return string[]
     */
    public function getSerializedTags(): array
    {
        return $this->tags->map(
            fn (Tag $tag) => $tag->prefix !== ''
                ? "{$tag->prefix}:{$tag->name}"
                : $tag->name
        )->toArray();
    }

    public function getDisplayedContentType(): string
    {
        $tag = $this->tags()->where('prefix', 'h5p')->first();

        if ($tag) {
            return $tag->pivot->verbatim_name ?? $tag->name;
        }

        return (string) $this->tool?->name;
    }

    public function givesScore(): bool
    {
        return bccomp((string) $this->max_score, '0', 2) !== 0 ||
            bccomp((string) $this->min_score, '0', 2) !== 0;
    }

    /**
     * @param Builder<self> $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('published', true);
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeDraft(Builder $query): void
    {
        $query->where('published', false);
    }

    /**
     * The locales (ISO 639-3) used by contents
     *
     * @return Collection<int, string>
     */
    public static function getUsedLocales(User $user = null): Collection
    {
        return DB::table('content_versions')
            ->select('language_iso_639_3')
            ->distinct()
            ->join('contents', 'contents.id', '=', 'content_versions.content_id')
            ->when(
                $user instanceof User,
                function ($query) use ($user) {
                    /** @var User $user */
                    $query->join('content_user', 'content_user.content_id', '=', 'contents.id')
                        ->where('content_user.user_id', '=', $user->id);
                },
                function ($query) {
                    $query->where('published', true);
                }
            )
            ->whereNull('contents.deleted_at')
            ->pluck('language_iso_639_3');
    }

    /**
     * The locales (ISO 639-3) used by content as key, display name in the current locale as value
     *
     * @return array<string, string>
     */
    public static function getTranslatedUsedLocales(User $user = null): array
    {
        $locales = self::getUsedLocales($user);
        $displayLocale = app()->getLocale();
        $fallBack = app()->getFallbackLocale();

        return $locales
            ->mapWithKeys(fn (string $locale) => [$locale => locale_get_display_name($locale, $displayLocale) ?: (locale_get_display_name($locale, $fallBack) ?: $locale)])
            ->sort()
            ->toArray();
    }

    public function getTranslatedLanguage(): string
    {
        return locale_get_display_name($this->language_iso_639_3, app()->getLocale()) ?: (locale_get_display_name($this->language_iso_639_3, app()->getFallbackLocale()) ?: $this->language_iso_639_3);
    }
}
