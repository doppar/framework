<?php

namespace Tests\Support;

class StrTest
{
    public static function urlHarmonize(string $url): string
    {
        return str_replace("\\", "/", $url);
    }
}
