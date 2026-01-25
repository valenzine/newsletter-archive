<?php

/**
 * Internationalization (i18n) System
 * 
 * Simple translation system for the archive application.
 * Loads language files from /lang/ directory.
 */

// Current locale (can be overridden by .env)
$current_locale = $_ENV['LOCALE'] ?? 'en';

// Extract just the language code (en from en_US.UTF-8)
if (strpos($current_locale, '_') !== false) {
    $current_locale = substr($current_locale, 0, strpos($current_locale, '_'));
}

// Translation storage
$translations = [];

/**
 * Load a language file
 * 
 * @param string $locale Language code (e.g., 'en', 'es')
 * @return bool True if loaded successfully
 */
function load_language(string $locale): bool {
    global $translations;
    
    $lang_file = __DIR__ . '/../lang/' . $locale . '.php';
    
    if (!file_exists($lang_file)) {
        // Fallback to English
        $lang_file = __DIR__ . '/../lang/en.php';
    }
    
    if (file_exists($lang_file)) {
        $translations = require $lang_file;
        return true;
    }
    
    return false;
}

/**
 * Get a translated string
 * 
 * @param string $key Translation key (dot notation supported, e.g., 'nav.back')
 * @param array $params Parameters for string interpolation
 * @return string Translated string or key if not found
 */
function __($key, array $params = []): string {
    global $translations;
    
    // Support dot notation for nested keys
    $value = $translations;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !isset($value[$segment])) {
            // Key not found, return the key itself
            return $key;
        }
        $value = $value[$segment];
    }
    
    if (!is_string($value)) {
        return $key;
    }
    
    // Replace parameters: {name} -> value
    if (!empty($params)) {
        foreach ($params as $param_key => $param_value) {
            $value = str_replace('{' . $param_key . '}', $param_value, $value);
        }
    }
    
    return $value;
}

/**
 * Echo a translated string (shorthand for echo __())
 * 
 * @param string $key Translation key
 * @param array $params Parameters for string interpolation
 */
function _e($key, array $params = []): void {
    echo __($key, $params);
}

/**
 * Get all translations (useful for passing to JavaScript)
 * 
 * @param string|null $prefix Only return keys starting with this prefix
 * @return array Translation array
 */
function get_translations(?string $prefix = null): array {
    global $translations;
    
    if ($prefix === null) {
        return $translations;
    }
    
    // Extract nested array by prefix
    $value = $translations;
    foreach (explode('.', $prefix) as $segment) {
        if (!is_array($value) || !isset($value[$segment])) {
            return [];
        }
        $value = $value[$segment];
    }
    
    return is_array($value) ? $value : [];
}

/**
 * Get current locale
 * 
 * @return string Current locale code
 */
function get_locale(): string {
    global $current_locale;
    return $current_locale;
}

// Auto-load the language on include
load_language($current_locale);
