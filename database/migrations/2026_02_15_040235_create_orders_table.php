<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | EXTERNAL PORTAL DATA
            |--------------------------------------------------------------------------
            */
            $table->string('external_order_id')->nullable()->index();
            $table->string('order_number')->unique();
            $table->string('order_id')->nullable()->unique();

            /*
            |--------------------------------------------------------------------------
            | RELATIONS
            |--------------------------------------------------------------------------
            */
          $table->unsignedBigInteger('client_id')->nullable()->index();
$table->unsignedBigInteger('project_id')->nullable()->index();


            /*
            |--------------------------------------------------------------------------
            | ORDER DETAILS
            |--------------------------------------------------------------------------
            */
            $table->text('address')->nullable();
            $table->string('batch')->nullable();
            $table->text('instruction')->nullable();

            $table->enum('source', [
                'client_portal',
                'admin_manual',
                'captur3d_portal'
            ])->default('client_portal');

            $table->enum('priority', [
                'regular',
                'high',
                'urgent'
            ])->default('regular')->index();

            /*
            |--------------------------------------------------------------------------
            | WORKFLOW STATUS
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'pending',
                'assigned_to_drawer',
                'drawer_completed',
                'checker_review',
                'checker_completed',
                'qa_review',
                'qa_completed',
                'completed',
                'rejected'
            ])->default('pending')->index();

            /*
            |--------------------------------------------------------------------------
            | LIVE QA FLAGS
            |--------------------------------------------------------------------------
            */
            $table->boolean('d_live_qa')->default(false);
            $table->boolean('c_live_qa')->default(false);
            $table->boolean('qa_live_qa')->default(false);

            /*
            |--------------------------------------------------------------------------
            | DEADLINES
            |--------------------------------------------------------------------------
            */
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | DRAWER TIMELINE
            |--------------------------------------------------------------------------
            */
            $table->timestamp('drawer_started_at')->nullable();
            $table->timestamp('drawer_completed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | CHECKER TIMELINE
            |--------------------------------------------------------------------------
            */
            $table->timestamp('checker_started_at')->nullable();
            $table->timestamp('checker_completed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | QA TIMELINE
            |--------------------------------------------------------------------------
            */
            $table->timestamp('qa_started_at')->nullable();
            $table->timestamp('qa_completed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | SYSTEM TRACKING
            |--------------------------------------------------------------------------
            */
            $table->timestamp('created_from_api_at')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
