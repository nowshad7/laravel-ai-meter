<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_meter_calls', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('source')->default('manual');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('operation')->default('chat');
            $table->string('status')->default('success');

            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('reasoning_tokens')->default(0);

            $table->decimal('cost_usd', 14, 8)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->decimal('cost_display', 14, 6)->nullable();

            $table->unsignedInteger('latency_ms')->nullable();

            $table->string('trace_id')->nullable();
            $table->string('run_id')->nullable();
            $table->string('tool_name')->nullable();

            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id')->nullable();

            $table->longText('input')->nullable();
            $table->longText('output')->nullable();
            $table->text('error')->nullable();
            $table->json('properties')->nullable();

            $table->timestamp('created_at')->nullable()->index();

            $table->index('model');
            $table->index(['scope_type', 'scope_id']);
            $table->index('run_id');
            $table->index('trace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_meter_calls');
    }
};
