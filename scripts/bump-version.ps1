param(
    [ValidateSet('major', 'minor', 'patch')]
    [string]$Part = 'patch'
)

$versionFile = Join-Path $PSScriptRoot '..\VERSION'

if (-not (Test-Path $versionFile)) {
    throw 'No se encontró el archivo VERSION.'
}

$rawVersion = (Get-Content $versionFile -Raw).Trim()

if ($rawVersion -notmatch '^v(\d+)\.(\d+)\.(\d+)$') {
    throw 'El archivo VERSION no tiene el formato vX.Y.Z.'
}

$major = [int]$Matches[1]
$minor = [int]$Matches[2]
$patch = [int]$Matches[3]

switch ($Part) {
    'major' {
        $major++
        $minor = 0
        $patch = 0
    }
    'minor' {
        $minor++
        $patch = 0
    }
    'patch' {
        $patch++
    }
}

$nextVersion = "v$major.$minor.$patch"
Set-Content -Path $versionFile -Value $nextVersion -NoNewline
Write-Output $nextVersion
