<?php

namespace Tests\Application\Mock\Services;

class MockMutableService
{
    public string $state = 'initial';
    public int    $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
}
