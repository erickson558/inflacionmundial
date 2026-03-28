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

function Get-RestCountriesPayload {
    param(
        [string]$Url
    )

    $response = Invoke-WebRequest -Uri $Url

    if ($null -eq $response.RawContentStream) {
        return $response.Content | ConvertFrom-Json
    }

    $reader = New-Object System.IO.StreamReader($response.RawContentStream, [System.Text.Encoding]::UTF8)

    try {
        return ($reader.ReadToEnd()) | ConvertFrom-Json
    } finally {
        $reader.Dispose()
    }
}

function Write-Utf8NoBomFile {
    param(
        [string]$Path,
        [string]$Content
    )

    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $encoding)
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

function Build-CurrencyMap {
    param(
        [array]$Rows,
        [hashtable]$AllowedCountries
    )

    $currencyMap = @{}

    foreach ($row in $Rows) {
        $countryCode = [string]$row.cca3

        if (-not $AllowedCountries.ContainsKey($countryCode)) {
            continue
        }

        if ($null -eq $row.currencies) {
            continue
        }

        $properties = @($row.currencies.PSObject.Properties)

        if ($properties.Count -lt 1) {
            continue
        }

        $currencyProperty = $properties[0]
        $currencyValue = $currencyProperty.Value

        $currencyMap[$countryCode] = [ordered]@{
            code   = [string]$currencyProperty.Name
            name   = if ($null -ne $currencyValue.name) { [string]$currencyValue.name } else { '' }
            symbol = if ($null -ne $currencyValue.symbol) { [string]$currencyValue.symbol } else { '' }
        }
    }

    return $currencyMap
}

New-Item -ItemType Directory -Force $OutputDir | Out-Null

$countriesPayload = Get-WorldBankPayload 'https://api.worldbank.org/v2/country?format=json&per_page=400'
$cpiPayload = Get-WorldBankPayload 'https://api.worldbank.org/v2/country/all/indicator/FP.CPI.TOTL?format=json&per_page=20000'
$inflationPayload = Get-WorldBankPayload 'https://api.worldbank.org/v2/country/all/indicator/FP.CPI.TOTL.ZG?format=json&per_page=20000'
$currencyPayload = Get-RestCountriesPayload 'https://restcountries.com/v3.1/all?fields=cca3,currencies'

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
$currencyMap = Build-CurrencyMap -Rows $currencyPayload -AllowedCountries $allowedCountries

$metadata = [ordered]@{
    generatedAt                 = (Get-Date).ToUniversalTime().ToString('o')
    sourceLastUpdated           = [string]$cpiPayload[0].lastupdated
    currencySource              = 'https://restcountries.com/v3.1/all?fields=cca3,currencies'
    countryCount                = $countries.Count
    cpiSeriesCountryCount       = $cpiSeries.Count
    inflationSeriesCountryCount = $inflationSeries.Count
    currencyCountryCount        = $currencyMap.Count
}

Write-Utf8NoBomFile -Path (Join-Path $OutputDir 'countries.json') -Content ($countries | ConvertTo-Json -Depth 6 -Compress)
Write-Utf8NoBomFile -Path (Join-Path $OutputDir 'cpi.json') -Content ($cpiSeries | ConvertTo-Json -Depth 100 -Compress)
Write-Utf8NoBomFile -Path (Join-Path $OutputDir 'inflation.json') -Content ($inflationSeries | ConvertTo-Json -Depth 100 -Compress)
Write-Utf8NoBomFile -Path (Join-Path $OutputDir 'currencies.json') -Content ($currencyMap | ConvertTo-Json -Depth 10 -Compress)
Write-Utf8NoBomFile -Path (Join-Path $OutputDir 'metadata.json') -Content ($metadata | ConvertTo-Json -Depth 6 -Compress)

Write-Output "Datos actualizados en $OutputDir"
