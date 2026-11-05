<?php

namespace Phaseolies;

use Composer\Script\Event;

class Installer
{
    public static function postCreateProject(Event $event)
    {
        $io = $event->getIO();

        $io->write("<info>🎉 Setting up doppar skeleton application...</info>");

        if (!file_exists('env.toml')) {
            copy('env.toml.example', 'env.toml');
            $io->write("<comment>  ✓ Created env.toml file from env.toml.example</comment>");
        }
    }
}
