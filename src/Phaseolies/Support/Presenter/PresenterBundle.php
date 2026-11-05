<?php

namespace Phaseolies\Support\Presenter;

use Phaseolies\Support\Collection;
use JsonSerializable;

class PresenterBundle implements JsonSerializable
{
    /**
     * Collection Underlying collection of resource data
     *
     * @var Collection
     */
    protected Collection $collection;

    /**
     * Fully-qualified class name of the resource to wrap items in
     *
     * @var string
     */
    protected string $presenter;

    /**
     * Fields to exclude from each resource
     *
     * @var array
     */
    protected array $except = [];

    /**
     * Fields to include in each resource
     *
     * @var array
     */
    protected array $only = [];

    /**
     * Whether to preserve original collection keys in output
     *
     * @var bool
     */
    protected bool $preserveKeys = false;

    /**
     * Whether to serialize lazily
     *
     * @var bool
     */
    protected bool $lazy = false;

    /**
     * Pagination metadata for paginated responses
     *
     * @var array
     */
    protected array $paginationMeta = [];

    /**
     * Create a new PresenterBundle instance
     *
     * @param array|Collection $collection
     * @param string $presenter
     * @throws \InvalidArgumentException
     */
    public function __construct($collection, string $presenter)
    {
        $this->assertPresenter($presenter);

        if (is_array($collection)) {
            if (isset($collection['data']) && $this->isPaginatedArray($collection)) {
                $this->paginationMeta = $this->extractPaginationMeta($collection);
                $this->collection = new Collection($presenter, $collection['data']);
            } else {
                $this->collection = new Collection($presenter, $collection);
            }
        } elseif ($collection instanceof Collection) {
            $this->collection = $collection;
        } else {
            throw new \InvalidArgumentException('Invalid collection type provided');
        }

        $this->presenter = $presenter;
    }

    /**
     * Ensure the configured class is a concrete Presenter implementation.
     *
     * @param string $presenter
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function assertPresenter(string $presenter): void
    {
        if (!class_exists($presenter) || !is_a($presenter, Presenter::class, true)) {
            throw new \InvalidArgumentException(
                "Presenter [{$presenter}] must be a concrete " . Presenter::class . ' class.'
            );
        }

        if (!(new \ReflectionClass($presenter))->isInstantiable()) {
            throw new \InvalidArgumentException("Presenter [{$presenter}] must be instantiable.");
        }
    }

    /**
     * Check if the given array matches a paginated structure
     *
     * @param array $data
     * @return bool
     */
    protected function isPaginatedArray(array $data): bool
    {
        return isset($data['data']) &&
            is_array($data['data']) &&
            (isset($data['current_page']) || isset($data['last_page']) || is_array($data['meta'] ?? null));
    }

    /**
     * Extract pagination metadata from a paginated dataset
     *
     * @param array $paginatedData
     * @return array
     */
    protected function extractPaginationMeta(array $paginatedData): array
    {
        $nestedMeta = is_array($paginatedData['meta'] ?? null)
            ? $paginatedData['meta']
            : [];
        $source = array_merge($nestedMeta, $paginatedData);

        $currentPage = max((int) ($source['current_page'] ?? 1), 1);
        $perPage = max((int) ($source['per_page'] ?? 15), 1);
        $total = max((int) ($source['total'] ?? count($paginatedData['data'])), 0);
        $lastPage = max((int) ($source['last_page'] ?? ceil($total / $perPage)), 1);
        $path = (string) ($source['path'] ?? $this->requestUrl());

        $meta = [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $source['from'] ?? 1,
            'to' => $source['to'] ?? count($paginatedData['data']),
            'path' => $path,
        ];

        $meta['first_page_url'] = $this->paginationUrl($source, 'first_page_url', $path, 1);
        $meta['last_page_url'] = $this->paginationUrl($source, 'last_page_url', $path, $lastPage);
        $meta['next_page_url'] = array_key_exists('next_page_url', $source)
            ? $source['next_page_url']
            : ($currentPage < $lastPage ? $this->buildPageUrl($path, $currentPage + 1) : null);
        $meta['previous_page_url'] = array_key_exists('previous_page_url', $source)
            ? $source['previous_page_url']
            : ($source['prev_page_url'] ?? ($currentPage > 1
                ? $this->buildPageUrl($path, $currentPage - 1)
                : null));

        return $meta;
    }

    /**
     * Get the pagination URL
     *
     * @param array $source
     * @param string $key
     * @param string $path
     * @param int $page
     * @return string|null
     */
    protected function paginationUrl(array $source, string $key, string $path, int $page): ?string
    {
        return array_key_exists($key, $source)
            ? $source[$key]
            : $this->buildPageUrl($path, $page);
    }

    /**
     * Get the request URL
     *
     * @return string
     */
    protected function requestUrl(): string
    {
        try {
            return (string) request()->url();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Get the request query string
     *
     * @return array
     */
    protected function requestQuery(): array
    {
        try {
            $query = request()->query();
            return is_array($query) ? $query : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Build a paginated URL for a given page number
     *
     * @param string $path
     * @param int $page
     * @return string
     */
    protected function buildPageUrl(string $path, int $page): string
    {
        $query = $this->requestQuery();
        $query['page'] = $page;

        if ($path === '') {
            return '?' . http_build_query($query);
        }

        $separator = str_contains($path, '?')
            ? (str_ends_with($path, '?') || str_ends_with($path, '&') ? '' : '&')
            : '?';

        return $path . $separator . http_build_query($query);
    }

    /**
     * Set fields to exclude from each resource's output
     *
     * @param string|array ...$fields
     * @return self
     */
    public function except(array|string ...$fields): self
    {
        $fields = count($fields) === 1 && is_array($fields[0])
            ? $fields[0]
            : $fields;

        $this->except = [...$this->except, ...$fields];

        return $this;
    }

    /**
     * Set fields to include in each resource's output
     *
     * @param string|array ...$fields
     * @return self
     */
    public function only(array|string ...$fields): self
    {
        $fields = count($fields) === 1 && is_array($fields[0])
            ? $fields[0]
            : $fields;

        $this->only = [...$this->only, ...$fields];

        return $this;
    }

    /**
     * Whether to preserve original keys in the output array
     *
     * @param bool $preserve
     * @return self
     */
    public function preserveKeys(bool $preserve = true): self
    {
        $this->preserveKeys = $preserve;

        return $this;
    }

    /**
     * Enable or disable lazy serialization
     *
     * @param bool $lazy
     * @return self
     */
    public function lazy(bool $lazy = true): self
    {
        $this->lazy = $lazy;

        return $this;
    }

    /**
     * Convert collection into array for JSON output
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        $data = $this->lazy
            ? $this->serializeLazy()
            : $this->serializeEager();

        if (!empty($this->paginationMeta)) {
            return [
                'data' => $data,
                'meta' => $this->paginationMeta,
            ];
        }

        return $data;
    }

    /**
     * Serialize all resources
     *
     * @return array
     */
    protected function serializeEager(): array
    {
        return iterator_to_array($this->resources(), true);
    }

    /**
     * Serialize resources using a generator. JSON serialization still
     * materializes an array for JSON compatibility.
     */
    protected function serializeLazy(): array
    {
        return iterator_to_array($this->resources(), true);
    }

    /**
     * Yield transformed resources without materializing the complete result.
     *
     * @return \Generator<int|string, array>
     */
    public function toIterable(): \Generator
    {
        yield from $this->resources();
    }

    /**
     * @return \Generator<int|string, array>
     */
    protected function resources(): \Generator
    {
        foreach ($this->collection as $key => $item) {
            $resource = new $this->presenter($item);

            if (!empty($this->only)) {
                $resource->only($this->only);
            }

            if (!empty($this->except)) {
                $resource->except($this->except);
            }

            if ($this->preserveKeys) {
                yield $key => $resource->jsonSerialize();
            } else {
                yield $resource->jsonSerialize();
            }
        }
    }

    /**
     * Build a paginated response array with data and metadata
     *
     * @return array
     */
    public function paginate(): array
    {
        return $this->jsonSerialize();
    }

    /**
     * Return the collection in a paginated response structure
     *
     * @return array
     */
    public function toPaginatedResponse(): array
    {
        return $this->paginate();
    }
}
