<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_isolated')->default(false)->after('email');
            $table->string('profile')->default('default')->after('is_isolated');
            $table->string('previous_profile')->nullable()->after('profile');
            $table->timestamp('isolated_at')->nullable()->after('previous_profile');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_isolated', 'profile', 'previous_profile', 'isolated_at']);
        });
    }
};
