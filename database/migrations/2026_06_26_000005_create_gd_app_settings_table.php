<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gd_app_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->default(0);
            $table->string('setting_key', 120);
            $table->text('setting_value')->nullable();
            $table->timestamps();
            $table->unique(['admin_id', 'setting_key'], 'gd_app_settings_admin_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gd_app_settings');
    }
};
