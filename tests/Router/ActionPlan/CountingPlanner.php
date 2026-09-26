<?php

namespace Tests\Router\ActionPlan;

use Phaseolies\Support\Router\Plan\ActionPlanner;

/**
 * Counts how often the (reflection based) planner is asked for a plan. A
 * request served from memo or from the compiled file must never reach it.
 */
final class CountingPlanner extends ActionPlanner
{
    /** @var array<int, string> */
    public array $actions = [];

    public int $closures = 0;

    public function forAction(string $class, string $method): array
    {
        $this->actions[] = $class . '::' . $method;

        return parent::forAction($class, $method);
    }

    public function forClosure(\Closure $closure): array
    {
        $this->closures++;

        return parent::forClosure($closure);
    }
}
