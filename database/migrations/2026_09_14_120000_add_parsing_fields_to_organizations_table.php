<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->enum('parsing_status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending')
                ->index();
            $table->text('parsing_error')->nullable();
            $table->timestamp('parsing_started_at')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->string('name')->nullable();
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->nullable();
            $table->unsignedInteger('reviews_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['parsing_status']);
            $table->dropColumn([
                'parsing_status',
                'parsing_error',
                'parsing_started_at',
                'parsed_at',
                'name',
                'average_rating',
                'ratings_count',
                'reviews_count',
            ]);
        });
    }
};
