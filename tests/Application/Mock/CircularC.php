<?php

namespace Tests\Application\Mock;

class CircularC
{
    public function __construct(public CircularA $a) {}
}