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
            $table->string('status', 32)->default('pending');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk', 32)->default('local');
            $table->json('columns');
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('generated_rows')->default(0);
            $table->unsignedInteger('interval_seconds')->default(5);
            $table->string('queue_name', 64)->default('default');
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csv_export_tasks');
    }
};
