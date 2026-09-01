<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_non_unique_key_resources', function (Blueprint $table) {
            $table->string('uuid');  // intentionally no ->primary() or ->unique()
            $table->string('name');
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_non_unique_key_resources');
    }
};
