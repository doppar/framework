<?php

namespace Phaseolies;

use Composer\Script\Event;

class Installer
{
    public static function postCreateProject(Event $event)
    {
        $io = $event->getIO();

        $io->write("<info>🎉 Setting up doppar skeleton application...</info>");

        if (!file_exists('.env')) {
            copy('.env.example', '.env');
            $io->write("<comment>  ✓ Created .env file from .env.example</comment>");
        }
    }
}
