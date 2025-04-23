<?php

namespace Phaseolies;

use Composer\Script\Event;

class Installer
{
    public static function postCreateProject(Event $event)
    {
        $io = $event->getIO();
        $io->write("Setting up Doppar project.");

        copy('.env.example', '.env');
    }
}
