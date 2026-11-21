<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idempotency_keys')) {
            return;
        }

        if (! Schema::hasColumn('idempotency_keys', 'status')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->string('status', 20)->default('completed')->after('request_hash');
            });
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE idempotency_keys MODIFY response_code SMALLINT UNSIGNED NULL');
            DB::statement('ALTER TABLE idempotency_keys MODIFY response_body JSON NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('idempotency_keys')) {
            return;
        }

        if (Schema::hasColumn('idempotency_keys', 'status')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
