param(
    [string]$OutputDir = (Join-Path $PSScriptRoot '..\data')
)

$ErrorActionPreference = 'Stop'

function Get-WorldBankPayload {
    param(
        [string]$Url
    )

    return Invoke-RestMethod -Uri $Url
}

function Build-SeriesMap {
    param(
        [array]$Rows,
        [hashtable]$AllowedCountries
    )

    $seriesMap = @{}

    foreach ($row in $Rows) {
        $countryCode = [string]$row.countryiso3code

        if (-not $AllowedCountries.ContainsKey($countryCode)) {
            continue
        }

        if ($null -eq $row.value) {
            continue
        }

        if (-not $seriesMap.ContainsKey($countryCode)) {
            $seriesMap[$countryCode] = [ordered]@{}
        }

        $seriesMap[$countryCode][[string]$row.date] = [double]$row.value
    }

    return $seriesMap
}

New-Item -ItemType Directory -Force $OutputDir | Out-Null

$countriesPayload = Get-WorldBankPayload 'https://api.worldbank.org/v2/country?format=json&per_page=400'
$cpiPayload = Get-WorldBankPayload 'https://api.worldbank.org/v2/country/all/indicator/FP.CPI.TOTL?format=json&per_page=20000'
$inflationPayload = Get-WorldBankPayload 'https://api.worldbank.org/v2/country/all/indicator/FP.CPI.TOTL.ZG?format=json&per_page=20000'

$countries = @()
$allowedCountries = @{}

foreach ($country in $countriesPayload[1]) {
    if ($country.region.id -eq 'NA') {
        continue
    }

    $entry = [ordered]@{
        id       = [string]$country.id
        iso2Code = [string]$country.iso2Code
        name     = [string]$country.name
        region   = [string]$country.region.value.Trim()
    }

    $countries += $entry
    $allowedCountries[[string]$country.id] = $true
}

$countries = $countries | Sort-Object name
$cpiSeries = Build-SeriesMap -Rows $cpiPayload[1] -AllowedCountries $allowedCountries
$inflationSeries = Build-SeriesMap -Rows $inflationPayload[1] -AllowedCountries $allowedCountries

$metadata = [ordered]@{
    generatedAt               = (Get-Date).ToUniversalTime().ToString('o')
    sourceLastUpdated         = [string]$cpiPayload[0].lastupdated
    countryCount              = $countries.Count
    cpiSeriesCountryCount     = $cpiSeries.Count
    inflationSeriesCountryCount = $inflationSeries.Count
}

($countries | ConvertTo-Json -Depth 6 -Compress) | Set-Content -Path (Join-Path $OutputDir 'countries.json') -Encoding UTF8
($cpiSeries | ConvertTo-Json -Depth 100 -Compress) | Set-Content -Path (Join-Path $OutputDir 'cpi.json') -Encoding UTF8
($inflationSeries | ConvertTo-Json -Depth 100 -Compress) | Set-Content -Path (Join-Path $OutputDir 'inflation.json') -Encoding UTF8
($metadata | ConvertTo-Json -Depth 6 -Compress) | Set-Content -Path (Join-Path $OutputDir 'metadata.json') -Encoding UTF8

Write-Output "Datos actualizados en $OutputDir"
