<div class="fiwa-step-import">
    <h3>{{ __('filament-import-wizard::filament-import-wizard.steps.import.title') }}</h3>
    
    <?php $queueDriver = $this->getQueueDriver(); ?>
    
    @if($status === 'idle')
        <div class="fiwa-start-import">
            <p>{{ __('filament-import-wizard::filament-import-wizard.steps.import.ready', ['count' => $totalRows]) }}</p>
            <p style="font-size: 12px; color: var(--color-gray-500); margin-bottom: 16px;">
                {{ __('filament-import-wizard::filament-import-wizard.steps.import.queue') }}: <strong>{{ $queueDriver }}</strong>
            </p>
            <button type="button" wire:click="startImport" class="fiwa-btn-primary fiwa-btn-lg">
                {{ __('filament-import-wizard::filament-import-wizard.steps.import.start') }}
            </button>
        </div>
    @elseif($status === 'processing')
        <div class="fiwa-progress">
            @if($queueDriver === 'sync')
                <div style="text-align: center; padding: 20px;">
                    <p style="color: var(--color-success-500); font-weight: 600;">{{ __('filament-import-wizard::filament-import-wizard.steps.import.imported') }}</p>
                    <p>{{ number_format($totalRows) }} {{ __('filament-import-wizard::filament-import-wizard.steps.import.imported') }}</p>
                </div>
            @else
                <p style="text-align: center; color: var(--color-primary-500); font-weight: 500; margin-bottom: 16px;">
                    {{ $totalChunks ?? 0 }} {{ __('filament-import-wizard::filament-import-wizard.messages.queued', ['count' => $totalChunks ?? 0, 'driver' => $queueDriver]) }}
                </p>
                <?php $progress = $totalRows > 0 ? round(($processedRows / $totalRows) * 100) : 0; ?>
                <div class="fiwa-progress-bar">
                    <div class="fiwa-progress-fill" style="width: <?php echo $progress; ?>%"></div>
                </div>
                <div class="fiwa-progress-stats">
                    <span>{{ __('filament-import-wizard::filament-import-wizard.steps.import.processed', ['count' => number_format($processedRows)]) }}</span>
                    <span>{{ __('filament-import-wizard::filament-import-wizard.steps.import.success', ['count' => number_format($successRows)]) }}</span>
                    <span>{{ __('filament-import-wizard::filament-import-wizard.steps.import.failed', ['count' => number_format($failedRows)]) }}</span>
                </div>
            @endif
        </div>
    @elseif($status === 'completed')
        <div class="fiwa-completed">
            <div class="fiwa-completed-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h4>{{ __('filament-import-wizard::filament-import-wizard.steps.import.completed') }}</h4>
            <div class="fiwa-completed-stats">
                <div class="fi-stat">
                    <span class="fiwa-stat-value">{{ number_format($successRows) }}</span>
                    <span class="fiwa-stat-label">{{ __('filament-import-wizard::filament-import-wizard.steps.import.imported') }}</span>
                </div>
                @if($failedRows > 0)
                    <div class="fi-stat fi-stat-error">
                        <span class="fiwa-stat-value">{{ number_format($failedRows) }}</span>
                        <span class="fiwa-stat-label">{{ __('filament-import-wizard::filament-import-wizard.steps.import.failed') }}</span>
                        <button type="button" wire:click="downloadErrors" class="fiwa-download-errors">{{ __('filament-import-wizard::filament-import-wizard.steps.import.download_errors') }}</button>
                    </div>
                @endif
            </div>
        </div>
    @elseif($status === 'failed')
        <div class="fiwa-failed">
            <div class="fiwa-failed-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h4>{{ __('filament-import-wizard::filament-import-wizard.steps.import.failed_status') }}</h4>
            <p>{{ $errorMessage ?? __('filament-import-wizard::filament-import-wizard.steps.import.error_occurred') }}</p>
            <button type="button" wire:click="retryImport" class="fiwa-btn-secondary">{{ __('filament-import-wizard::filament-import-wizard.steps.import.retry') }}</button>
        </div>
    @endif
</div>