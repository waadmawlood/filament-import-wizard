<?php

namespace Waad\FilamentImportWizard;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Waad\FilamentImportWizard\Livewire\ImportWizard;

class FilamentImportWizardServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-import-wizard';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasMigrations('1_create_import_sessions_table')
            ->hasTranslations()
            ->hasViews();
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('filament-import-wizard', __DIR__.'/../resources/css/filament-import-wizard.css'),
        ], package: 'waad/filament-import-wizard');

        Livewire::component('filament-import-wizard', ImportWizard::class);
    }
}
