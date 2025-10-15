<?php

namespace Phaseolies\Database\Migration\Grammars;

use InvalidArgumentException;

class GrammarFactory
{
    /**
     * Create a grammar for the given driver.
     *
     * @param string $driver
     * @return Grammar
     * @throws \InvalidArgumentException
     */
    public static function make(string $driver): Grammar
    {
        $driver = strtolower($driver);

        return match ($driver) {
            'mysql' => new MySQLGrammar(),
            'sqlite' => new SQLiteGrammar(),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}"),
        };
    }
}
