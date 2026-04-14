<div>
    @livewire(\Waad\FilamentImportWizard\Livewire\ImportWizard::class, [
        'modelClass' => $modelClass ?? '',
        'chunkSize' => $chunkSize ?? 1000,
        'enableUpsert' => $enableUpsert ?? true,
        'upsertKeys' => $upsertKeys ?? ['id'],
    ])
</div>