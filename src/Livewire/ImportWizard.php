<?php

namespace Waad\FilamentImportWizard\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use League\Csv\Bom;
use League\Csv\Reader;
use League\Csv\Writer;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Waad\FilamentImportWizard\Jobs\ProcessImportChunk;
use Waad\FilamentImportWizard\Models\ImportSession;

class ImportWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public ?ImportSession $session = null;

    public ?UploadedFile $uploadedFile = null;

    public array $headers = [];

    public array $columnMappings = [];

    public array $previewData = [];

    public array $errors = [];

    public int $totalRows = 0;

    public int $processedRows = 0;

    public int $totalChunks = 0;

    public int $successRows = 0;

    public int $failedRows = 0;

    public string $status = 'idle';

    public string $modelClass = '';

    public bool $enableUpsert = false;

    public array $upsertKeys = ['id'];

    public int $chunkSize = 1000;

    public array $mappingTypes = [];

    public array $relationNames = [];

    public array $relationFields = [];

    public array $relationOwnerKeys = [];

    public array $relationForeignKeys = [];

    public ?string $errorMessage = null;

    protected array $modelRules = [];

    public function getUniqueRelations(): array
    {
        if (! $this->modelClass || ! class_exists($this->modelClass)) {
            return [];
        }

        $model = new $this->modelClass;
        $uniqueRelations = [];

        try {
            $methods = get_class_methods($model);
            $baseMethods = get_class_methods(Model::class);

            foreach ($methods as $method) {
                if (in_array($method, $baseMethods)) {
                    continue;
                }
                if (str_starts_with($method, 'get') || str_starts_with($method, 'scope')) {
                    continue;
                }

                $reflectionMethod = new \ReflectionMethod($model, $method);
                if ($reflectionMethod->getNumberOfParameters() > 0) {
                    continue;
                }

                if (! is_callable([$model, $method])) {
                    continue;
                }

                try {
                    $result = $model->$method();
                } catch (\ArgumentCountError $e) {
                    continue;
                }

                if (! $result instanceof Relation) {
                    continue;
                }

                if (! $result instanceof BelongsTo) {
                    continue;
                }

                $relationName = $method;
                $relatedModel = $result->getRelated();
                $foreignKeyName = $result->getForeignKeyName(); // e.g., 'category_id'

                $relatedFillable = $relatedModel->getFillable() ?? [];
                if (empty($relatedFillable)) {
                    try {
                        $relatedFillable = Schema::getColumnListing($relatedModel->getTable()) ?? [];
                    } catch (\Exception $e) {
                        $relatedFillable = [];
                    }
                }

                // Find a suitable owner key (default to 'id', import logic handles auto-increment smartly)
                $ownerKeyName = $relatedModel->getKeyName(); // e.g., 'id'

                $uniqueRelations[$relationName] = [
                    'fields' => $relatedFillable,
                    'owner_key' => $ownerKeyName,
                    'foreign_key' => $foreignKeyName,
                    'related_model' => get_class($relatedModel),
                ];
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return $uniqueRelations;
    }

    public function getRelationFieldsFor(string $relationName): array
    {
        $rels = $this->getUniqueRelations();

        return $rels[$relationName]['fields'] ?? [];
    }

    public function getRelationOwnerKey(string $relationName): string
    {
        $rels = $this->getUniqueRelations();

        return $rels[$relationName]['owner_key'] ?? 'id';
    }

    public function getRelationForeignKey(string $relationName): string
    {
        $rels = $this->getUniqueRelations();

        return $rels[$relationName]['foreign_key'] ?? '';
    }

    protected function findSuitableOwnerKey(object $relatedModel, array $fillable): string
    {
        // Priority: 'name' > unique columns > primary key
        if (in_array('name', $fillable)) {
            return 'name';
        }

        // Check for unique columns from schema
        try {
            $table = $relatedModel->getTable();
            $indexes = Schema::getIndexes($table);

            foreach ($indexes as $index) {
                if ($index['unique'] ?? false) {
                    $columns = $index['columns'] ?? [];
                    // Prefer single-column unique indexes
                    if (count($columns) === 1) {
                        $column = $columns[0];
                        // Prefer non-primary key columns (like 'name', 'slug', etc.)
                        if ($column !== $relatedModel->getKeyName() && in_array($column, $fillable)) {
                            return $column;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Fallback to primary key
        return $relatedModel->getKeyName();
    }

    public function onMappingTypeChanged(string $header): void
    {
        $type = $this->mappingTypes[$header] ?? 'field';

        if ($type === 'field') {
            $this->columnMappings[$header] = '';
            $this->relationNames[$header] = '';
            $this->relationFields[$header] = '';
            $this->relationOwnerKeys[$header] = '';
            $this->relationForeignKeys[$header] = '';
        } else {
            $this->columnMappings[$header] = '';
            $this->relationNames[$header] = '';
            $this->relationFields[$header] = '';
            $this->relationOwnerKeys[$header] = '';
            $this->relationForeignKeys[$header] = '';
        }
    }

    public function onRelationChanged(string $header): void
    {
        $selectedRel = $this->relationNames[$header] ?? '';

        if ($selectedRel) {
            $rels = $this->getUniqueRelations();
            if (isset($rels[$selectedRel]) && count($rels[$selectedRel]['fields']) > 0) {
                $this->relationFields[$header] = $selectedRel.'.'.$rels[$selectedRel]['fields'][0];
                $this->relationOwnerKeys[$header] = $rels[$selectedRel]['owner_key'];
                $this->relationForeignKeys[$header] = $rels[$selectedRel]['foreign_key'];
                $this->columnMappings[$header] = $selectedRel.'.'.$rels[$selectedRel]['fields'][0].'|'.$rels[$selectedRel]['owner_key'].'|'.$rels[$selectedRel]['foreign_key'];
            } else {
                $this->relationFields[$header] = '';
                $this->relationOwnerKeys[$header] = '';
                $this->relationForeignKeys[$header] = '';
                $this->columnMappings[$header] = '';
            }
        } else {
            $this->relationFields[$header] = '';
            $this->relationOwnerKeys[$header] = '';
            $this->relationForeignKeys[$header] = '';
            $this->columnMappings[$header] = '';
        }
    }

    public function onRelationFieldChanged(string $header): void
    {
        $value = $this->relationFields[$header] ?? '';
        if ($header && $value) {
            $ownerKey = $this->relationOwnerKeys[$header] ?? '';
            $foreignKey = $this->relationForeignKeys[$header] ?? '';

            if ($ownerKey && $foreignKey) {
                $this->columnMappings[$header] = $value.'|'.$ownerKey.'|'.$foreignKey;
            } else {
                $this->columnMappings[$header] = $value;
            }
        }
    }

    // Updated hooks for wire:model.live
    public function updatedMappingTypes($value, string $key): void
    {
        // $key is like "mappingTypes.header_name" so we extract the header
        $header = str_replace('mappingTypes.', '', $key);
        $this->onMappingTypeChanged($header);
    }

    public function updatedRelationNames($value, string $key): void
    {
        $header = str_replace('relationNames.', '', $key);
        $this->onRelationChanged($header);
    }

    public function updatedRelationFields($value, string $key): void
    {
        $header = str_replace('relationFields.', '', $key);
        $this->onRelationFieldChanged($header);
    }

    public function updatedRelationOwnerKeys($value, string $key): void
    {
        $header = str_replace('relationOwnerKeys.', '', $key);
        // Update columnMappings with the full relation field + owner/foreign keys
        $relField = $this->relationFields[$header] ?? '';
        $ownerKey = $this->relationOwnerKeys[$header] ?? '';
        $foreignKey = $this->relationForeignKeys[$header] ?? '';

        if ($relField && $ownerKey && $foreignKey) {
            $this->columnMappings[$header] = $relField.'|'.$ownerKey.'|'.$foreignKey;
        } elseif ($relField) {
            $this->columnMappings[$header] = $relField;
        }
    }

    public function updatedRelationForeignKeys($value, string $key): void
    {
        $header = str_replace('relationForeignKeys.', '', $key);
        // Update columnMappings with the full relation field + owner/foreign keys
        $relField = $this->relationFields[$header] ?? '';
        $ownerKey = $this->relationOwnerKeys[$header] ?? '';
        $foreignKey = $this->relationForeignKeys[$header] ?? '';

        if ($relField && $ownerKey && $foreignKey) {
            $this->columnMappings[$header] = $relField.'|'.$ownerKey.'|'.$foreignKey;
        } elseif ($relField) {
            $this->columnMappings[$header] = $relField;
        }
    }

    protected ?string $fileFromUrl = null;

    protected array $rules = [
        'uploadedFile' => 'file|mimes:csv,xlsx,xls|max:51200',
    ];

    protected function rules(): array
    {
        return [
            'uploadedFile' => $this->step === 1 ? 'required|file|mimes:csv,xlsx,xls|max:51200' : 'file|mimes:csv,xlsx,xls|max:51200',
        ];
    }

    public function mount(?string $file = null, ?string $modelClass = null, ?string $enableUpsert = null, ?array $upsertKeys = null, ?int $chunkSize = null)
    {
        if ($modelClass) {
            $this->modelClass = $modelClass;
        } else {
            $this->modelClass = request()->get('model', '');
        }

        if ($enableUpsert !== null) {
            $this->enableUpsert = $enableUpsert === '1' || $enableUpsert === 'true';
        } else {
            $this->enableUpsert = request()->get('enableUpsert', true);
        }

        if ($upsertKeys !== null) {
            $this->upsertKeys = $upsertKeys;
        } else {
            $upsertKeysParam = request()->get('upsertKeys');
            if ($upsertKeysParam) {
                $this->upsertKeys = array_map('trim', explode(',', $upsertKeysParam));
            }
        }

        if ($chunkSize !== null) {
            $this->chunkSize = $chunkSize;
        }

        $file = $file ?? request()->get('file');
        if ($file) {
            $this->loadFromFile($file);
        }
    }

    protected function loadFromFile(string $file): void
    {
        $possiblePaths = [
            storage_path('app/imports/'.$file),
            storage_path('app/livewire-tmp/'.$file),
            storage_path('app/livewire/tmp/'.$file),
            storage_path('app/'.$file),
        ];

        $path = null;
        foreach ($possiblePaths as $possiblePath) {
            if (file_exists($possiblePath)) {
                $path = $possiblePath;
                break;
            }
        }

        if (! $path) {
            return;
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION);
        $data = $this->parseFile($path, $extension);

        $this->headers = $data['headers'];
        $this->previewData = array_slice($data['rows'], 0, 20);
        $this->totalRows = count($data['rows']);

        $this->autoMapColumns();

        $this->session = ImportSession::create([
            'user_id' => auth()->id() ?? null,
            'tenant_id' => $this->getTenantId(),
            'model_class' => $this->modelClass,
            'file_path' => $file,
            'file_name' => basename($file),
            'file_size' => filesize($path),
            'file_type' => mime_content_type($path),
            'headers' => $this->headers,
            'column_mappings' => $this->columnMappings,
            'total_rows' => $this->totalRows,
            'step' => 2,
            'status' => 'pending',
            'enable_upsert' => $this->enableUpsert,
            'upsert_keys' => $this->upsertKeys,
        ]);

        $this->step = 2;
    }

    protected function loadFromSession(int $sessionId): void
    {
        $this->session = ImportSession::find($sessionId);

        if ($this->session) {
            $this->step = $this->session->step;
            $this->headers = $this->session->headers ?? [];
            $this->columnMappings = $this->session->column_mappings ?? [];
            $this->totalRows = $this->session->total_rows;
            $this->processedRows = $this->session->processed_rows;
            $this->successRows = $this->session->success_rows;
            $this->failedRows = $this->session->failed_rows;
            $this->status = $this->session->status;
            $this->enableUpsert = $this->session->enable_upsert ?? true;
            $this->upsertKeys = $this->session->upsert_keys ?? ['id'];
        }
    }

    public function render(): View
    {
        return view('filament-import-wizard::wizard');
    }

    public function updatedUploadedFile(?TemporaryUploadedFile $file)
    {
        if (! $file) {
            return;
        }

        $this->validate();

        $this->processUploadedFile($file);
    }

    protected function processUploadedFile(UploadedFile $file): void
    {
        $clientOriginalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: pathinfo($clientOriginalName, PATHINFO_EXTENSION);

        $importsDir = storage_path('app/imports');
        if (! is_dir($importsDir)) {
            mkdir($importsDir, 0755, true);
        }

        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            $this->errorMessage = 'Uploaded file not found';

            return;
        }

        $newFileName = uniqid('', true).'.'.$extension;
        $filePath = 'imports/'.$newFileName;
        $fullPath = storage_path('app/'.$filePath);

        $copyResult = copy($realPath, $fullPath);
        if (! $copyResult) {
            $this->errorMessage = 'Failed to copy uploaded file';

            return;
        }

        $data = $this->parseFile($fullPath, $extension);

        $this->headers = $data['headers'];
        $this->previewData = array_slice($data['rows'], 0, 20);
        $this->totalRows = count($data['rows']);

        $this->autoMapColumns();

        $fileSize = filesize($fullPath);
        $fileMime = mime_content_type($fullPath) ?: 'text/csv';

        $this->session = ImportSession::create([
            'user_id' => auth()->id() ?? null,
            'tenant_id' => $this->getTenantId(),
            'model_class' => $this->modelClass,
            'file_path' => $filePath,
            'file_name' => $clientOriginalName,
            'file_size' => $fileSize,
            'file_type' => $fileMime,
            'headers' => $this->headers,
            'column_mappings' => $this->columnMappings,
            'total_rows' => $this->totalRows,
            'step' => 1,
            'status' => 'pending',
            'enable_upsert' => $this->enableUpsert,
            'upsert_keys' => $this->upsertKeys,
        ]);

        $this->step = 2;
    }

    protected function parseFile(string $path, string $extension): array
    {
        if ($extension === 'csv') {
            return $this->parseCsv($path);
        }

        return $this->parseExcel($path);
    }

    protected function parseCsv(string $path): array
    {
        $delimiter = config('filament-import-wizard.default_csv_delimiter', ',');

        $reader = Reader::createFromPath($path);
        $reader->setDelimiter($delimiter);

        $headers = [];
        $rows = [];

        foreach ($reader as $index => $record) {
            if ($index === 0) {
                $headers = [];
                foreach ($record as $i => $h) {
                    $headerName = Str::of($h)->trim()->studly()->toString();
                    $headers[] = $headerName ?: 'Column'.($i + 1);
                }

                continue;
            }

            // Only add row if headers are set and counts match
            if (! empty($headers) && count($headers) === count($record)) {
                if (! empty(array_filter($record))) {
                    $rows[] = array_combine($headers, $record);
                }
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function parseExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [];
        $rows = [];

        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getValue();
            }

            if ($rowIndex === 1) {
                $headers = [];
                foreach ($cells as $i => $h) {
                    $headerName = $h ? Str::of($h)->trim()->studly()->toString() : '';
                    $headers[] = $headerName ?: 'Column'.($i + 1);
                }

                continue;
            }

            if (! empty(array_filter($cells))) {
                $rows[] = array_combine($headers, $cells);
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function autoMapColumns(): void
    {
        $modelColumns = $this->getModelColumns();

        foreach ($this->headers as $header) {
            $normalized = Str::of($header)->snake()->lower()->toString();

            foreach ($modelColumns as $column) {
                if (Str::of($column)->snake()->lower()->toString() === $normalized) {
                    $this->columnMappings[$header] = $column;
                    break;
                }
            }
        }
    }

    public function getModelColumns(): array
    {
        if (! $this->modelClass || ! class_exists($this->modelClass)) {
            return [];
        }

        $model = new $this->modelClass;

        $fillable = $model->getFillable() ?? [];

        if (empty($fillable) && property_exists($model, 'guarded')) {
            $guarded = $model->guarded ?? [];
            if (empty($guarded) || (count($guarded) === 1 && $guarded[0] === '*')) {
                $fillable = $this->getAllModelColumnsFromSchema($model);
            }
        }

        return $fillable;
    }

    public function getGroupedModelColumns(): array
    {
        if (! $this->modelClass || ! class_exists($this->modelClass)) {
            return ['fields' => [], 'relationships' => []];
        }

        $model = new $this->modelClass;
        $groups = [
            'fields' => [],
            'relationships' => [],
        ];

        $fillable = $model->getFillable() ?? [];
        if (empty($fillable) && property_exists($model, 'guarded')) {
            $guarded = $model->guarded ?? [];
            if (empty($guarded) || (count($guarded) === 1 && $guarded[0] === '*')) {
                $fillable = $this->getAllModelColumnsFromSchema($model);
            }
        }

        $foreignKeys = $this->getForeignKeys($model);
        $fillableWithKeys = array_merge($fillable, $foreignKeys);
        $groups['fields'] = array_unique($fillableWithKeys);
        $groups['relationships'] = $this->getRelationshipFields($model);

        return $groups;
    }

    protected function getForeignKeys(object $model): array
    {
        $keys = [];

        try {
            $methods = get_class_methods($model);
            $baseMethods = get_class_methods(Model::class);

            foreach ($methods as $method) {
                if (in_array($method, $baseMethods)) {
                    continue;
                }
                if (str_starts_with($method, 'get') || str_starts_with($method, 'scope')) {
                    continue;
                }

                $reflectionMethod = new \ReflectionMethod($model, $method);
                if ($reflectionMethod->getNumberOfParameters() > 0) {
                    continue;
                }

                if (! is_callable([$model, $method])) {
                    continue;
                }

                try {
                    $result = $model->$method();
                } catch (\ArgumentCountError $e) {
                    continue;
                }

                if (! $result instanceof BelongsTo) {
                    continue;
                }

                $foreignKey = $result->getForeignKeyName();
                $keys[] = $foreignKey;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return $keys;
    }

    protected function getRelationshipFields(object $model): array
    {
        $fields = [];

        try {
            $methods = get_class_methods($model);
            $baseMethods = get_class_methods(Model::class);

            foreach ($methods as $method) {
                if (in_array($method, $baseMethods)) {
                    continue;
                }
                if (str_starts_with($method, 'get') || str_starts_with($method, 'scope')) {
                    continue;
                }

                $reflectionMethod = new \ReflectionMethod($model, $method);
                if ($reflectionMethod->getNumberOfParameters() > 0) {
                    continue;
                }

                if (! is_callable([$model, $method])) {
                    continue;
                }

                try {
                    $result = $model->$method();
                } catch (\ArgumentCountError $e) {
                    continue;
                }

                if (! $result instanceof Relation) {
                    continue;
                }

                if (! $result instanceof BelongsTo) {
                    continue;
                }

                $relationName = $method;
                $relatedModel = $result->getRelated();
                $relatedFillable = $relatedModel->getFillable() ?? [];

                if (empty($relatedFillable)) {
                    try {
                        $relatedFillable = Schema::getColumnListing($relatedModel->getTable()) ?? [];
                    } catch (\Exception $e) {
                        $relatedFillable = [];
                    }
                }

                foreach ($relatedFillable as $field) {
                    $fields[] = $relationName.'.'.$field;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return $fields;
    }

    protected function getAllModelColumnsFromSchema(object $model): array
    {
        if (! method_exists($model, 'getTable')) {
            return [];
        }

        try {
            $table = $model->getTable();
            $schema = Schema::getColumnListing($table);

            return $schema ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getTenantId(): ?string
    {
        if (function_exists('filament') && config('filament.panel')) {
            $panel = filament()->getCurrentPanel();
            if ($panel && method_exists($panel, 'getTenant')) {
                $tenant = $panel->getTenant();

                return $tenant?->getKey();
            }
        }

        return null;
    }

    public function nextStep()
    {
        if ($this->step === 2) {
            $this->validateColumnMappings();
            if ($this->session) {
                $this->session->update([
                    'column_mappings' => $this->columnMappings,
                    'step' => 2,
                    'enable_upsert' => $this->enableUpsert,
                    'upsert_keys' => $this->upsertKeys,
                ]);
            }
            $this->validateData();
        }

        if ($this->step < 4) {
            $this->step++;
        }
    }

    protected function validateColumnMappings(): void
    {
        $modelColumns = $this->getModelColumns();

        foreach ($this->columnMappings as $header => $column) {
            // Skip validation for relation mappings (format: relation.field|owner_key|foreign_key)
            if (str_contains($column, '|')) {
                continue;
            }

            if ($column && ! in_array($column, $modelColumns)) {
                unset($this->columnMappings[$header]);
            }
        }
    }

    protected function validateData(): void
    {
        $this->errors = [];
        $this->previewData = [];

        $data = $this->loadAllData();
        $sampleCount = 0;
        $maxSample = 100;

        foreach ($data as $index => $row) {
            $rowErrors = $this->validateRow($row);
            $row['_errors'] = $rowErrors;

            // Always add to preview data if we haven't reached the limit
            if ($sampleCount < $maxSample) {
                $this->previewData[] = $row;
                $sampleCount++;
            }

            if (! empty($rowErrors)) {
                $this->errors[] = [
                    'row' => $index + 1,
                    'errors' => $rowErrors,
                    'data' => $row,
                ];
            }

            // Stop if we have too many errors to show in the review UI
            if (count($this->errors) >= 100) {
                break;
            }

            // Also stop if we've processed a reasonable amount of rows for preview
            if ($index >= 500) {
                break;
            }
        }
    }

    protected function loadAllData(): array
    {
        if (! $this->session) {
            return [];
        }

        $path = $this->resolveSessionFilePath();

        if (! $path) {
            return [];
        }

        $extension = pathinfo($this->session->file_name, PATHINFO_EXTENSION);

        if ($extension === 'csv') {
            return $this->parseCsv($path)['rows'];
        }

        return $this->parseExcel($path)['rows'];
    }

    protected function resolveSessionFilePath(): ?string
    {
        $filePath = $this->session->file_path;
        $possiblePaths = [
            storage_path('app/'.$filePath),
            storage_path('app/imports/'.$filePath),
            storage_path('app/livewire-tmp/'.$filePath),
            storage_path('app/livewire/tmp/'.$filePath),
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function validateRow(array $row): array
    {
        if (! $this->modelClass || ! class_exists($this->modelClass)) {
            return [];
        }

        $rules = $this->getModelValidationRules();
        $dataToValidate = [];
        $relationForeignKeys = [];

        foreach ($this->columnMappings as $header => $mapping) {
            if (! $mapping) {
                continue;
            }

            $value = $row[$header] ?? null;

            if (str_contains($mapping, '|')) {
                // Relationship mapping: relation.field|owner_key|foreign_key
                $parts = explode('|', $mapping);
                if (count($parts) === 3) {
                    $foreignKey = $parts[2];
                    $dataToValidate[$foreignKey] = $value;
                    $relationForeignKeys[] = $foreignKey;
                }
            } else {
                $dataToValidate[$mapping] = $value;
            }
        }

        // For relation foreign keys, we should skip type validation (like numeric/integer)
        // because the value in the CSV is a label (e.g. Category Name) that will be
        // resolved to an ID later in the background job.
        foreach ($relationForeignKeys as $fk) {
            if (isset($rules[$fk])) {
                $fkRules = is_array($rules[$fk]) ? $rules[$fk] : explode('|', $rules[$fk]);
                $rules[$fk] = array_filter($fkRules, function ($rule) {
                    // Only keep essential rules, remove type constraints
                    return in_array($rule, ['required', 'nullable', 'min', 'max']);
                });
            }
        }

        $validator = Validator::make($dataToValidate, $rules);

        if ($validator->fails()) {
            $rowErrors = [];
            foreach ($validator->errors()->toArray() as $field => $messages) {
                // Find matching header for this field
                foreach ($this->columnMappings as $header => $mapping) {
                    if ($mapping === $field || (str_contains($mapping, '|') && explode('|', $mapping)[2] === $field)) {
                        $rowErrors[$header] = $messages[0];
                        break;
                    }
                }
            }

            return $rowErrors;
        }

        return [];
    }

    protected function getModelValidationRules(): array
    {
        if (! empty($this->modelRules)) {
            return $this->modelRules;
        }

        if (! $this->modelClass || ! class_exists($this->modelClass)) {
            return [];
        }

        $model = new $this->modelClass;

        // Check if model has custom rules for import
        if (method_exists($model, 'getImportRules')) {
            return $this->modelRules = $model->getImportRules();
        }

        $rules = [];
        try {
            $table = $model->getTable();
            // Schema::getColumns is available in Laravel 10.10+
            $columns = Schema::getColumns($table);

            foreach ($columns as $column) {
                $name = $column['name'];

                // Skip system columns
                if (in_array($name, [$model->getKeyName(), 'created_at', 'updated_at', 'deleted_at'])) {
                    continue;
                }

                $colRules = [];

                // Required check: not nullable and no default value
                if (! $column['nullable'] && ! isset($column['default']) && ! ($column['auto_increment'] ?? false)) {
                    $colRules[] = 'required';
                } else {
                    $colRules[] = 'nullable';
                }

                $type = strtolower($column['type_name'] ?? $column['type'] ?? '');

                if (str_contains($type, 'int') || str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
                    $colRules[] = 'numeric';
                } elseif (str_contains($type, 'bool') || $type === 'tinyint(1)') {
                    $colRules[] = 'boolean';
                } elseif (str_contains($type, 'date') || str_contains($type, 'time')) {
                    $colRules[] = 'date';
                }

                if ($name === 'email' || str_contains($name, 'email')) {
                    $colRules[] = 'email';
                }

                // Unique check (only if not upserting)
                if (($column['unique'] ?? false) && ! $this->enableUpsert) {
                    $colRules[] = "unique:{$table},{$name}";
                }

                if (! empty($colRules)) {
                    $rules[$name] = $colRules;
                }
            }
        } catch (\Exception $e) {
            // Fallback for older Laravel or unexpected schema format
            $rules = [
                'email' => ['nullable', 'email'],
            ];
        }

        return $this->modelRules = $rules;
    }

    public function previousStep()
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function setStep(int $step)
    {
        if ($step >= 1 && $step <= 4) {
            $this->step = $step;
        }
    }

    public function startImport()
    {
        if (! $this->session) {
            return;
        }

        $this->status = 'processing';
        $this->session->update(['status' => 'processing', 'step' => 3]);

        $chunkSize = config('filament-import-wizard.chunk_size', 1000);
        $totalChunks = (int) ceil($this->totalRows / $chunkSize);
        $this->totalChunks = $totalChunks;
        $queueConnection = $this->getQueueDriver();
        $message = '';

        for ($i = 0; $i < $totalChunks; $i++) {
            ProcessImportChunk::dispatch($this->session, $i, $chunkSize);
        }

        if ($queueConnection === 'sync') {
            $message = 'Import completed successfully!';
        } else {
            $message = "{$totalChunks} job(s) has been dispatched to the '{$queueConnection}' queue.";
        }

        $this->dispatch('importStarted', [
            'message' => $message,
            'sessionId' => $this->session->id,
        ]);
    }

    public function getQueueDriver(): string
    {
        return config('filament-import-wizard.queue_connection') ?? config('queue.default', 'sync');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', ['id' => 'filament-import-wizard']);
    }

    public function retryImport()
    {
        $this->status = 'idle';
        $this->errorMessage = null;
        $this->step = 3;
    }

    public function downloadErrors()
    {
        if (! $this->session || empty($this->session->errors)) {
            return;
        }

        $csv = Writer::createFromFileObject(new \SplTempFileObject);
        $csv->setOutputBOM(Bom::Utf8);

        $csv->insertOne(['Row', 'Error', 'Data']);

        foreach ($this->session->errors as $error) {
            $csv->insertOne([
                $error['row'] ?? '',
                $error['error'] ?? '',
                json_encode($error['data'] ?? []),
            ]);
        }

        return response()->streamDownload(
            fn () => $csv->output(),
            'import-errors.csv',
            ['Content-Type' => 'text/csv']
        );
    }
}
