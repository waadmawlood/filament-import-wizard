@csrf
@php
    $fileParam = request()->get('file') ?? ($file ?? null);
    $modelParam = request()->get('model') ?? ($modelClass ?? null);
@endphp
@livewireStyles
@livewire('filament-import-wizard', ['file' => $fileParam, 'modelClass' => $modelParam])
@livewireScripts