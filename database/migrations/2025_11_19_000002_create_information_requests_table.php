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
        Schema::create('information_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade'); // الموظف اللي طلب المعلومات
            $table->text('request_message'); // الرسالة/الطلب
            $table->text('user_response')->nullable(); // رد المستخدم
            $table->enum('status', ['pending', 'answered', 'closed'])->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('information_requests');
    }
};
