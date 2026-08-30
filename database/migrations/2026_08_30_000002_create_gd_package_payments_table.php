<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gd_package_payments')) {
            Schema::create('gd_package_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('biz_id')->index();
                $table->string('package_key', 50);
                $table->string('package_name', 120);
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('currency', 10)->default('INR');
                $table->string('razorpay_order_id', 120)->nullable()->index();
                $table->string('razorpay_payment_id', 120)->nullable();
                $table->string('razorpay_signature', 255)->nullable();
                $table->string('status', 30)->default('created');
                $table->text('failure_reason')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gd_package_payments');
    }
};
