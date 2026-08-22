<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas do spatie/laravel-permission — DENTRO do banco do tenant.
 *
 * Papéis e permissões são por tenant: cada cliente tem os seus. Por isso a
 * migration vive em tenant/, não em central/. É a versão padrão do pacote
 * (v6), sem o recurso de teams, que não usamos.
 *
 * Sem ela, o PermissoesSeeder e o syncRoles do seed de demonstração batem em
 * "relation roles does not exist".
 */
return new class extends Migration
{
    public function up(): void
    {
        $tabelas = config('permission.table_names');
        $colunas = config('permission.column_names');
        $pivoPapel = $colunas['role_pivot_key'] ?? 'role_id';
        $pivoPermissao = $colunas['permission_pivot_key'] ?? 'permission_id';
        $morphKey = $colunas['model_morph_key'] ?? 'model_id';

        Schema::create($tabelas['permissions'], function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tabelas['roles'], function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tabelas['model_has_permissions'], function (Blueprint $table) use ($tabelas, $pivoPermissao, $morphKey): void {
            $table->unsignedBigInteger($pivoPermissao);
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($pivoPermissao)->references('id')->on($tabelas['permissions'])->cascadeOnDelete();
            $table->primary([$pivoPermissao, $morphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tabelas['model_has_roles'], function (Blueprint $table) use ($tabelas, $pivoPapel, $morphKey): void {
            $table->unsignedBigInteger($pivoPapel);
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($pivoPapel)->references('id')->on($tabelas['roles'])->cascadeOnDelete();
            $table->primary([$pivoPapel, $morphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create($tabelas['role_has_permissions'], function (Blueprint $table) use ($tabelas, $pivoPapel, $pivoPermissao): void {
            $table->unsignedBigInteger($pivoPermissao);
            $table->unsignedBigInteger($pivoPapel);
            $table->foreign($pivoPermissao)->references('id')->on($tabelas['permissions'])->cascadeOnDelete();
            $table->foreign($pivoPapel)->references('id')->on($tabelas['roles'])->cascadeOnDelete();
            $table->primary([$pivoPermissao, $pivoPapel], 'role_has_permissions_permission_id_role_id_primary');
        });

        // Limpa o cache de permissões do spatie. A tabela `cache` do tenant já
        // existe (010050 roda antes), então isto não falha.
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tabelas = config('permission.table_names');

        Schema::drop($tabelas['role_has_permissions']);
        Schema::drop($tabelas['model_has_roles']);
        Schema::drop($tabelas['model_has_permissions']);
        Schema::drop($tabelas['roles']);
        Schema::drop($tabelas['permissions']);
    }
};
