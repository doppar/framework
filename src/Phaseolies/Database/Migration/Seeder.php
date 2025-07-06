<?php

namespace Phaseolies\Database\Migration;

class Seeder
{
    /**
     * Call the given seeder class(es).
     *
     * @param  array|string  $seeders
     * @return void
     */
    protected function call($seeders): void
    {
        $seeders = is_array($seeders) ? $seeders : [$seeders];

        foreach ($seeders as $seeder) {
            if (!class_exists($seeder)) {
                throw new \RuntimeException("Seeder class {$seeder} not found");
            }

            $instance = app($seeder);

            if (method_exists($instance, 'run')) {
                $instance->run();
            } else {
                throw new \RuntimeException("Seeder {$seeder} must have a run() method");
            }

            if (php_sapi_name() === 'cli') {
                echo "Seeded: {$seeder}\n";
            }
        }
    }
}
