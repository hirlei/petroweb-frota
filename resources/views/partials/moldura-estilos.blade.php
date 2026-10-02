{{-- Estilos da moldura do PetroWeb Frota — copiados do ERP (02/10/2026) para ficar IGUAL:
     menu lateral H6, barra superior, paleta Ctrl K e fita de indicadores.
     Fonte: Retaguarda resources/views/layouts/app.blade.php e partials/fita-estilos.blade.php.
     Ao mudar algo no ERP, trazer para cá também. --}}
<style>
        [x-cloak] { display: none !important; }
        body { background: rgb(var(--color-bg)); color: rgb(var(--color-text)); font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        html:not([data-theme="dark"]) .icon-sun  { display: none; }
        html[data-theme="dark"] .icon-moon { display: none; }
        @media (max-width: 767px) {
            aside.h6-menu:not(.-translate-x-full):not(.translate-x-0) { transform: translateX(-100%); }
        }

        /* ── Menu lateral H6 (02/10, docs/mockups/mockup-erp-menu-h6-ctrlk.html) ──────────────────────────
           Cores da marca: azul #1A3DA3 e laranja #FF6200; claro e escuro pelas variáveis abaixo. */
        :root {
            --h6-azul: #1A3DA3; --h6-azul-tx: #1A3DA3; --h6-laranja: #FF6200;
            --h6-campo-bg: #F7F9FE; --h6-cod-bg: #EEF2FB; --h6-cod-tx: #3A4A6B; --h6-cod-borda: #DCE3F3;
            --h6-ativo-bg: #FFFFFF; --h6-hover-bg: rgba(26, 61, 163, .06); --h6-secao: #8A94A6;
            --h6-contratar-bg: #FFF1E6; --h6-contratar-tx: #C2410C;
            --h6-rec-bg: #F1F1EF; --h6-rec-tx: #57534E; --h6-rec-borda: #E4E2DF; --h6-estrela: #F59E0B;
            --pk-sel-bg: #FFF1E8; --pk-sel-tx: #C24A00; --pk-marca: #FFE0CC; --pk-chip-bg: #EEF2FC; --pk-rodape-bg: #F8F9FC;
        }
        [data-theme="dark"] {
            --h6-azul: #3557C4; --h6-azul-tx: #9DB4F5;
            --h6-campo-bg: rgb(38 38 38); --h6-cod-bg: rgba(157, 180, 245, .12); --h6-cod-tx: #C7D2EE; --h6-cod-borda: rgba(157, 180, 245, .22);
            --h6-ativo-bg: rgb(38 38 38); --h6-hover-bg: rgba(157, 180, 245, .08); --h6-secao: #8A8F99;
            --h6-contratar-bg: rgba(255, 98, 0, .14); --h6-contratar-tx: #FFA36B;
            --h6-rec-bg: rgba(255, 255, 255, .06); --h6-rec-tx: #C4C0BC; --h6-rec-borda: rgba(255, 255, 255, .08); --h6-estrela: #FBBF24;
            --pk-sel-bg: rgba(255, 98, 0, .13); --pk-sel-tx: #FFA36B; --pk-marca: rgba(255, 98, 0, .30); --pk-chip-bg: rgba(157, 180, 245, .12); --pk-rodape-bg: rgba(255, 255, 255, .03);
        }
        .h6-sub { font-size: 10.5px; color: var(--h6-secao); margin-top: 3px; line-height: 1.25; }
        .h6-secao { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--h6-secao); margin: 0 2px 5px; }
        .h6-campo { display: flex; align-items: center; gap: 6px; height: 34px; padding: 0 6px 0 9px; border-radius: 9px;
            background: var(--h6-campo-bg); border: 1.5px solid var(--h6-azul); cursor: text; }
        .h6-campo-foco { box-shadow: 0 0 0 3px rgba(26, 61, 163, .15); }
        .h6-prompt { color: var(--h6-azul-tx); font-weight: 700; font-size: 14px; line-height: 1; }
        .h6-cursor { width: 7px; height: 15px; background: var(--h6-laranja); border-radius: 1px; animation: h6pisca 1.05s steps(1) infinite; flex-shrink: 0; }
        @keyframes h6pisca { 50% { opacity: 0; } }
        @media (prefers-reduced-motion: reduce) { .h6-cursor { animation: none; } }
        .h6-campo-input { flex: 1; min-width: 0; background: transparent; border: 0; padding: 0; font: 500 12.5px 'JetBrains Mono', ui-monospace, monospace; color: rgb(var(--color-text)); }
        .h6-campo-input:focus { outline: none; box-shadow: none; }
        .h6-campo-input::placeholder { color: rgb(var(--color-text-muted)); font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        .h6-kbd { font: 600 9.5px 'JetBrains Mono', monospace; color: #fff; background: var(--h6-azul); border-radius: 4px; padding: 2px 5px; flex-shrink: 0; }
        .h6-dica { font-size: 10.5px; color: var(--h6-azul-tx); margin: 4px 2px 0; }
        .h6-dica-erro { color: rgb(var(--color-danger)); }
        .h6-recentes { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; }
        .h6-rec { display: flex; flex-direction: column; align-items: center; padding: 5px 2px 4px; border-radius: 7px; background: var(--h6-rec-bg); border: 1px solid var(--h6-rec-borda); text-decoration: none; min-width: 0; }
        .h6-rec b { font: 600 12px 'JetBrains Mono', monospace; color: var(--h6-rec-tx); }
        .h6-rec span { font-size: 9px; color: var(--h6-secao); max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .h6-rec:hover { box-shadow: inset 0 0 0 1.5px rgb(var(--color-border-strong)); }
        .h6-cod { flex-shrink: 0; min-width: 32px; text-align: center; font: 600 9.5px 'JetBrains Mono', monospace; color: var(--h6-cod-tx);
            background: var(--h6-cod-bg); border: 1px solid var(--h6-cod-borda); border-radius: 4px; padding: 1px 3px; }
        .h6-nome { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: left; }
        .h6-grupo { width: 100%; display: flex; align-items: center; gap: 7px; padding: 7px 6px; margin-top: 1px; border-radius: 7px;
            font-size: 12.5px; font-weight: 500; color: rgb(var(--color-text)); }
        .h6-grupo:hover, .h6-rot:hover { background: var(--h6-hover-bg); }
        .h6-cod-grupo-ativo { color: var(--h6-azul-tx); border-color: var(--h6-azul); }
        /* Ícone do grupo (02/10, opção B): no lugar da caixinha "1xx" */
        .h6-gic { flex-shrink: 0; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; color: rgb(var(--color-text-secondary)); }
        .h6-gic-ativo { color: var(--h6-azul-tx); }
        .h6-seta { font-size: 10px; color: var(--h6-secao); transition: transform .15s; }
        .h6-seta-aberta { transform: rotate(90deg); color: var(--h6-azul-tx); }
        .h6-itens { margin: 1px 0 4px 8px; }
        .h6-rot { display: flex; align-items: center; gap: 7px; padding: 5px 6px; border-radius: 7px; font-size: 12px; font-weight: 400;
            color: rgb(var(--color-text-secondary)); text-decoration: none; }
        .h6-rot-topo { font-weight: 500; color: rgb(var(--color-text)); margin-bottom: 1px; }
        .h6-cod-ic { display: inline-flex; align-items: center; justify-content: center; padding: 2px 3px; }
        .h6-rot.h6-on { background: var(--h6-ativo-bg); color: var(--h6-azul-tx); font-weight: 500; box-shadow: 0 1px 3px rgba(16, 24, 40, .10), 0 1px 2px rgba(16, 24, 40, .06); }
        .h6-rot.h6-on .h6-cod { background: var(--h6-azul); border-color: var(--h6-azul); color: #fff; }
        .h6-badge { flex-shrink: 0; min-width: 17px; height: 17px; padding: 0 5px; border-radius: 999px; color: #fff; font: 700 9.5px 'Plus Jakarta Sans', sans-serif;
            display: inline-flex; align-items: center; justify-content: center; font-variant-numeric: tabular-nums; }
        .h6-badge-urg { background: #DC2626; }
        .h6-badge-pend { background: var(--h6-laranja); }
        .h6-bloq { cursor: default; color: rgb(var(--color-text-muted)); }
        .h6-bloq:hover { background: transparent; }
        .h6-contratar { flex-shrink: 0; font-size: 9px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: var(--h6-contratar-tx);
            background: var(--h6-contratar-bg); border-radius: 4px; padding: 2px 5px; }
        .h6-mini { width: 40px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 7px;
            color: rgb(var(--color-text-secondary)); text-decoration: none; }
        .h6-mini:hover { background: var(--h6-hover-bg); }
        .h6-mini-cod { font: 700 10px 'JetBrains Mono', monospace; color: var(--h6-cod-tx); }
        .h6-mini.h6-on { background: var(--h6-azul); color: #fff; }
        .h6-mini-ponto { position: absolute; top: 3px; right: 4px; width: 7px; height: 7px; border-radius: 999px; padding: 0; min-width: 0; }
        .h6-avatar { flex-shrink: 0; width: 28px; height: 28px; border-radius: 999px; background: var(--h6-laranja); color: #fff;
            font: 700 11px 'Plus Jakarta Sans', sans-serif; display: flex; align-items: center; justify-content: center; }
        .h6-recolher { width: 26px; height: 26px; align-items: center; justify-content: center; border-radius: 7px; border: 1px solid rgb(var(--color-border));
            color: rgb(var(--color-text-secondary)); background: rgb(var(--color-surface)); }
        .h6-recolher:hover { color: var(--h6-azul-tx); border-color: var(--h6-azul); }
        .h6-secao-lista { margin: 6px 4px 4px; }
        .h6-divisor { border-top: 1px solid rgb(var(--color-border)); margin: 8px 2px 4px; }
        .h6-fixar { flex-shrink: 0; width: 14px; text-align: center; font-size: 12px; line-height: 1; color: rgb(var(--color-text-muted)); opacity: 0; cursor: pointer; }
        .h6-rot:hover .h6-fixar, .h6-fixar:focus-visible { opacity: 1; }
        .h6-fixar.h6-fixado { opacity: 1; color: var(--h6-estrela); }
        @media (hover: none) { .h6-fixar { opacity: .5; } }
        .h6-menu a:focus-visible, .h6-menu button:focus-visible { outline: 2px solid var(--h6-azul); outline-offset: 1px; }

        /* ── Barra superior (02/10, fase 4): mesma linguagem do menu H6 ── */
        .tb { height: 60px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 20px;
            background: rgb(var(--color-surface)); border-bottom: 1px solid rgb(var(--color-border)); }
        .tb-esq { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .tb-cam { display: flex; align-items: center; gap: 8px; min-width: 0; font-size: 13.5px; white-space: nowrap; overflow: hidden; }
        .tb-grupo { color: rgb(var(--color-text-secondary)); } .tb-sep { color: rgb(var(--color-text-muted)); }
        .tb-cod { font: 600 10.5px 'JetBrains Mono', monospace; background: var(--h6-azul); color: #fff; border-radius: 5px; padding: 2px 6px; text-decoration: none; flex-shrink: 0; }
        .tb-nome { font-weight: 600; color: rgb(var(--color-text)); overflow: hidden; text-overflow: ellipsis; }
        .tb-yield { display: flex; align-items: center; gap: 6px; min-width: 0; font-size: 13.5px; }
        .tb-dir { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
        .tb-busca { display: flex; align-items: center; gap: 8px; width: 320px; height: 36px; padding: 0 8px 0 11px; border-radius: 10px;
            border: 1px solid rgb(var(--color-border)); background: var(--h6-campo-bg); color: rgb(var(--color-text-muted)); font-size: 12.5px; cursor: text; }
        .tb-busca span { flex: 1; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tb kbd, .tb-dd kbd { font: 600 9.5px 'JetBrains Mono', monospace; border: 1px solid var(--h6-cod-borda); background: var(--h6-cod-bg); color: var(--h6-cod-tx); border-radius: 4px; padding: 1px 5px; }
        @media (max-width: 1100px) { .tb-busca { width: 36px; padding: 0; justify-content: center; } .tb-busca span, .tb-busca kbd { display: none; } }
        .tb-band { height: 36px; align-items: center; padding: 0 6px; } .tb-band img { max-height: 36px; max-width: 9.5rem; object-fit: contain; } /* Frota: logo com "FROTA" embaixo é mais alta que a do ERP */
        .tb-ib { position: relative; width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center;
            color: rgb(var(--color-text-secondary)); background: transparent; }
        @media (min-width: 768px) { .tb-menu-mob { display: none; } }
        .tb-ib:hover, .tb-on { background: var(--h6-hover-bg); color: var(--h6-azul-tx); }
        .tb-bd { position: absolute; top: 3px; right: 3px; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 99px; background: #DC2626; color: #fff;
            font: 700 9.5px 'Plus Jakarta Sans', sans-serif; display: flex; align-items: center; justify-content: center; }
        .tb-us { display: flex; align-items: center; gap: 9px; border-radius: 10px; padding: 4px 8px 4px 4px; text-align: left; }
        .tb-us:hover { background: var(--h6-hover-bg); }
        .tb-av { width: 32px; height: 32px; border-radius: 999px; background: var(--h6-laranja); color: #fff; font: 700 12px 'Plus Jakarta Sans', sans-serif;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .tb-us-nm { flex-direction: column; line-height: 1.2; } .tb-us-nm b { font-size: 13px; font-weight: 600; color: rgb(var(--color-text)); }
        .tb-us-nm small { font-size: 11px; color: rgb(var(--color-text-secondary)); } .tb-car { font-size: 11px; color: rgb(var(--color-text-muted)); }
        .tb-dd { position: absolute; right: 0; top: calc(100% + 8px); z-index: 60; background: rgb(var(--color-surface)); border: 1px solid rgb(var(--color-border));
            border-radius: 14px; box-shadow: 0 18px 40px rgba(15, 26, 58, .16); }
        .tb-notif { width: 380px; } .tb-user { width: 290px; padding-bottom: 4px; } .tb-ajuda { width: 310px; padding: 0 16px 14px; }
        .tb-dd-t { display: flex; justify-content: space-between; padding: 12px 16px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            color: var(--h6-secao); border-bottom: 1px solid rgb(var(--color-border)); }
        .tb-dd-t span { color: var(--h6-azul-tx); letter-spacing: 0; text-transform: none; font-size: 12px; }
        .tb-ajuda .tb-dd-t { padding: 12px 0 10px; margin-bottom: 4px; }
        .tb-at { font-size: 12.5px; color: rgb(var(--color-text-secondary)); padding: 5px 0; line-height: 1.5; }
        .tb-link { display: block; margin-top: 8px; font-size: 13px; font-weight: 600; color: var(--h6-azul-tx); }
        .tb-nt { display: flex; gap: 10px; align-items: flex-start; padding: 11px 16px; border-bottom: 1px solid rgb(var(--color-border)); font-size: 13px; color: rgb(var(--color-text)); }
        .tb-nt:hover { background: var(--h6-hover-bg); }
        .tb-nt-tx { flex: 1; min-width: 0; } .tb-nt-tx b { display: block; font-weight: 500; } .tb-nova .tb-nt-tx b { font-weight: 600; }
        .tb-nt-tx small { display: block; font-size: 11.5px; color: rgb(var(--color-text-secondary)); }
        .tb-nt em { font-style: normal; font-size: 11px; color: rgb(var(--color-text-muted)); white-space: nowrap; }
        .tb-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; }
        .tb-urgente { background: #DC2626; } .tb-atencao { background: var(--h6-laranja); } .tb-info { background: var(--h6-azul); }
        .tb-vazio { padding: 18px 16px; font-size: 13px; color: rgb(var(--color-text-muted)); text-align: center; }
        .tb-dd-r { display: flex; justify-content: space-between; padding: 10px 16px; font-size: 12.5px; font-weight: 600; color: var(--h6-azul-tx); }
        .tb-u-cab { display: flex; gap: 10px; padding: 14px 16px; border-bottom: 1px solid rgb(var(--color-border)); }
        .tb-u-cab b { display: block; font-size: 13.5px; color: rgb(var(--color-text)); } .tb-u-cab small { display: block; font-size: 11.5px; color: rgb(var(--color-text-secondary)); }
        .tb-u-cab .tb-pf { color: var(--h6-azul-tx); font-weight: 600; }
        .tb-ui { width: 100%; display: flex; align-items: center; gap: 10px; padding: 9px 16px; font-size: 13px; color: rgb(var(--color-text)); text-align: left; }
        .tb-ui:hover { background: var(--h6-hover-bg); } .tb-ui-fixo:hover { background: transparent; }
        .tb-ui small { display: block; font-size: 11px; color: rgb(var(--color-text-secondary)); }
        .tb-mini { width: 28px; height: 28px; border-radius: 8px; background: var(--h6-hover-bg); color: var(--h6-azul-tx); display: flex; align-items: center; justify-content: center; flex: none; }
        .tb-fx { align-items: flex-start; } .tb-fxs { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }
        .tb-fxs a { font: 600 10px 'JetBrains Mono', monospace; background: var(--h6-cod-bg); border: 1px solid var(--h6-cod-borda); color: var(--h6-cod-tx); border-radius: 4px; padding: 1px 6px; }
        .tb-fxs a:hover { border-color: var(--h6-azul); }
        .tb-tema { padding: 8px 16px 10px; border-top: 1px solid rgb(var(--color-border)); margin-top: 4px; }
        .tb-tema small { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--h6-secao); }
        .tb-seg { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; margin-top: 6px; background: var(--h6-hover-bg); border-radius: 9px; padding: 3px; }
        .tb-seg button { font-size: 12px; font-weight: 600; padding: 5px 0; border-radius: 7px; color: rgb(var(--color-text-secondary)); }
        .tb-seg .tb-seg-on { background: rgb(var(--color-surface)); color: var(--h6-azul-tx); box-shadow: 0 1px 2px rgba(0, 0, 0, .08); }
        .tb-sair { border-top: 1px solid rgb(var(--color-border)); color: #DC2626; } .tb-sair .tb-mini { background: rgba(220, 38, 38, .08); color: #DC2626; }
        .tb-modal-bg { position: fixed; inset: 0; z-index: 80; background: rgba(15, 26, 58, .4); display: flex; align-items: center; justify-content: center; padding: 16px; }
        .tb-modal { width: 100%; max-width: 380px; background: rgb(var(--color-surface)); border-radius: 16px; padding: 22px; box-shadow: 0 30px 60px rgba(0, 0, 0, .25); }
        .tb-modal-t { display: block; font-size: 17px; color: rgb(var(--color-text)); } .tb-modal-s { display: block; font-size: 12px; color: rgb(var(--color-text-secondary)); margin: 2px 0 8px; }
        .tb-modal label { display: block; font-size: 12px; font-weight: 600; color: rgb(var(--color-text-secondary)); margin: 10px 0 5px; }
        .tb-modal input { width: 100%; height: 40px; border-radius: 9px; border: 1.5px solid rgb(var(--color-border)); background: var(--input-bg); padding: 0 12px; color: rgb(var(--color-text)); }
        .tb-modal input:focus { outline: none; border-color: var(--h6-azul); box-shadow: 0 0 0 3px rgba(26, 61, 163, .15); }
        .tb-erro { margin-top: 4px; font-size: 12px; color: rgb(var(--color-danger)); }
        .tb-modal-b { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
        .tb-sec { font-size: 13px; font-weight: 600; border-radius: 9px; padding: 9px 14px; border: 1px solid rgb(var(--color-border)); color: rgb(var(--color-text)); }
        .tb-pri { font-size: 13px; font-weight: 600; border-radius: 9px; padding: 9px 14px; background: var(--h6-laranja); color: #fff; }

        /* ── Paleta Ctrl K (02/10, mockup estados 2 a 4): sem negrito pesado, item escolhido em laranja claro ── */
        .pk-caixa { position: relative; width: 100%; max-width: 620px; border-radius: 16px; overflow: hidden;
            background: rgb(var(--color-surface)); border: 1px solid rgb(var(--color-border)); box-shadow: 0 30px 70px rgba(15, 26, 58, .40); }
        .pk-campo { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-bottom: 1px solid rgb(var(--color-border)); }
        .pk-campo input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; padding: 0; font-size: 16px; font-weight: 500; color: rgb(var(--color-text)); }
        .pk-campo input:focus { box-shadow: none; }
        .pk-campo input::placeholder { color: rgb(var(--color-text-muted)); font-weight: 400; }
        .pk-kbd { font: 500 10px 'JetBrains Mono', monospace; color: rgb(var(--color-text-muted)); background: rgb(var(--color-surface-elevated));
            border: 1px solid rgb(var(--color-border)); border-radius: 4px; padding: 1px 6px; white-space: nowrap; }
        .pk-filtros { display: flex; gap: 6px; padding: 8px 16px; border-bottom: 1px solid rgb(var(--color-border)); }
        .pk-filtro { font-size: 11px; font-weight: 500; border-radius: 999px; padding: 3px 11px; background: rgb(var(--color-surface-elevated)); color: rgb(var(--color-text-secondary)); cursor: pointer; }
        .pk-filtro.pk-on { background: var(--h6-azul); color: #fff; }
        .pk-res { max-height: min(420px, 52vh); overflow-y: auto; padding-bottom: 6px; }
        .pk-grupo { font-size: 10px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--h6-secao); padding: 10px 16px 4px; }
        .pk-it { display: flex; align-items: center; gap: 12px; padding: 7px 16px; font-size: 13.5px; color: rgb(var(--color-text)); text-decoration: none; cursor: pointer; }
        .pk-it.pk-sel { background: var(--pk-sel-bg); box-shadow: inset 3px 0 0 var(--h6-laranja); }
        .pk-it.pk-off { cursor: default; color: rgb(var(--color-text-muted)); }
        .pk-k { flex-shrink: 0; min-width: 44px; text-align: center; font: 600 11px 'JetBrains Mono', monospace; color: var(--h6-azul-tx);
            background: var(--pk-chip-bg); border-radius: 5px; padding: 2px 7px; }
        .pk-ic { flex-shrink: 0; width: 26px; height: 26px; border-radius: 6px; display: flex; align-items: center; justify-content: center;
            background: rgb(var(--color-surface-elevated)); color: rgb(var(--color-text-secondary)); }
        .pk-tx { display: flex; flex-direction: column; min-width: 0; flex: 1; }
        .pk-tx > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pk-tx small { font: 400 11px 'JetBrains Mono', monospace; color: rgb(var(--color-text-muted)); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pk-mod { flex-shrink: 0; margin-left: auto; font-size: 11px; color: var(--h6-secao); }
        .pk-tp { flex-shrink: 0; margin-left: auto; font-size: 10px; font-weight: 600; border-radius: 999px; padding: 2px 9px; background: var(--pk-chip-bg); color: var(--h6-azul-tx); white-space: nowrap; }
        .pk-ent { flex-shrink: 0; margin-left: auto; font: 500 10px 'JetBrains Mono', monospace; color: var(--pk-sel-tx); }
        .pk-it mark { background: var(--pk-marca); color: inherit; border-radius: 3px; padding: 0 1px; }
        .pk-aviso { margin: 6px 16px 2px; font-size: 12px; color: rgb(var(--color-text-secondary)); }
        .pk-vazio { padding: 26px 16px; text-align: center; font-size: 13px; color: rgb(var(--color-text-muted)); }
        .pk-rodape { display: flex; flex-wrap: wrap; gap: 6px 16px; padding: 9px 16px; background: var(--pk-rodape-bg); border-top: 1px solid rgb(var(--color-border));
            font-size: 11px; color: rgb(var(--color-text-muted)); }
        @media (max-width: 640px) { .pk-mod, .pk-rodape .pk-so-teclado { display: none; } }

        /* ── Fita de indicadores (só no Dashboard) ── */

    .fita { --fita-up: #4ade80; --fita-dn: #fda4af; --fita-wa: #fcd34d; --fita-eq: #a5b4d8;
        position: relative; height: 36px; display: flex; align-items: center; flex-shrink: 0; font-size: 12px; color: #cfd8f3;
        background: linear-gradient(90deg, #1A3DA3, #132E80); border-bottom: 1px solid #0F2667; box-shadow: 0 2px 6px rgba(15, 38, 103, .18); }
    [data-theme="dark"] .fita { background: linear-gradient(90deg, #17348C, #0E2263); border-bottom-color: #0A1A4D; }
    .fita-vivo { flex-shrink: 0; height: 100%; display: flex; align-items: center; gap: 5px; padding: 0 10px 0 14px; color: #9fb3ee;
        font-weight: 600; font-size: 9.5px; letter-spacing: .05em; text-transform: uppercase; }
    .fita-vivo i { width: 6px; height: 6px; border-radius: 50%; background: #4ade80; animation: fita-pulso 1.6s infinite; }
    .fita-jan { flex: 1; min-width: 0; overflow: hidden; height: 100%; display: flex; align-items: center;
        -webkit-mask-image: linear-gradient(90deg, transparent, #000 24px, #000 calc(100% - 24px), transparent);
        mask-image: linear-gradient(90deg, transparent, #000 24px, #000 calc(100% - 24px), transparent); }
    .fita-trilho { flex-shrink: 0; display: flex; white-space: nowrap; animation: fita-corre 60s linear infinite; }
    .fita:hover .fita-trilho, .fita-trilho:focus-within { animation-play-state: paused; }
    .fita-parada { animation: none; }
    .fita-jan:has(.fita-parada) { overflow-x: auto; scrollbar-width: none; -webkit-mask-image: none; mask-image: none; }
    .fita-q { display: flex; align-items: center; gap: 7px; padding: 0 16px; height: 36px; border-right: 1px solid rgba(255, 255, 255, .14); color: inherit; text-decoration: none; }
    a.fita-q:hover { background: rgba(255, 255, 255, .07); }
    a.fita-q:focus-visible { outline: 2px solid #fff; outline-offset: -3px; }
    .fita-sg { font-weight: 800; color: #fff; font-size: 10.5px; letter-spacing: .03em; text-transform: uppercase; }
    .fita-pd { min-width: 22px; height: 16px; border-radius: 4px; color: #fff; font-size: 8.5px; font-weight: 800; display: inline-flex;
        align-items: center; justify-content: center; padding: 0 3px; }
    .fita-v { font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #fff; border-radius: 3px; padding: 0 2px; transition: background .4s; }
    .fita-d, .fita-tx { font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600; }
    .fita-tx { color: #e4e9fb; font-family: inherit; font-weight: 500; }
    .fita-anp { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: #b9c6f2; background: rgba(255, 255, 255, .08); border-radius: 4px; padding: 1px 6px; }
    .fita-anp b { color: #fff; font-weight: 600; }
    .fita-meta { width: 54px; height: 6px; border-radius: 3px; background: rgba(255, 255, 255, .18); overflow: hidden; }
    .fita-meta i { display: block; height: 100%; background: linear-gradient(90deg, #fcd34d, #4ade80); }
    .fita-st { display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 600; color: #e4e9fb; }
    .fita-st i { width: 7px; height: 7px; border-radius: 50%; }
    .fita-st i.fita-ok { background: #4ade80; } .fita-st i.fita-wa { background: #fcd34d; }
    .fita-st i.fita-dn { background: #f87171; } .fita-st i.fita-off { background: #94a3b8; }
    .fita-cinza { color: #b6c0dc; font-weight: 500; }
    .fita-sp { width: 42px; height: 14px; }
    .fita-up { color: var(--fita-up); } .fita-dn { color: var(--fita-dn); } .fita-eq { color: var(--fita-eq); } .fita-wa { color: var(--fita-wa); }
    .fita-sobe { background: rgba(74, 222, 128, .35); } .fita-desce { background: rgba(253, 164, 175, .38); }
    .fita-fim { flex-shrink: 0; height: 100%; display: flex; align-items: center; gap: 6px; padding: 0 10px 0 12px; font-family: 'JetBrains Mono', monospace;
        font-size: 10px; color: #9fb3ee; border-left: 1px solid rgba(255, 255, 255, .14); }
    .fita-fim b { color: #fff; font-weight: 600; }
    .fita-vazia { flex: 1; padding: 0 16px; color: #cfd8f3; font-size: 12px; }
    /* Dentro da barra superior (02/10): pílula de 36 px no espaço livre entre o caminho e a busca; as medidas da
       barra não mudam. "Ao vivo" vira só a bolinha (o horário vai no title) e o "Atualizado" some. */
    .tb-fita { flex: 1 1 0; min-width: 0; display: flex; align-items: center; padding: 0 6px; }
    .tb-fita .fita-raiz { flex: 1; min-width: 0; }
    .tb-fita .fita { border-radius: 10px; border-bottom: 0; box-shadow: none; }
    .tb-fita .fita-jan { -webkit-mask-image: linear-gradient(90deg, transparent, #000 14px, #000 calc(100% - 14px), transparent);
        mask-image: linear-gradient(90deg, transparent, #000 14px, #000 calc(100% - 14px), transparent); }
    .tb-fita .fita-vivo { padding: 0 4px 0 11px; }
    .tb-fita .fita-vivo-tx, .tb-fita .fita-fim { display: none; }
    .tb-fita .fita-q { padding: 0 12px; }
    .tb-fita .fita-cfgb { width: 32px; border-radius: 0 10px 10px 0; }
    /* Tela estreita: não sobra espaço na barra — a fita volta para a faixa logo abaixo dela, como antes. */
    @media (max-width: 1279px) {
        .tb { position: relative; }
        .tb-fita { position: absolute; left: 0; right: 0; top: 100%; padding: 0; z-index: 40; }
        .tb-fita .fita { border-radius: 0; border-bottom: 1px solid #0F2667; box-shadow: 0 2px 6px rgba(15, 38, 103, .18); }
        .tb-fita .fita-vivo { padding: 0 10px 0 14px; }
        .tb-fita .fita-vivo-tx { display: inline; }
        .tb-fita .fita-fim { display: flex; }
        .tb-fita .fita-cfgb { width: 36px; border-radius: 0; }
        main.tem-fita { padding-top: calc(1.5rem + 36px); }
    }
    @keyframes fita-corre { from { transform: translateX(0); } to { transform: translateX(-50%); } }
    @keyframes fita-pulso { 0% { box-shadow: 0 0 0 0 rgba(74, 222, 128, .55); } 100% { box-shadow: 0 0 0 6px rgba(74, 222, 128, 0); } }
    @media (prefers-reduced-motion: reduce) {
        .fita-trilho { animation: none; } .fita-jan { overflow-x: auto; -webkit-mask-image: none; mask-image: none; } .fita-vivo i { animation: none; }
    }


        /* ── Seletor de empresa/filial no topo do menu (mesmo desenho do ERP) ── */
        .emp { margin-top: 10px; width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 6px; font-size: 11.5px;
            padding: 6px 8px; border: 1px solid rgb(var(--color-border)); border-radius: 8px; color: rgb(var(--color-text-secondary));
            background: rgb(var(--color-surface)); text-align: left; }
        .emp span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .emp-homolog { margin-top: 6px; display: flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em;
            text-transform: uppercase; color: #B45309; background: #FEF3C7; border-radius: 6px; padding: 4px 8px; }
        [data-theme="dark"] .emp-homolog { color: #FCD34D; background: rgba(245, 158, 11, .14); }
        .tb-aviso { margin: 0 0 16px; padding: 10px 14px; border-radius: 10px; font-size: 13px; background: #FFF4EC; border: 1px solid #FFD9BF; color: #9A3412; }
        [data-theme="dark"] .tb-aviso { background: rgba(255, 98, 0, .10); border-color: rgba(255, 98, 0, .28); color: #FFA36B; }
</style>
