<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas de framework do banco CENTRAL: cache, fila e sessão.
 *
 * O esqueleto padrão do Laravel traz estas na raiz de database/migrations.
 * Neste projeto tudo foi separado em central/ e tenant/, e as tabelas de
 * usuário/sessão do TENANT vivem em 010200 — mas o banco central ficou sem as
 * suas. Resultado: `CACHE_STORE=database` no domínio central batia em
 * "relation cache does not exist".
 *
 * Roda ANTES das demais (000050 < 000100) porque cache e sessão são exigidos
 * já no bootstrap da aplicação — antes de qualquer tenant existir.
 *
 * A fila fica no banco central de propósito: o worker (`queue:work`) roda sem
 * contexto de tenant, sobre a conexão padrão. Job fiscal que precise do tenant
 * carrega o contexto no payload e reidrata no handle() — ver a RN de tenancy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // O domínio central (painel do provedor) também tem sessão. A do tenant
        // é criada em tenant/010200 — quando o stancl troca a conexão, o nome
        // `sessions` resolve para o banco certo.
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
