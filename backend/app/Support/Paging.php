<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** R-123: paging is opt-in so callers that need full lists keep working. */
class Paging
{
    public static function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1']];
    }

    /** @return array{0: Collection, 1: ?array} the rows and, only when `page` is sent, the page meta */
    public static function rows(Builder|Relation $query, Request $request, ?int $limit = null): array
    {
        if (! $request->has('page')) {
            return [$limit ? $query->limit($limit)->get() : $query->get(), null];
        }

        $page = $query->paginate(25);

        return [collect($page->items()), [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]];
    }
}
