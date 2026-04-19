<?php

namespace Waad\FilamentImportWizard\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Waad\FilamentImportWizard\Models\ImportSession;

class ProcessImportChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public ImportSession $session,
        public int $chunkIndex,
        public int $chunkSize
    ) {
        $connection = config('filament-import-wizard.queue_connection');
        $queue = config('filament-import-wizard.queue_name');

        if ($connection) {
            $this->connection = $connection;
        }
        if ($queue) {
            $this->queue = $queue;
        }
    }

    public function handle(): void
    {
        $data = $this->loadChunkData();

        $modelClass = $this->session->model_class;
        $model = new $modelClass;
        $fillable = $model->getFillable();
        $mappings = $this->session->column_mappings ?? [];
        $enableUpsert = $this->session->enable_upsert ?? true;
        $upsertKeys = $this->session->upsert_keys ?? ['id'];

        $records = [];
        $errors = [];

        foreach ($data as $index => $row) {
            try {
                $record = $this->transformRow($row, $mappings, $fillable);

                if (! empty($record)) {
                    $records[] = $record;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => ($this->chunkIndex * $this->chunkSize) + $index + 1,
                    'error' => $e->getMessage(),
                    'data' => $row,
                ];
            }
        }

        if (! empty($records)) {
            $this->processRecords($model, $records, $upsertKeys, $enableUpsert);
        }

        $this->updateSessionStats(count($records), count($errors), $errors);
    }

    protected function loadChunkData(): array
    {
        $filePath = $this->resolveFilePath($this->session->file_path);

        if (! $filePath) {
            return [];
        }

        $extension = pathinfo($this->session->file_name, PATHINFO_EXTENSION);

        if ($extension === 'csv') {
            return $this->loadCsvChunk($filePath);
        }

        return $this->loadExcelChunk($filePath);
    }

    protected function resolveFilePath(string $filePath): ?string
    {
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

    protected function loadCsvChunk(string $path): array
    {
        $fp = fopen($path, 'r');
        $headers = $this->session->headers ?? [];

        $startRow = $this->chunkIndex * $this->chunkSize + 1;
        $endRow = $startRow + $this->chunkSize;

        $data = [];
        $currentRow = 0;

        while (($row = fgetcsv($fp)) !== false) {
            if ($currentRow < $startRow) {
                $currentRow++;

                continue;
            }

            if ($currentRow >= $endRow) {
                break;
            }

            $data[] = array_combine($headers, $row);
            $currentRow++;
        }

        fclose($fp);

        return $data;
    }

    protected function loadExcelChunk(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $headers = $this->session->headers ?? [];

        $startRow = ($this->chunkIndex * $this->chunkSize) + 2;
        $endRow = $startRow + $this->chunkSize - 1;

        $data = [];
        $rowIterator = $sheet->getRowIterator($startRow, $endRow);

        foreach ($rowIterator as $row) {
            $cellIterator = $row->getCellIterator();
            $cells = [];

            foreach ($cellIterator as $i => $cell) {
                $cells[] = $cell->getValue();
            }

            if (count($cells) === count($headers)) {
                $data[] = array_combine($headers, $cells);
            }
        }

        return $data;
    }

    protected function transformRow(array $row, array $mappings, array $fillable): array
    {
        $record = [];

        foreach ($row as $header => $value) {
            if (! isset($mappings[$header]) || $value === null || $value === '') {
                continue;
            }

            $mapping = $mappings[$header];

            // Check if it's a relation mapping (format: relation.field|owner_key|foreign_key)
            if (str_contains($mapping, '|')) {
                $parts = explode('|', $mapping);
                if (count($parts) === 3) {
                    [$relationField, $ownerKey, $foreignKey] = $parts;

                    \Log::info("Processing relation mapping: relationField={$relationField}, ownerKey={$ownerKey}, foreignKey={$foreignKey}, value={$value}");

                    // Verify foreign key is fillable
                    if (! in_array($foreignKey, $fillable)) {
                        \Log::warning("Foreign key '{$foreignKey}' is not in fillable array for {$this->session->model_class}");

                        continue;
                    }

                    // Find or create the related model
                    $relatedModel = $this->findOrCreateRelatedModel(
                        $relationField,
                        $ownerKey,
                        $foreignKey,
                        $value,
                        $this->session->model_class
                    );

                    if ($relatedModel) {
                        $keyValue = $relatedModel->getKey();
                        \Log::info("Successfully found/created related model with ID: {$keyValue}");
                        $record[$foreignKey] = $keyValue;
                    } else {
                        \Log::error("Failed to find or create related model for relation '{$relationField}' with value '{$value}'");
                    }
                }
            } elseif (in_array($mapping, $fillable)) {
                $record[$mapping] = $this->transformValue($value, $mapping);
            }
        }

        return $record;
    }

    protected function findOrCreateRelatedModel(
        string $relationField,
        string $ownerKey,
        string $foreignKey,
        mixed $value,
        string $parentModelClass
    ): ?Model {
        // Parse relation.field format
        $parts = explode('.', $relationField, 2);
        if (count($parts) !== 2) {
            \Log::error("Invalid relation field format: {$relationField}");

            return null;
        }

        [$relationName, $fieldName] = $parts;

        try {
            // Get the related model class from the parent model
            $parentModel = new $parentModelClass;

            if (! method_exists($parentModel, $relationName)) {
                \Log::error("Relation method '{$relationName}' does not exist on {$parentModelClass}");

                return null;
            }

            $relation = $parentModel->$relationName();
            if (! $relation instanceof BelongsTo) {
                \Log::error("Relation '{$relationName}' is not a BelongsTo relation");

                return null;
            }

            $relatedModelClass = get_class($relation->getRelated());
            $relatedModel = new $relatedModelClass;

            // Check if owner key is an auto-incrementing primary key
            $ownerKeyIsAutoIncrementId = $ownerKey === $relatedModel->getKeyName() && $relatedModel->getIncrementing();

            // If owner key is auto-increment ID, handle both numeric IDs and string values
            if ($ownerKeyIsAutoIncrementId) {
                // Try to convert value to integer (in case CSV has numeric ID)
                $numericId = filter_var($value, FILTER_VALIDATE_INT);

                if ($numericId !== false) {
                    // CSV has numeric ID, use it directly
                    $existingModel = $relatedModelClass::find($numericId);

                    if ($existingModel) {
                        return $existingModel;
                    }

                    // Create new model with specific ID
                    $newModel = new $relatedModelClass;
                    $newModel->id = $numericId;
                    $newModel->save();

                    return $newModel;
                }

                // Value is not numeric, try to find by 'name' field first
                if (in_array('name', $relatedModel->getFillable())) {
                    $existingModel = $relatedModelClass::where('name', $value)->first();

                    if ($existingModel) {
                        return $existingModel;
                    }

                    // Create new model with name field
                    $newModel = new $relatedModelClass;
                    $newModel->name = $value;
                    $newModel->save();

                    return $newModel;
                }

                // If no 'name' field, try to find a suitable string field
                $fillable = $relatedModel->getFillable();
                $stringField = null;

                foreach ($fillable as $field) {
                    if ($field === 'id' || str_contains($field, '_id') || str_contains($field, '_count')) {
                        continue;
                    }
                    // Check if it's likely a string field
                    try {
                        $columnType = $relatedModel->getConnection()->getSchemaBuilder()->getColumnType($relatedModel->getTable(), $field);
                        if (in_array($columnType, ['string', 'varchar', 'text'])) {
                            $stringField = $field;
                            break;
                        }
                    } catch (\Exception $e) {
                        // If we can't determine type, assume it's string
                        $stringField = $field;
                        break;
                    }
                }

                if ($stringField) {
                    $existingModel = $relatedModelClass::where($stringField, $value)->first();

                    if ($existingModel) {
                        return $existingModel;
                    }

                    $newModel = new $relatedModelClass;
                    $newModel->$stringField = $value;
                    $newModel->save();

                    return $newModel;
                }

                \Log::error("Cannot create related model: No suitable field found for relation '{$relationName}' with value '{$value}'");

                return null;
            }

            // Owner key is not an auto-increment ID, use it directly
            $existingModel = $relatedModelClass::where($ownerKey, $value)->first();

            if ($existingModel) {
                return $existingModel;
            }

            // Create new related model with the field value
            $newModel = new $relatedModelClass;
            $newModel->$ownerKey = $value;
            $newModel->save();

            return $newModel;
        } catch (\Exception $e) {
            \Log::error('Error in findOrCreateRelatedModel: '.$e->getMessage().' | Trace: '.$e->getTraceAsString());

            return null;
        }
    }

    protected function transformValue(mixed $value, string $field): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }

    protected function processRecords($model, array $records, array $upsertKeys, bool $enableUpsert = true): void
    {
        if ($enableUpsert && ! empty($upsertKeys) && $this->canUseUpsert($model, $upsertKeys)) {
            try {
                $model->upsert($records, $upsertKeys);
            } catch (\Exception $e) {
                foreach (array_chunk($records, 100) as $chunk) {
                    $model->insert($chunk);
                }
            }
        } else {
            foreach (array_chunk($records, 100) as $chunk) {
                $model->insert($chunk);
            }
        }
    }

    protected function canUseUpsert($model, array $upsertKeys): bool
    {
        if (empty($upsertKeys)) {
            return false;
        }

        try {
            $table = $model->getTable();
            $driver = $model->getConnection()->getDriverName();
            $columns = Schema::getColumnListing($table);

            foreach ($upsertKeys as $key) {
                if (! in_array($key, $columns)) {
                    return false;
                }
            }

            if (in_array($driver, ['sqlite', 'mysql', 'pgsql'])) {
                $indexes = Schema::getIndexes($table);
                $hasUniqueIndex = false;

                foreach ($indexes as $index) {
                    if ($index['unique'] ?? false) {
                        $indexColumns = $index['columns'] ?? [];
                        if (count($indexColumns) === count($upsertKeys)) {
                            $match = true;
                            foreach ($upsertKeys as $key) {
                                if (! in_array($key, $indexColumns)) {
                                    $match = false;
                                    break;
                                }
                            }
                            if ($match) {
                                $hasUniqueIndex = true;
                                break;
                            }
                        }
                    }
                }

                if (! $hasUniqueIndex) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function updateSessionStats(int $success, int $failed, array $errors): void
    {
        $this->session->increment('processed_rows', $success + $failed);
        $this->session->increment('success_rows', $success);
        $this->session->increment('failed_rows', $failed);

        if (! empty($errors)) {
            $existingErrors = $this->session->errors ?? [];
            $this->session->update([
                'errors' => array_merge($existingErrors, $errors),
            ]);
        }

        $totalRows = $this->session->total_rows;
        $processedRows = $this->session->processed_rows;

        if ($processedRows >= $totalRows) {
            $this->session->update([
                'status' => $this->session->failed_rows > 0 ? 'completed_with_errors' : 'completed',
            ]);
        }
    }
}
