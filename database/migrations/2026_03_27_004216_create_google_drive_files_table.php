<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('google_drive_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drive_file_id', 191)->unique();
            $table->string('folder_id', 191)->nullable();
            $table->string('file_name');
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('web_view_link')->nullable();
            $table->text('web_content_link')->nullable();
            $table->string('provider', 64)->default('google_drive');
            $table->index(['user_id', 'created_at']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_drive_files');
    }
};
