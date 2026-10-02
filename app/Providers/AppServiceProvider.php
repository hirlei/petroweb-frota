<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Fiscal\Sefaz\FakeSefazGateway;
use App\Services\Fiscal\Sefaz\SefazGateway;
use App\Services\Roteirizacao\Roteirizador;
use App\Services\Roteirizacao\RoteirizadorOrs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Roteirizador::class, function (): Roteirizador {
            $cfg = config('mapa.roteirizacao.ors');

            return new RoteirizadorOrs(
                baseUrl: rtrim((string) $cfg['base_url'], '/'),
                chave: $cfg['chave'] ?? null,
                perfil: (string) $cfg['perfil'],
                timeout: (int) $cfg['timeout'],
            );
        });

        // Gateway SEFAZ. Enquanto a integração real não entra, o fake responde
        // em homologação — o driver decide (config/fiscal.php).
        $this->app->bind(SefazGateway::class, function () {
            return match (config('fiscal.sefaz.driver', 'fake')) {
                // 'real' => new SefazGatewayReal(...), // entra na sprint fiscal
                default => new FakeSefazGateway(),
            };
        });

        // CIOT e vale-pedágio. Emissor de teste até haver instituição de
        // pagamento e fornecedora contratadas (config/ciot.php).
        $this->app->bind(\App\Services\Fiscal\Ciot\CiotGateway::class, function () {
            return match (config('ciot.driver', 'fake')) {
                default => new \App\Services\Fiscal\Ciot\FakeCiotGateway(),
            };
        });
        $this->app->bind(\App\Services\Fiscal\ValePedagio\ValePedagioGateway::class, function () {
            return match (config('ciot.vale_pedagio.driver', 'fake')) {
                default => new \App\Services\Fiscal\ValePedagio\FakeValePedagioGateway(),
            };
        });
    }

    public function boot(): void
    {
        $this->forcarHttps();
        $this->protegerBancoEmProducao();
        $this->rigorEmDesenvolvimento();
    }

    /**
     * Atrás do nginx com certbot, o Laravel normalmente detecta HTTPS pelo
     * fastcgi. "Normalmente" não basta: se falhar, o Vite gera as URLs dos
     * assets em http dentro de uma página https, o navegador bloqueia por
     * conteúdo misto e o sistema abre sem estilo nenhum — bem na hora de
     * mostrar para alguém. Se o APP_URL diz https, forçamos https.
     */
    private function forcarHttps(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * `migrate:fresh`, `migrate:refresh` e `db:wipe` apagam o banco inteiro.
     * Num sistema multi-tenant esse banco é de UM CLIENTE, com documento
     * fiscal dentro. Em produção, o comando falha em vez de rodar.
     */
    private function protegerBancoEmProducao(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    /**
     * Fora de produção, o Eloquent reclama alto: lazy loading vira exceção
     * (N+1 aparece na hora, não no cliente), atribuir coluna que não existe
     * também. Em produção fica desligado — a última coisa que se quer é
     * derrubar a tela do cliente por causa de um `with()` esquecido.
     */
    private function rigorEmDesenvolvimento(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
