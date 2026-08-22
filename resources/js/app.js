import './bootstrap';

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Mapa de percurso (Leaflet + OpenStreetMap).
 *
 * Componente Alpine registrado no Alpine que o Livewire já carrega. Recebe os
 * pontos (lat/lng/label/cor/tipo) e desenha marcadores + linha do percurso.
 * Usamos L.circleMarker (SVG, sem imagem) de propósito: evita o problema
 * clássico dos ícones-padrão do Leaflet quebrarem sob bundler. O container leva
 * wire:ignore para o Livewire não recriar o mapa a cada atualização.
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

function iniciarMapaPercurso(el, config) {
    const pontos = (config.pontos || []).filter((p) => p.lat != null && p.lng != null);

    const map = L.map(el, {
        scrollWheelZoom: false,
        attributionControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const coords = pontos.map((p) => [Number(p.lat), Number(p.lng)]);

    // Linha do percurso (trecho percorrido sólido + restante tracejado, quando indicado).
    if (config.linha && coords.length > 1) {
        const feitos = Number.isInteger(config.percorridoAte) ? config.percorridoAte : coords.length - 1;

        if (feitos > 0) {
            L.polyline(coords.slice(0, feitos + 1), { color: CORES.rota, weight: 5 }).addTo(map);
        }
        if (feitos < coords.length - 1) {
            L.polyline(coords.slice(Math.max(feitos, 0)), {
                color: CORES.rota, weight: 4, opacity: 0.55, dashArray: '2 10',
            }).addTo(map);
        }
    }

    // Marcadores.
    pontos.forEach((p) => {
        const cor = CORES[p.cor] || CORES[p.tipo] || CORES.parada;
        const raio = p.tipo === 'atual' || p.cor === 'atual' ? 10 : 7;

        L.circleMarker([Number(p.lat), Number(p.lng)], {
            radius: raio,
            color: '#ffffff',
            weight: 2,
            fillColor: cor,
            fillOpacity: 1,
        })
            .addTo(map)
            .bindPopup(p.label || '');
    });

    if (coords.length === 1) {
        map.setView(coords[0], 12);
    } else if (coords.length > 1) {
        map.fitBounds(coords, { padding: [34, 34] });
    } else {
        map.setView([-14.235, -51.925], 4); // Brasil, quando não há coordenadas.
    }

    // O container costuma nascer com tamanho 0 dentro de card/aba; recalcula.
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
