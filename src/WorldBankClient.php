<?php

namespace InflacionMundial;

use InvalidArgumentException;
use RuntimeException;

final class WorldBankClient
{
    const CPI_INDICATOR = 'FP.CPI.TOTL';
    const INFLATION_INDICATOR = 'FP.CPI.TOTL.ZG';

    private $dataDir;
    private $loadedFiles = array();

    public function __construct($dataDir)
    {
        $this->dataDir = $dataDir;

        if (!is_dir($this->dataDir)) {
            throw new RuntimeException('No existe el directorio de datos local.');
        }
    }

    public function getCountries()
    {
        $countries = $this->readJsonFile('countries.json');
        $cpiSeries = $this->readJsonFile('cpi.json');
        $inflationSeries = $this->readJsonFile('inflation.json');
        $supportedCountries = array();

        foreach ($countries as $country) {
            $countryCode = isset($country['id']) ? $country['id'] : '';

            if (
                $countryCode !== '' &&
                isset($cpiSeries[$countryCode]) &&
                !empty($cpiSeries[$countryCode]) &&
                isset($inflationSeries[$countryCode]) &&
                !empty($inflationSeries[$countryCode])
            ) {
                $supportedCountries[] = $country;
            }
        }

        return $supportedCountries;
    }

    public function getIndicatorSeries($countryCode, $indicator)
    {
        $countryCode = strtoupper($countryCode);
        $allSeries = $this->readJsonFile($this->resolveIndicatorFile($indicator));

        if (!isset($allSeries[$countryCode]) || !is_array($allSeries[$countryCode])) {
            return array();
        }

        $normalizedSeries = array();

        foreach ($allSeries[$countryCode] as $year => $value) {
            $normalizedSeries[(int) $year] = (float) $value;
        }

        ksort($normalizedSeries);

        return $normalizedSeries;
    }

    private function resolveIndicatorFile($indicator)
    {
        if ($indicator === self::CPI_INDICATOR) {
            return 'cpi.json';
        }

        if ($indicator === self::INFLATION_INDICATOR) {
            return 'inflation.json';
        }

        throw new InvalidArgumentException('Indicador no soportado.');
    }

    private function readJsonFile($filename)
    {
        if (isset($this->loadedFiles[$filename])) {
            return $this->loadedFiles[$filename];
        }

        $path = $this->dataDir . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($path)) {
            throw new RuntimeException(
                'Falta el archivo de datos ' . $filename . '. Ejecuta scripts/update-world-bank-data.ps1 para regenerarlo.'
            );
        }

        $raw = file_get_contents($path);

        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException('No se pudo leer el archivo de datos ' . $filename . '.');
        }

        $decoded = json_decode($raw, true);

        if (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('El archivo de datos ' . $filename . ' no contiene JSON válido.');
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('El archivo de datos ' . $filename . ' no tiene el formato esperado.');
        }

        $this->loadedFiles[$filename] = $decoded;

        return $decoded;
    }
}
