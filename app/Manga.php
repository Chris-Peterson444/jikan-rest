<?php

namespace App;

use App\Concerns\FilteredByLetter;
use App\Concerns\MediaFilters;
use App\Enums\MangaTypeEnum;
use App\Http\HttpHelper;
use Carbon\CarbonImmutable;
use Database\Factories\MangaFactory;
use Illuminate\Support\Facades\App;
use Jikan\Helper\Constants;
use Jikan\Jikan;
use Jikan\Request\Manga\MangaRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Manga extends JikanApiSearchableModel
{
    use HasFactory, MediaFilters, FilteredByLetter;

    protected array $filters = [
        "order_by", "status", "type", "sort", "max_score", "min_score", "score", "start_date", "end_date", "magazine",
        "magazines", "letter", "genres", "genres_exclude", "sfw", "unapproved", "kids"
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'mal_id', 'url', 'title', 'title_english', 'title_japanese', 'title_synonyms', 'titles',
        'images', 'status', 'type', 'volumes', 'chapters', 'publishing', 'published', 'rank', 'score',
        'scored_by', 'popularity', 'members', 'favorites', 'synopsis', 'background', 'related',
        'genres', 'explicit_genres', 'themes', 'demographics', 'authors', 'serializations',
        'createdAt', 'modifiedAt', 'approved'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['themes'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'manga';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        '_id', 'expiresAt', 'request_hash'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->displayNameFieldName = "title";
    }

    /** @noinspection PhpUnused */
    public function filterByStartDate(\Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder $query, CarbonImmutable $value): \Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where("published.from", ">=",
                $value->setTime(0, 0)
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->toAtomString()
            );
    }

    /** @noinspection PhpUnused */
    public function filterByEndDate(\Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder $query, CarbonImmutable $value): \Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where("published.to", "<=",
                $value->setTime(0, 0)
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->toAtomString()
            );
    }

    public function filterByMagazine(\Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder $query, string $value): \Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder
    {
        if (empty($value)) {
            return $query;
        }

        $magazine = (int)$value;
        return $query
            ->orWhere('serializations.mal_id', $magazine);
    }

    /** @noinspection PhpUnused */
    public function filterByMagazines(\Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder $query, string $value): \Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder
    {
        if (empty($value)) {
            return $query;
        }

        $magazines = explode(',', $value);
        foreach ($magazines as $magazine) {
            if (empty($magazine)) {
                continue;
            }

            $query = $this->filterByMagazine($query, $value);
        }

        return $query;
    }

    /** @noinspection PhpUnused */
    public function scopeExceptItemsWithAdultRating(\Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder $query): \Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where("type", "!=", MangaTypeEnum::doujin()->label)
            ->where("demographics.mal_id", "!=", Constants::GENRE_MANGA_HENTAI)
            ->where("demographics.mal_id", "!=", Constants::GENRE_MANGA_EROTICA)
            ->where("genres.mal_id", "!=", Constants::GENRE_MANGA_HENTAI);
    }

    /** @noinspection PhpUnused */
    public function scopeExceptKidsItems(\Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder $query): \Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where("demographics.mal_id", "!=", Constants::GENRE_MANGA_KIDS);
    }

    /** @noinspection PhpUnused */
    public function scopeOnlyKidsItems(\Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder $query): \Laravel\Scout\Builder|\Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where("demographics.mal_id", Constants::GENRE_MANGA_KIDS);
    }

    public static function scrape(int $id)
    {
        $data = app('JikanParser')->getManga(new MangaRequest($id));

        return HttpHelper::serializeEmptyObjectsControllerLevel(
            json_decode(
                app('SerializerV4')
                ->serialize($data, 'json'),
                true
            )
        );
    }

    /**
     * Converts the model to an index-able data array.
     *
     * @return array
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->mal_id,
            'mal_id' => (int) $this->mal_id,
            'start_date' => $this->convertToTimestamp($this->published['from']),
            'end_date' => $this->convertToTimestamp($this->published['to']),
            'title' => $this->title,
            'title_transformed' => $this->simplifyStringForSearch($this->title),
            'title_english' => $this->title_english ?? "",
            'title_english_transformed' => $this->simplifyStringForSearch($this->title_english),
            'title_japanese' => $this->title_japanese,
            'title_japanese_transformed' => $this->simplifyStringForSearch($this->title_japanese),
            'title_synonyms' => $this->title_synonyms,
            'type' => $this->type,
            'chapters' => $this->chapters,
            'volumes' => $this->volumes,
            'status' => $this->status,
            'publishing' => $this->publishing,
            'score' => $this->score,
            'rank' => $this->rank,
            'popularity' => $this->popularity,
            'members' => $this->members,
            'favorites' => $this->favorites,
            'synopsis' => $this->synopsis,
            'approved' => $this->approved ?? false,
            'magazines' => $this->getMalIdsOfField($this->magazines),
            'genres' => $this->getMalIdsOfField($this->genres),
            'explicit_genres' => $this->getMalIdsOfField($this->explicit_genres)
        ];
    }

    public function getCollectionSchema(): array
    {
        return [
            'name' => $this->searchableAs(),
            'fields' => [
                [
                    'name' => '.*',
                    'type' => 'auto',
                ],
                [
                    'name' => 'title',
                    'type' => 'string',
                    'optional' => false,
                    'infix' => true,
                    'sort' => true
                ],
                [
                    'name' => 'title_transformed',
                    'type' => 'string',
                    'optional' => false,
                    'infix' => true,
                    'sort' => true
                ],
                [
                    'name' => 'title_japanese',
                    'type' => 'string',
                    'optional' => true,
                    'locale' => 'jp',
                    'infix' => true,
                    'sort' => false
                ],
                [
                    'name' => 'title_japanese_transformed',
                    'type' => 'string',
                    'optional' => true,
                    'locale' => 'jp',
                    'infix' => true,
                    'sort' => false
                ],
                [
                    'name' => 'title_english',
                    'type' => 'string',
                    'optional' => true,
                    'infix' => true,
                    'sort' => true
                ],
                [
                    'name' => 'title_english_transformed',
                    'type' => 'string',
                    'optional' => true,
                    'infix' => true,
                    'sort' => true
                ],
                [
                    'name' => 'title_synonyms',
                    'type' => 'string[]',
                    'optional' => true,
                    'infix' => true,
                    'sort' => false
                ]
            ]
        ];
    }

    /** @noinspection PhpUnused */
    public function getThemesAttribute()
    {
        $result = [];
        if (array_key_exists("themes", $this->attributes)) {
            $result = $this->attributes["themes"];
        }

        return $result;
    }

    public function typesenseQueryBy(): array
    {
        return [
            'title',
            'title_transformed',
            'title_english',
            'title_english_transformed',
            'title_japanese',
            'title_japanese_transformed',
            'title_synonyms',
        ];
    }

    public function getTypeSenseQueryByWeights(): string|null
    {
        return "2,2,1,1,2,2,1";
    }

    /**
     * Returns which fields the search index should sort on when searching
     * @return array|null
     */
    public function getSearchIndexSortBy(): array|null
    {
        return [
            [
                "field" => "_text_match",
                "direction" => "desc"
            ],
            [
                "field" => "members",
                "direction" => "desc"
            ],
        ];
    }

    protected static function newFactory()
    {
        return App::make(MangaFactory::class);
    }
}
