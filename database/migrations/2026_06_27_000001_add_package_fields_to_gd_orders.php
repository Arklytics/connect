<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gd_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('gd_orders', 'package_name')) {
                $table->string('package_name', 120)->nullable()->after('webhook_url');
            }
            if (!Schema::hasColumn('gd_orders', 'message_limit')) {
                $table->unsignedInteger('message_limit')->default(1000)->after('package_name');
            }
            if (!Schema::hasColumn('gd_orders', 'messages_used')) {
                $table->unsignedInteger('messages_used')->default(0)->after('message_limit');
            }
            if (!Schema::hasColumn('gd_orders', 'package_price')) {
                $table->decimal('package_price', 10, 2)->nullable()->after('messages_used');
            }
            if (!Schema::hasColumn('gd_orders', 'package_started_at')) {
                $table->timestamp('package_started_at')->nullable()->after('package_price');
            }
            if (!Schema::hasColumn('gd_orders', 'package_ends_at')) {
                $table->timestamp('package_ends_at')->nullable()->after('package_started_at');
            }
            if (!Schema::hasColumn('gd_orders', 'limit_request_status')) {
                $table->string('limit_request_status', 20)->default('none')->after('package_ends_at');
            }
            if (!Schema::hasColumn('gd_orders', 'limit_request_note')) {
                $table->text('limit_request_note')->nullable()->after('limit_request_status');
            }
            if (!Schema::hasColumn('gd_orders', 'limit_request_at')) {
                $table->timestamp('limit_request_at')->nullable()->after('limit_request_note');
            }
        });

        if (!Schema::hasTable('gd_package_requests')) {
            Schema::create('gd_package_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('biz_id');
                $table->unsignedInteger('requested_limit');
                $table->string('current_package', 120)->nullable();
                $table->text('reason')->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gd_package_requests');

        Schema::table('gd_orders', function (Blueprint $table) {
            if (Schema::hasColumn('gd_orders', 'limit_request_at')) {
                $table->dropColumn('limit_request_at');
            }
            if (Schema::hasColumn('gd_orders', 'limit_request_note')) {
                $table->dropColumn('limit_request_note');
            }
            if (Schema::hasColumn('gd_orders', 'limit_request_status')) {
                $table->dropColumn('limit_request_status');
            }
            if (Schema::hasColumn('gd_orders', 'package_ends_at')) {
                $table->dropColumn('package_ends_at');
            }
            if (Schema::hasColumn('gd_orders', 'package_started_at')) {
                $table->dropColumn('package_started_at');
            }
            if (Schema::hasColumn('gd_orders', 'package_price')) {
                $table->dropColumn('package_price');
            }
            if (Schema::hasColumn('gd_orders', 'messages_used')) {
                $table->dropColumn('messages_used');
            }
            if (Schema::hasColumn('gd_orders', 'message_limit')) {
                $table->dropColumn('message_limit');
            }
            if (Schema::hasColumn('gd_orders', 'package_name')) {
                $table->dropColumn('package_name');
            }
        });
    }
};
