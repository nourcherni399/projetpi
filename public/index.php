<?php

use App\Kernel;

// Évite le dépassement du temps d'exécution lors du chargement des routes (notamment avec projet sur OneDrive)
set_time_limit(300);

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
