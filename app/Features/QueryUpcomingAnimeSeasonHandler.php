<?php

namespace App\Features;

use App\Dto\QueryUpcomingAnimeSeasonCommand;
use App\Enums\AnimeTypeEnum;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * @extends QueryAnimeSeasonHandlerBase<QueryUpcomingAnimeSeasonCommand>
 */
final class QueryUpcomingAnimeSeasonHandler extends QueryAnimeSeasonHandlerBase
{
    public function requestClass(): string
    {
        return QueryUpcomingAnimeSeasonCommand::class;
    }

    protected function getSeasonItems($request, ?AnimeTypeEnum $type): Builder
    {
        return $this->repository->getUpcomingSeasonItems($type, $request->kids, $request->sfw, $request->unapproved);
    }
}
