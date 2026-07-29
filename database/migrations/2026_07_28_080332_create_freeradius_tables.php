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
        // 1. Table radacct
        Schema::create('radacct', function (Blueprint $table) {
            $table->bigIncrements('radacctid');
            $table->string('acctsessionid', 64)->default('');
            $table->string('acctuniqueid', 32)->default('')->unique();
            $table->string('username', 64)->default('')->index();
            $table->string('groupname', 64)->default('');
            $table->string('realm', 64)->nullable()->default('');
            $table->string('nasipaddress', 15)->default('')->index();
            $table->string('nasportid', 32)->nullable();
            $table->string('nasporttype', 32)->nullable();
            $table->dateTime('acctstarttime')->nullable()->index();
            $table->dateTime('acctupdatetime')->nullable();
            $table->dateTime('acctstoptime')->nullable()->index();
            $table->integer('acctinterval')->nullable()->index();
            $table->unsignedInteger('acctsessiontime')->nullable()->index();
            $table->string('acctauthentic', 32)->nullable();
            $table->string('connectinfo_start', 50)->nullable();
            $table->string('connectinfo_stop', 50)->nullable();
            $table->bigInteger('acctinputoctets')->nullable();
            $table->bigInteger('acctoutputoctets')->nullable();
            $table->string('calledstationid', 50)->default('');
            $table->string('callingstationid', 50)->default('');
            $table->string('acctterminatecause', 32)->default('');
            $table->string('servicetype', 32)->nullable();
            $table->string('framedprotocol', 32)->nullable();
            $table->string('framedipaddress', 15)->default('')->index();
            $table->string('framedipv6address', 45)->default('')->index();
            $table->string('framedipv6prefix', 45)->default('')->index();
            $table->string('framedinterfaceid', 44)->default('')->index();
            $table->string('delegatedipv6prefix', 45)->default('')->index();
            $table->string('class', 64)->nullable();

            // Composite Index
            $table->index(['acctstoptime', 'nasipaddress', 'acctstarttime'], 'bulk_close');
        });

        // 2. Table radcheck
        Schema::create('radcheck', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 64)->default('');
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('==');
            $table->string('value', 253)->default('');
            // Menambahkan created_at dan updated_at
            $table->timestamps();

            $table->index('username');
        });

        // 3. Table radgroupcheck
        Schema::create('radgroupcheck', function (Blueprint $table) {
            $table->increments('id');
            $table->string('groupname', 64)->default('');
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('==');
            $table->string('value', 253)->default('');
            // Menambahkan created_at dan updated_at
            $table->timestamps();

            $table->index('groupname');
        });

        // 4. Table radgroupreply
        Schema::create('radgroupreply', function (Blueprint $table) {
            $table->increments('id');
            $table->string('groupname', 64)->default('');
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('=');
            $table->string('value', 253)->default('');
            // Menambahkan created_at dan updated_at
            $table->timestamps();

            $table->index('groupname');
        });

        // 5. Table radreply
        Schema::create('radreply', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 64)->default('');
            $table->string('attribute', 64)->default('');
            $table->char('op', 2)->default('=');
            $table->string('value', 253)->default('');
            // Menambahkan created_at dan updated_at
            $table->timestamps();

            $table->index('username');
        });

        // 6. Table radusergroup
        Schema::create('radusergroup', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 64)->default('');
            $table->string('groupname', 64)->default('');
            $table->integer('priority')->default(1);
            // Menambahkan created_at dan updated_at
            $table->timestamps();

            $table->index('username');
        });

        // 7. Table radpostauth
        Schema::create('radpostauth', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 64)->default('');
            $table->string('pass', 64)->default('');
            $table->string('reply', 32)->default('');
            $table->timestamp('authdate', 6)->useCurrent()->useCurrentOnUpdate();
            $table->string('class', 64)->default('');
        });

        // 8. Table nas
        Schema::create('nas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nasname', 128)->index();
            $table->string('shortname', 32)->nullable();
            $table->string('type', 30)->default('other');
            $table->integer('ports')->nullable();
            $table->string('secret', 60)->default('secret');
            $table->string('server', 64)->nullable();
            $table->string('community', 50)->nullable();
            $table->string('description', 200)->default('RADIUS Client');
            $table->string('require_ma', 4)->default('auto');
            $table->string('limit_proxy_state', 4)->default('auto');
            // Menambahkan created_at dan updated_at
            $table->timestamps();
        });

        // 9. Table nasreload
        Schema::create('nasreload', function (Blueprint $table) {
            $table->string('nasipaddress', 15)->primary();
            $table->dateTime('reloadtime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nasreload');
        Schema::dropIfExists('nas');
        Schema::dropIfExists('radpostauth');
        Schema::dropIfExists('radusergroup');
        Schema::dropIfExists('radreply');
        Schema::dropIfExists('radgroupreply');
        Schema::dropIfExists('radgroupcheck');
        Schema::dropIfExists('radcheck');
        Schema::dropIfExists('radacct');
    }
};