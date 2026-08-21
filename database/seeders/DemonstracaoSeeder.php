<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Cadastro\Documento;
use App\Models\Empresa;
use App\Models\Filial;
use App\Models\Municipio;
use App\Models\Pessoa;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Dados de DEMONSTRAÇÃO — transportadora fictícia, para apresentar o sistema
 * com tela cheia em vez de estado vazio.
 *
 * Regras que este seeder respeita de propósito:
 *
 *  · A filial nasce em AMBIENTE DE HOMOLOGAÇÃO (`ambiente_sefaz = 2`). Nunca
 *    se apresenta um sistema fiscal apontando para produção — um clique
 *    errado numa demonstração vira documento com valor jurídico.
 *  · Os CNPJ são fictícios mas com dígito verificador válido, porque o
 *    cadastro recusa documento inválido — e essa recusa é parte do que se
 *    quer mostrar.
 *  · Roda uma vez. Reexecutar não duplica: tudo é `firstOrCreate` por chave
 *    de negócio.
 *
 * NÃO roda junto do DatabaseSeeder. É explícito:
 *   php artisan tenants:run db:seed --argument="class=Database\\Seeders\\DemonstracaoSeeder"
 */
class DemonstracaoSeeder extends Seeder
{
    public function run(): void
    {
        TenantContext::semEscopo(function (): void {
            $empresa = $this->empresa();
            $filial = $this->filial($empresa);

            $this->usuarios($empresa, $filial);
            $this->pessoas($empresa);
        });

        $this->command?->newLine();
        $this->command?->info('Demonstração pronta. Entre com admin@serraazul.com.br / frota2026');
        $this->command?->warn('Troque essa senha antes de qualquer uso real.');
    }

    private function empresa(): Empresa
    {
        return Empresa::withoutGlobalScopes()->firstOrCreate(
            ['cnpj' => '12345678000195'],
            [
                'razao_social' => 'Transportes Serra Azul Ltda',
                'nome_fantasia' => 'Serra Azul Transportes',
                'timezone' => 'America/Bahia',
                'ativa' => true,
            ],
        );
    }

    private function filial(Empresa $empresa): Filial
    {
        $municipio = $this->municipio('Feira de Santana', 'BA');

        return Filial::withoutGlobalScopes()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'codigo' => 'FIL-01'],
            [
                'razao_social' => 'Transportes Serra Azul Ltda',
                'nome_fantasia' => 'Serra Azul — Matriz',
                'cnpj' => '12345678000195',
                'ie' => '103456789',
                'crt' => Filial::CRT_REGIME_NORMAL,
                'rntrc' => '11223344',
                'rntrc_validade' => now()->addYears(2)->toDateString(),
                'tp_transp' => '1',
                'matriz' => true,
                'logradouro' => 'Av. Getúlio Vargas',
                'numero' => '1200',
                'bairro' => 'Centro',
                'municipio_id' => $municipio->id,
                'cep' => '44001000',
                'telefone' => '7530000000',
                'email' => 'fiscal@serraazul.com.br',
                // Demonstração NUNCA aponta para produção.
                'ambiente_sefaz' => Filial::AMBIENTE_HOMOLOGACAO,
                'uf_autorizadora' => 'BA',
                'ativa' => true,
            ],
        );
    }

    private function usuarios(Empresa $empresa, Filial $filial): void
    {
        $usuarios = [
            ['Hirlei Andrade', 'admin@serraazul.com.br', 'Administrador', $filial->id],
            ['Ivandro Cunha', 'fiscal@serraazul.com.br', 'Fiscal', $filial->id],
            ['Rafael Dourado', 'operacao@serraazul.com.br', 'Operação', null],
            ['Patrícia Xavier', 'financeiro@serraazul.com.br', 'Financeiro', $filial->id],
        ];

        foreach ($usuarios as [$nome, $email, $papel, $filialId]) {
            $usuario = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $nome,
                    'password' => Hash::make('frota2026'),
                    'empresa_id' => $empresa->id,
                    'filial_id' => $filialId,
                    'ativo' => true,
                    'email_verified_at' => now(),
                ],
            );

            $usuario->syncRoles([$papel]);
        }
    }

    private function pessoas(Empresa $empresa): void
    {
        // [razão social, fantasia, tipo, documento, ie, indIE, papéis, município, UF, rntrc, tpTransp]
        $cadastro = [
            ['Agro Vale do Rio Ltda', 'Agro Vale', 'J', '11222333000181', '111222333', '1',
                ['cliente', 'proprietario'], 'Feira de Santana', 'BA', '98765432', '1'],
            ['Cerealista Boa Safra S/A', 'Boa Safra', 'J', '44555666000181', '107654321', '1',
                ['cliente'], 'Goiânia', 'GO', null, null],
            ['Distribuidora Sertão Ltda', 'Sertão', 'J', '77888999000181', null, '2',
                ['cliente'], 'Salvador', 'BA', null, null],
            ['Comercial Novo Horizonte Ltda', 'Novo Horizonte', 'J', '10456789000143', '104567890', '1',
                ['cliente'], 'Barreiras', 'BA', null, null],
            ['Oficina Diesel Norte Ltda', 'Diesel Norte', 'J', '33444555000181', null, '9',
                ['fornecedor', 'oficina'], 'Barreiras', 'BA', null, null],
            ['Posto Estrada Real Ltda', 'Estrada Real', 'J', '55666777000181', '105667770', '1',
                ['fornecedor', 'posto'], 'Luís Eduardo Magalhães', 'BA', null, null],
            ['Transrocha Transportes Ltda', 'Transrocha', 'J', '88999000000198', '108990000', '1',
                ['cliente', 'fornecedor'], 'Vitória', 'ES', '55667788', '1'],
            ['Seguradora Rota Segura S/A', 'Rota Segura', 'J', '22333444000181', null, '9',
                ['seguradora'], 'São Paulo', 'SP', null, null],
            ['Adenilson Rocha da Silva', 'TAC agregado', 'F', '52998224725', null, '9',
                ['motorista', 'proprietario'], 'Feira de Santana', 'BA', '12345670', '2'],
            ['Railson Barbosa dos Santos', 'Motorista CLT', 'F', '15350946056', null, '9',
                ['motorista'], 'Feira de Santana', 'BA', null, null],
        ];

        foreach ($cadastro as [$razao, $fantasia, $tipo, $documento, $ie, $indIe, $papeis, $cidade, $uf, $rntrc, $tpTransp]) {
            if (! Documento::valido($documento)) {
                // Documento inválido no seed vira cadastro que a própria tela
                // recusaria. Falhar aqui é melhor que demonstrar dado sujo.
                throw new RuntimeException("Documento inválido no seed de demonstração: {$razao} ({$documento})");
            }

            $municipio = $this->municipio($cidade, $uf);

            $pessoa = Pessoa::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresa->id, 'documento' => $documento],
                [
                    'tipo' => $tipo,
                    'razao_social' => $razao,
                    'nome_fantasia' => $fantasia,
                    'ie' => $ie,
                    'ie_indicador' => $indIe,
                    'rntrc' => $rntrc,
                    'rntrc_validade' => $rntrc !== null ? now()->addYears(2)->toDateString() : null,
                    'tp_transp' => $tpTransp,
                    'ativo' => true,
                ],
            );

            foreach ($papeis as $papel) {
                $pessoa->papeis()->firstOrCreate(['papel' => $papel], ['ativo' => true]);
            }

            $pessoa->enderecos()->firstOrCreate(
                ['principal' => true],
                [
                    'tipo' => 'principal',
                    'logradouro' => 'Rodovia BR-116',
                    'numero' => 'km 12',
                    'bairro' => 'Zona Rural',
                    'municipio_id' => $municipio->id,
                    'cep' => '44050000',
                ],
            );
        }
    }

    private function municipio(string $nome, string $uf): Municipio
    {
        $municipio = Municipio::where('nome', $nome)->where('uf', $uf)->first();

        if ($municipio === null) {
            throw new RuntimeException(
                "Município {$nome}/{$uf} não está na tabela. "
                . 'Rode `php artisan tenants:run municipios:importar` antes deste seeder.'
            );
        }

        return $municipio;
    }
}
