<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Cadastro\Documento;
use App\Domain\Cadastro\RegrasPessoa;
use PHPUnit\Framework\TestCase;

final class DocumentoTest extends TestCase
{
    public function testCpfValidoComEsemMascara(): void
    {
        $this->assertTrue(Documento::cpfValido('529.982.247-25'));
        $this->assertTrue(Documento::cpfValido('52998224725'));
        $this->assertTrue(Documento::cpfValido('  529 982 247 25 '));
    }

    public function testCpfInvalido(): void
    {
        $this->assertFalse(Documento::cpfValido('529.982.247-26'));
        $this->assertFalse(Documento::cpfValido('5299822472'));
        $this->assertFalse(Documento::cpfValido(''));
    }

    public function testCpfComDigitosRepetidosNaoPassa(): void
    {
        // Todos passam no módulo 11 e todos são inválidos na Receita.
        foreach (range(0, 9) as $d) {
            $this->assertFalse(
                Documento::cpfValido(str_repeat((string) $d, 11)),
                "CPF {$d}{$d}… não pode ser aceito"
            );
        }
    }

    public function testCnpjNumericoValido(): void
    {
        $this->assertTrue(Documento::cnpjValido('11.222.333/0001-81'));
        $this->assertTrue(Documento::cnpjValido('11222333000181'));
    }

    public function testCnpjNumericoInvalido(): void
    {
        $this->assertFalse(Documento::cnpjValido('11.222.333/0001-82'));
        $this->assertFalse(Documento::cnpjValido('1122233300018'));
        $this->assertFalse(Documento::cnpjValido('11111111111111'));
    }

    /**
     * IN RFB 2.229/2024 — as 12 primeiras posições passam a aceitar letras,
     * e o DV usa o valor ASCII menos 48. Um emissor que trate CNPJ como
     * inteiro quebra aqui, não em produção.
     */
    public function testCnpjAlfanumericoValido(): void
    {
        // DV calculado pela própria regra da IN, conferido posição a posição.
        $base = '12ABC34501DE';
        $dv = $this->dvEsperado($base);

        $this->assertTrue(Documento::cnpjValido($base . $dv));
        $this->assertTrue(Documento::ehAlfanumerico($base . $dv));
    }

    public function testCnpjAlfanumericoComDvErradoFalha(): void
    {
        $base = '12ABC34501DE';
        $dv = $this->dvEsperado($base);
        $errado = str_pad((string) (((int) $dv + 1) % 100), 2, '0', STR_PAD_LEFT);

        $this->assertFalse(Documento::cnpjValido($base . $errado));
    }

    public function testLetraNaoEhAceitaNoDigitoVerificador(): void
    {
        $this->assertFalse(Documento::cnpjValido('12ABC34501DEA1'));
        $this->assertFalse(Documento::cnpjValido('112223330001A1'));
    }

    public function testCnpjNumericoNaoEhMarcadoComoAlfanumerico(): void
    {
        $this->assertFalse(Documento::ehAlfanumerico('11222333000181'));
    }

    public function testTipoPeloTamanho(): void
    {
        $this->assertSame('CPF', Documento::tipo('529.982.247-25'));
        $this->assertSame('CNPJ', Documento::tipo('11.222.333/0001-81'));
        $this->assertNull(Documento::tipo('123'));
    }

    public function testFormatacao(): void
    {
        $this->assertSame('529.982.247-25', Documento::formatar('52998224725'));
        $this->assertSame('11.222.333/0001-81', Documento::formatar('11222333000181'));
        $this->assertSame('12.ABC.345/01DE-35', Documento::formatar('12ABC34501DE35'));
    }

    public function testMascaraProtegePessoaFisica(): void
    {
        $this->assertSame('529.***.***-25', Documento::mascarar('52998224725'));
        // CNPJ é público — não se mascara.
        $this->assertSame('11.222.333/0001-81', Documento::mascarar('11222333000181'));
    }

    /* ── Regras de pessoa ──────────────────────────────────── */

    public function testContribuinteSemIeNaoEmite(): void
    {
        $pendencias = RegrasPessoa::pendenciasParaEmissao([
            'tipo' => 'J',
            'documento' => '11222333000181',
            'ie' => null,
            'ie_indicador' => '1',
        ]);

        $this->assertContains('Contribuinte de ICMS sem inscrição estadual', $pendencias);
    }

    public function testIsentoSemIeEmiteNormalmente(): void
    {
        $this->assertTrue(RegrasPessoa::aptaParaEmissao([
            'tipo' => 'J',
            'documento' => '11222333000181',
            'ie' => null,
            'ie_indicador' => '2',
        ]));
    }

    public function testDocumentoInvalidoImpedeEmissao(): void
    {
        $pendencias = RegrasPessoa::pendenciasParaEmissao([
            'tipo' => 'J',
            'documento' => '11222333000182',
            'ie_indicador' => '9',
        ]);

        $this->assertContains('CPF ou CNPJ inválido', $pendencias);
    }

    public function testEstrangeiroNaoPrecisaDeCpfNemCnpj(): void
    {
        $this->assertTrue(RegrasPessoa::aptaParaEmissao([
            'tipo' => 'E',
            'documento' => '',
            'ie_indicador' => '9',
        ]));
    }

    public function testTransportadorSemRntrcNaoEmite(): void
    {
        $pendencias = RegrasPessoa::pendenciasParaEmissao([
            'tipo' => 'F',
            'documento' => '52998224725',
            'ie_indicador' => '9',
            'papeis' => ['motorista'],
            'rntrc' => null,
        ]);

        $this->assertContains('RNTRC obrigatório para transportador', $pendencias);
    }

    public function testClienteSemRntrcEmiteNormalmente(): void
    {
        $this->assertTrue(RegrasPessoa::aptaParaEmissao([
            'tipo' => 'J',
            'documento' => '11222333000181',
            'ie_indicador' => '9',
            'papeis' => ['cliente'],
            'rntrc' => null,
        ]));
    }

    public function testSugestaoDeIndicadorNuncaChutaContribuinte(): void
    {
        $this->assertSame('1', RegrasPessoa::sugerirIndicadorIe('J', '111222333'));
        $this->assertSame('2', RegrasPessoa::sugerirIndicadorIe('J', null));
        $this->assertSame('2', RegrasPessoa::sugerirIndicadorIe('J', 'ISENTO'));
        $this->assertSame('2', RegrasPessoa::sugerirIndicadorIe('J', '   '));
        $this->assertSame('9', RegrasPessoa::sugerirIndicadorIe('F', null));
    }

    public function testPapeisEspelhamOCheckDoBanco(): void
    {
        foreach (RegrasPessoa::PAPEIS as $papel) {
            $this->assertTrue(RegrasPessoa::papelValido($papel));
        }

        foreach (['despachante', 'Cliente', ''] as $invalido) {
            $this->assertFalse(RegrasPessoa::papelValido($invalido));
        }
    }

    /** Reimplementação independente do DV, para não testar o código com ele mesmo. */
    private function dvEsperado(string $base): string
    {
        $calc = function (string $b): int {
            $pesos = [];
            $peso = 2;
            for ($i = strlen($b) - 1; $i >= 0; $i--) {
                $pesos[$i] = $peso;
                $peso = $peso === 9 ? 2 : $peso + 1;
            }
            $soma = 0;
            foreach (str_split($b) as $i => $c) {
                $soma += (ord($c) - 48) * $pesos[$i];
            }
            $r = $soma % 11;

            return $r < 2 ? 0 : 11 - $r;
        };

        $d1 = $calc($base);

        return $d1 . $calc($base . $d1);
    }
}
