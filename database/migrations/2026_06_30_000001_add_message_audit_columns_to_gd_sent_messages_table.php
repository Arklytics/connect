<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gd_sent_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('gd_sent_messages', 'request_json')) {
                $table->longText('request_json')->nullable()->after('message_body');
            }

            if (!Schema::hasColumn('gd_sent_messages', 'response_json')) {
                $table->longText('response_json')->nullable()->after('error_message');
            }

            if (!Schema::hasColumn('gd_sent_messages', 'http_status_code')) {
                $table->unsignedSmallInteger('http_status_code')->nullable()->after('response_json');
            }

            if (!Schema::hasColumn('gd_sent_messages', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('http_status_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gd_sent_messages', function (Blueprint $table) {
            $dropColumns = [];
            foreach (['request_json', 'response_json', 'http_status_code', 'failure_reason'] as $column) {
                if (Schema::hasColumn('gd_sent_messages', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
