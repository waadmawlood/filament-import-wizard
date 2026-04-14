<?php

namespace Waad\FilamentImportWizard\Models;

use Illuminate\Database\Eloquent\Model;

class ImportSession extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'model_class',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'headers',
        'column_mappings',
        'total_rows',
        'processed_rows',
        'success_rows',
        'failed_rows',
        'step',
        'status',
        'config',
        'errors',
        'enable_upsert',
        'upsert_keys',
    ];

    protected $casts = [
        'headers' => 'array',
        'column_mappings' => 'array',
        'config' => 'array',
        'errors' => 'array',
        'processed_rows' => 'integer',
        'success_rows' => 'integer',
        'failed_rows' => 'integer',
        'step' => 'integer',
        'enable_upsert' => 'boolean',
        'upsert_keys' => 'array',
    ];
}
