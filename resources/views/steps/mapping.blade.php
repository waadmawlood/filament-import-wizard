<div class="fiwa-step-mapping">
    <h3>{{ __('filament-import-wizard::filament-import-wizard.steps.mapping.title') }}</h3>
    <p>{{ __('filament-import-wizard::filament-import-wizard.steps.mapping.description') }}</p>
    
    @if($modelClass)
        <div class="fiwa-model-info">
            <p class="fiwa-model-info-text">
                <strong>Model:</strong> {{ $modelClass }}
            </p>
        </div>
    @endif
    
    <div class="fiwa-mapping-grid" dir="ltr">
        @if(count($headers) > 0)
            @php
                $groupedColumns = $this->getGroupedModelColumns();
                $uniqueRelations = $this->getUniqueRelations();
            @endphp
            
            @foreach($headers as $header)
                <div class="fiwa-mapping-row">
                    <div class="fiwa-source-column">
                        <span class="fiwa-source-badge">{{ $header }}</span>
                    </div>
                    <div class="fiwa-mapping-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </div>
                    <div class="fiwa-mapping-selects">
                        <select wire:model.live="mappingTypes.{{ $header }}" class="fiwa-select">
                            <option value="field">Field</option>
                            <option value="relation">Relation</option>
                        </select>
                        
                        @if(($mappingTypes[$header] ?? 'field') === 'field')
                            <select wire:model.live="columnMappings.{{ $header }}" class="fiwa-select">
                                <option value="">{{ __('Skip') }}</option>
                                @if(!empty($groupedColumns['fields']))
                                    @foreach($groupedColumns['fields'] as $col)
                                        <option value="{{ $col }}">{{ $col }}</option>
                                    @endforeach
                                @endif
                            </select>
                        @else
                            <select wire:model.live="relationNames.{{ $header }}" class="fiwa-select">
                                <option value="">Select relation</option>
                                @if(!empty($uniqueRelations))
                                    @foreach($uniqueRelations as $relName => $relData)
                                        <option value="{{ $relName }}">{{ $relName }}</option>
                                    @endforeach
                                @endif
                            </select>

                            @php
                                $selectedRel = $relationNames[$header] ?? '';
                                $relFields = $this->getRelationFieldsFor($selectedRel);
                                $ownerKey = $this->getRelationOwnerKey($selectedRel);
                                $foreignKey = $this->getRelationForeignKey($selectedRel);
                                $relatedModel = $uniqueRelations[$selectedRel]['related_model'] ?? '';
                            @endphp

                            @if(!empty($relFields))
                                <select wire:model.live="relationFields.{{ $header }}" class="fiwa-select">
                                    <option value="">Select field</option>
                                    @foreach($relFields as $fld)
                                        <option value="{{ $selectedRel }}.{{ $fld }}">{{ $fld }}</option>
                                    @endforeach
                                </select>

                                {{-- Owner Key Select --}}
                                <select wire:model.live="relationOwnerKeys.{{ $header }}" class="fiwa-select" title="Owner Key ({{ $relatedModel }})">
                                    <option value="">Owner Key</option>
                                    @php
                                        // Get columns from related model for owner key options
                                        $ownerKeyOptions = [];
                                        if ($relatedModel && class_exists($relatedModel)) {
                                            $relatedModelInstance = new $relatedModel;
                                            $ownerKeyOptions = array_merge($relatedModelInstance->getFillable() ?? [], [$relatedModelInstance->getKeyName()]);
                                            $ownerKeyOptions = array_unique($ownerKeyOptions);
                                        }
                                    @endphp
                                    @foreach($ownerKeyOptions as $keyCol)
                                        <option value="{{ $keyCol }}">{{ $keyCol }} @if($keyCol === 'id')(auto)@endif</option>
                                    @endforeach
                                </select>

                                {{-- Foreign Key Select --}}
                                <select wire:model.live="relationForeignKeys.{{ $header }}" class="fiwa-select" title="Foreign Key ({{ $modelClass }})">
                                    <option value="">Foreign Key</option>
                                    @if(!empty($groupedColumns['fields']))
                                        @foreach($groupedColumns['fields'] as $fkCol)
                                            <option value="{{ $fkCol }}">{{ $fkCol }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            @else
                                <span class="fiwa-placeholder-span">—</span>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        @else
            <p class="fiwa-no-headers">{{ __('filament-import-wizard::filament-import-wizard.steps.mapping.no_columns') }}</p>
        @endif
    </div>
</div>