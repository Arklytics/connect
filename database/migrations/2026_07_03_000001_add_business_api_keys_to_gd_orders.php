<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gd_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('gd_orders', 'api_key_hash')) {
                $table->string('api_key_hash', 64)->nullable()->after('webhook_url');
            }

            if (!Schema::hasColumn('gd_orders', 'api_key_prefix')) {
                $table->string('api_key_prefix', 20)->nullable()->after('api_key_hash');
            }

            if (!Schema::hasColumn('gd_orders', 'api_key_last4')) {
                $table->string('api_key_last4', 4)->nullable()->after('api_key_prefix');
            }

            if (!Schema::hasColumn('gd_orders', 'api_enabled')) {
                $table->boolean('api_enabled')->default(false)->after('api_key_last4');
            }

            if (!Schema::hasColumn('gd_orders', 'api_key_created_at')) {
                $table->timestamp('api_key_created_at')->nullable()->after('api_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gd_orders', function (Blueprint $table) {
            $dropColumns = [];
            foreach (['api_key_hash', 'api_key_prefix', 'api_key_last4', 'api_enabled', 'api_key_created_at'] as $column) {
                if (Schema::hasColumn('gd_orders', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
