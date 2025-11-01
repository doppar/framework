<?php

namespace Phaseolies;

use Composer\Script\Event;

class Installer
{
    public static function postCreateProject(Event $event)
    {
        $io = $event->getIO();

        $io->write("<info>🚀 Setting up doppar skeleton application...</info>");

        if (!file_exists('.env')) {
            copy('.env.example', '.env');
            $io->write("<comment>  ✓ Created .env file from .env.example</comment>");
        } else {
            $io->write("<comment>  ⚠ .env file already exists, skipping</comment>");
        }

        $io->write("\n<info>🎉 Doppar project setup complete</info>");
    }

    public static function preInstall(Event $event)
    {
        $io = $event->getIO();
        $io->write("<info>🔍 Checking system requirements...</info>");

        $requirements = self::checkRequirements();

        foreach ($requirements as $requirement => $met) {
            if ($met) {
                $io->write("<comment>  ✓ {$requirement}</comment>");
            } else {
                $io->write("<error>  ✗ {$requirement}</error>");
            }
        }

        // Check if all requirements are met
        if (in_array(false, $requirements, true)) {
            $io->write("\n<error>Some system requirements are not met. Please fix them before continuing.</error>");
            exit(1);
        }

        $io->write("\n<info>All system requirements met..</info>");
    }

    protected static function checkRequirements()
    {
        return [
            'PHP >= 8.3' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'PDO Extension' => extension_loaded('pdo'),
            'MBString Extension' => extension_loaded('mbstring'),
            'JSON Extension' => extension_loaded('json'),
            'OpenSSL Extension' => extension_loaded('openssl'),
            'Tokenizer Extension' => extension_loaded('tokenizer'),
            'XML Extension' => extension_loaded('xml'),
            'Ctype Extension' => extension_loaded('ctype'),
        ];
    }
}
