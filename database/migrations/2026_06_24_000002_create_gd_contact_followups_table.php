<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gd_contact_followups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biz_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('channel', 30)->default('whatsapp');
            $table->string('sequence_name', 150)->nullable();
            $table->unsignedInteger('step_no')->default(1);
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('message')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gd_contact_followups');
    }
};
