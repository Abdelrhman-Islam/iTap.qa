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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->enum     ('type', ['individual', 'employee'])->nullable();
            $table->string   ('fName');
            $table->string   ('mName')->nullable();
            $table->string   ('lName')->nullable();
            $table->string   ('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string   ('password');
            $table->string   ('phone_num')->nullable();
            $table->string   ('sex')->nullable(); 
            $table->string   ('age')->nullable(); 
            $table->string   ('profile_url_slug')->nullable()->unique();
            $table->string   ('profile_image')->nullable();
            $table->string   ('profile_video')->nullable();
            $table->string   ('profile_animation')->nullable();
            $table->string   ('bio')->nullable();
            $table->string   ('profile_language')->nullable();
            $table->text     ('email_signature')->nullable();
            $table->boolean  ('is_super_admin')->default(false);

       
            $table->rememberToken();
            $table->timestamps();





});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
