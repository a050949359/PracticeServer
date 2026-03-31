<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csv_export_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk', 32)->default('local');
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('generated_rows')->default(0);
            $table->unsignedInteger('last_influx_imported_row')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'created_at']);
            $table->index(['channel_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('csv_export_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('columns');
            $table->unsignedInteger('interval_seconds')->default(5);
            $table->string('queue_name', 64)->default('default');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('csv_export_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('measurement', 100);
            $table->string('timestamp_source', 32)->default('now');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'code']);
            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('csv_export_channel_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('csv_export_channels')->cascadeOnDelete();
            $table->string('column_name', 64);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['channel_id', 'column_name']);
            $table->index(['channel_id', 'sort_order']);
        });

        Schema::create('csv_export_channel_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('csv_export_channels')->cascadeOnDelete();
            $table->string('column_name', 64);
            $table->string('data_type', 16)->default('string');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['channel_id', 'column_name']);
            $table->index(['channel_id', 'sort_order']);
        });

        Schema::table('csv_export_tasks', function (Blueprint $table) {
            $table->foreign('template_id')
                ->references('id')
                ->on('csv_export_templates')
                ->nullOnDelete();

            $table->foreign('channel_id')
                ->references('id')
                ->on('csv_export_channels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('csv_export_tasks', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropForeign(['channel_id']);
        });

        Schema::dropIfExists('csv_export_channel_fields');
        Schema::dropIfExists('csv_export_channel_tags');
        Schema::dropIfExists('csv_export_channels');
        Schema::dropIfExists('csv_export_templates');
        Schema::dropIfExists('csv_export_tasks');
    }
};
