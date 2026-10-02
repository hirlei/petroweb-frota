<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cadastro unificado — cliente, fornecedor, motorista, proprietário, oficina,
 * seguradora e posto são a MESMA tabela, distinguidos por `pessoa_papeis`.
 *
 * No transporte o acúmulo de papéis é a regra, não a exceção: o agregado é
 * motorista e proprietário; o cliente é tomador, remetente e destinatário em
 * fretes diferentes. Tabelas separadas produziriam o mesmo CNPJ cadastrado
 * três vezes com endereços divergentes.
 */
class Pessoa extends Model
{
    use PertenceAEmpresa;
    use SoftDeletes;

    protected $table = 'pessoas';

    protected $guarded = [];

    protected $casts = [
        'rntrc_validade' => 'date',
        'ativo'          => 'boolean',
        'multa_percentual'     => 'decimal:2',
        'juros_mes_percentual' => 'decimal:2',
    ];

    public const TIPO_FISICA = 'F';
    public const TIPO_JURIDICA = 'J';
    public const TIPO_ESTRANGEIRO = 'E';

    /** indIEDest do CT-e — a causa mais comum de rejeição por cadastro. */
    public const IE_CONTRIBUINTE = '1';
    public const IE_ISENTO = '2';
    public const IE_NAO_CONTRIBUINTE = '9';

    public const PAPEIS = [
        'cliente', 'fornecedor', 'motorista', 'proprietario',
        'oficina', 'seguradora', 'posto',
    ];

    public function papeis(): HasMany
    {
        return $this->hasMany(PessoaPapel::class);
    }

    public function enderecos(): HasMany
    {
        return $this->hasMany(Endereco::class);
    }

    public function enderecoPrincipal(): HasOne
    {
        return $this->hasOne(Endereco::class)->where('principal', true);
    }

    public function contatos(): HasMany
    {
        return $this->hasMany(Contato::class);
    }

    public function motorista(): HasOne
    {
        return $this->hasOne(Motorista::class);
    }

    public function temPapel(string $papel): bool
    {
        return $this->papeis->contains(
            fn (PessoaPapel $p): bool => $p->papel === $papel && $p->ativo
        );
    }

    public function scopeComPapel(Builder $query, string $papel): Builder
    {
        return $query->whereHas(
            'papeis',
            fn (Builder $q) => $q->where('papel', $papel)->where('ativo', true)
        );
    }

    public function ehPessoaFisica(): bool
    {
        return $this->tipo === self::TIPO_FISICA;
    }

    /**
     * Transportador autônomo de carga. Determina se o frete vai a CIOT e se o
     * vale-pedágio é obrigação do embarcador equiparado (RN-10).
     */
    public function ehTac(): bool
    {
        return $this->tp_transp === '2';
    }

    /** "28/56 dias", "À vista" — ou null sem prazo (ou com prazo inválido) no cadastro. */
    public function prazoFaturamentoRotulo(): ?string
    {
        if ($this->prazo_faturamento === null || trim((string) $this->prazo_faturamento) === '') {
            return null;
        }

        try {
            return \App\Domain\Financeiro\Parcelamento::rotulo(\App\Domain\Financeiro\Parcelamento::prazos((string) $this->prazo_faturamento));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function documentoFormatado(): string
    {
        $d = (string) $this->documento;

        return match (strlen($d)) {
            11 => vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($d)),
            14 => vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($d)),
            default => $d,
        };
    }
}
