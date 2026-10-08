<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_meter_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('run_id')->unique();
            $table->string('label')->nullable();
            $table->string('status')->default('running');

            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();

            $table->unsignedInteger('step_count')->default(0);
            $table->unsignedInteger('tool_call_count')->default(0);
            $table->decimal('cost_usd', 14, 8)->default(0);

            $table->decimal('budget_usd', 14, 6)->nullable();
            $table->unsignedInteger('max_tool_calls')->nullable();
            $table->unsignedInteger('max_wall_clock')->nullable();

            $table->text('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->index(['scope_type', 'scope_id']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_meter_runs');
    }
};
