<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('weight_lbs', 5, 1)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        Schema::create('activity_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_sessions')->default(0);
            $table->unsignedInteger('total_minutes')->default(0);
            $table->unsignedInteger('calories_burned')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        Schema::create('symptom_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('trend', 16)->nullable();
            $table->string('fatigue', 16)->nullable();
            $table->string('dizziness', 16)->nullable();
            $table->unsignedTinyInteger('max_pain')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        Schema::create('ai_write_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 32);
            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id');
            $table->date('log_date');
            $table->json('before_payload')->nullable();
            $table->json('after_payload')->nullable();
            $table->text('prompt')->nullable();
            $table->text('assistant_summary')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'domain', 'log_date']);
            $table->index(['target_type', 'target_id']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('domain', 16)->default('food')->after('daily_log_id');
            $table->date('log_date')->nullable()->after('domain');
            $table->index(['user_id', 'domain', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'domain', 'log_date']);
            $table->dropColumn(['domain', 'log_date']);
        });

        Schema::dropIfExists('ai_write_audits');
        Schema::dropIfExists('symptom_daily_logs');
        Schema::dropIfExists('activity_daily_logs');
        Schema::dropIfExists('measurements');
    }
};
