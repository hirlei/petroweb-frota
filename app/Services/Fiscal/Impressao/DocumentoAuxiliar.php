<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Impressao;

use App\Domain\Cadastro\Documento;
use App\Domain\Fiscal\CodigoBarras128;
use App\Domain\Fiscal\RegrasCce;
use App\Domain\Fiscal\RegrasCiot;
use App\Models\Cte;
use App\Models\CteEvento;
use App\Models\Filial;
use App\Models\Mdfe;
use App\Models\Municipio;
use App\Models\Pessoa;
use App\Models\ValePedagio;
use App\Models\Veiculo;
use App\Support\Impressao\QrCodeSvg;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Monta os dados do DACTE e do DAMDFE (mockup aprovado em 03/10/2026) — o
 * leiaute fica nas views fiscal/dacte e fiscal/damdfe, que o dompdf converte em
 * PDF A4.
 *
 * Marca d'água: homologação → "Sem valor fiscal"; rascunho/rejeitado → "Prévia —
 * não é documento fiscal"; cancelado → "Cancelado". O QR Code usa o texto que a
 * SEFAZ devolver no payload (qrCodCTe / qrCodMDFe); sem ele, monta a URL de
 * consulta do portal (config fiscal.qrcode).
 */
final class DocumentoAuxiliar
{
    /** @return array<string,mixed> */
    public function dacte(Cte $cte): array
    {
        $cte->loadMissing([
            'filial.municipio', 'tomador.enderecoPrincipal.municipio', 'remetente.enderecoPrincipal.municipio',
            'destinatario.enderecoPrincipal.municipio', 'expedidor.enderecoPrincipal.municipio',
            'recebedor.enderecoPrincipal.municipio', 'municipioInicio', 'municipioFim', 'componentes', 'documentos',
            'viagens.ciot',
        ]);

        $chave = $this->chaveValida($cte->chave);
        $viagem = $cte->viagens->first();
        $ciot = $cte->viagens->map(fn ($v) => $v->ciot)->first(fn ($c) => $c?->valido());

        return [
            'doc' => $cte,
            'emitente' => $this->emitente($cte->filial),
            'marca' => $this->marca($cte->status, (int) $cte->ambiente),
            'homologacao' => (int) $cte->ambiente === 2,
            'chave' => $chave,
            'chaveFormatada' => $chave ? $this->formatarChave($chave) : null,
            'barras' => $chave ? $this->imagem(CodigoBarras128::svg($chave, 1, 40)) : null,
            'qr' => $chave ? $this->qr((string) data_get($cte->payload, 'qrcode', ''), 'cte', $chave, (int) $cte->ambiente) : null,
            'numero' => $cte->numero ? number_format((int) $cte->numero, 0, ',', '.') : 'Rascunho',
            'tipoCte' => [0 => 'Normal', 1 => 'Complementar', 2 => 'Anulação', 3 => 'Substituto'][(int) $cte->tipo_cte] ?? '—',
            'tipoServico' => [0 => 'Normal', 1 => 'Subcontratação', 2 => 'Redespacho', 3 => 'Redespacho intermediário', 4 => 'Vinculado a multimodal'][(int) $cte->tipo_servico] ?? '—',
            'tomadorPapel' => config('fiscal.cte.tomadores.' . (int) $cte->tomador_tipo, 'Outros'),
            'partes' => [
                'Remetente' => $this->parte($cte->remetente),
                'Destinatário' => $this->parte($cte->destinatario),
                'Expedidor' => $this->parte($cte->expedidor),
                'Recebedor' => $this->parte($cte->recebedor),
            ],
            'tomador' => $this->parte($cte->tomador),
            'cst' => $cte->icms_cst ? $cte->icms_cst . ' — ' . (config('fiscal.cte.cst.' . $cte->icms_cst) ?? '') : '—',
            'componentes' => $cte->componentes->sortBy('ordem')->values(),
            'documentos' => $cte->documentos,
            'ciot' => $ciot?->numeroFormatado(),
            'previsaoEntrega' => $viagem?->chegada_prevista?->format('d/m/Y'),
            'viagem' => $viagem?->numero,
            'rntrc' => $cte->filial?->rntrc,
        ];
    }

    /** @return array<string,mixed> */
    public function damdfe(Mdfe $mdfe): array
    {
        $mdfe->loadMissing(['filial.municipio', 'veiculoTracao.proprietario', 'documentos', 'viagem']);

        $chave = $this->chaveValida($mdfe->chave);
        $rntrcEmpresa = $mdfe->filial?->rntrc;

        $veiculos = [];
        if ($v = $mdfe->veiculoTracao) {
            $veiculos[] = ['placa' => $v->placaFormatada(), 'tipo' => 'Tração', 'rntrc' => $v->rntrcParaMdfe() ?? $rntrcEmpresa];
        }
        foreach ((array) ($mdfe->reboques ?? []) as $r) {
            $placa = (string) ($r['placa'] ?? '');
            $rv = $placa !== '' ? Veiculo::query()->where('placa', $placa)->first() : null;
            $veiculos[] = ['placa' => $rv?->placaFormatada() ?? $placa, 'tipo' => 'Reboque', 'rntrc' => $rv?->rntrcParaMdfe() ?? $rntrcEmpresa];
        }

        $vales = ValePedagio::query()->ativos()->where('mdfe_id', $mdfe->id)->where('dispensado', false)->get();
        $municipios = Municipio::query()->whereIn('id', $mdfe->documentos->pluck('municipio_descarregamento_id')->filter()->unique())->get()->keyBy('id');

        $documentos = $mdfe->documentos->map(fn ($d) => [
            'tipo' => strtoupper((string) $d->tipo) === 'CTE' ? 'CT-e' : 'NF-e',
            'chave' => $d->chave ? $this->formatarChave((string) $d->chave) : '—',
            'descarga' => ($m = $municipios->get($d->municipio_descarregamento_id)) ? "{$m->nome}/{$m->uf}" : '—',
        ]);

        return [
            'doc' => $mdfe,
            'emitente' => $this->emitente($mdfe->filial),
            'marca' => $this->marca($mdfe->status, (int) $mdfe->ambiente),
            'homologacao' => (int) $mdfe->ambiente === 2,
            'chave' => $chave,
            'chaveFormatada' => $chave ? $this->formatarChave($chave) : null,
            'barras' => $chave ? $this->imagem(CodigoBarras128::svg($chave, 1, 40)) : null,
            'qr' => $chave ? $this->qr((string) data_get($mdfe->payload, 'qrcode', ''), 'mdfe', $chave, (int) $mdfe->ambiente) : null,
            'numero' => $mdfe->numero ? number_format((int) $mdfe->numero, 0, ',', '.') : 'Rascunho',
            'qtdCte' => $mdfe->documentos->filter(fn ($d) => strtolower((string) $d->tipo) === 'cte')->count(),
            'qtdNfe' => $mdfe->documentos->filter(fn ($d) => strtolower((string) $d->tipo) === 'nfe')->count(),
            'veiculos' => $veiculos,
            'condutores' => collect((array) ($mdfe->condutores ?? []))->map(fn ($c) => [
                'cpf' => Documento::formatar((string) ($c['cpf'] ?? '')), 'nome' => mb_strtoupper((string) ($c['nome'] ?? '')),
            ])->all(),
            'vales' => $vales->map(fn (ValePedagio $vp) => [
                'fornecedora' => Documento::formatar((string) $vp->cnpj_forn),
                'responsavel' => $vp->pagador_documento ? Documento::formatar((string) $vp->pagador_documento) : '—',
                'compra' => $vp->idvpo,
                'valor' => $vp->valor,
            ])->all(),
            'ciot' => $mdfe->ciot ? RegrasCiot::formatar($mdfe->ciot) : null,
            'ciotResponsavel' => $mdfe->ciot_cpf_cnpj ? Documento::formatar((string) $mdfe->ciot_cpf_cnpj) : null,
            'carregamento' => collect((array) ($mdfe->municipio_carregamento ?? []))->pluck('nome')->map(fn ($n) => mb_strtoupper((string) $n) . ' — ' . $mdfe->uf_inicio)->implode(', '),
            'descarregamento' => $municipios->map(fn ($m) => mb_strtoupper($m->nome) . ' — ' . $m->uf)->implode(', '),
            'percurso' => implode(' → ', (array) ($mdfe->percurso_ufs ?? [])),
            'documentos' => $documentos,
            'seguro' => $mdfe->seguro,
            'viagem' => $mdfe->viagem?->numero,
        ];
    }

    /**
     * Impressão da carta de correção (110110) — comprovante que acompanha o
     * DACTE quando o cliente ou a fiscalização pedir.
     *
     * @return array<string,mixed>
     */
    public function cce(Cte $cte, CteEvento $evento): array
    {
        $cte->loadMissing('filial.municipio');
        $chave = $this->chaveValida($cte->chave);

        return [
            'cte' => $cte,
            'evento' => $evento,
            'emitente' => $this->emitente($cte->filial),
            'homologacao' => (int) $cte->ambiente === 2,
            'numero' => number_format((int) $cte->numero, 0, ',', '.'),
            'chaveFormatada' => $chave ? $this->formatarChave($chave) : '—',
            'correcoes' => array_values((array) ($evento->correcoes ?? [])),
            'catalogo' => RegrasCce::CATALOGO,
            'condicao' => RegrasCce::CONDICAO_USO,
        ];
    }

    /* ── auxiliares ── */

    /** @return array<string,mixed> */
    public function emitente(?Filial $f): array
    {
        if ($f === null) {
            return ['nome' => '—', 'linhas' => [], 'logo' => null];
        }

        $endereco = trim(implode(', ', array_filter([$f->logradouro, $f->numero, $f->complemento])) . ($f->bairro ? ' — ' . $f->bairro : ''));
        $cidade = trim(($f->municipio ? $f->municipio->nome . '/' . $f->municipio->uf : '') . ($f->cep ? ' · CEP ' . substr((string) $f->cep, 0, 5) . '-' . substr((string) $f->cep, 5) : '') . ($f->telefone ? ' · Fone ' . $f->telefone : ''));

        return [
            'nome' => mb_strtoupper((string) $f->razao_social),
            'linhas' => array_values(array_filter([
                $endereco,
                $cidade,
                'CNPJ ' . Documento::formatar((string) $f->cnpj) . ($f->ie ? ' · IE ' . $f->ie : ''),
            ])),
            'rntrc' => $f->rntrc,
            'logo' => $this->logo($f),
        ];
    }

    /** @return array{nome:string,linhas:list<string>}|null */
    private function parte(?Pessoa $p): ?array
    {
        if ($p === null) {
            return null;
        }

        $e = $p->enderecoPrincipal;
        $linhas = [];
        if ($e) {
            $linhas[] = trim(implode(', ', array_filter([$e->logradouro, $e->numero, $e->complemento])) . ($e->bairro ? ' — ' . $e->bairro : ''));
            $linhas[] = trim(($e->municipio ? $e->municipio->nome . '/' . $e->municipio->uf : '') . ($e->cep ? ' · CEP ' . substr((string) $e->cep, 0, 5) . '-' . substr((string) $e->cep, 5) : ''));
        }
        $linhas[] = trim((strlen((string) $p->documento) === 11 ? 'CPF ' : 'CNPJ ') . Documento::formatar((string) $p->documento)
            . ($p->ie ? ' · IE ' . $p->ie : '') . ($p->telefone ? ' · Fone ' . $p->telefone : ''));

        return ['nome' => mb_strtoupper((string) $p->razao_social), 'linhas' => array_values(array_filter($linhas))];
    }

    private function marca(?string $status, int $ambiente): ?string
    {
        return match (true) {
            $status === 'cancelado' => 'CANCELADO',
            ! in_array($status, ['autorizado', 'encerrado', 'contingencia'], true) => 'PRÉVIA — NÃO É DOCUMENTO FISCAL',
            $ambiente === 2 => 'SEM VALOR FISCAL',
            default => null,
        };
    }

    private function chaveValida(?string $chave): ?string
    {
        $c = preg_replace('/\D/', '', (string) $chave) ?? '';

        return strlen($c) === 44 ? $c : null;
    }

    private function formatarChave(string $chave): string
    {
        return trim(implode(' ', str_split($chave, 4)));
    }

    private function qr(string $texto, string $tipo, string $chave, int $ambiente): ?string
    {
        if ($texto === '') {
            $base = (string) config("fiscal.qrcode.{$tipo}");
            $param = $tipo === 'cte' ? 'chCTe' : 'chMDFe';
            $texto = "{$base}?{$param}={$chave}&tpAmb={$ambiente}";
        }

        $svg = QrCodeSvg::gerar($texto);

        return $svg ? $this->imagem($svg) : null;
    }

    private function imagem(string $svg): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function logo(Filial $f): ?string
    {
        if (! $f->logo_path) {
            return null;
        }

        try {
            foreach (['public', 'local'] as $disco) {
                if (Storage::disk($disco)->exists($f->logo_path)) {
                    $conteudo = Storage::disk($disco)->get($f->logo_path);
                    $mime = str_ends_with(strtolower($f->logo_path), '.png') ? 'image/png' : 'image/jpeg';

                    return 'data:' . $mime . ';base64,' . base64_encode((string) $conteudo);
                }
            }
        } catch (Throwable) {
            // Sem logo, o espaço fica em branco.
        }

        return null;
    }
}
