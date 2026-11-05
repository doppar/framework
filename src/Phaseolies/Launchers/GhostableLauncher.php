<?php

namespace Phaseolies\Launchers;

interface GhostableLauncher
{
    /**
     * Get the service identifiers that should trigger loading this provider.
     *
     * @return array<int, string>
     */
    public function ghosts(): array;
}
