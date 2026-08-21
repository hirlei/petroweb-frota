<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro unificado de pessoas — ordem 04 da sequência do 02_MODELAGEM_DADOS §9.
 *
 * UMA tabela para cliente, fornecedor, motorista, proprietário, oficina,
 * seguradora e posto. O papel é linha em `pessoa_papeis`, nunca coluna aqui:
 * no transporte a mesma pessoa acumula papéis o tempo todo — o cliente que
 * também é remetente, o motorista agregado que é proprietário do cavalo.
 * Modelar como tabelas separadas duplica CNPJ e endereço e produz cadastro
 * divergente no primeiro mês de uso.
 *
 * `ie_indicador` é NOT NULL de propósito: é a causa mais recorrente de
 * rejeição do CT-e quando fica em branco e o emissor "chuta" na hora do XML.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');

            $table->char('tipo', 1);                    // F | J | E
            // CNPJ é STRING de 14 — a NT Conjunta 2025.001 o torna alfanumérico.
            $table->string('documento', 20);
            $table->string('documento_estrangeiro', 20)->nullable();
            $table->string('razao_social', 150);
            $table->string('nome_fantasia', 150)->nullable();

            $table->string('ie', 20)->nullable();
            $table->char('ie_indicador', 1);            // 1 contribuinte | 2 isento | 9 não contribuinte
            $table->string('im', 20)->nullable();
            $table->string('rntrc', 10)->nullable();
            $table->date('rntrc_validade')->nullable();
            $table->char('tp_transp', 1)->nullable();   // 1 ETC | 2 TAC | 3 CTC
            $table->string('suframa', 9)->nullable();
            $table->string('cnae', 7)->nullable();
            $table->char('crt', 1)->nullable();

            $table->string('email', 150)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'documento']);
            $table->index(['empresa_id', 'ativo']);
            $table->index(['empresa_id', 'razao_social']);
        });

        DB::statement(
            "ALTER TABLE pessoas ADD CONSTRAINT pessoas_tipo_valido "
            . "CHECK (tipo IN ('F','J','E'))"
        );
        DB::statement(
            "ALTER TABLE pessoas ADD CONSTRAINT pessoas_ie_indicador_valido "
            . "CHECK (ie_indicador IN ('1','2','9'))"
        );
        // Contribuinte sem IE é rejeição garantida no CT-e. O banco recusa antes.
        DB::statement(
            "ALTER TABLE pessoas ADD CONSTRAINT pessoas_contribuinte_tem_ie "
            . "CHECK (ie_indicador <> '1' OR ie IS NOT NULL)"
        );

        Schema::create('pessoa_papeis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->string('papel', 20);
            $table->jsonb('dados')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['pessoa_id', 'papel']);
        });

        DB::statement(
            "ALTER TABLE pessoa_papeis ADD CONSTRAINT pessoa_papeis_papel_valido "
            . "CHECK (papel IN ('cliente','fornecedor','motorista','proprietario',"
            . "'oficina','seguradora','posto'))"
        );

        Schema::create('enderecos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->string('tipo', 20)->default('principal'); // principal|coleta|entrega|cobranca
            $table->string('logradouro', 150);
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 60)->nullable();
            $table->string('bairro', 80)->nullable();
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->char('cep', 8)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('principal')->default(false);
            $table->timestamps();

            $table->index(['pessoa_id', 'tipo']);
        });

        // Um endereço principal por pessoa — regra do banco, não da aplicação.
        DB::statement(
            "CREATE UNIQUE INDEX enderecos_principal_unico ON enderecos (pessoa_id) "
            . "WHERE principal = true"
        );

        Schema::create('contatos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->string('nome', 120);
            $table->string('cargo', 60)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('setor', 40)->nullable();
            $table->boolean('recebe_dfe')->default(false);
            $table->timestamps();

            $table->index('pessoa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contatos');
        Schema::dropIfExists('enderecos');
        Schema::dropIfExists('pessoa_papeis');
        Schema::dropIfExists('pessoas');
    }
};
