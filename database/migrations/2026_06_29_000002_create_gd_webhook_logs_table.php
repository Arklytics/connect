<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gd_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biz_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('phone_number_id', 120)->nullable();
            $table->string('whatsapp_business_account_id', 120)->nullable();
            $table->string('event_type', 40)->default('message');
            $table->string('direction', 40)->default('inbound');
            $table->string('from_phone', 30)->nullable();
            $table->string('message_id', 191)->nullable();
            $table->string('delivery_status', 30)->nullable();
            $table->text('message_text')->nullable();
            $table->text('payload_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('webhook_at')->nullable();
            $table->timestamps();

            $table->index('biz_id');
            $table->index('contact_id');
            $table->index('phone_number_id');
            $table->index('whatsapp_business_account_id');
            $table->index('event_type');
            $table->index('direction');
            $table->index('from_phone');
            $table->index('message_id');
            $table->index('delivery_status');
            $table->index('webhook_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gd_webhook_logs');
    }
};
