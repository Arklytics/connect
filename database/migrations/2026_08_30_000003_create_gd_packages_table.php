<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gd_packages')) {
            Schema::create('gd_packages', function (Blueprint $table) {
                $table->id();
                $table->string('package_key', 50)->unique();
                $table->string('package_name', 120);
                $table->unsignedInteger('marketing_message_limit')->default(0);
                $table->unsignedInteger('utility_message_limit')->default(0);
                $table->unsignedInteger('duration_days')->default(30);
                $table->decimal('marketing_price', 10, 2)->default(0);
                $table->decimal('utility_price', 10, 2)->default(0);
                $table->decimal('total_price', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gd_packages');
    }
};
