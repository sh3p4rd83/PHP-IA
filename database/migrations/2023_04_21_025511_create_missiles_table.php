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
        Schema::create('missiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("partie_id");
            $table->foreign("partie_id")
                ->references("id")
                ->on("parties")
                ->onDelete("cascade");
            $table->string("coordonnées", 3);
            $table->unsignedInteger("resultat")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missiles');
    }
};
