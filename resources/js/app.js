import './bootstrap';

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Mapa de percurso (Leaflet + OpenStreetMap / Esri satélite).
 *
 * Componente Alpine registrado no Alpine que o Livewire já carrega. Desenha:
 *  - o traçado pela estrada (config.geometria, vindo do OpenRouteService) ou,
 *    na falta, a linha reta ligando os pontos;
 *  - os marcadores dos pontos (origem, pedágio, destino…);
 *  - o caminhão na posição informada, com balão de informações.
 * Duas camadas base (Mapa / Satélite) via seletor. O container leva wire:ignore
 * para o Livewire não recriar o mapa a cada atualização. Usamos L.circleMarker
 * (SVG) de propósito: evita o problema dos ícones-padrão do Leaflet sob bundler.
 */
const CORES = {
    origem: '#16a34a',
    destino: '#dc2626',
    pedagio: '#d97706',
    parada: '#2563eb',
    passagem: '#0d9488',
    atual: '#d97706',
    rota: '#d97706',
};

function camadasBase(tiles) {
    const base = {};
    const mapa = tiles?.mapa;
    const satelite = tiles?.satelite;

    if (mapa) {
        base['Mapa'] = L.tileLayer(mapa.url, { maxZoom: mapa.max_zoom || 19, attribution: mapa.atribuicao || '' });
    }
    if (satelite) {
        base['Satélite'] = L.tileLayer(satelite.url, { maxZoom: satelite.max_zoom || 19, attribution: satelite.atribuicao || '' });
    }
    if (Object.keys(base).length === 0) {
        base['Mapa'] = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' });
    }
    return base;
}

function iniciarMapaPercurso(el, config) {
    const pontos = (config.pontos || []).filter((p) => p.lat != null && p.lng != null);
    const geometria = Array.isArray(config.geometria?.pontos) ? config.geometria.pontos : (Array.isArray(config.geometria) ? config.geometria : null);

    const map = L.map(el, { scrollWheelZoom: false });

    const bases = camadasBase(config.tiles);
    Object.values(bases)[0].addTo(map); // primeira camada (Mapa) por padrão
    if (Object.keys(bases).length > 1) {
        L.control.layers(bases, {}, { position: 'topright' }).addTo(map);
    }

    let limites = [];

    // 1) Traçado pela estrada, quando calculado.
    if (geometria && geometria.length > 1) {
        const linha = geometria.map((c) => [Number(c[0]), Number(c[1])]);
        L.polyline(linha, { color: CORES.rota, weight: 5, opacity: 0.9 }).addTo(map);
        limites = linha;
    } else if (config.linha && pontos.length > 1) {
        // 2) Sem traçado: liga os pontos em reta (fallback).
        const linha = pontos.map((p) => [Number(p.lat), Number(p.lng)]);
        L.polyline(linha, { color: CORES.rota, weight: 4, opacity: 0.6, dashArray: '4 8' }).addTo(map);
        limites = linha;
    }

    // Marcadores dos pontos.
    pontos.forEach((p) => {
        const cor = CORES[p.cor] || CORES[p.tipo] || CORES.parada;
        L.circleMarker([Number(p.lat), Number(p.lng)], {
            radius: 7, color: '#ffffff', weight: 2, fillColor: cor, fillOpacity: 1,
        }).addTo(map).bindPopup(p.label || '');
        if (limites.length === 0) limites.push([Number(p.lat), Number(p.lng)]);
    });

    // Caminhão na posição atual, com balão.
    if (config.caminhao && config.caminhao.lat != null && config.caminhao.lng != null) {
        const c = config.caminhao;
        const icone = L.divIcon({
            className: 'marcador-caminhao',
            html: '<div style="width:30px;height:30px;border-radius:50%;background:' + CORES.atual
                + ';border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;font-size:15px;">🚚</div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
        });
        const marcador = L.marker([Number(c.lat), Number(c.lng)], { icon: icone }).addTo(map);
        if (c.popup) {
            marcador.bindPopup(c.popup);
            marcador.openPopup();
        }
        limites.push([Number(c.lat), Number(c.lng)]);
    }

    if (limites.length === 1) {
        map.setView(limites[0], 12);
    } else if (limites.length > 1) {
        map.fitBounds(limites, { padding: [34, 34] });
    } else {
        map.setView([-14.235, -51.925], 4);
    }

    setTimeout(() => map.invalidateSize(), 250);

    return map;
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('mapaPercurso', (config = {}) => ({
        mapa: null,
        init() {
            this.mapa = iniciarMapaPercurso(this.$refs.mapa, config);
        },
        destroy() {
            if (this.mapa) {
                this.mapa.remove();
                this.mapa = null;
            }
        },
    }));
});
