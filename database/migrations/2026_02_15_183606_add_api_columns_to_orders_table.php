<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            // Date & time breakdown for API import
            $table->string('year')->nullable()->after('order_id');
            $table->string('month')->nullable()->after('year');
            $table->string('date')->nullable()->after('month');
            $table->timestamp('ausDatein')->nullable()->after('date');

            // Client and property info
            $table->string('client_name')->nullable()->after('priority');
            $table->string('property')->nullable()->after('client_name');

            // Order codes and types
            $table->string('code')->nullable()->after('property');
            $table->string('plan_type')->nullable()->after('code');
            $table->string('project_type')->nullable()->after('plan_type');

            // New due_in field
            $table->string('due_in')->nullable()->after('due_at');

        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'year', 'month', 'date', 'ausDatein',
                'client_name', 'property', 'code', 'plan_type', 'project_type',
                'due_in'
            ]);
        });
    }
};