<?php

namespace Tests\Unit\Support\View;

use Tests\Support\MockContainer;
use Phaseolies\Http\Controllers\Controller;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for a nested-render corruption bug in View::fetch().
 *
 * The Controller/View instance is a singleton for the whole request (see
 * RippleLauncher's `Controller::class` binding), and $parents / the
 * '__current_template__' block used to be flat instance state shared by
 * every fetch() call. A *nested* fetch() — e.g. a pagination widget
 * rendering its own partial view from inside an already in-flight
 * #extends page — would drain the outer call's still-pending parent
 * template (its layout) via the shared $parents queue, then overwrite
 * '__current_template__' with its own result, leaving the outer render
 * with nothing once it returned. In production this showed up as a
 * completely blank page on any view whose layout called
 * paginator(...)->links()/linkWithJumps() while a matching
 * vendor/pagination/*.odo.php template existed.
 */
class ViewFetchReentrancyTest extends TestCase
{
    private Controller $controller;
    private string $viewDir;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new MockContainer();
        Container::setInstance($container);
        $container->bind('view', \Phaseolies\Support\View\Factory::class);

        $this->viewDir = sys_get_temp_dir() . '/doppar_view_reentrancy_' . uniqid();
        mkdir($this->viewDir, 0777, true);

        $this->controller = new Controller();
        $this->controller->setViewFolder($this->viewDir);

        // View::$cache is keyed by view name + data only (not file content
        // or mtime), and is static/process-wide — reset it so each test's
        // "page"/"partial" names don't collide with another test's cached
        // rendering of a same-named-but-different-content view.
        $reflection = new \ReflectionClass(\Phaseolies\Support\View\View::class);
        $cache = $reflection->getProperty('cache');
        $cache->setValue(null, []);
    }

    protected function tearDown(): void
    {
        Container::forgetInstance();

        foreach (glob($this->viewDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->viewDir);

        parent::tearDown();
    }

    private function putView(string $name, string $contents): void
    {
        file_put_contents($this->viewDir . '/' . $name . '.odo.php', $contents);
    }

    public function testNestedFetchDoesNotCorruptAnInFlightExtendsRender(): void
    {
        // A layout consumed via #extends/#yield — mirrors a real app's
        // layouts.app.odo.php wrapping page content.
        $this->putView('layout', 'LAYOUT-BEFORE|#yield(\'content\')|LAYOUT-AFTER');

        // The page being rendered makes a *nested* fetch() call from
        // inside its own content section — exactly what
        // Paginator::links()/linkWithJumps() does when a matching
        // vendor/pagination/*.odo.php view exists.
        $this->putView(
            'page',
            "#extends('layout')\n#section('content')\nPAGE-BEFORE|[[! \$this->fetch('partial', []) !]]|PAGE-AFTER\n#endsection"
        );

        // A standalone partial — does not itself extend anything.
        $this->putView('partial', 'PARTIAL-CONTENT');

        $html = $this->controller->render('page', [], true);

        $this->assertSame(
            'LAYOUT-BEFORE|PAGE-BEFORE|PARTIAL-CONTENT|PAGE-AFTER|LAYOUT-AFTER',
            $this->normalize($html)
        );
    }

    public function testDeeplyNestedFetchesAreEachFullyIsolated(): void
    {
        $this->putView('layout', 'L[#yield(\'content\')]L');

        $this->putView(
            'page',
            "#extends('layout')\n#section('content')\n" .
                "P1[[! \$this->fetch('partial-a', []) !]]P2[[! \$this->fetch('partial-b', []) !]]P3\n" .
                "#endsection"
        );

        // partial-a itself makes another nested call.
        $this->putView('partial-a', "A1[[! \$this->fetch('partial-nested', []) !]]A2");
        $this->putView('partial-nested', 'NESTED');
        $this->putView('partial-b', 'B');

        $html = $this->controller->render('page', [], true);

        $this->assertSame('L[P1A1NESTEDA2P2BP3]L', trim($html));
    }
}
