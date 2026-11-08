<?php

declare(strict_types=1);

namespace App\Platform;

use Illuminate\Support\ServiceProvider;
use ReflectionClass;

abstract class FeatureModule extends ServiceProvider
{
    public function boot(): void
    {
        // Native provider registration and optional automatic discovery share validation.
        FeatureCatalog::load($this->app->basePath());
        $fileName = (new ReflectionClass($this))->getFileName();
        if ($fileName === false) {
            throw new \RuntimeException('Unable to locate the feature provider file.');
        }
        $root = dirname($fileName);

        foreach (['web', 'api'] as $transport) {
            $path = $root.'/Routes/'.$transport.'.php';
            if (is_file($path)) {
                // Each route file declares its own middleware, prefix and name group.
                $this->loadRoutesFrom($path);
            }
        }

        if (is_dir($root.'/Views')) {
            $this->loadViewsFrom($root.'/Views', strtolower(basename($root)));
        }
        if (is_dir($root.'/Database/Migrations')) {
            $this->loadMigrationsFrom($root.'/Database/Migrations');
        }
    }
}
