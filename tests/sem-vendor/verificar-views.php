<?php

declare(strict_types=1);

/**
 * Confere as views SEM vendor/ instalado.
 *
 * O compilador do Blade só roda com o Laravel completo, e neste ambiente o
 * packagist é inalcançável. Isso não é desculpa para subir view quebrada: os
 * erros que realmente aparecem em produção — diretiva sem fechar, rota que
 * não existe, ícone que não está no sprite, componente sem arquivo — são
 * todos detectáveis por leitura estática.
 *
 * Uso: php tests/sem-vendor/verificar-views.php
 */

$raiz = dirname(__DIR__, 2);
$falhas = [];
$conferidos = 0;

/* ── 1. Diretivas balanceadas ─────────────────────────────── */

$pares = [
    'if' => 'endif', 'foreach' => 'endforeach', 'forelse' => 'endforelse',
    'for' => 'endfor', 'while' => 'endwhile', 'can' => 'endcan',
    'cannot' => 'endcannot', 'error' => 'enderror', 'php' => 'endphp',
    'section' => 'endsection', 'auth' => 'endauth', 'guest' => 'endguest',
    'isset' => 'endisset', 'empty' => 'endempty', 'once' => 'endonce',
];

/** @return list<string> */
function arquivosBlade(string $dir): array
{
    $saida = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($it as $arquivo) {
        if ($arquivo->isFile() && str_ends_with($arquivo->getFilename(), '.blade.php')) {
            $saida[] = $arquivo->getPathname();
        }
    }

    sort($saida);

    return $saida;
}

$views = arquivosBlade($raiz . '/resources/views');

/**
 * Percorre as diretivas em ORDEM, com pilha. Contar aberturas e fechamentos
 * não basta: um @endif no lugar errado mantém a conta certa e quebra a tela
 * do mesmo jeito. Descobri isso da pior forma, restaurando um arquivo com
 * sed e vendo o verificador dar tudo certo.
 *
 * @return list<string>
 */
function conferirAninhamento(string $conteudo, array $pares, string $relativo): array
{
    $falhas = [];
    $pilha = [];
    $fechaPara = array_flip($pares);

    // Uma passada só, na ordem em que as diretivas aparecem.
    $regex = '/@(' . implode('|', [...array_keys($pares), ...array_values($pares)]) . ')\b\s*(\()?/';
    preg_match_all($regex, $conteudo, $ocorrencias, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

    foreach ($ocorrencias as $ocorrencia) {
        $diretiva = $ocorrencia[1][0];
        $temParenteses = isset($ocorrencia[2]) && $ocorrencia[2][0] === '(';
        $linha = substr_count($conteudo, "\n", 0, (int) $ocorrencia[0][1]) + 1;

        // Formas que NÃO abrem bloco.
        if ($diretiva === 'empty' && ! $temParenteses) {
            continue; // ramo do @forelse
        }

        if ($diretiva === 'php' && $temParenteses) {
            continue; // @php(...) inline
        }

        if ($diretiva === 'section' && $temParenteses) {
            // @section('titulo', 'valor') é inline; @section('conteudo') abre bloco.
            $trecho = substr($conteudo, (int) $ocorrencia[0][1], 200);

            if (preg_match("/@section\\s*\\(\\s*'[^']*'\\s*,/", $trecho) === 1) {
                continue;
            }
        }

        if (isset($pares[$diretiva])) {
            $pilha[] = [$diretiva, $linha];

            continue;
        }

        // É um fechamento.
        $esperado = $fechaPara[$diretiva] ?? null;

        if ($pilha === []) {
            $falhas[] = "{$relativo}:{$linha}: @{$diretiva} sem @{$esperado} correspondente";

            continue;
        }

        [$aberto, $linhaAberto] = array_pop($pilha);

        if ($aberto !== $esperado) {
            $falhas[] = "{$relativo}:{$linha}: @{$diretiva} fecha @{$aberto} "
                . "(aberto na linha {$linhaAberto}) — esperava @{$pares[$aberto]}";
        }
    }

    foreach ($pilha as [$aberto, $linhaAberto]) {
        $falhas[] = "{$relativo}:{$linhaAberto}: @{$aberto} nunca é fechado";
    }

    return $falhas;
}

foreach ($views as $arquivo) {
    $conteudo = file_get_contents($arquivo);
    $relativo = str_replace($raiz . '/', '', $arquivo);
    $conferidos++;

    $falhas = [...$falhas, ...conferirAninhamento($conteudo, $pares, $relativo)];
}

/* ── 2. Rotas referenciadas existem ───────────────────────── */

$rotas = [];

foreach (['web', 'auth', 'central'] as $arquivoRotas) {
    $caminho = $raiz . "/routes/{$arquivoRotas}.php";

    if (! file_exists($caminho)) {
        continue;
    }

    preg_match_all("/->name\('([^']+)'\)/", file_get_contents($caminho), $m);
    $rotas = [...$rotas, ...$m[1]];
}

foreach ($views as $arquivo) {
    $relativo = str_replace($raiz . '/', '', $arquivo);
    $conteudo = file_get_contents($arquivo);

    preg_match_all("/route\('([^']+)'/", $conteudo, $m);

    // Rota protegida por Route::has() é opcional de propósito: a view lida
    // com a ausência. Cobrar existência dela seria exigir stub.
    preg_match_all("/Route::has\('([^']+)'\)/", $conteudo, $mOpcionais);
    $opcionais = $mOpcionais[1];

    foreach (array_unique($m[1]) as $rota) {
        if (in_array($rota, $rotas, true) || in_array($rota, $opcionais, true)) {
            continue;
        }

        $falhas[] = "{$relativo}: rota '{$rota}' não existe em routes/";
    }
}

/* ── 3. Ícones existem no sprite ──────────────────────────── */

$sprite = $raiz . '/public/img/icons/sprite.svg';

if (! file_exists($sprite)) {
    $falhas[] = 'public/img/icons/sprite.svg não existe — rode `npm run icones`';
} else {
    preg_match_all('/id="([a-z0-9-]+)"/', file_get_contents($sprite), $m);
    $disponiveis = $m[1];

    foreach ($views as $arquivo) {
        $relativo = str_replace($raiz . '/', '', $arquivo);
        $conteudo = file_get_contents($arquivo);

        preg_match_all('/<x-icon[^>]*\sname="([a-z0-9-]+)"/', $conteudo, $m1);
        preg_match_all('/icon="([a-z0-9-]+)"/', $conteudo, $m2);

        foreach (array_unique([...$m1[1], ...$m2[1]]) as $icone) {
            if (! in_array($icone, $disponiveis, true)) {
                $falhas[] = "{$relativo}: ícone '{$icone}' não está no sprite";
            }
        }
    }
}

/* ── 4. Componentes x-* têm arquivo ───────────────────────── */

$componentes = [];

foreach (glob($raiz . '/resources/views/components/*.blade.php') ?: [] as $arquivo) {
    $componentes[] = basename($arquivo, '.blade.php');
}

// Componentes que o Laravel/Livewire fornecem — não têm arquivo no projeto.
$nativos = ['slot', 'icon'];

foreach ($views as $arquivo) {
    $relativo = str_replace($raiz . '/', '', $arquivo);
    preg_match_all('/<x-([a-z0-9-]+)[\s>\/]/', file_get_contents($arquivo), $m);

    foreach (array_unique($m[1]) as $componente) {
        if (in_array($componente, $nativos, true) || in_array($componente, $componentes, true)) {
            continue;
        }

        $falhas[] = "{$relativo}: componente <x-{$componente}> não tem arquivo em components/";
    }
}

/* ── 5. Chaves de config referenciadas existem ────────────── */

$papeis = require $raiz . '/config/papeis.php';

foreach ($views as $arquivo) {
    $relativo = str_replace($raiz . '/', '', $arquivo);
    preg_match_all("/config\('papeis\.([a-z]+)\./", file_get_contents($arquivo), $m);

    foreach (array_unique($m[1]) as $secao) {
        if (! array_key_exists($secao, $papeis)) {
            $falhas[] = "{$relativo}: config('papeis.{$secao}.…') não existe";
        }
    }
}

/* ── 6. Views que rotas e componentes renderizam ──────────── */

foreach (['web', 'auth', 'central'] as $arquivoRotas) {
    $caminho = $raiz . "/routes/{$arquivoRotas}.php";

    if (! file_exists($caminho)) {
        continue;
    }

    // Route::view('/', 'inicio') — a view precisa existir tanto quanto a rota.
    preg_match_all("/Route::view\('[^']*',\s*'([^']+)'/", file_get_contents($caminho), $m);

    foreach ($m[1] as $view) {
        $arquivoView = $raiz . '/resources/views/' . str_replace('.', '/', $view) . '.blade.php';

        if (! file_exists($arquivoView)) {
            $falhas[] = "routes/{$arquivoRotas}.php: Route::view aponta para '{$view}', que não existe";
        }
    }
}


foreach (glob($raiz . '/app/Livewire/*/*.php') ?: [] as $componente) {
    $conteudo = file_get_contents($componente);
    $relativo = str_replace($raiz . '/', '', $componente);

    preg_match_all("/view\('([^']+)'\)/", $conteudo, $m);

    foreach ($m[1] as $view) {
        $caminho = $raiz . '/resources/views/' . str_replace('.', '/', $view) . '.blade.php';

        if (! file_exists($caminho)) {
            $falhas[] = "{$relativo}: view '{$view}' não existe";
        }
    }

    preg_match_all("/@include\('([^']+)'/", $conteudo, $m);
}

// @include dentro das próprias views
foreach ($views as $arquivo) {
    $relativo = str_replace($raiz . '/', '', $arquivo);
    preg_match_all("/@include\('([^']+)'/", file_get_contents($arquivo), $m);

    foreach (array_unique($m[1]) as $view) {
        $caminho = $raiz . '/resources/views/' . str_replace('.', '/', $view) . '.blade.php';

        if (! file_exists($caminho)) {
            $falhas[] = "{$relativo}: @include('{$view}') não existe";
        }
    }
}

/* ── Resultado ────────────────────────────────────────────── */

echo str_repeat('─', 60) . "\n";

if ($falhas === []) {
    echo "\033[32m✔\033[0m {$conferidos} views conferidas — nenhum problema estrutural.\n";
    exit(0);
}

echo "\033[31m✘\033[0m " . count($falhas) . " problema(s) em {$conferidos} views:\n\n";

foreach ($falhas as $falha) {
    echo "   · {$falha}\n";
}

echo "\n";
exit(1);
