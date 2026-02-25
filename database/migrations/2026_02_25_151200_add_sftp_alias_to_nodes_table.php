<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSftpAliasToNodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('sftp_alias_address')->nullable()->after('daemonSFTP');
            $table->integer('sftp_alias_port')->nullable()->after('sftp_alias_address');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('sftp_alias_address');
            $table->dropColumn('sftp_alias_port');
        });
    }
}
