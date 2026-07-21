<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Internationalisation helpers
// ---------------------------------------------------------------------------

/**
 * Load a lang file for the given language code, with fallbacks:
 * exact match → base language (e.g. 'en' from 'en-GB') → 'en'.
 */
function _lang_load_file(string $language): array
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $language) ?? 'en';
    $base = preg_replace('/[^a-zA-Z0-9]/', '', explode('-', $safe)[0]) ?? 'en';

    foreach (array_unique([$safe, strtolower($safe), $base, strtolower($base), 'en']) as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $path = PUREBLOG_BASE_PATH . '/lang/' . $candidate . '.php';
        if (is_file($path)) {
            $data = require $path;
            if (is_array($data)) {
                return $data;
            }
        }
    }

    return [];
}

/**
 * Override the language used by t() — must be called before the first t() call.
 * Used by the setup wizard so the page can render in the selected language
 * before a config file exists.
 */
function lang_init(string $language): void
{
    global $_pureblog_lang_code;
    $_pureblog_lang_code = $language;
}

/**
 * Return all available languages as [code => nativeName], sorted by name.
 * Scans lang/*.php and reads the top-level 'name' key from each file.
 */
function lang_available(): array
{
    $langs = [];
    $files = glob(PUREBLOG_BASE_PATH . '/lang/*.php') ?: [];

    foreach ($files as $file) {
        $code = basename($file, '.php');
        $data = require $file;
        if (is_array($data) && isset($data['name']) && is_string($data['name'])) {
            $langs[$code] = $data['name'];
        }
    }

    asort($langs);
    return $langs;
}

/**
 * Return the loaded lang strings, lazily initialising from config on first call.
 * Respects lang_init() override if set (used during setup).
 * @internal
 */
function _lang_strings(): array
{
    static $strings = null;

    if ($strings === null) {
        global $_pureblog_lang_code;
        if (isset($_pureblog_lang_code) && $_pureblog_lang_code !== '') {
            $strings = _lang_load_file($_pureblog_lang_code);
        } else {
            $config  = load_config();
            $strings = _lang_load_file((string) ($config['language'] ?? 'en'));
        }
    }

    return $strings;
}

/**
 * Translate a dot-notation key, with optional {placeholder} replacements.
 * Returns the key itself if no translation is found, so missing strings
 * degrade gracefully.
 *
 * Example: t('admin.login.heading')
 * Example: t('admin.dashboard.stat_this_year', ['year' => 2026])
 */
function t(string $key, array $replacements = []): string
{
    $strings = _lang_strings();
    $parts   = explode('.', $key);
    $value   = $strings;

    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $key;
        }
        $value = $value[$part];
    }

    if (!is_string($value)) {
        return $key;
    }

    foreach ($replacements as $placeholder => $replacement) {
        $value = str_replace('{' . $placeholder . '}', (string) $replacement, $value);
    }

    return $value;
}

/**
 * Substitute translated month/day names into a pre-formatted date string.
 * Replacement order (full before short) prevents partial matches.
 * @internal
 */
function _lang_translate_date(string $formatted): string
{
    $strings     = _lang_strings();
    $months      = $strings['date']['months']       ?? [];
    $monthsShort = $strings['date']['months_short'] ?? [];
    $days        = $strings['date']['days']         ?? [];
    $daysShort   = $strings['date']['days_short']   ?? [];

    if (!$months && !$days) {
        return $formatted;
    }

    $enMonths      = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $enMonthsShort = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $enDays        = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $enDaysShort   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    // Build a single strtr map so that longer keys (e.g. "Monday") are matched
    // before shorter ones (e.g. "Mon"), preventing partial-match corruption such
    // as "Montag" → "Motag" when both full and short day arrays are present.
    $map = [];
    if (count($months) === 12) {
        $map += array_combine($enMonths, $months);
    }
    if (count($monthsShort) === 12) {
        $map += array_combine($enMonthsShort, $monthsShort);
    }
    if (count($days) === 7) {
        $map += array_combine($enDays, $days);
    }
    if (count($daysShort) === 7) {
        $map += array_combine($enDaysShort, $daysShort);
    }

    return $map ? strtr($formatted, $map) : $formatted;
}

// ---------------------------------------------------------------------------
// Hook helpers
// ---------------------------------------------------------------------------

function call_hook(string $name, array $args = []): void
{
    load_hooks();
    if (function_exists($name)) {
        $name(...$args);
    }
}

function apply_filter(string $name, mixed $value, mixed ...$args): mixed
{
    load_hooks();
    if (function_exists($name)) {
        return $name($value, ...$args);
    }
    return $value;
}

/**
 * @return list<array{id:string,label:string,class:string,confirm:string,icon:string}>
 */
function get_admin_action_buttons(): array
{
    load_hooks();
    if (!function_exists('admin_action_buttons')) {
        return [];
    }

    $raw = admin_action_buttons();
    if (!is_array($raw)) {
        return [];
    }

    $buttons = [];
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = strtolower(trim((string) ($item['id'] ?? '')));
        $id = preg_replace('/[^a-z0-9_-]/', '', $id) ?? '';
        $label = trim((string) ($item['label'] ?? ''));
        if ($id === '' || $label === '') {
            continue;
        }

        $class = trim((string) ($item['class'] ?? ''));
        $class = preg_replace('/[^a-zA-Z0-9_ -]/', '', $class) ?? '';

        $buttons[] = [
            'id' => $id,
            'label' => $label,
            'class' => $class,
            'confirm' => trim((string) ($item['confirm'] ?? '')),
            'icon' => trim((string) ($item['icon'] ?? '')),
        ];
    }

    return $buttons;
}

/**
 * @return array{ok:bool,message:string}
 */
function run_admin_action(string $actionId): array
{
    load_hooks();
    if (!function_exists('on_admin_action')) {
        return ['ok' => false, 'message' => 'No admin action handler is configured.'];
    }

    try {
        $result = on_admin_action($actionId);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Action failed: ' . $e->getMessage()];
    }

    if (is_array($result)) {
        $ok = (bool) ($result['ok'] ?? false);
        $message = trim((string) ($result['message'] ?? ''));
        if ($message === '') {
            $message = $ok ? 'Action completed.' : 'Action failed.';
        }
        return ['ok' => $ok, 'message' => $message];
    }

    if (is_bool($result)) {
        return [
            'ok' => $result,
            'message' => $result ? 'Action completed.' : 'Action failed.',
        ];
    }

    if (is_string($result) && trim($result) !== '') {
        return ['ok' => true, 'message' => trim($result)];
    }

    return ['ok' => true, 'message' => 'Action completed.'];
}
