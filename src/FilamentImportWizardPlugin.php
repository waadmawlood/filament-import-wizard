<?php

namespace Waad\FilamentImportWizard;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentImportWizardPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-import-wizard';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook('panels::sidebar.start', function () {
            return '';
        });
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
