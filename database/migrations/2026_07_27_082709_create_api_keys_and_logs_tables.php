<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');                      // Nama Client / Sistem
            $table->string('key', 64)->unique();         // Masked / Secret Key
            $table->json('scopes')->nullable();          // Scope permissions: ['nas:read', 'users:write']
            $table->text('ip_whitelist')->nullable();    // IP Whitelist (comma separated)
            $table->integer('rate_limit')->default(60);  // Requests per minute
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable(); // Tanggal Expired
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('api_key')->nullable();
            $table->string('endpoint');
            $table->string('method');
            $table->string('ip_address');
            $table->integer('response_status');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('api_keys');
    }
};