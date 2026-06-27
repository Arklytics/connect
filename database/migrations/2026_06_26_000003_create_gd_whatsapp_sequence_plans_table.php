<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gd_whatsapp_sequence_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biz_id');
            $table->string('plan_name', 150);
            $table->string('audience', 150)->nullable();
            $table->string('objective', 255)->nullable();
            $table->string('sequence_type', 50)->default('custom');
            $table->unsignedInteger('step_count')->default(0);
            $table->unsignedInteger('default_gap_days')->default(0);
            $table->text('structure_json');
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gd_whatsapp_sequence_plans');
    }
};
