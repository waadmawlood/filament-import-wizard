<div>
    <div class="fiwa-container">
        <div class="fiwa-header">
            <h2>{{ __('filament-import-wizard::filament-import-wizard.steps.import.title') }}</h2>
        </div>

        <div class="fiwa-steps">
            <div class="fiwa-step {{ $step >= 1 ? 'active' : '' }}" wire:click="setStep(1)">
                <span class="fiwa-step-number">1</span>
                <span class="fiwa-step-label">{{ __('filament-import-wizard::filament-import-wizard.steps.upload.title') }}</span>
            </div>
            <div class="fiwa-step {{ $step >= 2 ? 'active' : '' }}" wire:click="setStep(2)">
                <span class="fiwa-step-number">2</span>
                <span class="fiwa-step-label">{{ __('filament-import-wizard::filament-import-wizard.steps.mapping.title') }}</span>
            </div>
            <div class="fiwa-step {{ $step >= 3 ? 'active' : '' }}" wire:click="setStep(3)">
                <span class="fiwa-step-number">3</span>
                <span class="fiwa-step-label">{{ __('filament-import-wizard::filament-import-wizard.steps.review.title') }}</span>
            </div>
            <div class="fiwa-step {{ $step >= 4 ? 'active' : '' }}">
                <span class="fiwa-step-number">4</span>
                <span class="fiwa-step-label">{{ __('filament-import-wizard::filament-import-wizard.steps.import.title') }}</span>
            </div>
        </div>

        <?php $isDisabled = $status === 'processing'; ?>
        <div class="fiwa-content" @if($isDisabled) style="pointer-events: none; opacity: 0.6;" @endif>
            @switch($step)
                @case(1)
                    @include('filament-import-wizard::steps.upload')
                    @break
                @case(2)
                    @include('filament-import-wizard::steps.mapping')
                    @break
                @case(3)
                    @include('filament-import-wizard::steps.review')
                    @break
                @case(4)
                    @include('filament-import-wizard::steps.import')
                    @break
            @endswitch
        </div>

        <div class="fiwa-actions" @if($isDisabled) style="pointer-events: none; opacity: 0.6;" @endif>
            @if($step > 1 && $step < 4)
                <button type="button" wire:click="previousStep" class="fiwa-btn-secondary">
                    {{ __('filament-import-wizard::filament-import-wizard.navigation.previous') }}
                </button>
            @endif
            
            @if($step < 4)
                <button type="button" wire:click="nextStep" class="fiwa-btn-primary">
                    {{ __('filament-import-wizard::filament-import-wizard.navigation.next') }}
                </button>
            @endif
        </div>
    </div>
</div>