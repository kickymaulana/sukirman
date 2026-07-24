<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->string('role');
            $table->string('action'); // forward, acknowledge, approve, reject, revision, verify, complete
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Tambah kolom ke material_requests
        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->constrained('users')->after('user_id');
            $table->foreignId('direksi_id')->nullable()->constrained('users')->after('manager_id');
            $table->text('revision_notes')->nullable()->after('status_workflow');
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropForeign(['direksi_id']);
            $table->dropColumn(['manager_id', 'direksi_id', 'revision_notes']);
        });
        Schema::dropIfExists('approval_logs');
    }
};
