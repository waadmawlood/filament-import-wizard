<?php

namespace Waad\FilamentImportWizard\Actions;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;

class ImportWizardAction extends Action
{
    protected ?string $modelClass = null;

    protected int $chunkSize = 1000;

    protected bool $enableUpsert = false;

    protected array $upsertKeys = ['id'];

    protected function setUp(): void
    {
        parent::setUp();

        $modalWidthConfig = Config::get('filament-import-wizard.modal_width', Width::Full);

        $this->modalSubmitAction(false);
        $this->modalCancelAction(false);
        $this->label(__('filament-import-wizard::filament-import-wizard.import_wizard.label'));
        $this->modalHeading(__('filament-import-wizard::filament-import-wizard.import_wizard.heading'));
        $this->modalWidth($modalWidthConfig);
        $this->closeModalByClickingAway(false);
        $this->modalContent(fn () => View::make('filament-import-wizard::wizard-modal', [
            'modelClass' => $this->getModelClass(),
            'chunkSize' => $this->chunkSize,
            'enableUpsert' => $this->enableUpsert,
            'upsertKeys' => $this->upsertKeys,
        ]));
    }

    public static function make(?string $name = 'importWizard'): static
    {
        return parent::make($name);
    }

    public function forModel(string $model): static
    {
        $this->modelClass = $model;

        return $this;
    }

    public function chunkSize(int $size): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    public function enableUpsert(bool $enable = true): static
    {
        $this->enableUpsert = $enable;

        return $this;
    }

    public function upsertKeys(array $keys): static
    {
        $this->upsertKeys = $keys;

        return $this;
    }

    public function setModalWidth(Width $width): static
    {
        $this->modalWidth($width);

        return $this;
    }

    public function getModelClass(): ?string
    {
        return $this->modelClass ?? $this->getModel();
    }
}
