<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookup_queries', function (Blueprint $table) {
            $table->id();

            // Nullable: hay puntos de entrada (ej. comando artisan de diagnóstico)
            // que no tienen un tenant resuelto.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->string('provider', 50);          // 'apiperu', y en el futuro otros
            $table->string('lookup_type', 20);        // 'dni' | 'ruc' | 'dni_ruc' | futuros: 'tipo_cambio', 'cpe', etc.
            $table->string('document_number', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('cache_hit')->default(false);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->ipAddress('ip_address')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['lookup_type', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_queries');
    }
};
