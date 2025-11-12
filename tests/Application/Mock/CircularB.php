<?php

namespace Tests\Application\Mock;

class CircularB
{
    public function __construct(public CircularA $a) {}
}