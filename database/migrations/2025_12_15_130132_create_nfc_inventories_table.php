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
        Schema::create('nfc_inventories', function (Blueprint $table) {
            $table->string('tag_id')->primary(); 
            $table->string('batch_id')->index(); 
            $table->string('secret_key')->nullable(); 
            
            $table->enum('status', ['IN_STOCK', 'ASSIGNED', 'BLACKLISTED'])->default('IN_STOCK');

            $table->boolean('deliverd')->nullable()->default(false); 
            $table->boolean('nfc_assigned_to_card')->nullable()->default(false); 
            
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nfc_inventories');
    }
};
