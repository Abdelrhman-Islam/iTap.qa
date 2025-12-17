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
        Schema::create('cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // 2. Ownership 
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('set null');

        // 3. Physical Link (
        $table->string('nfc_tag_id')->nullable()->unique(); 
        $table->foreign('nfc_tag_id')->references('tag_id')->on('nfc_inventories')->onDelete('set null');

        // 4. Card Details
        $table->string('full_name');
        $table->string('profile_image')->nullable();
        $table->enum('type', ['PERSONAL', 'EMPLOYEE', 'COMPANY']);
        $table->enum('status', ['ACTIVE', 'FROZEN', 'SUSPENDED', 'DEACTIVATED'])->default('ACTIVE');
        $table->boolean('is_primary')->default(false);
        $table->integer('contacts_count')->default(0);

        // 5. Profile Data (Embedded 1:1)
        $table->text('bio')->nullable();
        $table->string('company_name')->nullable(); // Display Name
        $table->string('position')->nullable();
        $table->enum('theme_id', ['MODERN', 'CLASSIC'])->default('MODERN');
        $table->enum('color_scheme', ['LIGHT', 'DARK'])->default('LIGHT');

        // 6. JSON Data
        $table->json('social_links')->nullable(); 
        $table->json('settings')->nullable();     

        $table->timestamps();
        $table->softDeletes(); // Admin Remove
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
