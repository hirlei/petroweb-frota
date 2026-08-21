<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licencas', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('plano', 30)->default('starter');
            $table->jsonb('modulos')->nullable();
            $table->unsignedInteger('veiculos_max')->nullable();
            $table->date('vigencia_ate')->nullable();
            $table->boolean('ativa')->default(true);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->unique('tenant_id');
            $table->index(['ativa', 'vigencia_ate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencas');
    }
};
