<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 3080 — reconciliação noturna do custo e da margem das viagens (US-079).
\Illuminate\Support\Facades\Schedule::command('viagens:recalcular-custos')->dailyAt('03:10')->withoutOverlapping();
