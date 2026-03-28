# InflacionMundial

Aplicación en PHP para calcular inflación y precios equivalentes de cualquier país usando datos del Banco Mundial.

La app cubre cuatro escenarios:

1. Inflación futura acumulada desde el año actual hasta un año objetivo.
2. Precio actual equivalente de un producto a partir de un precio histórico y un año base.
3. Inflación estimada para un año futuro específico.
4. Precio futuro estimado de un producto a partir de su precio actual y un año final.

## Fuente de datos

- Banco Mundial, indicador `FP.CPI.TOTL`: índice de precios al consumidor.
- Banco Mundial, indicador `FP.CPI.TOTL.ZG`: inflación anual de precios al consumidor.

## Características

- Selector de países con snapshot local y series suficientes para calcular.
- Snapshot local versionado en `data/` para que funcione incluso en PHP 5.4.
- Interfaz responsiva en una sola página.
- `VERSION` como fuente única de verdad para la versión de la app.
- Release automático en GitHub al hacer push a `main`.

## Requisitos

- PHP 5.4 o superior.
- Extensión `json`.
- PowerShell con acceso saliente a internet para regenerar el snapshot del Banco Mundial cuando quieras actualizar datos.
- Git y GitHub CLI (`gh`) para publicar el proyecto.

## Estructura

```text
.
|-- .github/workflows/release.yml
|-- assets/styles.css
|-- data/
|-- index.php
|-- scripts/bump-version.ps1
|-- scripts/update-world-bank-data.ps1
|-- src/
|   |-- InflationService.php
|   |-- WorldBankClient.php
|   `-- bootstrap.php
`-- VERSION
```

## Ejecución local

Con EasyPHP basta con abrir la carpeta del proyecto desde el servidor local. Si prefieres el servidor embebido de PHP:

```powershell
"C:\Program Files (x86)\EasyPHP-Webserver-14.1b2\binaries\php\php.exe" -S 127.0.0.1:8080
```

Luego abre `http://127.0.0.1:8080`.

## Cómo calcula

- El cálculo de "precio actual" convierte el precio al último año con CPI disponible en el snapshot local del país.
- Las proyecciones futuras usan una mezcla entre:
  - Promedio de los últimos 10 años observados.
  - Tendencia lineal sobre esos mismos años.
- Las proyecciones futuras son estimaciones estadísticas, no pronósticos oficiales.

## Actualizar datos del Banco Mundial

```powershell
.\scripts\update-world-bank-data.ps1
```

Ese script descarga de nuevo:

- `data/countries.json`
- `data/cpi.json`
- `data/inflation.json`
- `data/metadata.json`

## Versionado

El proyecto usa SemVer con prefijo `vX.Y.Z`.

- `major`: cambios incompatibles o rediseños grandes.
- `minor`: nuevas funciones compatibles.
- `patch`: correcciones o ajustes menores.

La versión se guarda únicamente en `VERSION`. La app la lee directamente y el workflow de release usa el mismo valor.

### Subir de versión

```powershell
.\scripts\bump-version.ps1 patch
```

Opciones válidas: `major`, `minor`, `patch`.

## Flujo manual recomendado para cada commit

```powershell
.\scripts\bump-version.ps1 patch
git add .
git commit -m "fix: describe aquí el cambio"
git push origin main
```

Cada commit a `main` debe llevar una nueva versión en `VERSION`. Si repites una versión, el workflow fallará para evitar releases duplicados.

## Publicación en GitHub

Pasos manuales:

```powershell
git init -b main
git add .
git commit -m "feat: create initial PHP inflation calculator site"
gh repo create inflacionmundial --public --source=. --remote=origin --push
```

## Licencia

Este proyecto usa Apache License 2.0. Revisa el archivo `LICENSE`.
