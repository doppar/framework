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
     * @return bool
     */
    public function hasPages(): bool
    {
        return $this->data['last_page'] > 1;
    }

    /**
     * Check if the current page is the first page
     * @return bool
     */
    public function onFirstPage(): bool
    {
        return $this->data['current_page'] === 1;
    }

    /**
     * Check if there are more pages after the current page
     * @return bool
     */
    public function hasMorePages(): bool
    {
        return $this->data['current_page'] < $this->data['last_page'];
    }

    /**
     * Get the URL for the previous page
     * @return string|null
     */
    public function previousPageUrl(): ?string
    {
        return $this->data['previous_page_url'];
    }

    /**
     * Get the URL for the next page
     * @return string|null
     */
    public function nextPageUrl(): ?string
    {
        return $this->data['next_page_url'];
    }

    /**
     * Get the current page number
     * @return int
     */
    public function currentPage(): int
    {
        return $this->data['current_page'];
    }

    /**
     * Get the last page number
     * @return int
     */
    public function lastPage(): int
    {
        return $this->data['last_page'];
    }

    /**
     * Generate an array of page numbers with ellipsis for gaps
     * @return string|null
     */
    public function jump(): array
    {
        $elements = [];
        $currentPage = $this->currentPage();
        $lastPage = $this->lastPage();

        // Add the first page
        if ($currentPage > 1) {
            $elements[] = 1;
        }

        // Add ellipsis if the current page is far from the first page
        if ($currentPage > 3) {
            $elements[] = '...';
        }

        // Add pages around the current page
        for ($i = max(1, $currentPage - 2); $i <= min($lastPage, $currentPage + 2); $i++) {
            $elements[] = $i;
        }

        // Add ellipsis if the current page is far from the last page
        if ($currentPage < $lastPage - 2) {
            $elements[] = '...';
        }

        // Add the last page
        if ($currentPage < $lastPage) {
            $elements[] = $lastPage;
        }

        return $elements;
    }

    /**
     * Generate an array of page numbers with ellipsis for gaps (simplified version)
     * @return string|null
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
     * @param int $page
     * @return string
     */
    public function url($page): string
    {
        return $this->data['path'] . '?page=' . $page;
    }

    /**
     * Render pagination links with a "Jump to Page" dropdown
     * @return string|null
     */
    public function linkWithJumps(): ?string
    {
        if (file_exists(resource_path('views/vendor/pagination/jump.blade.php'))) {
            return view('vendor.pagination.jump', ['paginator' => $this])->render();
        }

        if ($this->hasPages()) {
            $html = '<div class="d-flex justify-content-between align-items-center">';
            $html .= '<div class="d-flex align-items-center">';
            $html .= '<span class="me-2">Jump:</span>';
            $html .= '<select class="form-select form-select-sm" onchange="window.location.href = this.value">';

            for ($i = 1; $i <= $this->lastPage(); $i++) {
                $html .= '<option value="' . $this->url($i) . '" ' . ($i == $this->currentPage() ? 'selected' : '') . '>';
                $html .= 'Page ' . $i;
                $html .= '</option>';
            }

            $html .= '</select>';
            $html .= '</div>';
            $html .= '<div class="ms-3">';
            $html .= 'Page ' . $this->currentPage() . ' of ' . $this->lastPage();
            $html .= '</div>';

            $html .= '<ul class="pagination mb-0">';
            if ($this->onFirstPage()) {
                $html .= '<li class="page-item disabled"><span class="page-link">« Previous</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $this->previousPageUrl() . '" rel="prev">« Previous</a></li>';
            }
            if ($this->hasMorePages()) {
                $html .= '<li class="page-item"><a class="page-link" href="' . $this->nextPageUrl() . '" rel="next">Next »</a></li>';
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
     * @return string|null
     */
    public function links(): ?string
    {
        if (file_exists(resource_path('views/vendor/pagination/number.blade.php'))) {
            return view('vendor.pagination.number', ['paginator' => $this])->render();
        }
        if ($this->hasPages()) {
            $html = '<div class="d-flex justify-content-between align-items-center">';
            $html .= '<div class="ms-3">';
            $html .= 'Page ' . $this->currentPage() . ' of ' . $this->lastPage();
            $html .= '</div>';
            $html .= '<ul class="pagination mb-0">';

            if ($this->onFirstPage()) {
                $html .= '<li class="page-item disabled"><span class="page-link">« Previous</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $this->previousPageUrl() . '" rel="prev">« Previous</a></li>';
            }

            foreach ($this->numbers() as $page) {
                if (is_string($page)) {
                    // Dots (ellipsis)
                    $html .= '<li class="page-item disabled"><span class="page-link">' . $page . '</span></li>';
                } else {
                    $isActive = $page == $this->currentPage();
                    $html .= '<li class="page-item' . ($isActive ? ' active' : '') . '">';
                    $html .= '<a class="page-link" href="' . $this->url($page) . '">' . $page . '</a>';
                    $html .= '</li>';
                }
            }

            if ($this->hasMorePages()) {
                $html .= '<li class="page-item"><a class="page-link" href="' . $this->nextPageUrl() . '" rel="next">Next »</a></li>';
            } else {
                $html .= '<li class="page-item disabled"><span class="page-link">Next »</span></li>';
            }

            $html .= '</ul>';
            $html .= '</div>';

            return $html;
        }

        return null;
    }
}
