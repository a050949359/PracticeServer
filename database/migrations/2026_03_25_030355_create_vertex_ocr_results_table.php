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
        Schema::create('vertex_ocr_results', function (Blueprint $table) {
            $table->id();
            $table->string('image_name');
            $table->string('image_path');
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('image_size')->nullable();
            $table->json('types');
            $table->longText('text');
            $table->string('provider', 64)->default('cloud_vision_ocr');
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vertex_ocr_results');
    }
};
