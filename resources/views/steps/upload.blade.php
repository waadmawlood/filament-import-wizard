<div class="fiwa-step-upload">
    <h3>{{ __('filament-import-wizard::filament-import-wizard.steps.upload.title') }}</h3>
    <p>{{ __('filament-import-wizard::filament-import-wizard.steps.upload.description') }}</p>

    <div class="fiwa-upload-zone" onclick="document.getElementById('file-input').click()">
        <input type="file"
               id="file-input"
               wire:model="uploadedFile"
               accept=".csv,.xlsx,.xls"
               class="fiwa-file-input">

        <div class="fiwa-upload-placeholder">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="fiwa-upload-icon">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
            </svg>
            <p>{{ __('filament-import-wizard::filament-import-wizard.steps.upload.dropzone') }}</p>
            <span>{{ __('filament-import-wizard::filament-import-wizard.steps.upload.formats') }}</span>
        </div>
    </div>

    @if($this->uploadedFile)
        <div class="fiwa-file-preview">
            <span class="fiwa-file-preview-name">{{ $this->uploadedFile->getClientOriginalName() }}</span>
            <span class="fiwa-file-preview-status">{{ __('filament-import-wizard::filament-import-wizard.steps.upload.processing') }}</span>
        </div>
    @endif
</div>