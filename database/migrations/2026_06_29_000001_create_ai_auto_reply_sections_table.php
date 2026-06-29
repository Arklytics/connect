<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('gd_orders', 'ai_auto_reply_enabled')) {
            Schema::table('gd_orders', function (Blueprint $table) {
                $table->boolean('ai_auto_reply_enabled')->default(false)->after('webhook_url');
            });
        }

        if (!Schema::hasColumn('gd_orders', 'ai_fallback_reply')) {
            Schema::table('gd_orders', function (Blueprint $table) {
                $table->text('ai_fallback_reply')->nullable()->after('ai_auto_reply_enabled');
            });
        }

        if (!Schema::hasTable('gd_ai_knowledge_sections')) {
            Schema::create('gd_ai_knowledge_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('biz_id');
                $table->string('title');
                $table->mediumText('content');
                $table->string('status', 20)->default('active');
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['biz_id', 'status']);
                $table->index(['biz_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gd_ai_knowledge_sections');

        if (Schema::hasColumn('gd_orders', 'ai_fallback_reply')) {
            Schema::table('gd_orders', function (Blueprint $table) {
                $table->dropColumn('ai_fallback_reply');
            });
        }

        if (Schema::hasColumn('gd_orders', 'ai_auto_reply_enabled')) {
            Schema::table('gd_orders', function (Blueprint $table) {
                $table->dropColumn('ai_auto_reply_enabled');
            });
        }
    }
};
