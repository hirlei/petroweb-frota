<?php

declare(strict_types=1);

/**
 * Roda os testes de domínio SEM vendor/ instalado.
 *
 * Por que existe: o ambiente onde este código é escrito não alcança o
 * packagist, então o PHPUnit real só roda na máquina do desenvolvedor. Este
 * runner executa OS MESMOS arquivos de teste com um TestCase mínimo, para que
 * a lógica pura (derivações fiscais) seja verificada na hora em que é escrita
 * — e não dias depois.
 *
 * NÃO substitui o PHPUnit: só cobre testes que não tocam Laravel nem banco.
 *
 * Uso: php tests/sem-vendor/runner.php
 */

namespace PHPUnit\Framework {
    class AssertionFailedError extends \RuntimeException {}

    abstract class TestCase
    {
        public static int $assercoes = 0;

        private function falhar(string $msg, string $detalhe): never
        {
            throw new AssertionFailedError($detalhe === '' ? $msg : "{$msg} — {$detalhe}");
        }

        protected function assertSame(mixed $esperado, mixed $real, string $msg = ''): void
        {
            self::$assercoes++;
            if ($esperado !== $real) {
                $this->falhar(sprintf(
                    'assertSame falhou: esperado %s, veio %s',
                    var_export($esperado, true), var_export($real, true),
                ), $msg);
            }
        }

        protected function assertNotSame(mixed $esperado, mixed $real, string $msg = ''): void
        {
            self::$assercoes++;
            if ($esperado === $real) {
                $this->falhar('assertNotSame falhou: os valores são idênticos', $msg);
            }
        }

        protected function assertTrue(mixed $valor, string $msg = ''): void
        {
            self::$assercoes++;
            if ($valor !== true) {
                $this->falhar('assertTrue falhou', $msg);
            }
        }

        protected function assertFalse(mixed $valor, string $msg = ''): void
        {
            self::$assercoes++;
            if ($valor !== false) {
                $this->falhar('assertFalse falhou', $msg);
            }
        }

        protected function assertNull(mixed $valor, string $msg = ''): void
        {
            self::$assercoes++;
            if ($valor !== null) {
                $this->falhar('assertNull falhou: veio ' . var_export($valor, true), $msg);
            }
        }

        protected function assertNotNull(mixed $valor, string $msg = ''): void
        {
            self::$assercoes++;
            if ($valor === null) {
                $this->falhar('assertNotNull falhou', $msg);
            }
        }

        protected function assertCount(int $esperado, \Countable|array $itens, string $msg = ''): void
        {
            self::$assercoes++;
            if (count($itens) !== $esperado) {
                $this->falhar(sprintf('assertCount falhou: esperado %d, veio %d', $esperado, count($itens)), $msg);
            }
        }

        protected function assertContains(mixed $agulha, array $palheiro, string $msg = ''): void
        {
            self::$assercoes++;
            if (! in_array($agulha, $palheiro, true)) {
                $this->falhar(sprintf('assertContains falhou: %s não está na lista', var_export($agulha, true)), $msg);
            }
        }

        protected function assertNotContains(mixed $agulha, array $palheiro, string $msg = ''): void
        {
            self::$assercoes++;
            if (in_array($agulha, $palheiro, true)) {
                $this->falhar(sprintf('assertNotContains falhou: %s está na lista e não deveria', var_export($agulha, true)), $msg);
            }
        }

        protected function assertArrayHasKey(int|string $chave, array $lista, string $msg = ''): void
        {
            self::$assercoes++;
            if (! array_key_exists($chave, $lista)) {
                $this->falhar(sprintf('assertArrayHasKey falhou: falta a chave %s', var_export($chave, true)), $msg);
            }
        }

        protected function assertArrayNotHasKey(int|string $chave, array $lista, string $msg = ''): void
        {
            self::$assercoes++;
            if (array_key_exists($chave, $lista)) {
                $this->falhar(sprintf('assertArrayNotHasKey falhou: a chave %s existe', var_export($chave, true)), $msg);
            }
        }

        protected function assertStringContainsString(string $agulha, string $texto, string $msg = ''): void
        {
            self::$assercoes++;
            if (! str_contains($texto, $agulha)) {
                $this->falhar(sprintf('assertStringContainsString falhou: "%s" não está em "%s"', $agulha, $texto), $msg);
            }
        }

        protected function assertInstanceOf(string $classe, mixed $objeto, string $msg = ''): void
        {
            self::$assercoes++;
            if (! $objeto instanceof $classe) {
                $this->falhar("assertInstanceOf falhou: não é {$classe}", $msg);
            }
        }
    }
}

namespace {
    use PHPUnit\Framework\AssertionFailedError;
    use PHPUnit\Framework\TestCase;

    $raiz = dirname(__DIR__, 2);

    // Autoloader PSR-4 mínimo para App\ e Tests\.
    spl_autoload_register(function (string $classe) use ($raiz): void {
        foreach (['App\\' => 'app/', 'Tests\\' => 'tests/'] as $prefixo => $dir) {
            if (str_starts_with($classe, $prefixo)) {
                $caminho = $raiz . '/' . $dir
                    . str_replace('\\', '/', substr($classe, strlen($prefixo))) . '.php';
                if (is_file($caminho)) {
                    require_once $caminho;
                }
                return;
            }
        }
    });

    $arquivos = glob($raiz . '/tests/Unit/**/*Test.php') ?: [];
    $arquivos = array_merge($arquivos, glob($raiz . '/tests/Unit/*Test.php') ?: []);

    $totalTestes = 0;
    $falhas = [];

    foreach ($arquivos as $arquivo) {
        require_once $arquivo;
    }

    foreach (get_declared_classes() as $classe) {
        if (! is_subclass_of($classe, TestCase::class)) {
            continue;
        }

        $reflexao = new ReflectionClass($classe);
        if ($reflexao->isAbstract()) {
            continue;
        }

        echo "\n\033[1m" . $reflexao->getShortName() . "\033[0m\n";
        $instancia = $reflexao->newInstance();

        foreach ($reflexao->getMethods(ReflectionMethod::IS_PUBLIC) as $metodo) {
            if (! str_starts_with($metodo->getName(), 'test')) {
                continue;
            }

            $totalTestes++;
            try {
                $metodo->invoke($instancia);
                echo "  \033[32m✔\033[0m " . $metodo->getName() . "\n";
            } catch (AssertionFailedError $e) {
                $falhas[] = $classe . '::' . $metodo->getName() . ' — ' . $e->getMessage();
                echo "  \033[31m✘\033[0m " . $metodo->getName() . "\n      " . $e->getMessage() . "\n";
            } catch (Throwable $e) {
                $falhas[] = $classe . '::' . $metodo->getName() . ' — ERRO: ' . $e->getMessage();
                echo "  \033[31m✘\033[0m " . $metodo->getName() . " (erro) \n      " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n" . str_repeat('─', 60) . "\n";
    printf(
        "%d testes, %d asserções, %d falha(s)\n",
        $totalTestes, TestCase::$assercoes, count($falhas),
    );

    exit(count($falhas) === 0 ? 0 : 1);
}
