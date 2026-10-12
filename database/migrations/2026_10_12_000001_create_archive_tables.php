<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff'])->default('staff');
            $table->boolean('is_active')->default(true)->index();
        });
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->enum('section', ['receiving', 'shipping', 'routers'])->index();
            $table->boolean('requires_handled_on')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->enum('kind', ['vendor', 'customer']);
            $table->timestamps();
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('status', ['inbox', 'filed', 'discarded'])->default('inbox');
            $table->date('document_date')->nullable();
            $table->date('handled_on')->nullable();
            $table->string('carrier')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['document_type_id', 'status', 'document_date']);
            $table->index(['status', 'created_at']);
            $table->index('handled_on');
        });
        Schema::create('document_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['po', 'job', 'invoice', 'tracking']);
            $table->string('value');
            $table->string('normalized_value')->index();
            $table->timestamps();
            $table->unique(['document_id', 'kind', 'normalized_value'], 'document_reference_unique');
        });
        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['original', 'searchable', 'thumbnail']);
            $table->string('disk')->default('documents');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 127);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64)->index();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['document_id', 'role', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_files');
        Schema::dropIfExists('document_references');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('parties');
        Schema::dropIfExists('document_types');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'is_active']));
    }
};
