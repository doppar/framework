<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class PaginationPublishCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'publish:pagination';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Publish pagination views to resources/views/vendor/pagination';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $startTime = microtime(true);
        $this->newLine();

        $paginationPath = resource_path('views/vendor/pagination');

        try {
            if (!is_dir($paginationPath)) {
                if (!mkdir($paginationPath, 0755, true)) {
                    throw new \RuntimeException("Failed to create directory: {$paginationPath}");
                }
                $this->line("<info>Created directory:</info> {$paginationPath}");
                $this->newLine();
            }

            $files = [
                'jump.blade.php' => $this->getJumpPaginationView(),
                'number.blade.php' => $this->getNumberPaginationView(),
            ];

            $createdCount = 0;
            $skippedCount = 0;

            foreach ($files as $filename => $content) {
                $filePath = $paginationPath . DIRECTORY_SEPARATOR . $filename;

                if (file_exists($filePath)) {
                    $this->line("<comment>Already exists:</comment> {$filename}");
                    $skippedCount++;
                    continue;
                }

                if (file_put_contents($filePath, $content) === false) {
                    throw new \RuntimeException("Failed to write file: {$filePath}");
                }

                $this->line("<info>Created:</info> {$filename}");
                $createdCount++;
            }

            $this->newLine();
            if ($createdCount > 0) {
                $this->line('<bg=green;options=bold> SUCCESS </> Pagination views published successfully.');
            } else {
                $this->line('<bg=yellow;options=bold> NOTE </> All pagination views already exist.');
            }
        } catch (\Exception $e) {
            $this->newLine();
            $this->line('<bg=red;options=bold> ERROR </> ' . $e->getMessage());
            $this->newLine();
            return 1;
        }

        $executionTime = microtime(true) - $startTime;
        $this->newLine();
        $this->line(sprintf(
            "<fg=yellow>⏱ Time:</> <fg=white>%.4fs</> <fg=#6C7280>(%d μs)</>",
            $executionTime,
            (int) ($executionTime * 1000000)
        ));
        $this->newLine();

        return 0;
    }

    /**
     * Get the jump pagination view content.
     *
     * @return string
     */
    private function getJumpPaginationView(): string
    {
        return <<<'EOT'
<!-- Pagination Links -->
@if ($paginator->hasPages())
    <div class="d-flex justify-content-between align-items-center">
        <!-- Jump to Page Dropdown -->
        <div class="d-flex align-items-center">
            <span class="me-2">Jump:</span>
            <select class="form-select form-select-sm" onchange="window.location.href = this.value">
                @for ($i = 1; $i <= $paginator->lastPage(); $i++)
                    <option value="{{ $paginator->url($i) }}" {{ $i == $paginator->currentPage() ? 'selected' : '' }}>
                        Page {{ $i }}
                    </option>
                @endfor
            </select>
        </div>

        <!-- Page X of Y -->
        <div class="ms-3">
            Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
        </div>

        <!-- Previous and Next Buttons -->
        <ul class="pagination mb-0">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled"><span class="page-link">« Previous</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">« Previous</a></li>
            @endif

            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next »</a></li>
            @else
                <li class="page-item disabled"><span class="page-link">Next »</span></li>
            @endif
        </ul>
    </div>
@endif
EOT;
    }

    /**
     * Get the number pagination view content.
     *
     * @return string
     */
    private function getNumberPaginationView(): string
    {
        return <<<'EOT'
<!-- Pagination Links -->
@if ($paginator->hasPages())
    <ul class="pagination">
        <!-- Previous Button -->
        @if ($paginator->onFirstPage())
            <li class="page-item disabled"><span class="page-link">« Previous</span></li>
        @else
            <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">« Previous</a></li>
        @endif

        <!-- Page Numbers -->
        @foreach ($paginator->numbers() as $page)
            @if (is_string($page))
                <li class="page-item disabled"><span class="page-link">{{ $page }}</span></li>
            @else
                <li class="page-item {{ $page == $paginator->currentPage() ? 'active' : '' }}">
                    <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                </li>
            @endif
        @endforeach

        <!-- Next Button -->
        @if ($paginator->hasMorePages())
            <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next »</a></li>
        @else
            <li class="page-item disabled"><span class="page-link">Next »</span></li>
        @endif
    </ul>
@endif
EOT;
    }
}
