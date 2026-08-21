/**
 * Gera public/img/icons/sprite.svg a partir do lucide-static.
 *
 * Por que sprite local e não CDN: nenhuma tela do PetroWeb depende de rede de
 * terceiro em runtime. O CDN do lucide some, o ícone some, e o operador fica
 * olhando para uma tela sem affordance nenhuma.
 *
 * O script VARRE o código à procura dos nomes realmente usados — em
 * `<x-icon name="...">`, em `:name="..."` e no config/navegacao.php — e
 * embute só esses. Ícone novo no código entra no sprite na próxima execução;
 * ícone que ninguém usa não engorda o arquivo.
 *
 * Uso: node scripts/gerar-sprite-icones.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const origemLucide = path.join(raiz, 'node_modules', 'lucide-static', 'icons');
const destino = path.join(raiz, 'public', 'img', 'icons', 'sprite.svg');

const PASTAS = ['resources/views', 'app', 'config'];

/** Nomes que o build precisa mesmo que o grep não pegue (uso dinâmico). */
const SEMPRE = [
  'check', 'x', 'plus', 'search', 'alert-triangle', 'info', 'chevron-down',
  'chevron-right', 'sun', 'moon', 'log-out', 'inbox', 'lock', 'pencil',
  'trash-2', 'star', 'mail', 'eye', 'download', 'upload', 'clock',
];

function arquivos(dir, acc = []) {
  for (const item of fs.readdirSync(dir, { withFileTypes: true })) {
    const alvo = path.join(dir, item.name);
    if (item.isDirectory()) arquivos(alvo, acc);
    else if (/\.(blade\.php|php|js)$/.test(item.name)) acc.push(alvo);
  }
  return acc;
}

function nomesUsados() {
  const encontrados = new Set(SEMPRE);
  const padroes = [
    /<x-icon[^>]*\sname="([a-z0-9-]+)"/g,          // <x-icon name="truck">
    /:name="'([a-z0-9-]+)'"/g,                      // :name="'check'"
    /'icon'\s*=>\s*'([a-z0-9-]+)'/g,                // config/navegacao.php
    /icon="([a-z0-9-]+)"/g,                         // <x-button icon="plus">
  ];

  for (const pasta of PASTAS) {
    const caminho = path.join(raiz, pasta);
    if (!fs.existsSync(caminho)) continue;

    for (const arquivo of arquivos(caminho)) {
      const conteudo = fs.readFileSync(arquivo, 'utf8');
      for (const padrao of padroes) {
        for (const m of conteudo.matchAll(padrao)) encontrados.add(m[1]);
      }
    }
  }

  return [...encontrados].sort();
}

const usados = nomesUsados();
const simbolos = [];
const faltando = [];

for (const nome of usados) {
  const arquivo = path.join(origemLucide, `${nome}.svg`);

  if (!fs.existsSync(arquivo)) {
    faltando.push(nome);
    continue;
  }

  const svg = fs.readFileSync(arquivo, 'utf8');
  const miolo = svg
    .replace(/<svg[^>]*>/, '')
    .replace(/<\/svg>/, '')
    .trim();

  // stroke="currentColor" fica no <symbol>: a cor segue a classe text-* de quem usa.
  simbolos.push(
    `  <symbol id="${nome}" viewBox="0 0 24 24" fill="none" stroke="currentColor" ` +
    `stroke-width="2" stroke-linecap="round" stroke-linejoin="round">\n` +
    `    ${miolo.replace(/\n\s*/g, '\n    ')}\n  </symbol>`
  );
}

fs.mkdirSync(path.dirname(destino), { recursive: true });
fs.writeFileSync(
  destino,
  `<svg xmlns="http://www.w3.org/2000/svg" style="display:none">\n${simbolos.join('\n')}\n</svg>\n`,
  'utf8'
);

console.log(`sprite gerado: ${simbolos.length} ícones em public/img/icons/sprite.svg`);

if (faltando.length > 0) {
  console.error(`\n⚠  ${faltando.length} ícone(s) não existem no lucide e vão renderizar VAZIO:`);
  for (const nome of faltando) console.error(`   · ${nome}`);
  process.exit(1);
}
