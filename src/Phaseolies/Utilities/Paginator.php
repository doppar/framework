<?php

namespace Phaseolies\Utilities;

class Paginator
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Check if there is any page for pagination
     *
     * @return bool
     */
    public function hasPages(): bool
    {
        return $this->data['last_page'] > 1;
    }

    /**
     * Check if the current page is the first page
     *
     * @return bool
     */
    public function onFirstPage(): bool
    {
        return $this->data['current_page'] === 1;
    }

    /**
     * Check if there are more pages after the current page
     *
     * @return bool
     */
    public function hasMorePages(): bool
    {
        return $this->data['current_page'] < $this->data['last_page'];
    }

    /**
     * Get the URL for the previous page
     *
     * @return string|null
     */
    public function previousPageUrl(): ?string
    {
        if (empty($this->data['previous_page_url'])) {
            return null;
        }

        $queryParams = request()->except('page');

        return $this->appendQueryParameters($this->data['previous_page_url'], $queryParams);
    }

    /**
     * Get the URL for the next page
     *
     * @return string|null
     */
    public function nextPageUrl(): ?string
    {
        if (empty($this->data['next_page_url'])) {
            return null;
        }

        $queryParams = request()->except('page');

        return $this->appendQueryParameters($this->data['next_page_url'], $queryParams);
    }

    /**
     * Get the current page number
     *
     * @return int
     */
    public function currentPage(): int
    {
        return $this->data['current_page'];
    }

    /**
     * Get the last page number
     *
     * @return int
     */
    public function lastPage(): int
    {
        return $this->data['last_page'];
    }

    /**
     * Generate an array of page numbers with ellipsis for gaps
     *
     * @return string|null
     */
    public function jump(): array
    {
        $elements = [];
        $currentPage = $this->currentPage();
        $lastPage = $this->lastPage();

        // Add the first page only if it's not already going to be included
        // Changed from > 1 to > 2 to prevent duplicate
        if ($currentPage > 2) {
            $elements[] = 1;
        }

        // Add ellipsis if the current page is far from the first page
        // Changed from > 2 to > 3 to match new first page condition
        if ($currentPage > 3) {
            $elements[] = '...';
        }

        // Add pages around the current page
        $startPage = max(1, $currentPage - 2);
        $endPage = min($lastPage, $currentPage + 2);

        // Ensure we don't duplicate the first page
        if ($startPage === 1 && !empty($elements) && $elements[0] === 1) {
            $startPage = 2;
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $elements[] = $i;
        }

        // Add ellipsis if the current page is far from the last page
        if ($currentPage < $lastPage - 2) {
            $elements[] = '...';
        }

        // Add the last page if it's not already included
        if ($currentPage < $lastPage - 1) {
            $elements[] = $lastPage;
        }

        return $elements;
    }

    /**
     * Generate an array of page numbers with ellipsis for gaps (simplified version)
     *
     * @return array
     */
    public function numbers(): array
    {
        $elements = [];
        $currentPage = $this->currentPage();
        $lastPage = $this->lastPage();

        $elements[] = 1;

        // Add ellipsis if the current page is far from the first page
        if ($currentPage > 3) {
            $elements[] = '...';
        }

        // Add pages around the current page
        for ($i = max(2, $currentPage - 1); $i <= min($lastPage - 1, $currentPage + 1); $i++) {
            $elements[] = $i;
        }

        // Add ellipsis if the current page is far from the last page
        if ($currentPage < $lastPage - 2) {
            $elements[] = '...';
        }

        // Always show the last page
        if ($lastPage > 1) {
            $elements[] = $lastPage;
        }

        return $elements;
    }

    /**
     * Generate the URL for a specific page
     *
     * @param int $page
     * @return string
     */
    public function url($page): string
    {
        $queryParams = request()->query();
        $queryParams['page'] = $page;

        return $this->data['path'] . '?' . http_build_query($queryParams);
    }

    /**
     * Render pagination links with a "Jump to Page" dropdown
     *
     * @return string|null
     */
    public function linkWithJumps(): ?string
    {
        if (file_exists(resource_path('views/vendor/pagination/jump.blade.php'))) {
            return view('vendor.pagination.jump', ['paginator' => $this])->render();
        }

        if ($this->hasPages()) {
            $queryParams = request()->query();

            $html = '<div class="d-flex justify-content-between align-items-center">';
            $html .= '<div class="d-flex align-items-center">';
            $html .= '<span class="me-2">Jump:</span>';
            $html .= '<select class="form-select form-select-sm" onchange="window.location.href = this.value">';

            for ($i = 1; $i <= $this->lastPage(); $i++) {
                $url = $this->url($i);
                $url = $this->appendQueryParameters($url, $queryParams);

                $html .= '<option value="' . $url . '" ' . ($i == $this->currentPage() ? 'selected' : '') . '>';
                $html .= 'Page ' . $i;
                $html .= '</option>';
            }

            $html .= '</select>';
            $html .= '</div>';
            $html .= '<div class="ms-3">';
            $html .= 'Page ' . $this->currentPage() . ' of ' . $this->lastPage();
            $html .= '</div>';

            $html .= '<ul class="pagination mb-0">';

            // Previous page link with query parameters
            if ($this->onFirstPage()) {
                $html .= '<li class="page-item disabled"><span class="page-link">« Previous</span></li>';
            } else {
                $prevUrl = $this->previousPageUrl();
                $prevUrl = $this->appendQueryParameters($prevUrl, $queryParams);
                $html .= '<li class="page-item"><a class="page-link" href="' . $prevUrl . '" rel="prev">« Previous</a></li>';
            }

            // Next page link with query parameters
            if ($this->hasMorePages()) {
                $nextUrl = $this->nextPageUrl();
                $nextUrl = $this->appendQueryParameters($nextUrl, $queryParams);
                $html .= '<li class="page-item"><a class="page-link" href="' . $nextUrl . '" rel="next">Next »</a></li>';
            } else {
                $html .= '<li class="page-item disabled"><span class="page-link">Next »</span></li>';
            }

            $html .= '</ul>';
            $html .= '</div>';

            return $html;
        }

        return null;
    }

    /**
     * Render pagination links with page numbers
     *
     * @return string|null
     */
    public function links(): ?string
    {
        if (file_exists(resource_path('views/vendor/pagination/number.blade.php'))) {
            return view('vendor.pagination.number', ['paginator' => $this])->render();
        }

        if ($this->hasPages()) {
            $queryParams = request()->query();

            $html = '<div class="d-flex justify-content-between align-items-center">';
            $html .= '<div class="ms-3">';
            $html .= 'Page ' . $this->currentPage() . ' of ' . $this->lastPage();
            $html .= '</div>';
            $html .= '<ul class="pagination mb-0">';

            // Previous page link
            if ($this->onFirstPage()) {
                $html .= '<li class="page-item disabled"><span class="page-link">« Previous</span></li>';
            } else {
                $prevUrl = $this->previousPageUrl();
                $prevUrl = $this->appendQueryParameters($prevUrl, $queryParams);
                $html .= '<li class="page-item"><a class="page-link" href="' . $prevUrl . '" rel="prev">« Previous</a></li>';
            }

            // Page number links
            foreach ($this->numbers() as $page) {
                if (is_string($page)) {
                    // Dots (ellipsis)
                    $html .= '<li class="page-item disabled"><span class="page-link">' . $page . '</span></li>';
                } else {
                    $isActive = $page == $this->currentPage();
                    $pageUrl = $this->url($page);
                    $pageUrl = $this->appendQueryParameters($pageUrl, $queryParams);

                    $html .= '<li class="page-item' . ($isActive ? ' active' : '') . '">';
                    $html .= '<a class="page-link" href="' . $pageUrl . '">' . $page . '</a>';
                    $html .= '</li>';
                }
            }

            // Next page link
            if ($this->hasMorePages()) {
                $nextUrl = $this->nextPageUrl();
                $nextUrl = $this->appendQueryParameters($nextUrl, $queryParams);
                $html .= '<li class="page-item"><a class="page-link" href="' . $nextUrl . '" rel="next">Next »</a></li>';
            } else {
                $html .= '<li class="page-item disabled"><span class="page-link">Next »</span></li>';
            }

            $html .= '</ul>';
            $html .= '</div>';

            return $html;
        }

        return null;
    }

    /**
     * Append query parameters to a URL
     *
     * @param string|null $url
     * @param array $queryParams
     * @return string
     */
    protected function appendQueryParameters(?string $url, array $queryParams): string
    {
        if (!$url) {
            return '';
        }

        // Parse the URL to get its components
        $parsedUrl = parse_url($url);

        $existingParams = [];

        // Get existing query parameters from the URL
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $existingParams);
        }

        // Merge with new parameters
        // New ones take precedence
        $mergedParams = array_merge($queryParams, $existingParams);

        // Rebuild the URL without modifying the base URL
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
        $host = $parsedUrl['host'] ?? '';
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $path = $parsedUrl['path'] ?? '';
        $query = http_build_query($mergedParams);

        return $scheme . $host . $port . $path . '?' . $query;
    }
}
