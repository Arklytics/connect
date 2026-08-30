<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gd_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('gd_orders', 'marketing_message_limit')) {
                $table->unsignedInteger('marketing_message_limit')->default(0)->after('message_limit');
            }
            if (!Schema::hasColumn('gd_orders', 'utility_message_limit')) {
                $table->unsignedInteger('utility_message_limit')->default(0)->after('marketing_message_limit');
            }
            if (!Schema::hasColumn('gd_orders', 'marketing_messages_used')) {
                $table->unsignedInteger('marketing_messages_used')->default(0)->after('messages_used');
            }
            if (!Schema::hasColumn('gd_orders', 'utility_messages_used')) {
                $table->unsignedInteger('utility_messages_used')->default(0)->after('marketing_messages_used');
            }
            if (!Schema::hasColumn('gd_orders', 'marketing_package_price')) {
                $table->decimal('marketing_package_price', 10, 2)->default(0)->after('package_price');
            }
            if (!Schema::hasColumn('gd_orders', 'utility_package_price')) {
                $table->decimal('utility_package_price', 10, 2)->default(0)->after('marketing_package_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gd_orders', function (Blueprint $table) {
            foreach ([
                'utility_package_price',
                'marketing_package_price',
                'utility_messages_used',
                'marketing_messages_used',
                'utility_message_limit',
                'marketing_message_limit',
            ] as $column) {
                if (Schema::hasColumn('gd_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
