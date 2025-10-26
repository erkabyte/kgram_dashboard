<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entertainments', function (Blueprint $table) {
            if (!Schema::hasColumn('entertainments', 'dropbox_video_status')) {
                $table->enum('dropbox_video_status', ['queued','uploading','processing','completed','failed'])
                    ->nullable()
                    ->default(null)
                    ->after('video_url_input');
            }
            if (!Schema::hasColumn('entertainments', 'dropbox_url')) {
                $table->text('dropbox_url')
                    ->nullable()
                    ->after('dropbox_video_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entertainments', function (Blueprint $table) {
            if (Schema::hasColumn('entertainments', 'dropbox_video_status')) {
                $table->dropColumn('dropbox_video_status');
            }
            if (Schema::hasColumn('entertainments', 'dropbox_url')) {
                $table->dropColumn('dropbox_url');
            }
        });
    }
};


