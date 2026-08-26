<?php

namespace Phaseolies\Console\Commands\Tests;

use Phaseolies\Console\Schedule\Command;
use Symfony\Component\Process\Process;

class UnitTestCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'unit:test
                       {--class= : Specific test class file path to run}
                       {--details : Add --testdox and --disallow-test-output}
                       {--filter= : Filter tests by name pattern}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run unit tests with detailed';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        $this->displayBanner();

        $classPath = $this->option('class');
        $filter = $this->option('filter');
        $testdox = $this->option('details');

        $command = $this->buildPHPUnitCommand($classPath, $filter, $testdox);

        $this->line('🔍 Running tests...', 'fg=yellow');
        $this->newLine();

        $process = new Process($command, base_path());
        $process->setTimeout(300);

        $outputBuffer = '';

        $process->run(function ($type, $buffer) use (&$outputBuffer) {
            $outputBuffer .= $buffer;
            $this->formatAndDisplayOutput($buffer);
        });

        $exitCode = $process->getExitCode();

        $duration = round(microtime(true) - $startTime, 3);
        $this->displaySummary($outputBuffer, $duration, $exitCode);

        return $exitCode;
    }

    /**
     * Build PHPUnit command
     *
     * @param string|null $classPath
     * @param string|null $filter
     * @param string|null $testdox
     * @return array
     */
    private function buildPHPUnitCommand(?string $classPath, ?string $filter, ?string $testdox): array
    {
        $command = [base_path('vendor/bin/phpunit')];

        // Add specific test file if provided
        if ($classPath) {
            $command[] = base_path($classPath);
        }

        if ($filter) {
            $command[] = '--filter';
            $command[] = $filter;
        }

        if($testdox){
            $command[] = '--testdox';
            $command[] = '--disallow-test-output';
            $command[] = '--colors=never';
        }

        return $command;
    }

    /**
     * Format and display output in real-time
     *
     * @param string $buffer
     * @return void
     */
    private function formatAndDisplayOutput(string $buffer): void
    {
        static $inFailureDetails = false;

        $lines = explode("\n", $buffer);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Skip PHPUnit header lines
            if (
                str_starts_with($line, 'PHPUnit') ||
                str_starts_with($line, 'Runtime:') ||
                str_starts_with($line, 'Configuration:') ||
                preg_match('/^[\.FESIRw]+\s+\d+\s*\/\s*\d+/', $line) ||
                str_starts_with($line, 'Time:')
            ) {
                continue;
            }

            // Detect test class headers
            if (preg_match('/^([A-Z][A-Za-z0-9 ]+)\s+\(([^)]+)\)$/', trim($line), $matches)) {
                $this->newLine();
                $this->line("✓ {$matches[2]}", 'fg=white;options=bold');
                $this->line(str_repeat('─', 60), 'fg=gray');
                $inFailureDetails = false;
                continue;
            }

            // Detect passed tests
            if (preg_match('/^[\s]*[✔]\s*(.+)$/u', $line, $matches)) {
                $this->line("  ✓ {$matches[1]}", 'fg=green');
                $inFailureDetails = false;
                continue;
            }

            // Detect failed tests
            if (preg_match('/^\s*[✘✗✖×xX]\s+(.+)$/u', $line, $matches)) {
                $this->line("  ✗ {$matches[1]}", 'fg=red;options=bold');
                $inFailureDetails = true;
                continue;
            }

            // Detect skipped/incomplete tests
            if (preg_match('/^\s*[→➜↩➔]\s+(.+)$/', $line, $matches)) {
                $this->line("  ⊘ {$matches[1]}", 'fg=yellow');
                $inFailureDetails = false;
                continue;
            }

            // Show failure details
            if ($inFailureDetails && preg_match('/^\s*[│├┐┴┤┼┘└┌┬](.*)$/', $line, $matches)) {
                $detail = trim($matches[1]);

                // Remove any leading box-drawing characters from the detail
                $detail = preg_replace('/^[│├┐┴┤┼┘└┌┬\s]+/', '', $detail);
                $detail = trim($detail);

                if (!empty($detail)) {
                    // Check if it's a failure message
                    if (str_contains($detail, 'Failed asserting')) {
                        $this->line("    × {$detail}", 'fg=red');
                    }
                    // Check if it's a file path
                    elseif (preg_match('/^(\/[^\s]+\.php):(\d+)$/', $detail, $pathMatches)) {
                        $this->line("    📍 {$pathMatches[1]}:{$pathMatches[2]}", 'fg=yellow');
                    }
                    // Show other non-empty details
                    elseif (strlen($detail) > 1) {
                        $this->line("    ✔ {$detail}", 'fg=gray');
                    }
                }
                continue;
            }

            // Stop processing failure details when we hit summary section
            if (
                str_contains($line, 'FAILURES!')
                || str_contains($line, 'ERRORS!')
                || str_contains($line, 'OK (')
            ) {
                $inFailureDetails = false;
            }
        }
    }

    /**
     * Display an eye-catching banner
     *
     * @return void
     */
    private function displayBanner(): void
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════════╗', 'fg=cyan;options=bold');
        $this->line('║                                                              ║', 'fg=cyan;options=bold');
        $this->line('║              🧪  DOPPAR UNIT TEST RUNNER  🧪                 ║', 'fg=cyan;options=bold');
        $this->line('║                                                              ║', 'fg=cyan;options=bold');
        $this->line('╚══════════════════════════════════════════════════════════════╝', 'fg=cyan;options=bold');
        $this->newLine();
    }

    /**
     * Display the final summary
     *
     * @param string $output
     * @param float $duration
     * @param int $exitCode
     * @return void
     */
    private function displaySummary(string $output, float $duration, int $exitCode): void
    {
        $this->newLine();
        $this->line(str_repeat('═', 60), 'fg=cyan;options=bold');
        $this->newLine();

        // Summary header
        $this->line('📊 TEST SUMMARY', 'fg=cyan;options=bold');
        $this->line(str_repeat('─', 60), 'fg=cyan');
        $this->newLine();

        // Parse summary from PHPUnit output
        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;
        $skippedTests = 0;
        $assertions = 0;

        // Parse "OK (X tests, Y assertions)" or "FAILURES! Tests: X, Assertions: Y, Failures: Z"
        if (preg_match('/OK \((\d+) test[s]?, (\d+) assertion[s]?\)/', $output, $matches)) {
            $totalTests = (int) $matches[1];
            $passedTests = (int) $matches[1];
            $assertions = (int) $matches[2];
        } elseif (preg_match('/Tests:\s*(\d+),\s*Assertions:\s*(\d+)(?:,\s*Failures:\s*(\d+))?(?:,\s*Errors:\s*(\d+))?(?:,\s*Skipped:\s*(\d+))?/', $output, $matches)) {
            $totalTests = (int) $matches[1];
            $assertions = (int) $matches[2];
            $failedTests = isset($matches[3]) ? (int) $matches[3] : 0;
            $failedTests += isset($matches[4]) ? (int) $matches[4] : 0; // Add errors to failures
            $skippedTests = isset($matches[5]) ? (int) $matches[5] : 0;
            $passedTests = $totalTests - $failedTests - $skippedTests;
        }

        // Display stats
        $this->line("  Total Tests:    {$totalTests}", 'fg=white;options=bold');
        $this->line("  ✓ Passed:       {$passedTests}", 'fg=green;options=bold');

        if ($failedTests > 0) {
            $this->line("  ✗ Failed:       {$failedTests}", 'fg=red;options=bold');
        }

        if ($skippedTests > 0) {
            $this->line("  ⊘ Skipped:      {$skippedTests}", 'fg=yellow');
        }

        if ($assertions > 0) {
            $this->line("  📝 Assertions:  {$assertions}", 'fg=cyan');
        }

        $this->newLine();

        // Success rate
        $successRate = $totalTests > 0
            ? round(($passedTests / $totalTests) * 100, 2)
            : 0;

        $this->line("  Success Rate:   {$successRate}%", $successRate === 100.0 ? 'fg=green;options=bold' : 'fg=yellow');
        $this->line("  ⏱ Duration:      {$duration}s", 'fg=gray');

        $this->newLine();

        // Final result
        $this->line(str_repeat('═', 60), 'fg=cyan;options=bold');
        $this->newLine();

        if ($exitCode === 0 && $totalTests > 0) {
            $this->line('🎉 ALL TESTS PASSED! 🎉', 'fg=green;options=bold');
            $assertionText = $assertions > 0 ? " with {$assertions} assertion(s)" : "";
            $this->newLine();
            $this->displaySuccess("Test suite completed successfully{$assertionText}");
        } else if ($totalTests === 0) {
            $this->displayWarning('No tests were executed');
        } else {
            $this->line('❌ SOME TESTS FAILED', 'fg=red;options=bold');
            $this->newLine();
            $this->displayError("Test suite completed with {$failedTests} failure(s)");
        }

        $this->newLine();
    }
}
