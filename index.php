<?php

use InflacionMundial\InflationService;
use InflacionMundial\WorldBankClient;

require __DIR__ . '/src/bootstrap.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_number($value, $decimals)
{
    return number_format((float) $value, (int) $decimals, ',', '.');
}

function format_percent($value)
{
    return format_number($value, 2) . '%';
}

function parse_decimal($value)
{
    $normalized = preg_replace('/\s+/', '', trim((string) $value));

    if ($normalized === null || $normalized === '') {
        throw new InvalidArgumentException('Debes ingresar un valor numérico.');
    }

    if (preg_match('/^\d{1,3}(\.\d{3})*,\d+$/', $normalized) === 1) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } elseif (preg_match('/^\d{1,3}(,\d{3})*\.\d+$/', $normalized) === 1) {
        $normalized = str_replace(',', '', $normalized);
    } elseif (substr_count($normalized, ',') === 1 && substr_count($normalized, '.') === 0) {
        $normalized = str_replace(',', '.', $normalized);
    }

    if (!is_numeric($normalized)) {
        throw new InvalidArgumentException('El valor ingresado no es numérico.');
    }

    return (float) $normalized;
}

function current_version($path)
{
    $version = trim((string) @file_get_contents($path));

    if (preg_match('/^v\d+\.\d+\.\d+$/', $version) === 1) {
        return $version;
    }

    return 'v0.0.0';
}

function request_value($key, $default)
{
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }

    if (isset($_GET[$key])) {
        return $_GET[$key];
    }

    return $default;
}

function post_value($key, $default)
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function collect_country_codes(array $countries)
{
    $codes = array();

    foreach ($countries as $country) {
        $codes[] = $country['id'];
    }

    return $codes;
}

$service = new InflationService(new WorldBankClient(__DIR__ . '/data'));
$version = current_version(__DIR__ . '/VERSION');
$cssAssetVersion = rawurlencode($version . '-' . (string) @filemtime(__DIR__ . '/assets/styles.css'));
$jsAssetVersion = rawurlencode($version . '-' . (string) @filemtime(__DIR__ . '/assets/app.js'));
$countries = $service->getCountries();
$countryCodes = collect_country_codes($countries);
$selectedCountry = strtoupper((string) request_value('country', 'GTM'));

if (!in_array($selectedCountry, $countryCodes, true)) {
    if (in_array('GTM', $countryCodes, true)) {
        $selectedCountry = 'GTM';
    } else {
        $selectedCountry = isset($countryCodes[0]) ? $countryCodes[0] : '';
    }
}

$errors = array();
$results = array();
$context = null;
$activeCalculator = '';

try {
    $context = $service->getCountryContext($selectedCountry);
} catch (Exception $exception) {
    $errors[] = $exception->getMessage();
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && $context !== null) {
    try {
        $calculator = (string) post_value('calculator', '');
        $activeCalculator = $calculator;

        if ($calculator === 'future_accumulated') {
            $results['future_accumulated'] = $service->calculateFutureAccumulatedInflation(
                $selectedCountry,
                (int) post_value('target_year_future_accumulated', 0)
            );
        } elseif ($calculator === 'current_price') {
            $results['current_price'] = $service->calculateCurrentPrice(
                $selectedCountry,
                parse_decimal(post_value('historic_price', '')),
                (int) post_value('comparison_year', 0)
            );
        } elseif ($calculator === 'future_year_inflation') {
            $results['future_year_inflation'] = $service->calculateFutureYearInflation(
                $selectedCountry,
                (int) post_value('target_year_specific', 0)
            );
        } elseif ($calculator === 'future_price') {
            $results['future_price'] = $service->calculateFuturePrice(
                $selectedCountry,
                parse_decimal(post_value('current_price', '')),
                (int) post_value('target_year_future_price', 0)
            );
        } else {
            throw new InvalidArgumentException('No se seleccionó un cálculo válido.');
        }
    } catch (Exception $exception) {
        $errors[] = $exception->getMessage();
    }
}

$projectionMinYear = $context !== null ? ((int) $context['currentYear'] + 1) : ((int) date('Y') + 1);
$projectionMaxYear = $context !== null ? (int) $context['projectionEndYear'] : ((int) date('Y') + 25);
$comparisonYears = $context !== null ? $context['comparisonYears'] : array();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inflación Mundial</title>
    <meta name="description" content="Calculadora PHP para estimar inflación y precios por país usando datos del Banco Mundial.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700;800&family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function () {
            var theme = 'dark';
            document.documentElement.className += document.documentElement.className ? ' has-js' : 'has-js';
            try {
                var savedTheme = window.localStorage ? localStorage.getItem('inflacionmundial-theme') : null;
                if (savedTheme === 'light' || savedTheme === 'dark') {
                    theme = savedTheme;
                }
            } catch (error) {
                theme = 'dark';
            }
            document.documentElement.setAttribute('data-theme', theme);
        }());
    </script>
    <link rel="stylesheet" href="assets/styles.css?v=<?= h($cssAssetVersion) ?>">
</head>
<body data-active-calculator="<?= h($activeCalculator) ?>">
<main class="shell">
    <section class="topbar" data-reveal="1" style="--reveal-order: 1;">
        <div class="topbar-copy">
            <p class="eyebrow eyebrow-inline">InflacionMundial <?= h($version) ?></p>
            <p class="topbar-note">Modo oscuro por defecto, con opción clara y cálculos guiados paso a paso.</p>
        </div>
        <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="true">
            <span class="theme-toggle-label">Modo oscuro</span>
            <span class="theme-toggle-hint">Cambiar a claro</span>
        </button>
    </section>

    <section class="hero" data-reveal="2" style="--reveal-order: 2;">
        <div class="hero-copy">
            <p class="hero-tag">Calculadora simple para cualquier persona</p>
            <h1>Calculadora de inflación y precios por país</h1>
            <p class="lead">
                Elige un país, selecciona lo que quieres saber y escribe uno o dos datos. La app hace el resto
                con información histórica del Banco Mundial.
            </p>
            <div class="hero-actions">
                <a href="#quick-start" class="secondary-link">Cómo usarla</a>
                <a href="#calc-1" class="secondary-link secondary-link-strong">Ir a las calculadoras</a>
            </div>
        </div>
        <div class="hero-meta">
            <form method="get" class="country-selector">
                <label for="country">País de análisis</label>
                <div class="inline-form">
                    <select id="country" name="country">
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= h($country['id']) ?>" <?= $country['id'] === $selectedCountry ? 'selected' : '' ?>>
                                <?= h($country['name']) ?> (<?= h($country['id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Cambiar país</button>
                </div>
            </form>
            <?php if ($context !== null): ?>
                <div class="stat-grid">
                    <article class="stat-card">
                        <span>País activo</span>
                        <strong><?= h($context['country']['name']) ?></strong>
                        <small><?= h($context['country']['region']) ?></small>
                    </article>
                    <article class="stat-card">
                        <span>Precio actual hasta</span>
                        <strong><?= h($context['latestCpiYear']) ?></strong>
                        <small>Es el último año CPI disponible.</small>
                    </article>
                    <article class="stat-card">
                        <span>Última inflación oficial</span>
                        <strong><?= h($context['latestInflationYear']) ?></strong>
                        <small><?= format_percent($context['latestInflationValue']) ?> anual</small>
                    </article>
                    <article class="stat-card">
                        <span>Promedio usado para proyectar</span>
                        <strong><?= format_percent($context['averageRecentInflation']) ?></strong>
                        <small>Ventana <?= h($context['modelStartYear']) ?>-<?= h($context['modelEndYear']) ?></small>
                    </article>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="quick-start" class="steps-panel" data-reveal="3" style="--reveal-order: 3;">
        <article class="step-card">
            <span class="step-index">1</span>
            <h2>Elige el país</h2>
            <p>Selecciona arriba el país que quieres analizar. Todos los cálculos usarán ese país.</p>
        </article>
        <article class="step-card">
            <span class="step-index">2</span>
            <h2>Escoge la pregunta</h2>
            <p>Debajo tienes cuatro opciones claras: inflación futura, precio actual, inflación anual o precio futuro.</p>
        </article>
        <article class="step-card">
            <span class="step-index">3</span>
            <h2>Ingresa pocos datos</h2>
            <p>La mayoría de cálculos solo piden un año o un precio. El resultado aparece en la misma tarjeta.</p>
        </article>
    </section>

    <?php if (!empty($errors)): ?>
        <section class="alert" data-reveal="4" style="--reveal-order: 4;">
            <strong>No se pudo completar el cálculo.</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="jump-links" data-reveal="5" style="--reveal-order: 5;">
        <a href="#calc-1">Quiero saber la inflación futura acumulada</a>
        <a href="#calc-2">Quiero actualizar un precio viejo</a>
        <a href="#calc-3">Quiero ver la inflación de un año futuro</a>
        <a href="#calc-4">Quiero proyectar un precio futuro</a>
    </section>

    <section class="info-panel" data-reveal="6" style="--reveal-order: 6;">
        <h2>Cómo interpreta la app los datos</h2>
        <p>
            "Precio actual" significa el equivalente al <strong>último año con CPI disponible</strong>, no
            necesariamente al año calendario <?= h(date('Y')) ?>. Las proyecciones futuras son estimaciones
            basadas en tendencia lineal y promedio de los últimos
            <?= h(InflationService::HISTORY_WINDOW) ?> años observados.
        </p>
    </section>

    <section class="calculator-grid">
        <article id="calc-1" class="calculator-card <?= $activeCalculator === 'future_accumulated' ? 'is-active' : '' ?>" data-reveal="7" style="--reveal-order: 7;">
            <div class="card-head">
                <p class="section-kicker">Herramienta 1</p>
                <h2>Calcular la inflación futura acumulada</h2>
                <p>Proyecta la inflación acumulada desde el año actual hasta un año futuro objetivo.</p>
                <p class="simple-tip">Úsalo si quieres saber cuánto podrían subir los precios en total.</p>
            </div>
            <form method="post" class="calculator-form">
                <input type="hidden" name="country" value="<?= h($selectedCountry) ?>">
                <input type="hidden" name="calculator" value="future_accumulated">
                <label for="target_year_future_accumulated">Año futuro</label>
                <input
                    id="target_year_future_accumulated"
                    name="target_year_future_accumulated"
                    type="number"
                    min="<?= h($projectionMinYear) ?>"
                    max="<?= h($projectionMaxYear) ?>"
                    value="<?= h(post_value('target_year_future_accumulated', $projectionMinYear + 4)) ?>"
                    required
                >
                <p class="field-note">Solo debes indicar hasta qué año quieres proyectar.</p>
                <button type="submit">Calcular inflación futura</button>
            </form>
            <?php if (isset($results['future_accumulated'])): ?>
                <div class="result-card" role="status">
                    <p class="result-label">Resultado estimado</p>
                    <strong><?= format_percent($results['future_accumulated']['accumulatedInflation']) ?></strong>
                    <p>
                        Entre <?= h($results['future_accumulated']['baseYear']) ?> y
                        <?= h($results['future_accumulated']['targetYear']) ?> la inflación acumulada estimada
                        para <?= h($results['future_accumulated']['countryName']) ?> es
                        <?= format_percent($results['future_accumulated']['accumulatedInflation']) ?>.
                    </p>
                    <small>
                        Promedio anual proyectado: <?= format_percent($results['future_accumulated']['projectedAverageRate']) ?>.
                        Modelo histórico: <?= h($results['future_accumulated']['modelStartYear']) ?>-<?= h($results['future_accumulated']['modelEndYear']) ?>.
                    </small>
                </div>
            <?php endif; ?>
        </article>

        <article id="calc-2" class="calculator-card <?= $activeCalculator === 'current_price' ? 'is-active' : '' ?>" data-reveal="8" style="--reveal-order: 8;">
            <div class="card-head">
                <p class="section-kicker">Herramienta 2</p>
                <h2>Calcular el precio actual de un producto</h2>
                <p>Ingresa un precio histórico y compáralo contra el último CPI disponible del país.</p>
                <p class="simple-tip">Úsalo si quieres traer un precio antiguo al valor más reciente disponible.</p>
            </div>
            <form method="post" class="calculator-form">
                <input type="hidden" name="country" value="<?= h($selectedCountry) ?>">
                <input type="hidden" name="calculator" value="current_price">
                <label for="historic_price">Precio del producto</label>
                <input
                    id="historic_price"
                    name="historic_price"
                    type="text"
                    inputmode="decimal"
                    placeholder="Ejemplo: 125.50"
                    value="<?= h(post_value('historic_price', '')) ?>"
                    required
                >
                <label for="comparison_year">Año inicial o de comparación</label>
                <select id="comparison_year" name="comparison_year" required>
                    <?php foreach ($comparisonYears as $year): ?>
                        <option value="<?= h($year) ?>" <?= (string) $year === (string) post_value('comparison_year', '') ? 'selected' : '' ?>>
                            <?= h($year) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-note">Ejemplo: si en 2015 algo costaba 100, aquí puedes ver su equivalente actual.</p>
                <button type="submit">Calcular precio actual</button>
            </form>
            <?php if (isset($results['current_price'])): ?>
                <div class="result-card" role="status">
                    <p class="result-label">Precio equivalente</p>
                    <strong><?= format_number($results['current_price']['currentPrice'], 2) ?></strong>
                    <p>
                        Un precio de <?= format_number($results['current_price']['originalPrice'], 2) ?> en
                        <?= h($results['current_price']['baseYear']) ?> equivale a
                        <?= format_number($results['current_price']['currentPrice'], 2) ?> en
                        <?= h($results['current_price']['latestCpiYear']) ?> para
                        <?= h($results['current_price']['countryName']) ?>.
                    </p>
                    <small>
                        Inflación acumulada del período: <?= format_percent($results['current_price']['accumulatedInflation']) ?>.
                    </small>
                </div>
            <?php endif; ?>
        </article>

        <article id="calc-3" class="calculator-card <?= $activeCalculator === 'future_year_inflation' ? 'is-active' : '' ?>" data-reveal="9" style="--reveal-order: 9;">
            <div class="card-head">
                <p class="section-kicker">Herramienta 3</p>
                <h2>Calcular la inflación en otro año futuro</h2>
                <p>Entrega la tasa anual estimada para un año futuro específico.</p>
                <p class="simple-tip">Úsalo si solo quieres conocer una tasa anual proyectada.</p>
            </div>
            <form method="post" class="calculator-form">
                <input type="hidden" name="country" value="<?= h($selectedCountry) ?>">
                <input type="hidden" name="calculator" value="future_year_inflation">
                <label for="target_year_specific">Año futuro específico</label>
                <input
                    id="target_year_specific"
                    name="target_year_specific"
                    type="number"
                    min="<?= h($projectionMinYear) ?>"
                    max="<?= h($projectionMaxYear) ?>"
                    value="<?= h(post_value('target_year_specific', $projectionMinYear + 1)) ?>"
                    required
                >
                <p class="field-note">Ideal para comparar un año puntual, por ejemplo 2028 o 2030.</p>
                <button type="submit">Calcular inflación anual</button>
            </form>
            <?php if (isset($results['future_year_inflation'])): ?>
                <div class="result-card" role="status">
                    <p class="result-label">Tasa proyectada</p>
                    <strong><?= format_percent($results['future_year_inflation']['projectedRate']) ?></strong>
                    <p>
                        La inflación anual estimada para <?= h($results['future_year_inflation']['targetYear']) ?>
                        en <?= h($results['future_year_inflation']['countryName']) ?> es
                        <?= format_percent($results['future_year_inflation']['projectedRate']) ?>.
                    </p>
                    <small>
                        Último dato oficial: <?= h($results['future_year_inflation']['latestInflationYear']) ?>.
                        Promedio reciente: <?= format_percent($results['future_year_inflation']['averageRecentInflation']) ?>.
                    </small>
                </div>
            <?php endif; ?>
        </article>

        <article id="calc-4" class="calculator-card <?= $activeCalculator === 'future_price' ? 'is-active' : '' ?>" data-reveal="10" style="--reveal-order: 10;">
            <div class="card-head">
                <p class="section-kicker">Herramienta 4</p>
                <h2>Calcular el precio futuro de un producto</h2>
                <p>Proyecta cuánto podría costar un producto en el año final que elijas.</p>
                <p class="simple-tip">Úsalo si quieres estimar cuánto podría costar algo más adelante.</p>
            </div>
            <form method="post" class="calculator-form">
                <input type="hidden" name="country" value="<?= h($selectedCountry) ?>">
                <input type="hidden" name="calculator" value="future_price">
                <label for="current_price">Precio actual del producto</label>
                <input
                    id="current_price"
                    name="current_price"
                    type="text"
                    inputmode="decimal"
                    placeholder="Ejemplo: 49.99"
                    value="<?= h(post_value('current_price', '')) ?>"
                    required
                >
                <label for="target_year_future_price">Año final</label>
                <input
                    id="target_year_future_price"
                    name="target_year_future_price"
                    type="number"
                    min="<?= h($projectionMinYear) ?>"
                    max="<?= h($projectionMaxYear) ?>"
                    value="<?= h(post_value('target_year_future_price', $projectionMinYear + 4)) ?>"
                    required
                >
                <p class="field-note">Ingresa el precio de hoy y el año final al que quieres llevarlo.</p>
                <button type="submit">Calcular precio futuro</button>
            </form>
            <?php if (isset($results['future_price'])): ?>
                <div class="result-card" role="status">
                    <p class="result-label">Precio proyectado</p>
                    <strong><?= format_number($results['future_price']['futurePrice'], 2) ?></strong>
                    <p>
                        Un precio actual de <?= format_number($results['future_price']['originalPrice'], 2) ?> podría
                        llegar a <?= format_number($results['future_price']['futurePrice'], 2) ?> en
                        <?= h($results['future_price']['targetYear']) ?> para
                        <?= h($results['future_price']['countryName']) ?>.
                    </p>
                    <small>
                        Inflación acumulada estimada: <?= format_percent($results['future_price']['accumulatedInflation']) ?>.
                        Modelo histórico: <?= h($results['future_price']['modelStartYear']) ?>-<?= h($results['future_price']['modelEndYear']) ?>.
                    </small>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <footer class="footer">
        <p>Fuente de datos: Banco Mundial (FP.CPI.TOTL y FP.CPI.TOTL.ZG).</p>
        <p>Versión activa: <?= h($version) ?>.</p>
    </footer>
</main>
<script src="assets/app.js?v=<?= h($jsAssetVersion) ?>"></script>
</body>
</html>
