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
     * @param array|Collection $collection  The data source (array or Collection)
     * @param string $presenter Class name of the bundle
     * @throws \InvalidArgumentException If $collection is not a supported type
     */
    public function __construct($collection, string $presenter)
    {
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
     * Check if the given array matches a paginated structure
     *
     * @param array $data
     * @return bool
     */
    protected function isPaginatedArray(array $data): bool
    {
        return isset($data['data']) &&
            (isset($data['current_page']) || isset($data['meta']));
    }

    /**
     * Extract pagination metadata from a paginated dataset
     *
     * @param array $paginatedData
     * @return array
     */
    protected function extractPaginationMeta(array $paginatedData): array
    {
        $meta = [
            'current_page' => $paginatedData['current_page'] ?? 1,
            'per_page' => $paginatedData['per_page'] ?? 15,
            'total' => $paginatedData['total'] ?? count($paginatedData['data']),
            'last_page' => $paginatedData['last_page'] ?? 1,
            'from' => $paginatedData['from'] ?? 1,
            'to' => $paginatedData['to'] ?? count($paginatedData['data']),
            'path' => $paginatedData['path'] ?? request()->url(),
        ];

        $currentPage = $meta['current_page'];
        $lastPage = $meta['last_page'];
        $path = $meta['path'];

        $meta['first_page_url'] = $paginatedData['first_page_url'] ?? $this->buildPageUrl($path, 1);
        $meta['last_page_url'] = $paginatedData['last_page_url'] ?? $this->buildPageUrl($path, $lastPage);

        $meta['next_page_url'] = $currentPage < $lastPage
            ? $this->buildPageUrl($path, $currentPage + 1)
            : null;

        $meta['prev_page_url'] = $currentPage > 1
            ? $this->buildPageUrl($path, $currentPage - 1)
            : null;

        return $meta;
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
        $query = request()->query();

        $query['page'] = $page;

        return $path . '?' . http_build_query($query);
    }

    /**
     * Set fields to exclude from each resource's output
     *
     * @param array $fields
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
     * @param array $fields
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
        if ($this->lazy) {
            return $this->serializeLazy();
        }

        return $this->serializeEager();
    }

    /**
     * Serialize all resources
     *
     * @return array
     */
    protected function serializeEager(): array
    {
        $data = [];

        foreach ($this->collection as $key => $item) {
            $resource = new $this->presenter($item);

            if (!empty($this->only)) {
                $resource->only($this->only);
            }

            if (!empty($this->except)) {
                $resource->except($this->except);
            }

            if ($this->preserveKeys) {
                $data[$key] = $resource->jsonSerialize();
            } else {
                $data[] = $resource->jsonSerialize();
            }
        }

        return $data;
    }

    /**
     * Serialize resources using a generator
     *
     * @return array
     */
    protected function serializeLazy(): array
    {
        $generator = function () {
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
        };

        return iterator_to_array($generator());
    }

    /**
     * Build a paginated response array with data and metadata
     *
     * @return array
     */
    private function paginate(): array
    {
        $data = $this->jsonSerialize();

        if (!empty($this->paginationMeta)) {
            return [
                'data' => $data,
                'meta' => $this->paginationMeta
            ];
        }

        return $data;
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
