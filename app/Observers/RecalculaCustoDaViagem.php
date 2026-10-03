<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Cte;
use App\Services\Operacao\CustosDaViagem;
use Illuminate\Database\Eloquent\Model;

/**
 * Rotina 3080: todo lançamento que mexe no custo ou na receita de uma viagem
 * agenda o recálculo dela (depois do commit, um por viagem). Ligado em
 * AppServiceProvider a abastecimento, OS, despesa, vale-pedágio, CIOT, acerto
 * e CT-e. A rotina noturna `viagens:recalcular-custos` cobre o que escapar.
 */
class RecalculaCustoDaViagem
{
    public function saved(Model $model): void
    {
        $this->agendar($model);
    }

    public function deleted(Model $model): void
    {
        $this->agendar($model);
    }

    private function agendar(Model $model): void
    {
        if ($model instanceof Cte) {
            if ($model->wasRecentlyCreated || ! $model->wasChanged(['status', 'valor_total_servico'])) {
                return;
            }
            CustosDaViagem::agendar(...$model->viagens()->pluck('viagens.id')->map(fn ($id) => (int) $id)->all());

            return;
        }

        $atual = $model->getAttribute('viagem_id');
        $anterior = $model->getOriginal('viagem_id');
        CustosDaViagem::agendar(
            $atual !== null ? (int) $atual : null,
            $anterior !== null && $anterior !== $atual ? (int) $anterior : null,
        );
    }
}
