<?php

namespace Phaseolies\Database\Entity\Query;

trait InteractsWithCursorPagination
{
    /**
     * Cursor pagination with forward only support
     *
     * @param int $perPage
     * @param string $cursorColumn
     * @param string $direction asc|desc
     * @param string|null $cursor
     * @return array
     */
    public function cursorPaginate(int $perPage = 15, string $cursorColumn = 'id', string $direction = 'asc', ?string $cursor = null): array
    {
        $direction   = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $operator    = $direction === 'asc' ? '>' : '<';
        $cursorValue = $cursor ? $this->decodeCursor($cursor) : null;

        $query = clone $this;

        if ($cursorValue !== null) {
            $query->where($cursorColumn, $operator, $cursorValue);
        }

        $query->orderBy($cursorColumn, strtoupper($direction));
        $query->limit($perPage + 1);

        $results = $query->get();
        $items   = $results->all();

        $hasMore = count($items) > $perPage;

        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $last       = end($items);
            $nextCursor = $this->encodeCursor($this->getCursorValue($last, $cursorColumn));
        }

        $path    = $this->getCurrentPath();
        $nextUrl = $nextCursor
            ? "{$path}?cursor={$nextCursor}&per_page={$perPage}&direction={$direction}"
            : null;

        return [
            'data'          => $items,
            'per_page'      => $perPage,
            'next_cursor'   => $nextCursor,
            'next_page_url' => $nextUrl,
            'has_more'      => $hasMore,
            'direction'     => $direction,
        ];
    }

    /**
     * Encode a cursor value to a URL-safe base64 string
     *
     * @param mixed $value
     * @return string
     */
    protected function encodeCursor(mixed $value): string
    {
        return base64_encode(json_encode(['v' => $value]));
    }

    /**
     * Decode a cursor string back to its original value
     *
     * @param string $cursor
     * @return mixed
     */
    protected function decodeCursor(string $cursor): mixed
    {
        try {
            $decoded = json_decode(base64_decode($cursor), true);
            return $decoded['v'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extract the cursor column value from a model or array item
     *
     * @param mixed $item
     * @param string $column
     * @return mixed
     */
    protected function getCursorValue(mixed $item, string $column): mixed
    {
        if (is_array($item)) {
            return $item[$column] ?? null;
        }

        return $item->{$column} ?? null;
    }

    /**
     * Get current request path
     *
     * @return string
     */
    protected function getCurrentPath(): string
    {
        return \Phaseolies\Support\Facades\URL::current();
    }
}
