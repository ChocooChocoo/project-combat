<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // R-52: remember an actual pack debit, including when the balance was already zero.
        Schema::table('session_bookings', function (Blueprint $table) {
            $table->boolean('session_pack_used')->default(false);
        });
        Schema::create('gym_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('checked_in_at');
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 20);
            $table->timestamps();
            $table->index(['gym_id', 'checked_in_at']);
        });
        // R-55: null athlete means a whole-session note.
        Schema::create('session_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_notes');
        Schema::dropIfExists('gym_visits');
        Schema::table('session_bookings', fn (Blueprint $table) => $table->dropColumn('session_pack_used'));
    }
};
