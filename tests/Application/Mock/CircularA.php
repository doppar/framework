<?php

namespace Tests\Application\Mock;

class CircularA
{
    public function __construct(public CircularB $b) {}
}