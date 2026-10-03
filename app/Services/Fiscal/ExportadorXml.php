<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Domain\Fiscal\ConferenciaNumeracao;
use App\Models\Cte;
use App\Models\CteEvento;
use App\Models\ExportacaoXml;
use App\Models\Filial;
use App\Models\Mdfe;
use App\Models\MdfeEvento;
use App\Support\TenantContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Rotina 4060 — os XML do mês para o contador (mockup de 03/10/2026).
 *
 * Entra no mês o que foi EMITIDO no mês (CT-e e MDF-e autorizados, cancelados
 * ou encerrados) e os eventos REGISTRADOS no mês. O ZIP leva pastas por tipo e
 * um resumo.csv (separador ";", UTF-8 com BOM para abrir direto no Excel).
 * Documento sem XML guardado vai para o resumo marcado — nunca some calado.
 *
 * O mês é o do calendário de Brasília (config fiscal.fuso): o banco guarda em
 * UTC, então o período é montado no fuso e convertido antes da consulta.
 */
final class ExportadorXml
{
    private const CTE_VALIDOS = ['autorizado', 'cancelado', 'contingencia'];
    private const MDFE_VALIDOS = ['autorizado', 'encerrado', 'cancelado'];

    public static function fuso(): string
    {
        return (string) config('fiscal.fuso', 'America/Sao_Paulo');
    }

    public static function mesValido(string $mes): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes);
    }

    /** Início e fim do mês no fuso fiscal, já no fuso do app (UTC). @return array{0: Carbon, 1: Carbon} */
    public static function periodo(string $mes): array
    {
        if (! self::mesValido($mes)) {
            throw new RuntimeException('Mês inválido.');
        }
        $ini = Carbon::createFromFormat('Y-m-d H:i:s', $mes . '-01 00:00:00', self::fuso())->startOfMonth();
        $fim = $ini->copy()->endOfMonth();
        $app = (string) config('app.timezone', 'UTC');

        return [$ini->setTimezone($app), $fim->setTimezone($app)];
    }

    /**
     * Números, pendências e o que vai no ZIP.
     *
     * @param  array{cte:bool, mdfe:bool, eventos:bool}  $opcoes
     * @return array<string,mixed>
     */
    public function resumo(string $mes, ?int $filialId, array $opcoes): array
    {
        [$ini, $fim] = self::periodo($mes);
        $ctes = $this->ctes($ini, $fim, $filialId);
        $mdfes = $this->mdfes($ini, $fim, $filialId);
        $evCte = $this->eventosCte($ini, $fim, $filialId);
        $evMdfe = $this->eventosMdfe($ini, $fim, $filialId);

        $autorizados = $ctes->whereIn('status', ['autorizado', 'contingencia']);
        $arquivos = $this->arquivos($ctes, $mdfes, $evCte, $evMdfe, $opcoes);
        $semXml = collect($arquivos)->where('existe', false)->count();

        $abertos = Mdfe::query()->where('status', 'autorizado')->where('emissao', '<=', $fim)
            ->when($filialId, fn ($q) => $q->where('filial_id', $filialId))
            ->orderBy('numero')->get(['id', 'numero', 'serie', 'filial_id']);

        $numeracao = $this->numeracao($ini, $fim, $filialId);

        return [
            'cte_autorizados' => $autorizados->count(),
            'cte_valor' => round((float) $autorizados->sum('valor_total_servico'), 2),
            'cte_cancelados' => $ctes->where('status', 'cancelado')->count(),
            'mdfe' => $mdfes->count(),
            'mdfe_abertos' => $abertos,
            'eventos' => $evCte->count() + $evMdfe->count(),
            'eventos_por_tipo' => $evCte->groupBy(fn ($e) => CteEvento::TIPOS[$e->tipo_evento] ?? $e->tipo_evento)->map->count()
                ->merge($evMdfe->groupBy(fn ($e) => 'MDF-e: ' . (MdfeEvento::TIPOS[$e->tipo_evento] ?? $e->tipo_evento))->map->count())
                ->all(),
            'numeracao' => $numeracao,
            'sem_numero' => collect($numeracao)->sum('total_faltando'),
            'arquivos' => count($arquivos),
            'sem_xml' => $semXml,
            'pastas' => collect($arquivos)->groupBy('pasta')->map->count()->all(),
        ];
    }

    /**
     * Gera o ZIP, guarda no disco fiscal e registra a exportação.
     *
     * @param  array{cte:bool, mdfe:bool, eventos:bool}  $opcoes
     */
    public function gerar(string $mes, ?int $filialId, array $opcoes): ExportacaoXml
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('A extensão zip do PHP não está instalada no servidor.');
        }
        if (! ($opcoes['cte'] ?? false) && ! ($opcoes['mdfe'] ?? false)) {
            throw new RuntimeException('Escolha ao menos CT-e ou MDF-e.');
        }

        [$ini, $fim] = self::periodo($mes);
        $ctes = $this->ctes($ini, $fim, $filialId);
        $mdfes = $this->mdfes($ini, $fim, $filialId);
        $evCte = $this->eventosCte($ini, $fim, $filialId);
        $evMdfe = $this->eventosMdfe($ini, $fim, $filialId);
        $arquivos = $this->arquivos($ctes, $mdfes, $evCte, $evMdfe, $opcoes);

        $disco = Storage::disk((string) config('fiscal.xml.disco', 'local'));
        $local = method_exists($disco, 'path') && config('filesystems.disks.' . config('fiscal.xml.disco', 'local') . '.driver') === 'local';
        $tmp = tempnam(sys_get_temp_dir(), 'xml') ?: throw new RuntimeException('Sem espaço temporário para o ZIP.');

        try {
            return $this->montar($disco, $local, $tmp, $mes, $filialId, $opcoes, $arquivos);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @param  array{cte:bool, mdfe:bool, eventos:bool}  $opcoes
     * @param  list<array<string,mixed>>  $arquivos
     */
    private function montar(Filesystem $disco, bool $local, string $tmp, string $mes, ?int $filialId, array $opcoes, array $arquivos): ExportacaoXml
    {
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o ZIP.');
        }

        $csv = [['Documento', 'Filial', 'Série', 'Número', 'Chave', 'Emissão', 'Tomador', 'Valor', 'Situação', 'XML']];
        $usados = [];
        foreach ($arquivos as $a) {
            if ($a['existe']) {
                $nome = $a['pasta'] . '/' . $a['nome'];
                if (isset($usados[$nome])) {
                    $nome = $a['pasta'] . '/' . pathinfo($a['nome'], PATHINFO_FILENAME) . '-' . $a['id'] . '.xml';
                }
                $usados[$nome] = true;
                // Disco local: o ZipArchive lê do arquivo no close() — não carrega tudo na memória.
                $local ? $zip->addFile($disco->path($a['caminho']), $nome) : $zip->addFromString($nome, (string) $disco->get($a['caminho']));
            }
            $csv[] = [...$a['linha'], $a['existe'] ? 'Sim' : 'Não guardado'];
        }

        $zip->addFromString('resumo.csv', "\u{FEFF}" . collect($csv)
            ->map(fn (array $l) => implode(';', array_map(fn ($v) => '"' . str_replace('"', '""', self::semFormula((string) $v)) . '"', $l)))
            ->implode("\r\n") . "\r\n");
        $zip->close();

        $empresa = (int) TenantContext::empresaId();
        $caminho = trim((string) config('fiscal.xml.exportacoes', 'exportacoes-xml'), '/') . "/{$empresa}/{$mes}-" . Str::lower(Str::random(8)) . '.zip';
        clearstatcache(true, $tmp);
        $tamanho = (int) filesize($tmp);
        $stream = fopen($tmp, 'rb');
        $gravou = $stream !== false && $disco->put($caminho, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (! $gravou) {
            throw new RuntimeException('Não foi possível guardar o ZIP no servidor.');
        }

        return ExportacaoXml::create([
            'filial_id' => $filialId,
            'mes' => $mes,
            'opcoes' => $opcoes,
            'arquivos' => collect($arquivos)->where('existe', true)->count() + 1,
            'sem_xml' => collect($arquivos)->where('existe', false)->count(),
            'tamanho' => $tamanho,
            'caminho' => $caminho,
            'criado_por' => Auth::id(),
        ]);
    }

    /** Texto que o Excel leria como fórmula (=, +, -, @) ganha um apóstrofo na frente. */
    private static function semFormula(string $v): string
    {
        return preg_match('/^[=+\-@\t\r]/', $v) && ! is_numeric(str_replace(',', '.', $v)) ? "'" . $v : $v;
    }

    public function nomeDoArquivo(ExportacaoXml $e): string
    {
        $filial = $e->filial ? '-' . Str::slug((string) ($e->filial->nome_fantasia ?: $e->filial->razao_social)) : '';

        return 'xml-' . $e->mes . $filial . '.zip';
    }

    /* ── consultas ── */

    /** @return Collection<int, Cte> */
    private function ctes(Carbon $ini, Carbon $fim, ?int $filialId): Collection
    {
        return Cte::query()->with(['tomador:id,razao_social', 'filial:id,razao_social,nome_fantasia'])
            ->whereIn('status', self::CTE_VALIDOS)->whereBetween('emissao', [$ini, $fim])
            ->when($filialId, fn ($q) => $q->where('filial_id', $filialId))
            ->orderBy('filial_id')->orderBy('serie')->orderBy('numero')->get();
    }

    /** @return Collection<int, Mdfe> */
    private function mdfes(Carbon $ini, Carbon $fim, ?int $filialId): Collection
    {
        return Mdfe::query()->with('filial:id,razao_social,nome_fantasia')
            ->whereIn('status', self::MDFE_VALIDOS)->whereBetween('emissao', [$ini, $fim])
            ->when($filialId, fn ($q) => $q->where('filial_id', $filialId))
            ->orderBy('filial_id')->orderBy('serie')->orderBy('numero')->get();
    }

    /** @return Collection<int, CteEvento> */
    private function eventosCte(Carbon $ini, Carbon $fim, ?int $filialId): Collection
    {
        return CteEvento::query()->with('cte.filial:id,razao_social,nome_fantasia')
            ->where('status', 'registrado')->whereBetween('data_evento', [$ini, $fim])
            ->when($filialId, fn ($q) => $q->whereHas('cte', fn ($c) => $c->where('filial_id', $filialId)))
            ->orderBy('data_evento')->get();
    }

    /** @return Collection<int, MdfeEvento> */
    private function eventosMdfe(Carbon $ini, Carbon $fim, ?int $filialId): Collection
    {
        return MdfeEvento::query()->with('mdfe.filial:id,razao_social,nome_fantasia')
            ->where('status', 'registrado')->whereBetween('data_evento', [$ini, $fim])
            ->when($filialId, fn ($q) => $q->whereHas('mdfe', fn ($m) => $m->where('filial_id', $filialId)))
            ->orderBy('data_evento')->get();
    }

    /**
     * @return list<array{filial:string, documento:string, serie:int, primeiro:?int, ultimo:?int, emitidos:int, faltando:list<array{numero:int,motivo:string}>, total_faltando:int}>
     */
    private function numeracao(Carbon $ini, Carbon $fim, ?int $filialId): array
    {
        $saida = [];
        $filiais = Filial::query()->get(['id', 'razao_social', 'nome_fantasia'])
            ->mapWithKeys(fn (Filial $f) => [$f->id => $f->nome_fantasia ?: $f->razao_social]);

        foreach ([['CT-e', Cte::class], ['MDF-e', Mdfe::class]] as [$rotulo, $classe]) {
            $docs = $classe::query()->whereNotNull('numero')->whereBetween('emissao', [$ini, $fim])
                ->when($filialId, fn ($q) => $q->where('filial_id', $filialId))
                ->get(['filial_id', 'serie', 'numero', 'status'])
                ->groupBy(fn ($d) => $d->filial_id . '|' . $d->serie);

            foreach ($docs as $chave => $grupo) {
                [$fil, $serie] = explode('|', (string) $chave);
                $r = ConferenciaNumeracao::conferir($grupo->mapWithKeys(fn ($d) => [(int) $d->numero => (string) $d->status])->all());
                $saida[] = ['filial' => (string) ($filiais[(int) $fil] ?? '—'), 'documento' => $rotulo, 'serie' => (int) $serie] + $r;
            }
        }

        return $saida;
    }

    /**
     * Cada arquivo que iria no ZIP, com a linha do resumo.csv.
     *
     * @param  array{cte:bool, mdfe:bool, eventos:bool}  $opcoes
     * @return list<array{id:int, pasta:string, nome:string, caminho:?string, existe:bool, linha:list<string>}>
     */
    private function arquivos(Collection $ctes, Collection $mdfes, Collection $evCte, Collection $evMdfe, array $opcoes): array
    {
        $disco = Storage::disk((string) config('fiscal.xml.disco', 'local'));
        $existe = fn (?string $p): bool => $p !== null && $p !== '' && $disco->exists($p);
        $filial = fn ($f): string => (string) ($f?->nome_fantasia ?: $f?->razao_social ?: '—');
        $valor = fn ($v): string => number_format((float) $v, 2, ',', '');
        $out = [];

        if ($opcoes['cte'] ?? false) {
            foreach ($ctes as $c) {
                $out[] = [
                    'id' => $c->id, 'pasta' => $c->status === 'cancelado' ? 'CT-e/cancelados' : 'CT-e/autorizados',
                    'nome' => ($c->chave ?: 'cte-' . $c->id) . '-cte.xml', 'caminho' => $c->xml_path, 'existe' => $existe($c->xml_path),
                    'linha' => ['CT-e', $filial($c->filial), (string) $c->serie, (string) $c->numero, (string) $c->chave,
                        $c->emissao?->format('d/m/Y H:i') ?? '', (string) ($c->tomador?->razao_social ?? ''), $valor($c->valor_total_servico),
                        (string) config('fiscal.cte.status.' . $c->status, $c->status)],
                ];
            }
            if ($opcoes['eventos'] ?? false) {
                foreach ($evCte as $e) {
                    $tipo = CteEvento::TIPOS[$e->tipo_evento] ?? $e->tipo_evento;
                    $out[] = [
                        'id' => $e->id, 'pasta' => 'CT-e/eventos',
                        'nome' => ($e->cte?->chave ?: 'cte-' . $e->cte_id) . '-' . $e->tipo_evento . '-' . $e->sequencia . '.xml',
                        'caminho' => $e->xml_path, 'existe' => $existe($e->xml_path),
                        'linha' => ['Evento do CT-e', $filial($e->cte?->filial), (string) $e->cte?->serie, (string) $e->cte?->numero, (string) $e->cte?->chave,
                            $e->data_evento?->format('d/m/Y H:i') ?? '', '', '', $tipo . ($e->tipo_evento === CteEvento::CCE ? ' nº ' . $e->sequencia : '')],
                    ];
                }
            }
        }

        if ($opcoes['mdfe'] ?? false) {
            foreach ($mdfes as $m) {
                $out[] = [
                    'id' => $m->id, 'pasta' => 'MDF-e',
                    'nome' => ($m->chave ?: 'mdfe-' . $m->id) . '-mdfe.xml', 'caminho' => $m->xml_path, 'existe' => $existe($m->xml_path),
                    'linha' => ['MDF-e', $filial($m->filial), (string) $m->serie, (string) $m->numero, (string) $m->chave,
                        $m->emissao?->format('d/m/Y H:i') ?? '', '', $valor($m->valor_carga_total),
                        (string) config('fiscal.mdfe.status.' . $m->status, $m->status)],
                ];
            }
            if ($opcoes['eventos'] ?? false) {
                foreach ($evMdfe as $e) {
                    $out[] = [
                        'id' => $e->id, 'pasta' => 'MDF-e/eventos',
                        'nome' => ($e->mdfe?->chave ?: 'mdfe-' . $e->mdfe_id) . '-' . $e->tipo_evento . '-' . $e->sequencia . '.xml',
                        'caminho' => $e->xml_path, 'existe' => $existe($e->xml_path),
                        'linha' => ['Evento do MDF-e', $filial($e->mdfe?->filial), (string) $e->mdfe?->serie, (string) $e->mdfe?->numero, (string) $e->mdfe?->chave,
                            $e->data_evento?->format('d/m/Y H:i') ?? '', '', '', MdfeEvento::TIPOS[$e->tipo_evento] ?? $e->tipo_evento],
                    ];
                }
            }
        }

        return $out;
    }
}
