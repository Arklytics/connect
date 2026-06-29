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
            $table->unsignedBigInteger('biz_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('phone_number_id', 120)->nullable()->index();
            $table->string('whatsapp_business_account_id', 120)->nullable()->index();
            $table->string('event_type', 40)->default('message')->index();
            $table->string('direction', 40)->default('inbound')->index();
            $table->string('from_phone', 30)->nullable()->index();
            $table->string('message_id', 191)->nullable()->index();
            $table->string('delivery_status', 30)->nullable()->index();
            $table->text('message_text')->nullable();
            $table->longText('payload_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('webhook_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gd_webhook_logs');
    }
};
