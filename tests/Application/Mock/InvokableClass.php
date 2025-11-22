<?php

namespace Tests\Application\Mock;

class InvokableClass
{
    public function __invoke(): string
    {
        return 'invoked';
    }
}