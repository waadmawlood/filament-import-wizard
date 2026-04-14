<div class="fiwa-step-review">
    <h3>{{ __('filament-import-wizard::filament-import-wizard.steps.review.title') }}</h3>
    <p>{{ __('filament-import-wizard::filament-import-wizard.steps.review.description') }}</p>
    
    <div class="fiwa-preview-wrapper">
        <table class="fiwa-preview-table">
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($previewData, 0, 20) as $row)
                    <tr class="{{ isset($row['_errors']) && count($row['_errors']) > 0 ? 'fiwa-row-error' : '' }}">
                        @foreach($headers as $header)
                            <td>
                                {{ $row[$header] ?? '' }}
                                @if(isset($row['_errors'][$header]))
                                    <span class="fiwa-error-badge" title="{{ $row['_errors'][$header] }}">!</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    @if(count($errors) > 0)
        <div class="fiwa-validation-errors">
            <h4>{{ __('filament-import-wizard::filament-import-wizard.steps.review.validation_errors') }}</h4>
            <ul>
                @foreach($errors as $error)
                    <li>{{ __('filament-import-wizard::filament-import-wizard.steps.review.row') }} {{ $error['row'] }}: {{ implode(', ', $error['errors']) }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <div class="fiwa-summary">
        <div class="fiwa-summary-item">
            <span class="fiwa-label">{{ __('filament-import-wizard::filament-import-wizard.steps.review.total_rows') }}</span>
            <span class="fiwa-value">{{ number_format($totalRows) }}</span>
        </div>
        <div class="fiwa-summary-item">
            <span class="fiwa-label">{{ __('filament-import-wizard::filament-import-wizard.steps.review.errors') }}</span>
            <span class="fiwa-value fiwa-value-error">{{ count($errors) }}</span>
        </div>
    </div>

    <div class="fiwa-upsert-settings">
        <h4>{{ __('filament-import-wizard::filament-import-wizard.steps.review.import_settings') }}</h4>

        <div class="fiwa-upsert-checkbox-wrapper">
            <label class="fiwa-upsert-label">
                <input type="checkbox" wire:model="enableUpsert" class="fiwa-upsert-checkbox">
                {{ __('filament-import-wizard::filament-import-wizard.steps.review.enable_upsert') }}
            </label>
        </div>

        @if($enableUpsert)
        <div>
            <label class="fiwa-upsert-field-label">{{ __('filament-import-wizard::filament-import-wizard.steps.review.upsert_keys') }}</label>
            <input dir="ltr" type="text"
                   wire:model="upsertKeys"
                   class="fiwa-upsert-input"
                   placeholder="{{ __('filament-import-wizard::filament-import-wizard.steps.review.upsert_keys_placeholder') }}">
            <p class="fiwa-upsert-help">{{ __('filament-import-wizard::filament-import-wizard.steps.review.upsert_keys_help') }}</p>
        </div>
        @endif
    </div>
</div>