<?php

namespace Phaseolies\Utilities;

class PathMatcher
{
    /**
     * Whether a request path matches a pattern in which "*" stands for anything.
     *
     * @param string $pattern
     * @param string $path
     * @return bool
     */
    public static function matches(string $pattern, string $path): bool
    {
        $regex = str_replace('\*', '.*', preg_quote('/' . ltrim($pattern, '/'), '#'));

        return preg_match('#^' . $regex . '\z#i', '/' . ltrim($path, '/')) === 1;
    }
}
