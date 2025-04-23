<?php

namespace Phaseolies\Session\Contracts;

interface SessionHandlerInterface
{
    public function initialize(): void;
    public function start(): void;
    public function regenerate(): void;
    public function validate(): void;
    public function generateToken(): void;
}
