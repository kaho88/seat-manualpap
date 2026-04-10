<?php

namespace Seat\ManualPap;

use Seat\Services\AbstractSeatPlugin;

class ManualPapServiceProvider extends AbstractSeatPlugin
{
    public function boot(): void
    {
        $this->addRoutes();
        $this->addViews();
        $this->addTranslations();
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/package.sidebar.php', 'package.sidebar');
        $this->mergeConfigFrom(__DIR__ . '/Config/manualpap.php', 'manualpap');
        $this->registerPermissions(__DIR__ . '/Config/Permissions/manualpap.php', 'manualpap');
    }

    private function addRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Http/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/Http/routes/api.php');
    }

    private function addViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'manualpap');
    }

    private function addTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'manualpap');
    }

    public function getName(): string
    {
        return 'Manual PAP';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/hermesdj/seat-manual-pap';
    }

    public function getPackagistPackageName(): string
    {
        return 'seat-manual-pap';
    }

    public function getPackagistVendorName(): string
    {
        return 'hermesdj';
    }
}
