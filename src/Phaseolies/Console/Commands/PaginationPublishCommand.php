<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PaginationPublishCommand extends Command
{
    protected static $defaultName = 'publish:pagination';

    protected function configure()
    {
        $this
            ->setName('publish:pagination')
            ->setDescription('Publish custom pagination views to resources/views/pagination');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paginationPath = resource_path('vendor/views/pagination');

        if (!is_dir($paginationPath)) {
            mkdir($paginationPath, 0755, true);
        }

        $files = [
            'jump.blade.php' => $this->getJumpPaginationView(),
            'number.blade.php' => $this->getNumberPaginationView(),
        ];

        foreach ($files as $filename => $content) {
            $filePath = $paginationPath . DIRECTORY_SEPARATOR . $filename;
            if (!file_exists($filePath)) {
                file_put_contents($filePath, $content);
                $output->writeln("<info>Created:</info> $filePath");
            } else {
                $output->writeln("<comment>Skipped (already exists):</comment> $filePath");
            }
        }

        $output->writeln('<info>Pagination views published successfully.</info>');

        return Command::SUCCESS;
    }

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
