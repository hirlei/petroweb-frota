<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Cadastro\Documento;
use App\Models\Abastecimento;
use App\Models\Composicao;
use App\Models\ComposicaoItem;
use App\Models\Empresa;
use App\Models\Filial;
use App\Models\Mercadoria;
use App\Models\Motorista;
use App\Models\Municipio;
use App\Models\OcItem;
use App\Models\Ocorrencia;
use App\Models\OrdemColeta;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Pessoa;
use App\Models\Rota;
use App\Models\RotaPonto;
use App\Models\TabelaFrete;
use App\Models\TabelaFreteItem;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\VeiculoDocumento;
use App\Models\Viagem;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
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
            $this->mercadorias($empresa);
            $this->tabelasFrete($empresa);
            $this->rotas($empresa);
            $this->veiculos($empresa, $filial);
            $this->composicoes($empresa);
            $this->motoristas($empresa);
            $this->documentosVeiculo($empresa);
            $this->abastecimentos($empresa, $filial);
            $this->ocorrencias($empresa);
            $this->manutencao($empresa, $filial);
            $this->ordensColeta($empresa, $filial);
            $this->viagens($empresa, $filial);
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

    private function pessoaPorDoc(Empresa $empresa, string $doc): ?Pessoa
    {
        return Pessoa::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->where('documento', $doc)
            ->first();
    }

    private function mercadorias(Empresa $empresa): void
    {
        $itens = [
            ['MILHO-GRAO', 'Milho em grãos a granel', '10059010', 'KG', false, []],
            ['SOJA-FARELO', 'Farelo de soja', '23040010', 'KG', false, []],
            ['ACUCAR-SC', 'Açúcar cristal ensacado 50kg', '17019900', 'SC', false, []],
            ['DEFENSIVO-CL9', 'Defensivo agrícola — perigoso', '38089329', 'LT', true, [
                'num_onu' => '3082', 'nome_embarque' => 'Substância que apresenta risco para o meio ambiente, líquida, N.E.',
                'classe_risco' => '9', 'grupo_embalagem' => 'III', 'exige_mopp' => true, 'risco_ambiental' => true,
            ]],
        ];

        foreach ($itens as [$codigo, $descricao, $ncm, $unidade, $perigoso, $extra]) {
            Mercadoria::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresa->id, 'codigo_interno' => $codigo],
                array_merge([
                    'descricao' => $descricao,
                    'ncm' => $ncm,
                    'unidade_comercial' => $unidade,
                    'eh_perigoso' => $perigoso,
                    'ativo' => true,
                ], $extra),
            );
        }
    }

    private function tabelasFrete(Empresa $empresa): void
    {
        $tabela = TabelaFrete::withoutGlobalScopes()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'descricao' => 'Tabela geral 2026 — grãos e granéis'],
            [
                'pessoa_id' => null,
                'vigencia_inicio' => now()->startOfYear()->toDateString(),
                'ativo' => true,
            ],
        );

        $componentes = [
            ['peso', 'por_ton', 185.0, 350.0, 0],
            ['gris', 'percentual_valor', 0.30, null, 1],
            ['pedagio', 'por_km', 0.28, null, 2],
        ];

        foreach ($componentes as [$comp, $base, $valor, $minimo, $ordem]) {
            TabelaFreteItem::withoutGlobalScopes()->firstOrCreate(
                ['tabela_frete_id' => $tabela->id, 'componente' => $comp],
                ['base_calculo' => $base, 'valor' => $valor, 'minimo' => $minimo, 'ordem' => $ordem],
            );
        }
    }

    private function rotas(Empresa $empresa): void
    {
        $origem = $this->municipio('Feira de Santana', 'BA');
        $destino = $this->municipio('Goiânia', 'GO');
        $meio = $this->municipio('Barreiras', 'BA');

        $rota = Rota::withoutGlobalScopes()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'descricao' => 'Feira de Santana → Goiânia'],
            [
                'municipio_origem_id' => $origem->id,
                'municipio_destino_id' => $destino->id,
                'distancia_km' => 1180,
                'tempo_estimado_min' => 1230,
                'valor_pedagio_estimado' => 330.40,
                'ativa' => true,
            ],
        );

        $pontos = [
            [1, 'origem', $origem->id, 'Base Serra Azul', 0, null],
            [2, 'pedagio', $meio->id, 'Praça Barreiras', 620, 84.20],
            [3, 'destino', $destino->id, 'CD do cliente', 1180, null],
        ];

        foreach ($pontos as [$ordem, $tipo, $munId, $desc, $km, $ped]) {
            RotaPonto::withoutGlobalScopes()->firstOrCreate(
                ['rota_id' => $rota->id, 'ordem' => $ordem],
                ['tipo' => $tipo, 'municipio_id' => $munId, 'descricao' => $desc,
                    'distancia_acumulada_km' => $km, 'valor_pedagio' => $ped],
            );
        }
    }

    private function veiculos(Empresa $empresa, Filial $filial): void
    {
        $agroVale = $this->pessoaPorDoc($empresa, '11222333000181'); // proprietário terceiro

        // [placa, tipo, eixos, tara, pbt, pbtc, capacidade, marca, modelo, anoF, anoM, tp_rod, propriedade, propId, propRntrc, media, odometro, status]
        $frota = [
            ['OKZ1A34', 'tracao', 3, 8200, 23000, 66000, null, 'Volvo', 'FH 540', 2021, 2022, '03', 'propria', null, null, 2.4, 486000, 'ativo'],
            ['JKL5B67', 'tracao', 3, 8000, 23000, 57000, null, 'Scania', 'R 450', 2020, 2021, '03', 'propria', null, null, 2.3, 612000, 'ativo'],
            ['PXR3C56', 'semirreboque', 3, 6500, null, null, 37000, 'Randon', 'Graneleiro', 2020, 2020, null, 'propria', null, null, null, 0, 'ativo'],
            ['RTA4D67', 'semirreboque', 3, 6400, null, null, 37000, 'Librelato', 'Graneleiro', 2019, 2019, null, 'propria', null, null, null, 0, 'ativo'],
            ['TCK9E88', 'semirreboque', 3, 6600, null, null, 36000, 'Guerra', 'Graneleiro', 2018, 2018, null, 'terceiro',
                $agroVale?->id, '98765432', null, 0, 'manutencao'],
        ];

        foreach ($frota as $v) {
            [$placa, $tipo, $eixos, $tara, $pbt, $pbtc, $cap, $marca, $modelo, $anoF, $anoM, $tpRod, $prop, $propId, $propRntrc, $media, $odo, $status] = $v;

            Veiculo::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresa->id, 'placa' => $placa],
                [
                    'filial_id' => $filial->id,
                    'tipo' => $tipo,
                    'eixos' => $eixos,
                    'tara_kg' => $tara,
                    'pbt_kg' => $pbt,
                    'pbtc_kg' => $pbtc,
                    'capacidade_kg' => $cap,
                    'marca' => $marca,
                    'modelo' => $modelo,
                    'ano_fabricacao' => $anoF,
                    'ano_modelo' => $anoM,
                    'tp_rod' => $tpRod,
                    'uf_licenciamento' => 'BA',
                    'municipio_licenciamento_id' => $filial->municipio_id,
                    'propriedade' => $prop,
                    'proprietario_id' => $propId,
                    'proprietario_rntrc' => $propRntrc,
                    'proprietario_tp_transp' => $prop === 'terceiro' ? '1' : null,
                    'combustivel' => $tipo === 'tracao' ? 'Diesel S10' : null,
                    'media_referencia_kml' => $media,
                    'odometro_atual' => $odo,
                    'status' => $status,
                ],
            );
        }
    }

    private function veiculoPorPlaca(Empresa $empresa, string $placa): ?Veiculo
    {
        return Veiculo::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)->where('placa', $placa)->first();
    }

    private function composicoes(Empresa $empresa): void
    {
        $tracao = $this->veiculoPorPlaca($empresa, 'OKZ1A34');
        $r1 = $this->veiculoPorPlaca($empresa, 'PXR3C56');
        $r2 = $this->veiculoPorPlaca($empresa, 'RTA4D67');

        if ($tracao === null || $r1 === null || $r2 === null) {
            return;
        }

        $composicao = Composicao::withoutGlobalScopes()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'descricao' => 'Bitrem graneleiro — frota BA'],
            [
                'veiculo_tracao_id' => $tracao->id,
                'eixos_total' => 9,
                'tara_total_kg' => 21200,
                'pbtc_kg' => 66000,
                'capacidade_kg' => 74000,
                'categ_comb_veic' => '12',
                'exige_aet' => true,
                'ativa' => true,
            ],
        );

        foreach ([[$tracao->id, 1], [$r1->id, 2], [$r2->id, 3]] as [$vid, $ordem]) {
            ComposicaoItem::withoutGlobalScopes()->firstOrCreate(
                ['composicao_id' => $composicao->id, 'veiculo_id' => $vid],
                ['ordem' => $ordem],
            );
        }
    }

    private function motoristas(Empresa $empresa): void
    {
        // [doc da pessoa, cnh, cat, validade(dias a partir de hoje), vinculo, rntrc, toxicologico(dias)]
        $lista = [
            ['52998224725', '04567890123', 'E', 35, 'agregado', '12345670', 210],   // CNH vencendo
            ['15350946056', '09876543210', 'E', 300, 'clt', null, -12],              // toxicológico vencido
        ];

        foreach ($lista as [$doc, $cnh, $cat, $validadeDias, $vinculo, $rntrc, $toxDias]) {
            $pessoa = $this->pessoaPorDoc($empresa, $doc);

            if ($pessoa === null) {
                continue;
            }

            Motorista::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresa->id, 'pessoa_id' => $pessoa->id],
                [
                    'cnh_numero' => $cnh,
                    'cnh_categoria' => $cat,
                    'cnh_validade' => Carbon::today()->addDays($validadeDias)->toDateString(),
                    'cnh_ear' => true,
                    'vinculo' => $vinculo,
                    'tp_transp' => $vinculo === 'agregado' ? '2' : null,
                    'rntrc' => $rntrc,
                    'rntrc_validade' => $rntrc !== null ? Carbon::today()->addYears(1)->toDateString() : null,
                    'toxicologico_validade' => Carbon::today()->addDays($toxDias)->toDateString(),
                    'admissao' => Carbon::today()->subYears(2)->toDateString(),
                    'status' => 'ativo',
                ],
            );
        }
    }

    private function documentosVeiculo(Empresa $empresa): void
    {
        // [placa, tipo, vencimento(dias), bloqueia]
        $docs = [
            ['OKZ1A34', 'crlv', 20, true],    // vence em 20 dias
            ['OKZ1A34', 'civ', -6, true],     // vencido
            ['JKL5B67', 'crlv', 55, true],
            ['JKL5B67', 'tacografo', 130, false],
            ['PXR3C56', 'cipp', 12, true],    // vence em 12 dias
            ['RTA4D67', 'crlv', 200, true],
        ];

        foreach ($docs as [$placa, $tipo, $dias, $bloqueia]) {
            $veiculo = $this->veiculoPorPlaca($empresa, $placa);

            if ($veiculo === null) {
                continue;
            }

            VeiculoDocumento::withoutGlobalScopes()->firstOrCreate(
                ['documentavel_type' => Veiculo::class, 'documentavel_id' => $veiculo->id, 'tipo' => $tipo],
                [
                    'empresa_id' => $empresa->id,
                    'vencimento' => Carbon::today()->addDays($dias)->toDateString(),
                    'bloqueia_operacao' => $bloqueia,
                ],
            );
        }
    }

    private function abastecimentos(Empresa $empresa, Filial $filial): void
    {
        $veiculo = $this->veiculoPorPlaca($empresa, 'OKZ1A34');
        $posto = $this->pessoaPorDoc($empresa, '55666777000181');   // Posto Estrada Real
        $motorista = Motorista::withoutGlobalScopes()->where('empresa_id', $empresa->id)->first();

        if ($veiculo === null) {
            return;
        }

        // [ref, diasAtras, litros, valorLitro, odometro, media, desvio, alerta]
        $lancamentos = [
            ['demo-ab-1', 40, 520.0, 5.94, 480000, 2.42, 0.8, false],
            ['demo-ab-2', 22, 540.5, 6.02, 481260, 2.33, -2.9, false],
            ['demo-ab-3', 6, 610.0, 6.10, 482460, 1.97, -17.9, true],   // desvio alto → alerta
        ];

        foreach ($lancamentos as [$ref, $diasAtras, $litros, $valorLitro, $odo, $media, $desvio, $alerta]) {
            Abastecimento::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresa->id, 'origem' => 'manual', 'referencia_externa' => $ref],
                [
                    'filial_id' => $filial->id,
                    'veiculo_id' => $veiculo->id,
                    'motorista_id' => $motorista?->id,
                    'posto_id' => $posto?->id,
                    'data_hora' => Carbon::now()->subDays($diasAtras)->setTime(9, 30),
                    'combustivel' => 'Diesel S10',
                    'litros' => $litros,
                    'valor_litro' => $valorLitro,
                    'valor_total' => round($litros * $valorLitro, 2),
                    'odometro' => $odo,
                    'tanque_cheio' => true,
                    'media_calculada' => $media,
                    'desvio_percentual' => $desvio,
                    'alerta' => $alerta,
                ],
            );
        }
    }

    private function ocorrencias(Empresa $empresa): void
    {
        $municipio = $this->municipio('Barreiras', 'BA');

        $itens = [
            ['avaria', 4, 'Avaria parcial em 12 sacas de açúcar por chuva na carga/descarga.', 'transportadora', 3480.00, 'aberta'],
            ['multa', 9, 'Multa por excesso de peso no eixo traseiro — pesagem BR-242.', 'motorista', 293.47, 'em_analise'],
            ['atraso', 15, 'Atraso de 6h na entrega por bloqueio na BR-020.', 'terceiro', null, 'resolvida'],
        ];

        foreach ($itens as [$tipo, $diasAtras, $descricao, $responsavel, $prejuizo, $status]) {
            Ocorrencia::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresa->id, 'tipo' => $tipo, 'descricao' => $descricao],
                [
                    'data_hora' => Carbon::now()->subDays($diasAtras)->setTime(14, 0),
                    'municipio_id' => $municipio->id,
                    'responsavel' => $responsavel,
                    'valor_prejuizo' => $prejuizo,
                    'status' => $status,
                ],
            );
        }
    }

    private function manutencao(Empresa $empresa, Filial $filial): void
    {
        $oficina = $this->pessoaPorDoc($empresa, '33444555000181'); // Oficina Diesel Norte
        $okz = $this->veiculoPorPlaca($empresa, 'OKZ1A34');
        $tck = $this->veiculoPorPlaca($empresa, 'TCK9E88');

        // OS encerrada no mês — alimenta o custo de manutenção do dashboard.
        if ($okz !== null) {
            $os = OrdemServico::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresa->id, 'numero' => 'OS-0041'],
                [
                    'filial_id' => $filial->id,
                    'veiculo_id' => $okz->id,
                    'tipo' => 'revisao',
                    'oficina_id' => $oficina?->id,
                    'interna' => false,
                    'abertura' => Carbon::now()->subDays(9)->toDateString(),
                    'encerramento' => Carbon::now()->subDays(4)->toDateString(),
                    'odometro' => 482000,
                    'status' => 'encerrada',
                    'valor_pecas' => 980.00,
                    'valor_mao_obra' => 539.00,
                    'valor_total' => 1519.00,
                    'observacoes' => 'Revisão de 10.000 km — troca de óleo, filtros e correias.',
                ],
            );

            foreach ([
                ['peca', 'Óleo motor 15W40 (balde 20L)', 'OL-1540', 1, 620.00],
                ['peca', 'Kit filtros (óleo, ar, combustível)', 'KF-VLV', 1, 360.00],
                ['servico', 'Mão de obra revisão preventiva', null, 1, 539.00],
            ] as [$tipo, $desc, $cod, $qtd, $unit]) {
                OrdemServicoItem::withoutGlobalScopes()->firstOrCreate(
                    ['ordem_servico_id' => $os->id, 'descricao' => $desc],
                    ['tipo' => $tipo, 'codigo' => $cod, 'quantidade' => $qtd,
                        'valor_unitario' => $unit, 'valor_total' => $qtd * $unit],
                );
            }
        }

        // OS aberta — aparece em "próximas manutenções".
        if ($tck !== null) {
            OrdemServico::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresa->id, 'numero' => 'OS-0042'],
                [
                    'filial_id' => $filial->id,
                    'veiculo_id' => $tck->id,
                    'tipo' => 'corretiva',
                    'oficina_id' => $oficina?->id,
                    'interna' => false,
                    'abertura' => Carbon::now()->subDays(3)->toDateString(),
                    'status' => 'aguardando_peca',
                    'valor_pecas' => 0,
                    'valor_mao_obra' => 0,
                    'valor_total' => 0,
                    'observacoes' => 'Vazamento no sistema de freio do 2º eixo — aguardando cuíca.',
                ],
            );
        }
    }

    private function ordensColeta(Empresa $empresa, Filial $filial): void
    {
        $tabela = TabelaFrete::withoutGlobalScopes()->where('empresa_id', $empresa->id)->first();
        $feira = $this->municipio('Feira de Santana', 'BA');
        $goiania = $this->municipio('Goiânia', 'GO');
        $salvador = $this->municipio('Salvador', 'BA');

        // [numero, diasAtras, clienteDoc, remetenteDoc, destinatarioDoc, munIni, munFim, peso, valor, frete, status, itens]
        $ordens = [
            ['000101', 3, '11222333000181', '11222333000181', '44555666000181', $feira->id, $goiania->id, 32000, 148000, 6280.00, 'aberta', [
                ['Milho a granel', 'SC', 640, 32000, 148000, '35240611222333000181550010000001011000001015'],
            ]],
            ['000100', 8, '44555666000181', '44555666000181', '10456789000143', $goiania->id, $feira->id, 30500, 132500, 5910.00, 'coletada', [
                ['Farelo de soja', 'SC', 610, 30500, 132500, '52240644555666000181550010000000991000000998'],
            ]],
            ['000099', 16, '77888999000181', '77888999000181', '77888999000181', $salvador->id, $goiania->id, 28000, 96000, 5140.00, 'faturada', [
                ['Fertilizante NPK', 'TON', 28, 28000, 96000, null],
            ]],
        ];

        foreach ($ordens as [$numero, $diasAtras, $cliDoc, $remDoc, $destDoc, $munIni, $munFim, $peso, $valor, $frete, $status, $itens]) {
            $cliente = $this->pessoaPorDoc($empresa, $cliDoc);

            if ($cliente === null) {
                continue;
            }

            $ordem = OrdemColeta::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresa->id, 'numero' => $numero],
                [
                    'filial_id' => $filial->id,
                    'data' => Carbon::today()->subDays($diasAtras)->toDateString(),
                    'cliente_id' => $cliente->id,
                    'tomador_tipo' => 'remetente',
                    'remetente_id' => $this->pessoaPorDoc($empresa, $remDoc)?->id,
                    'destinatario_id' => $this->pessoaPorDoc($empresa, $destDoc)?->id,
                    'municipio_inicio_id' => $munIni,
                    'municipio_fim_id' => $munFim,
                    'previsao_coleta' => Carbon::today()->subDays($diasAtras)->setTime(8, 0),
                    'previsao_entrega' => Carbon::today()->subDays($diasAtras)->addDay()->setTime(18, 0),
                    'peso_bruto' => $peso,
                    'volumes' => (int) $itens[0][2],
                    'valor_mercadoria' => $valor,
                    'tabela_frete_id' => $tabela?->id,
                    'valor_frete_calculado' => $frete,
                    'status' => $status,
                ],
            );

            foreach ($itens as [$desc, $unidade, $qtd, $pesoItem, $valorItem, $chave]) {
                OcItem::withoutGlobalScopes()->firstOrCreate(
                    ['ordem_coleta_id' => $ordem->id, 'descricao' => $desc],
                    [
                        'quantidade' => $qtd,
                        'unidade' => $unidade,
                        'peso' => $pesoItem,
                        'valor' => $valorItem,
                        'nfe_chave' => $chave,
                    ],
                );
            }
        }
    }

    private function viagens(Empresa $empresa, Filial $filial): void
    {
        $tracao = $this->veiculoPorPlaca($empresa, 'OKZ1A34');
        $rota = Rota::withoutGlobalScopes()->where('empresa_id', $empresa->id)->first();
        $motorista = Motorista::withoutGlobalScopes()->where('empresa_id', $empresa->id)->first();
        $feira = $this->municipio('Feira de Santana', 'BA');
        $goiania = $this->municipio('Goiânia', 'GO');

        if ($tracao === null || $motorista === null) {
            return;
        }

        $snapshot = ['PXR-3C56', 'RTA-4D67'];

        // [numero, diasAtras, status, kmIni, kmFin, receita, comb, ped, mot, manut, outros]
        $lista = [
            ['000042', 2, 'em_transito', 486000, 486000, 12190.00, 5180.00, 1240.00, 1860.00, 480.00, 200.00],
            ['000041', 12, 'entregue', 484800, 486000, 11800.00, 5020.00, 1210.00, 1800.00, 0.00, 180.00],
            ['000040', 25, 'encerrada', 483600, 484800, 11450.00, 4980.00, 1190.00, 1760.00, 320.00, 150.00],
        ];

        foreach ($lista as [$numero, $diasAtras, $status, $kmIni, $kmFin, $receita, $comb, $ped, $mot, $manut, $outros]) {
            $viagem = Viagem::withoutGlobalScopes()->firstOrNew(
                ['empresa_id' => $empresa->id, 'numero' => $numero],
            );

            if ($viagem->exists) {
                continue;
            }

            $viagem->fill([
                'filial_id' => $filial->id,
                'tipo' => 'carga_lotacao',
                'veiculo_tracao_id' => $tracao->id,
                'composicao_snapshot' => $snapshot,
                'motorista_id' => $motorista->id,
                'rota_id' => $rota?->id,
                'municipio_origem_id' => $feira->id,
                'municipio_destino_id' => $goiania->id,
                'saida_prevista' => Carbon::now()->subDays($diasAtras)->setTime(6, 0),
                'saida_real' => Carbon::now()->subDays($diasAtras)->setTime(6, 30),
                'chegada_prevista' => Carbon::now()->subDays($diasAtras)->addDay()->setTime(20, 0),
                'chegada_real' => $status === 'em_transito' ? null : Carbon::now()->subDays($diasAtras)->addDay()->setTime(19, 20),
                'km_inicial' => $kmIni,
                'km_final' => $kmFin,
                'km_percorrido' => $kmFin - $kmIni,
                'peso_total' => 32000,
                'valor_carga' => 148000,
                'custo_combustivel' => $comb,
                'custo_pedagio' => $ped,
                'custo_motorista' => $mot,
                'custo_manutencao' => $manut,
                'custo_outros' => $outros,
                'receita_total' => $receita,
                'status' => $status,
            ]);

            $viagem->consolidarCustos();
            $viagem->save();
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
