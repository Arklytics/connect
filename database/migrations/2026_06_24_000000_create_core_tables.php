<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gd_admin', function (Blueprint $table) {
            $table->id();
            $table->string('admin_number', 30)->unique();
            $table->string('password', 255);
            $table->string('full_name', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('gd_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('full_name', 255);
            $table->string('mobile_number', 30);
            $table->string('email', 255)->nullable();
            $table->string('password', 255);
            $table->string('business_name', 255);
            $table->string('business_number', 30)->nullable();
            $table->string('business_email', 255)->nullable();
            $table->string('business_location', 255)->nullable();
            $table->text('business_description')->nullable();
            $table->string('business_logo', 255)->nullable();
            $table->string('status', 10)->default('0');
            $table->text('auth_token')->nullable();
            $table->string('whatsapp_id', 120)->nullable();
            $table->string('phone_number_id', 120)->nullable();
            $table->string('webhook_url', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('gd_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biz_id');
            $table->string('group_name', 255);
            $table->timestamps();
        });

        Schema::create('gd_group_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biz_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('contact_id');
            $table->timestamps();
        });

        Schema::create('gd_user_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biz_id');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('full_name', 255);
            $table->string('phone_number', 30);
            $table->string('email', 255)->nullable();
            $table->string('status', 40)->default('new');
            $table->timestamps();
        });

        Schema::create('gd_whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biz_id');
            $table->string('template_id', 120)->nullable();
            $table->string('template_name', 150);
            $table->string('message_title', 255);
            $table->text('message_body');
            $table->text('placeholders')->nullable();
            $table->string('subtitle', 255)->nullable();
            $table->string('media_url', 255)->nullable();
            $table->string('status', 30)->default('PENDING');
            $table->string('category', 50)->nullable();
            $table->text('buttons')->nullable();
            $table->timestamps();
        });

        Schema::create('gd_sent_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('biz_id');
            $table->string('phone_number', 30);
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('message_title', 255);
            $table->text('message_body');
            $table->string('status', 30)->default('pending');
            $table->text('error_message')->nullable();
            $table->string('message_id', 191)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gd_sent_messages');
        Schema::dropIfExists('gd_whatsapp_templates');
        Schema::dropIfExists('gd_user_contacts');
        Schema::dropIfExists('gd_group_contacts');
        Schema::dropIfExists('gd_groups');
        Schema::dropIfExists('gd_orders');
        Schema::dropIfExists('gd_admin');
    }
};
