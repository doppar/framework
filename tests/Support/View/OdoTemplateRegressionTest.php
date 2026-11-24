<?php

namespace Tests\Unit\Support\View;

use Tests\Support\MockContainer;
use Phaseolies\Http\Controllers\Controller;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;

/**
 * Template behaviours that broke while building a real application.
 */
class OdoTemplateRegressionTest extends TestCase
{
    private Controller $controller;
    private string $viewDir;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new MockContainer();
        Container::setInstance($container);
        $container->bind('view', \Phaseolies\Support\View\Factory::class);

        $this->viewDir = sys_get_temp_dir() . '/doppar_odo_regression_' . uniqid();
        mkdir($this->viewDir, 0777, true);

        $this->controller = new Controller();
        $this->controller->setViewFolder($this->viewDir);

        $cache = (new \ReflectionClass(\Phaseolies\Support\View\View::class))->getProperty('cache');
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

    private function render(string $view, array $data = []): string
    {
        return trim(preg_replace('/\R+/', '', $this->controller->render($view, $data, true)));
    }

    public function testEchoExpressionMayContainNestedBrackets(): void
    {
        $this->putView('page', "[[ \$row['values'][\$column['key']] ]]");

        $this->assertSame('42', $this->render('page', [
            'row' => ['values' => ['P' => 42]],
            'column' => ['key' => 'P'],
        ]));
    }

    public function testEchoExpressionMayContainClosingTagInsideAString(): void
    {
        $this->putView('page', "[[ 'a]]b' ]]|[[! '<i>]]</i>' !]]");

        $this->assertSame('a]]b|<i>]]</i>', $this->render('page'));
    }

    public function testEscapedEchoTagIsOutputLiterally(): void
    {
        $this->putView('page', '#[[ $value ]]');

        $this->assertSame('[[ $value ]]', $this->render('page', ['value' => 'x']));
    }

    public function testPhpBlockCodeIsNotCompiledAsEchoTags(): void
    {
        $this->putView('page', "#php\n\$groups = ['goal' => [[], []]];\n#endphp\n[[ count(\$groups['goal']) ]]");

        $this->assertSame('2', $this->render('page'));
    }

    public function testOneLinePhpBlockCompiles(): void
    {
        $this->putView('page', "#php \$total = 2 + 3; #endphp[[ \$total ]]");

        $this->assertSame('5', $this->render('page'));
    }

    public function testClosingDirectiveRightAfterTextIsCompiled(): void
    {
        $this->putView('layout', "<title>#yield('title')</title>");
        $this->putView('page', "#extends('layout')\n#section('title')Page title#endsection");

        $this->assertSame('<title>Page title</title>', $this->render('page'));
    }

    public function testDirectiveWithExpressionRightAfterTextIsCompiled(): void
    {
        $this->putView('page', '3 games#if ($live) · live#endif');

        $this->assertSame('3 games · live', $this->render('page', ['live' => true]));
        $this->assertSame('3 games', $this->render('page', ['live' => false]));
    }

    public function testUrlFragmentNamedLikeADirectiveIsLeftAlone(): void
    {
        $this->putView('page', '<a href="/docs#section">Docs</a><a href="/faq#if">FAQ</a>');

        $this->assertSame('<a href="/docs#section">Docs</a><a href="/faq#if">FAQ</a>', $this->render('page'));
    }

    public function testIncludedViewDoesNotOverwriteParentVariables(): void
    {
        $this->putView('partial', "#php \$title = 'from partial'; #endphp");
        $this->putView('page', "#include('partial')[[ \$title ]]");

        $this->assertSame('from page', $this->render('page', ['title' => 'from page']));
    }

    public function testIncludeDataDoesNotLeakIntoTheNextInclude(): void
    {
        $this->putView('badge', "[[ isset(\$round) ? 'round' : 'square' ]]|");
        $this->putView('page', "#include('badge', ['round' => true])#include('badge')");

        $this->assertSame('round|square|', $this->render('page'));
    }

    public function testIncludedViewStillSeesParentVariables(): void
    {
        $this->putView('greeting', 'Hello [[ $user ]] from [[ $city ]]');
        $this->putView('page', "#include('greeting', ['city' => 'Dhaka'])");

        $this->assertSame('Hello Mahedi from Dhaka', $this->render('page', ['user' => 'Mahedi']));
    }

    public function testNestedFetchWithDifferentObjectsRendersEachObject(): void
    {
        $this->putView('card', '[[ $item->name ]]');
        $this->putView(
            'page',
            "[[! \$this->fetch('card', ['item' => \$first]) !]]|[[! \$this->fetch('card', ['item' => \$second]) !]]"
        );

        $this->assertSame('Arsenal|Chelsea', $this->render('page', [
            'first' => (object) ['name' => 'Arsenal'],
            'second' => (object) ['name' => 'Chelsea'],
        ]));
    }

    public function testNestedFetchWithPlainDataIsStillCached(): void
    {
        $this->putView('card', '[[ $club ]]');
        $this->putView('page', "[[! \$this->fetch('card', ['club' => 'Arsenal']) !]]");

        $this->assertSame('Arsenal', $this->render('page'));

        $cache = (new \ReflectionClass(\Phaseolies\Support\View\View::class))->getProperty('cache');
        $this->assertNotEmpty($cache->getValue());
    }

    public function testDataKeysNamedLikeRendererVariablesReachTheView(): void
    {
        $this->putView('page', '[[ $name ]]|[[ $data ]]|[[ $template ]]|[[ $cacheKey ]]');

        $this->assertSame('Mahedi|d|t|c', $this->render('page', [
            'name' => 'Mahedi',
            'data' => 'd',
            'template' => 't',
            'cacheKey' => 'c',
        ]));
    }

    public function testRendererVariablesDoNotLeakIntoTheView(): void
    {
        $this->putView('page', "[[ isset(\$name) ? 'leaked' : 'clean' ]]");

        $this->assertSame('clean', $this->render('page'));
    }

    public function testVariableSetInAViewIsVisibleInItsLayout(): void
    {
        $this->putView('layout', '<title>[[ $pageTitle ]]</title>#yield(\'body\')');
        $this->putView('page', "#extends('layout')\n#php \$pageTitle = 'Scores'; #endphp\n#section('body')ok#endsection");

        $this->assertSame('<title>Scores</title>ok', $this->render('page'));
    }
}
