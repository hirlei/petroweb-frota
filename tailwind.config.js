import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    safelist: [
        'border-l-[3px]',
    ],

    darkMode: ['class'],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
            },
            colors: {
                bg:                 'rgb(var(--color-bg) / <alpha-value>)',
                surface:            'rgb(var(--color-surface) / <alpha-value>)',
                'surface-elevated': 'rgb(var(--color-surface-elevated) / <alpha-value>)',
                sidebar:            'rgb(var(--color-sidebar) / <alpha-value>)',
                border:             'rgb(var(--color-border) / <alpha-value>)',
                'border-strong':    'rgb(var(--color-border-strong) / <alpha-value>)',
                text:               'rgb(var(--color-text) / <alpha-value>)',
                'text-secondary':   'rgb(var(--color-text-secondary) / <alpha-value>)',
                'text-muted':       'rgb(var(--color-text-muted) / <alpha-value>)',
                primary: {
                    DEFAULT: 'rgb(var(--color-primary) / <alpha-value>)',
                    hover:   'rgb(var(--color-primary-hover) / <alpha-value>)',
                    soft:    'rgb(var(--color-primary-soft) / <alpha-value>)',
                },
                'amber-b': {
                    DEFAULT: 'rgb(var(--color-amber-b) / <alpha-value>)',
                    fg:      'rgb(var(--color-amber-b-fg) / <alpha-value>)',
                    hover:   'rgb(var(--color-amber-b-hover) / <alpha-value>)',
                },
                secondary: {
                    DEFAULT: 'rgb(var(--color-secondary) / <alpha-value>)',
                    hover:   'rgb(var(--color-secondary-hover) / <alpha-value>)',
                    soft:    'rgb(var(--color-secondary-soft) / <alpha-value>)',
                },
                success: 'rgb(var(--color-success) / <alpha-value>)',
                warning: 'rgb(var(--color-warning) / <alpha-value>)',
                danger:  'rgb(var(--color-danger) / <alpha-value>)',
                info:    'rgb(var(--color-info) / <alpha-value>)',
            },
        },
    },

    plugins: [forms],
};
