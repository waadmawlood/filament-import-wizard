<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('tenant_id')->nullable()->index();
            $table->string('model_class');
            $table->string('file_path');
            $table->string('file_name');
            $table->integer('file_size');
            $table->string('file_type');
            $table->json('headers')->nullable();
            $table->json('column_mappings')->nullable();
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('success_rows')->default(0);
            $table->unsignedBigInteger('failed_rows')->default(0);
            $table->unsignedTinyInteger('step')->default(1);
            $table->string('status')->default('pending');
            $table->json('config')->nullable();
            $table->json('errors')->nullable();
            $table->boolean('enable_upsert')->default(false);
            $table->json('upsert_keys')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
    }
};
