<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gd_template_media')) {
            return;
        }

        Schema::create('gd_template_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biz_id');
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('s3_key', 500)->nullable();
            $table->text('s3_url');
            $table->text('media_handle')->nullable();
            $table->timestamps();
            $table->index('biz_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gd_template_media');
    }
};
