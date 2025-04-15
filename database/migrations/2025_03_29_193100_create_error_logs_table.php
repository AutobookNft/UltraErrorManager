<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('error_code');
            $table->string('type')->default('error');
            $table->string('blocking')->default('not');
            $table->text('message')->nullable();
            $table->text('user_message')->nullable();
            $table->integer('http_status_code')->nullable();
            $table->json('context')->nullable();
            $table->string('display_mode')->nullable();
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->integer('exception_code')->nullable();
            $table->string('exception_file')->nullable();
            $table->integer('exception_line')->nullable();
            $table->text('exception_trace')->nullable();
            $table->string('request_method')->nullable();
            $table->text('request_url')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            
            // Modifica qui: usa unsignedBigInteger invece di foreignId per maggiore flessibilità
            $table->unsignedBigInteger('user_id')->nullable();
            // La foreign key può essere definita dall'applicazione che usa il pacchetto
            
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->boolean('notified')->default(false);
            $table->timestamps();

            // Indexes for faster lookups
            $table->index('error_code');
            $table->index('type');
            $table->index('resolved');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};