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
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'login',
                'email_verification',
                'reset_password',
            ]);
            
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->char('otp_code', 6);
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index('user_id');
            $table->index('company_id');
            $table->index('otp_code');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};


// id
// - user_id (nullable)
// - company_id (nullable)
// - otp_code
// - expires_at
// - created_at
