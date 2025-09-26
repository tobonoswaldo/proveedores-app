<?php
/**
 * includes/datetime_es.php
 * Helper para saludo, fecha y hora en español (México).
 *
 * Uso:
 *   require_once __DIR__ . '/datetime_es.php';
 *   es_init_time('America/Mexico_City', 'es_MX');
 *   $info = es_hoy(); // ['saludo' => ..., 'fecha' => ..., 'hora' => ...]
 */

if (!function_exists('es_init_time')) {
  function es_init_time(string $tz = 'America/Mexico_City', string $locale = 'es_MX'): bool {
    if (function_exists('date_default_timezone_set')) {
      @date_default_timezone_set($tz);
    }
    if (!class_exists('IntlDateFormatter')) {
      @setlocale(LC_TIME, $locale . '.UTF-8', $locale, 'es_ES.UTF-8', 'es_ES', 'es');
    }
    return class_exists('IntlDateFormatter');
  }
}

if (!function_exists('es_format_datetime')) {
  function es_format_datetime(
    ?DateTimeInterface $dt = null,
    string $datePattern = "EEEE, d 'de' MMMM 'de' yyyy",
    string $timePattern = "HH:mm",
    string $tz = 'America/Mexico_City',
    string $locale = 'es_MX'
  ): array {
    $dt = $dt ?? new DateTime('now');
    if (class_exists('IntlDateFormatter')) {
      $fmtFecha = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE, $tz, IntlDateFormatter::GREGORIAN, $datePattern);
      $fmtHora  = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::SHORT, $tz, IntlDateFormatter::GREGORIAN, $timePattern);
      $fecha = $fmtFecha->format($dt);
      $hora  = $fmtHora->format($dt);
    } else {
      $timestamp = ($dt instanceof DateTimeInterface) ? $dt->getTimestamp() : time();
      $fecha = strftime('%A, %e de %B de %Y', $timestamp);
      $hora  = date('H:i', $timestamp);
    }
    return ['fecha' => $fecha, 'hora' => $hora];
  }
}

if (!function_exists('es_saludo')) {
  function es_saludo(?DateTimeInterface $dt = null): string {
    $dt = $dt ?? new DateTime('now');
    $h = (int)$dt->format('G');
    if ($h < 12) return 'Buenos días';
    if ($h < 19) return 'Buenas tardes';
    return 'Buenas noches';
  }
}

if (!function_exists('es_hoy')) {
  function es_hoy(
    string $tz = 'America/Mexico_City',
    string $locale = 'es_MX',
    string $datePattern = "EEEE, d 'de' MMMM 'de' yyyy",
    string $timePattern = "HH:mm"
  ): array {
    es_init_time($tz, $locale);
    $dt = new DateTime('now', new DateTimeZone($tz));
    $fmt = es_format_datetime($dt, $datePattern, $timePattern, $tz, $locale);
    return ['saludo' => es_saludo($dt), 'fecha' => $fmt['fecha'], 'hora' => $fmt['hora']];
  }
}
es_init_time('America/Mexico_City', 'es_MX');