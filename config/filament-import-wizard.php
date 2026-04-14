<?php

use Filament\Support\Enums\Width;

/*
|--------------------------------------------------------------------------
| Modal Width Sizes
|--------------------------------------------------------------------------
|
| Available Width enum values:
| - Width::ExtraTiny => 'extra-tiny' (max 320px)
| - Width::Tiny => 'tiny' (max 384px)
| - Width::Small => 'small' (max 448px)
| - Width::Medium => 'medium' (max 512px) - Default in Filament
| - Width::Large => 'large' (max 576px)
| - Width::ExtraLarge => 'extra-large' (max 672px)
| - Width::Full => 'full' (100% width)
| - Width::Screen => 'screen' (100% width with max 100vw)
| - Width::ThreeQuarter => 'three-quarter' (75% width)
| - Width::Half => 'half' (50% width)
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Modal Width
    |--------------------------------------------------------------------------
    |
    | Control the width of the import wizard modal.
    |
    */
    'modal_width' => Width::Full,

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | Number of rows to process per queue job.
    | Adjust based on your server capacity.
    |
    */
    'chunk_size' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Default CSV Delimiter
    |--------------------------------------------------------------------------
    |
    | The default delimiter used when parsing CSV files.
    | Common values: ',' (comma), ';' (semicolon), '\t' (tab)
    |
    */
    'default_csv_delimiter' => ',',

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    |
    | Configure the queue connection and queue name for background processing.
    | Set to null to use Laravel defaults.
    |
    */
    'queue_connection' => null,
    'queue_name' => null,
];
