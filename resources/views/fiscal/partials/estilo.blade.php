{{-- Estilo comum do DACTE e do DAMDFE — só o que o dompdf entende (tabelas, sem flex/grid). --}}
<style>
    @page { size: A4 portrait; margin: 8mm 8mm 9mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; font-size: 7.6pt; color: #000; margin: 0; line-height: 1.2; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td { border: 0.7pt solid #000; padding: 1.6pt 3pt; vertical-align: top; overflow: hidden; }
    .sem td { border: 0; padding: 0; }
    .l { display: block; font-size: 5.4pt; font-weight: bold; text-transform: uppercase; }
    .v { display: block; font-size: 7.6pt; }
    .b { font-weight: bold; }
    .g { font-size: 9.6pt; font-weight: bold; }
    .c { text-align: center; }
    .r { text-align: right; }
    .mono { font-family: "DejaVu Sans Mono", "Courier New", monospace; }
    .tit { background: #e6e6e6; text-align: center; font-size: 5.8pt; font-weight: bold; text-transform: uppercase; padding: 1.4pt; }
    .bloco { margin-top: -0.7pt; }
    .homolog { border: 1pt solid #000; text-align: center; font-weight: bold; font-size: 8pt; padding: 2pt; margin-bottom: 3pt; }
    .marca { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 46pt; font-weight: bold; color: #000; opacity: 0.08; transform: rotate(-32deg); }
    .canhoto td { border-style: dashed; }
    .chave { font-family: "DejaVu Sans Mono", "Courier New", monospace; font-size: 7.4pt; font-weight: bold; text-align: center; letter-spacing: 0.3pt; }
    .lista td { border: 0; border-bottom: 0.3pt solid #999; padding: 1.2pt 3pt; font-size: 7pt; }
    .lista tr.cab td { font-size: 5.4pt; font-weight: bold; text-transform: uppercase; border-bottom: 0.7pt solid #000; }
    .rodape { font-size: 5.8pt; text-align: center; margin-top: 3pt; }
    .imprimir { position: fixed; top: 10px; right: 10px; padding: 8px 14px; font: 600 13px Arial, sans-serif; background: #1A3DA3; color: #fff; border: 0; border-radius: 8px; cursor: pointer; }
    @media print { .imprimir { display: none; } }
    @media screen { body { background: #e9ecf3; } .folha { width: 194mm; margin: 12px auto; background: #fff; padding: 8mm; box-shadow: 0 6px 24px rgba(0,0,0,.15); position: relative; } }
</style>
