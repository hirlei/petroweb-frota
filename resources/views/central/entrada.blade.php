{{--
    Painel do provedor — domínio central, banco central.
    Não tem tenant resolvido aqui: nenhum dado de cliente pode ser lido nesta
    página sem entrar explicitamente no banco do tenant.
--}}
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetroWeb Frota</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }</style>
</head>

<body class="flex h-full items-center justify-center bg-bg px-6 text-text">
    <div class="w-full max-w-md text-center">
        <x-brand-logo size="lg" class="mx-auto" />
        <div class="mt-2">
            <span class="inline-block rounded bg-primary-soft px-2 py-0.5 text-[11px] font-extrabold uppercase
                         tracking-[0.14em] text-primary" style="font-family:'Poppins',sans-serif">Frota</span>
        </div>

        <h1 class="mt-6 text-2xl font-bold tracking-tight">Gestão de transporte rodoviário de cargas</h1>
        <p class="mt-2 text-sm leading-relaxed text-text-secondary">
            Cada transportadora acessa pelo seu próprio endereço.
            Se você já é cliente, use o subdomínio que recebeu — algo como
            <span class="font-mono text-text">suaempresa.frota.petroweb.app</span>.
        </p>

        <div class="mt-7 rounded-lg border border-border bg-surface p-5 text-left">
            <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Precisa de acesso?</p>
            <p class="mt-2 text-sm leading-relaxed text-text-secondary">
                Fale com a HALC pelo
                <a href="mailto:desenvolvimento@halc.com.br" class="text-primary hover:text-primary-hover">
                    desenvolvimento@halc.com.br</a>.
            </p>
        </div>

        <p class="mt-8 text-xs text-text-muted">© {{ date('Y') }} HALC · PetroWeb Frota</p>
    </div>
</body>
</html>
