# Changelog

## v1.6.0 - 2026-03-28

- Nueva Herramienta 5 para calcular un precio histórico equivalente desde el último año CPI disponible hacia un año pasado, por ejemplo 1996.
- La app ahora permite hacer un cálculo de inflación reversiva sin recargar la página completa.
- Textos de ayuda y navegación actualizados para reflejar que la app ya incluye cinco herramientas.

## v1.5.0 - 2026-03-28

- Las herramientas de precio ahora muestran la unidad monetaria del país seleccionado, por ejemplo `GTQ / Q` para Guatemala.
- Los resultados de precio se formatean con código o símbolo de moneda para que el valor calculado sea más claro.
- El snapshot local ahora incluye `data/currencies.json` y el script de actualización también descarga metadatos de moneda por país.

## v1.4.2 - 2026-03-28

- Las calculadoras ahora envían el cálculo de forma asíncrona y muestran el resultado dentro de la misma herramienta.
- Se evita la recarga completa del sitio al calcular, manteniendo visible la tarjeta activa.
- Se agregó fallback con anclas por herramienta para que, si falla JavaScript, el formulario vuelva al bloque correcto.

## v1.4.1 - 2026-03-28

- Paleta del modo oscuro ajustada a negro con azul profundo para que el tema se sienta más frío y nocturno.
- El modo claro conserva la tipografía gótica en títulos y texto base para mantener identidad visual entre ambos temas.

## v1.4.0 - 2026-03-28

- Rediseño más fuerte del modo oscuro hacia una estética de catedral con vitrales, marcos angulares y fondo arquitectónico.
- Hero reconstruido con composición en varias líneas y un acento blackletter visible para que el tono gótico se note desde el primer bloque.
- Botones, tarjetas y paneles ajustados para sentirse menos SaaS y más heráldicos sin complicar el uso de la app.

## v1.3.0 - 2026-03-28

- Rediseño más marcado del modo oscuro hacia una estética gótica sobria, menos vino y más piedra/obsidiana.
- Tipografías nuevas para el modo oscuro con `Cinzel` y `Cormorant Garamond`.
- Cache-busting de assets por fecha de modificación para forzar recarga real de estilos y scripts.

## v1.2.0 - 2026-03-28

- Redirección visual del modo oscuro hacia una estética más gótica, con negro carbón, borgoña y dorado envejecido.
- Fondos, paneles, botones y resultados ajustados para que el modo oscuro se sienta más solemne sin perder claridad.

## v1.1.2 - 2026-03-28

- Cambio de texto en las cards: `Tema` pasa a `Herramienta`.

## v1.1.1 - 2026-03-28

- Animaciones y efectos visuales más notorios en botones, tarjetas y resultados.
- Entrada escalonada más visible para las secciones principales.
- Cache-busting en `styles.css` y `app.js` usando la versión actual para forzar recarga del navegador.

## v1.1.0 - 2026-03-28

- Tema oscuro por defecto con cambio manual a modo claro y persistencia en el navegador.
- Interfaz simplificada con guía rápida de 3 pasos y textos más directos para cada cálculo.
- Nuevas animaciones y efectos visuales suaves para dar más claridad sin recargar la pantalla.

## v1.0.1 - 2026-03-28

- Filtro del selector para mostrar solo países con series suficientes para calcular.
- Corrección de `metadata.json` para conservar la fecha de actualización del snapshot del Banco Mundial.

## v1.0.0 - 2026-03-28

- Lanzamiento inicial del sitio PHP.
- Calculadoras para inflación futura, inflación anual futura, precio actual y precio futuro.
- Integración con snapshot del Banco Mundial para compatibilidad con EasyPHP / PHP 5.4.
- Versionado centralizado en `VERSION`.
- Workflow de GitHub Actions para crear releases automáticos en `main`.
