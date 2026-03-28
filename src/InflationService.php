<?php

namespace InflacionMundial;

use InvalidArgumentException;
use RuntimeException;

final class InflationService
{
    const CPI_INDICATOR = 'FP.CPI.TOTL';
    const INFLATION_INDICATOR = 'FP.CPI.TOTL.ZG';
    const HISTORY_WINDOW = 10;

    private $client;
    private $countries = null;
    private $contexts = array();

    public function __construct(WorldBankClient $client)
    {
        $this->client = $client;
    }

    public function getCountries()
    {
        if ($this->countries === null) {
            $this->countries = $this->client->getCountries();
        }

        return $this->countries;
    }

    public function getCountryContext($countryCode)
    {
        $countryCode = strtoupper($countryCode);

        if (isset($this->contexts[$countryCode])) {
            return $this->contexts[$countryCode];
        }

        $country = $this->findCountry($countryCode);
        $cpiSeries = $this->client->getIndicatorSeries($countryCode, self::CPI_INDICATOR);
        $inflationSeries = $this->client->getIndicatorSeries($countryCode, self::INFLATION_INDICATOR);

        if (count($cpiSeries) < 2 || count($inflationSeries) < 2) {
            throw new RuntimeException('No hay datos históricos suficientes para este país.');
        }

        $recentInflationSeries = array_slice(
            $inflationSeries,
            -min(self::HISTORY_WINDOW, count($inflationSeries)),
            null,
            true
        );

        $latestCpiYear = (int) $this->getLastKey($cpiSeries);
        $latestInflationYear = (int) $this->getLastKey($inflationSeries);
        $currentYear = (int) date('Y');

        $this->contexts[$countryCode] = array(
            'country' => $country,
            'cpiSeries' => $cpiSeries,
            'inflationSeries' => $inflationSeries,
            'recentInflationSeries' => $recentInflationSeries,
            'latestCpiYear' => $latestCpiYear,
            'latestCpiValue' => $cpiSeries[$latestCpiYear],
            'latestInflationYear' => $latestInflationYear,
            'latestInflationValue' => $inflationSeries[$latestInflationYear],
            'averageRecentInflation' => array_sum($recentInflationSeries) / count($recentInflationSeries),
            'currentYear' => $currentYear,
            'projectionEndYear' => max($currentYear + 25, $latestInflationYear + 25),
            'comparisonYears' => array_keys($cpiSeries),
            'modelStartYear' => (int) $this->getFirstKey($recentInflationSeries),
            'modelEndYear' => (int) $this->getLastKey($recentInflationSeries),
        );

        return $this->contexts[$countryCode];
    }

    public function calculateCurrentPrice($countryCode, $price, $baseYear)
    {
        if ($price <= 0) {
            throw new InvalidArgumentException('El precio debe ser mayor que cero.');
        }

        $context = $this->getCountryContext($countryCode);
        $cpiSeries = $context['cpiSeries'];

        if (!isset($cpiSeries[$baseYear])) {
            throw new InvalidArgumentException('El año base seleccionado no tiene datos de CPI disponibles.');
        }

        $latestCpiYear = (int) $context['latestCpiYear'];
        $latestCpiValue = (float) $context['latestCpiValue'];
        $baseCpiValue = (float) $cpiSeries[$baseYear];
        $factor = $latestCpiValue / $baseCpiValue;

        return array(
            'countryName' => $context['country']['name'],
            'baseYear' => $baseYear,
            'latestCpiYear' => $latestCpiYear,
            'originalPrice' => $price,
            'currentPrice' => $price * $factor,
            'accumulatedInflation' => ($factor - 1) * 100,
            'factor' => $factor,
        );
    }

    public function calculatePastPrice($countryCode, $price, $targetYear)
    {
        if ($price <= 0) {
            throw new InvalidArgumentException('El precio debe ser mayor que cero.');
        }

        $context = $this->getCountryContext($countryCode);
        $cpiSeries = $context['cpiSeries'];
        $latestCpiYear = (int) $context['latestCpiYear'];

        if (!isset($cpiSeries[$targetYear])) {
            throw new InvalidArgumentException('El año histórico seleccionado no tiene datos de CPI disponibles.');
        }

        if ($targetYear >= $latestCpiYear) {
            throw new InvalidArgumentException('El año histórico debe ser menor al último año CPI disponible.');
        }

        $latestCpiValue = (float) $context['latestCpiValue'];
        $targetCpiValue = (float) $cpiSeries[$targetYear];
        $factor = $targetCpiValue / $latestCpiValue;

        return array(
            'countryName' => $context['country']['name'],
            'referenceYear' => $latestCpiYear,
            'targetYear' => $targetYear,
            'originalPrice' => $price,
            'pastPrice' => $price * $factor,
            'accumulatedVariation' => ($factor - 1) * 100,
            'factor' => $factor,
        );
    }

    public function calculateFutureAccumulatedInflation($countryCode, $targetYear)
    {
        $context = $this->getCountryContext($countryCode);
        $currentYear = (int) $context['currentYear'];

        if ($targetYear <= $currentYear) {
            throw new InvalidArgumentException('El año objetivo debe ser mayor al año actual.');
        }

        $projection = $this->buildProjection($context['inflationSeries'], $currentYear + 1, $targetYear);

        return array(
            'countryName' => $context['country']['name'],
            'baseYear' => $currentYear,
            'targetYear' => $targetYear,
            'accumulatedInflation' => $projection['accumulatedInflation'],
            'projectedAverageRate' => $projection['averageRate'],
            'projectedRates' => $projection['rates'],
            'modelStartYear' => $context['modelStartYear'],
            'modelEndYear' => $context['modelEndYear'],
        );
    }

    public function calculateFutureYearInflation($countryCode, $targetYear)
    {
        $context = $this->getCountryContext($countryCode);
        $currentYear = (int) $context['currentYear'];

        if ($targetYear <= $currentYear) {
            throw new InvalidArgumentException('El año objetivo debe ser mayor al año actual.');
        }

        return array(
            'countryName' => $context['country']['name'],
            'targetYear' => $targetYear,
            'projectedRate' => $this->projectRate($context['inflationSeries'], $targetYear),
            'averageRecentInflation' => (float) $context['averageRecentInflation'],
            'latestInflationYear' => (int) $context['latestInflationYear'],
            'modelStartYear' => $context['modelStartYear'],
            'modelEndYear' => $context['modelEndYear'],
        );
    }

    public function calculateFuturePrice($countryCode, $price, $targetYear)
    {
        if ($price <= 0) {
            throw new InvalidArgumentException('El precio debe ser mayor que cero.');
        }

        $context = $this->getCountryContext($countryCode);
        $currentYear = (int) $context['currentYear'];

        if ($targetYear <= $currentYear) {
            throw new InvalidArgumentException('El año objetivo debe ser mayor al año actual.');
        }

        $projection = $this->buildProjection($context['inflationSeries'], $currentYear + 1, $targetYear);

        return array(
            'countryName' => $context['country']['name'],
            'baseYear' => $currentYear,
            'targetYear' => $targetYear,
            'originalPrice' => $price,
            'futurePrice' => $price * $projection['factor'],
            'accumulatedInflation' => $projection['accumulatedInflation'],
            'projectedAverageRate' => $projection['averageRate'],
            'modelStartYear' => $context['modelStartYear'],
            'modelEndYear' => $context['modelEndYear'],
        );
    }

    private function findCountry($countryCode)
    {
        foreach ($this->getCountries() as $country) {
            if ($country['id'] === $countryCode) {
                return $country;
            }
        }

        throw new InvalidArgumentException('El país seleccionado no es válido.');
    }

    private function buildProjection(array $inflationSeries, $fromYear, $toYear)
    {
        if ($toYear < $fromYear) {
            throw new InvalidArgumentException('El rango de proyección no es válido.');
        }

        $rates = array();
        $factor = 1.0;

        for ($year = $fromYear; $year <= $toYear; $year++) {
            $rate = $this->projectRate($inflationSeries, $year);
            $rates[$year] = $rate;
            $factor *= 1 + ($rate / 100);
        }

        return array(
            'rates' => $rates,
            'factor' => $factor,
            'accumulatedInflation' => ($factor - 1) * 100,
            'averageRate' => array_sum($rates) / count($rates),
        );
    }

    private function projectRate(array $inflationSeries, $targetYear)
    {
        if (isset($inflationSeries[$targetYear])) {
            return (float) $inflationSeries[$targetYear];
        }

        $recentInflationSeries = array_slice(
            $inflationSeries,
            -min(self::HISTORY_WINDOW, count($inflationSeries)),
            null,
            true
        );

        $averageRate = array_sum($recentInflationSeries) / count($recentInflationSeries);

        if (count($recentInflationSeries) < 2) {
            return $averageRate;
        }

        list($slope, $intercept) = $this->linearRegression($recentInflationSeries);
        $trendRate = ($slope * $targetYear) + $intercept;
        $blendedRate = ($trendRate + $averageRate) / 2;

        return max(-50.0, min(300.0, $blendedRate));
    }

    private function linearRegression(array $series)
    {
        $count = count($series);
        $sumX = 0.0;
        $sumY = 0.0;
        $sumXX = 0.0;
        $sumXY = 0.0;

        foreach ($series as $year => $value) {
            $x = (float) $year;
            $y = (float) $value;
            $sumX += $x;
            $sumY += $y;
            $sumXX += $x * $x;
            $sumXY += $x * $y;
        }

        $denominator = ($count * $sumXX) - ($sumX * $sumX);

        if (abs($denominator) < 0.000001) {
            return array(0.0, $sumY / $count);
        }

        $slope = (($count * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $count;

        return array($slope, $intercept);
    }

    private function getFirstKey(array $series)
    {
        reset($series);
        return key($series);
    }

    private function getLastKey(array $series)
    {
        end($series);
        return key($series);
    }
}
