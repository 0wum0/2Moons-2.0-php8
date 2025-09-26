<?php

declare(strict_types=1);

/**
 * 2Moons - Modernized General Functions
 * 
 * Modernized general utility functions with PHP 8.3 compatibility,
 * improved security, and enhanced functionality for the futuristic 2Moons game.
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @author Modernized by AI Assistant
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2024 Modernized Version
 * @license MIT
 * @version 2.0.0
 * @link https://github.com/jkroepke/2Moons
 */

/**
 * =============================================================================
 * GAME FACTORS & BONUSES
 * =============================================================================
 */

/**
 * Get game factors for calculations
 *
 * @param array $user User data
 * @param string $type Factor type ('basic', 'production', 'research', etc.)
 * @param int|null $time Timestamp (default: current time)
 * @return array Game factors
 */
function getFactors(array $user, string $type = 'basic', ?int $time = null): array
{
    global $PLANET, $resource, $pricelist, $reslist;
    
    if ($time === null) {
        $time = TIMESTAMP;
    }
    
    $bonusList = BuildFunctions::getBonusList();
    $factor = ArrayUtil::combineArrayWithSingleElement($bonusList, 0);
    
    foreach ($reslist['bonus'] as $elementID) {
        $bonus = $pricelist[$elementID]['bonus'];
        
        $elementLevel = 0;
        if (isset($PLANET[$resource[$elementID]])) {
            $elementLevel = (int) $PLANET[$resource[$elementID]];
        } elseif (isset($user[$resource[$elementID]])) {
            $elementLevel = (int) $user[$resource[$elementID]];
        } else {
            continue;
        }
        
        if (in_array($elementID, $reslist['dmfunc'], true)) {
            if (DMExtra($elementLevel, $time, false, true)) {
                continue;
            }
            
            foreach ($bonusList as $bonusKey) {
                $factor[$bonusKey] += $bonus[$bonusKey][0];
            }
        } else {
            foreach ($bonusList as $bonusKey) {
                $factor[$bonusKey] += $elementLevel * $bonus[$bonusKey][0];
            }
        }
    }
    
    return $factor;
}

/**
 * =============================================================================
 * PLANET MANAGEMENT
 * =============================================================================
 */

/**
 * Get user's planets
 *
 * @param array $user User data
 * @return array Planets list
 */
function getPlanets(array $user): array
{
    if (isset($user['PLANETS'])) {
        return $user['PLANETS'];
    }

    $order = $user['planet_sort_order'] == 1 ? 'DESC' : 'ASC';
    $sql = 'SELECT id, name, galaxy, system, planet, planet_type, image, b_building, b_building_id
            FROM %%PLANETS%% WHERE id_owner = :userId AND destruyed = :destruyed ORDER BY ';

    switch ($user['planet_sort']) {
        case 0:
            $sql .= 'id ' . $order;
            break;
        case 1:
            $sql .= 'galaxy, system, planet, planet_type ' . $order;
            break;
        case 2:
            $sql .= 'name ' . $order;
            break;
        default:
            $sql .= 'id ' . $order;
            break;
    }

    $planetsResult = Database::get()->select($sql, [
        ':userId' => $user['id'],
        ':destruyed' => 0
    ]);
    
    $planetsList = [];
    foreach ($planetsResult as $planetRow) {
        $planetsList[$planetRow['id']] = $planetRow;
    }

    return $planetsList;
}

/**
 * Calculate maximum planet fields
 *
 * @param array $planet Planet data
 * @return int Maximum fields
 */
function calculateMaxPlanetFields(array $planet): int
{
    global $resource;
    
    return (int) $planet['field_max'] + 
           ((int) $planet[$resource[33]] * FIELDS_BY_TERRAFORMER) + 
           ((int) $planet[$resource[41]] * FIELDS_BY_MOONBASIS_LEVEL);
}

/**
 * =============================================================================
 * TIME & DATE FUNCTIONS
 * =============================================================================
 */

/**
 * Get timezone selector options
 *
 * @return array Timezone options grouped by continent
 */
function getTimezoneSelector(): array
{
    $timezones = [];
    $timezoneIdentifiers = DateTimeZone::listIdentifiers();

    foreach ($timezoneIdentifiers as $value) {
        if (preg_match('/^(America|Antarctica|Arctic|Asia|Atlantic|Europe|Indian|Pacific)\//', $value)) {
            $ex = explode('/', $value);
            $city = isset($ex[2]) ? $ex[1] . ' - ' . $ex[2] : $ex[1];
            $timezones[$ex[0]][$value] = str_replace('_', ' ', $city);
        }
    }
    
    return $timezones;
}

/**
 * Format date with locale support
 *
 * @param string $format Date format
 * @param int $time Timestamp
 * @param array|null $language Language data
 * @return string Formatted date
 */
function localeDateFormat(string $format, int $time, ?array $language = null): string
{
    if ($language === null) {
        global $LNG;
        $language = $LNG;
    }
    
    $weekDay = (int) date('w', $time);
    $months = (int) date('n', $time) - 1;
    
    $format = str_replace(['D', 'M'], ['$D$', '$M$'], $format);
    $format = str_replace('$D$', addcslashes($language['week_day'][$weekDay], 'A..z'), $format);
    $format = str_replace('$M$', addcslashes($language['months'][$months], 'A..z'), $format);
    
    return $format;
}

/**
 * Format date with timezone support
 *
 * @param string $format Date format
 * @param int|null $time Timestamp
 * @param string|null $toTimeZone Target timezone
 * @param array|null $language Language data
 * @return string Formatted date
 */
function formatDate(string $format, ?int $time = null, ?string $toTimeZone = null, ?array $language = null): string
{
    if ($time === null) {
        $time = TIMESTAMP;
    } else {
        $time = (int) floor($time);
    }

    if ($toTimeZone !== null) {
        $date = new DateTime();
        $date->setTimestamp($time);
        
        $time -= $date->getOffset();
        try {
            $date->setTimezone(new DateTimeZone($toTimeZone));
        } catch (Exception $e) {
            // Invalid timezone, continue with original time
        }
        $time += $date->getOffset();
    }
    
    $format = localeDateFormat($format, $time, $language);
    return date($format, $time);
}

/**
 * Format time in a pretty way
 *
 * @param int $seconds Time in seconds
 * @return string Formatted time
 */
function prettyTime(int $seconds): string
{
    global $LNG;
    
    $day = (int) floor($seconds / 86400);
    $hour = (int) floor(($seconds / 3600) % 24);
    $minute = (int) floor(($seconds / 60) % 60);
    $second = (int) floor($seconds % 60);

    $time = '';
    if ($day > 0) {
        $time .= sprintf('%d%s ', $day, $LNG['short_day']);
    }

    return $time . sprintf('%02d%s %02d%s %02d%s',
        $hour, $LNG['short_hour'],
        $minute, $LNG['short_minute'],
        $second, $LNG['short_second']
    );
}

/**
 * Format flight time
 *
 * @param int $seconds Time in seconds
 * @return string Formatted flight time
 */
function prettyFlyTime(int $seconds): string
{
    $hour = (int) floor($seconds / 3600);
    $minute = (int) floor(($seconds / 60) % 60);
    $second = (int) floor($seconds % 60);

    return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

/**
 * =============================================================================
 * NUMBER FORMATTING
 * =============================================================================
 */

/**
 * Format number with decimal places
 *
 * @param float|int $number Number to format
 * @param int $decimals Decimal places
 * @return string Formatted number
 */
function prettyNumber(float|int $number, int $decimals = 0): string
{
    return number_format(floatToString($number, $decimals), $decimals, ',', '.');
}

/**
 * Convert float to string with precision
 *
 * @param float|int $number Number to convert
 * @param int $precision Decimal precision
 * @param bool $output Whether to use dot as decimal separator
 * @return string Converted string
 */
function floatToString(float|int $number, int $precision = 0, bool $output = false): string
{
    return $output ? str_replace(',', '.', sprintf('%.' . $precision . 'f', $number)) : sprintf('%.' . $precision . 'f', $number);
}

/**
 * Format large numbers with units
 *
 * @param float|int $number Number to format
 * @param int|null $decimals Decimal places
 * @return string Formatted number with unit
 */
function shortlyNumber(float|int $number, ?int $decimals = null): string
{
    $negate = $number < 0 ? -1 : 1;
    $number = abs($number);
    $units = ['', 'K', 'M', 'B', 'T', 'Q', 'Q+', 'S', 'S+', 'O', 'N'];
    $key = 0;
    
    if ($number >= 1000000) {
        ++$key;
        while ($number >= 1000000) {
            ++$key;
            $number = $number / 1000000;
        }
    } elseif ($number >= 1000) {
        ++$key;
        $number = $number / 1000;
    }
    
    $decimals = !is_numeric($decimals) ? 
        ((int) (((int) $number != $number) && $key != 0 && $number != 0 && $number < 100)) : 
        $decimals;
        
    return prettyNumber($negate * $number, $decimals) . '&nbsp;' . $units[$key];
}

/**
 * =============================================================================
 * USER MANAGEMENT
 * =============================================================================
 */

/**
 * Get user by ID
 *
 * @param int $userId User ID
 * @param string|array $getInfo Fields to retrieve
 * @return array|null User data
 */
function getUserById(int $userId, string|array $getInfo = '*'): ?array
{
    if (is_array($getInfo)) {
        $getOnSelect = implode(', ', $getInfo);
    } else {
        $getOnSelect = $getInfo;
    }

    $sql = 'SELECT ' . $getOnSelect . ' FROM %%USERS%% WHERE id = :userId';
    return Database::get()->selectSingle($sql, [':userId' => $userId]);
}

/**
 * Check noob protection
 *
 * @param array $ownerPlayer Owner player data
 * @param array $targetPlayer Target player data
 * @param array $player Current player data
 * @return array Protection status
 */
function checkNoobProtection(array $ownerPlayer, array $targetPlayer, array $player): array
{
    $config = Config::get();
    
    if ($config->noobprotection == 0 || 
        $config->noobprotectiontime == 0 || 
        $config->noobprotectionmulti == 0 || 
        $player['banaday'] > TIMESTAMP || 
        $player['onlinetime'] < TIMESTAMP - INACTIVE) {
        return ['NoobPlayer' => false, 'StrongPlayer' => false];
    }
    
    return [
        'NoobPlayer' => (
            ($targetPlayer['total_points'] <= $config->noobprotectiontime) &&
            ($ownerPlayer['total_points'] > $targetPlayer['total_points'] * $config->noobprotectionmulti)
        ),
        'StrongPlayer' => (
            ($ownerPlayer['total_points'] < $config->noobprotectiontime) &&
            ($ownerPlayer['total_points'] * $config->noobprotectionmulti < $targetPlayer['total_points'])
        ),
    ];
}

/**
 * Check if user is in vacation mode
 *
 * @param array $user User data
 * @return bool Vacation mode status
 */
function isVacationMode(array $user): bool
{
    return $user['urlaubs_modus'] == 1;
}

/**
 * =============================================================================
 * VALIDATION & SECURITY
 * =============================================================================
 */

/**
 * Validate email address
 *
 * @param string $address Email address
 * @return bool Validation result
 */
function validateAddress(string $address): bool
{
    if (function_exists('filter_var')) {
        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // Fallback regex for older PHP versions
    return preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $address) === 1;
}

/**
 * Generate random string
 *
 * @param int $length String length
 * @return string Random string
 */
function getRandomString(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * =============================================================================
 * MODULE MANAGEMENT
 * =============================================================================
 */

/**
 * Check if module is available
 *
 * @param int $id Module ID
 * @return bool Module availability
 */
function isModuleAvailable(int $id): bool
{
    global $USER;
    $modules = explode(';', Config::get()->moduls);

    if (!isset($modules[$id])) {
        $modules[$id] = 1;
    }

    return $modules[$id] == 1 || (isset($USER['authlevel']) && $USER['authlevel'] > AUTH_USR);
}

/**
 * Check user permissions
 *
 * @param string $side Permission side
 * @return bool Permission status
 */
function allowedTo(string $side): bool
{
    global $USER;
    return ($USER['authlevel'] == AUTH_ADM || (isset($USER['rights']) && $USER['rights'][$side] == 1));
}

/**
 * =============================================================================
 * DARK MATTER FUNCTIONS
 * =============================================================================
 */

/**
 * Check if dark matter extra is active
 *
 * @param int $extra Extra timestamp
 * @param int $time Current time
 * @return bool Active status
 */
function isActiveDMExtra(int $extra, int $time): bool
{
    return $time - $extra <= 0;
}

/**
 * Dark matter extra helper
 *
 * @param int $extra Extra timestamp
 * @param int $time Current time
 * @param mixed $true Value if active
 * @param mixed $false Value if inactive
 * @return mixed Result value
 */
function DMExtra(int $extra, int $time, mixed $true, mixed $false): mixed
{
    return isActiveDMExtra($extra, $time) ? $true : $false;
}

/**
 * =============================================================================
 * UI HELPERS
 * =============================================================================
 */

/**
 * Convert line breaks to HTML
 *
 * @param string $text Text to convert
 * @return string Converted text
 */
function makeBr(string $text): string
{
    $br = "<br>\n";
    return nl2br($text, false);
}

/**
 * Get start address link for fleet
 *
 * @param array $fleetRow Fleet data
 * @param string $fleetType Fleet type class
 * @return string HTML link
 */
function getStartAddressLink(array $fleetRow, string $fleetType = ''): string
{
    return '<a href="game.php?page=galaxy&amp;galaxy=' . $fleetRow['fleet_start_galaxy'] . 
           '&amp;system=' . $fleetRow['fleet_start_system'] . '" class="' . $fleetType . '">[' . 
           $fleetRow['fleet_start_galaxy'] . ':' . $fleetRow['fleet_start_system'] . ':' . 
           $fleetRow['fleet_start_planet'] . ']</a>';
}

/**
 * Get target address link for fleet
 *
 * @param array $fleetRow Fleet data
 * @param string $fleetType Fleet type class
 * @return string HTML link
 */
function getTargetAddressLink(array $fleetRow, string $fleetType = ''): string
{
    return '<a href="game.php?page=galaxy&amp;galaxy=' . $fleetRow['fleet_end_galaxy'] . 
           '&amp;system=' . $fleetRow['fleet_end_system'] . '" class="' . $fleetType . '">[' . 
           $fleetRow['fleet_end_galaxy'] . ':' . $fleetRow['fleet_end_system'] . ':' . 
           $fleetRow['fleet_end_planet'] . ']</a>';
}

/**
 * Build planet address link
 *
 * @param array $currentPlanet Planet data
 * @return string HTML link
 */
function buildPlanetAddressLink(array $currentPlanet): string
{
    return '<a href="game.php?page=galaxy&amp;galaxy=' . $currentPlanet['galaxy'] . 
           '&amp;system=' . $currentPlanet['system'] . '">[' . 
           $currentPlanet['galaxy'] . ':' . $currentPlanet['system'] . ':' . 
           $currentPlanet['planet'] . ']</a>';
}

/**
 * =============================================================================
 * CACHE MANAGEMENT
 * =============================================================================
 */

/**
 * Clear all caches
 */
function clearCache(): void
{
    $dirs = ['cache/', 'cache/templates/'];
    
    foreach ($dirs as $dir) {
        $files = array_diff(scandir($dir), ['..', '.', '.htaccess']);
        foreach ($files as $file) {
            if (is_dir(ROOT_PATH . $dir . $file)) {
                continue;
            }
            unlink(ROOT_PATH . $dir . $file);
        }
    }

    $template = new template();
    $template->clearAllCache();

    require_once 'includes/classes/Cronjob.class.php';
    Cronjob::reCalculateCronjobs();

    $sql = 'UPDATE %%PLANETS%% SET eco_hash = :ecoHash;';
    Database::get()->update($sql, [':ecoHash' => '']);
    
    clearstatcache();

    $config = Config::get();
    $version = explode('.', $config->VERSION);
    $config->VERSION = $version[0] . '.' . $version[1] . '.git';
    $config->save();
}

/**
 * =============================================================================
 * ERROR HANDLING
 * =============================================================================
 */

/**
 * Get friendly error severity name
 *
 * @param int $severity Error severity
 * @return string Friendly severity name
 */
function friendlySeverity(int $severity): string
{
    $names = [];
    $consts = array_flip(array_slice(get_defined_constants(true)['Core'], 0, 15, true));

    foreach ($consts as $code => $name) {
        if ($severity & $code) {
            $names[] = $name;
        }
    }

    return implode(' | ', $names);
}

/**
 * Exception handler
 *
 * @param Throwable $exception Exception to handle
 */
function exceptionHandler(Throwable $exception): void
{
    if (!headers_sent()) {
        if (!class_exists('HTTP', false)) {
            require_once 'includes/classes/HTTP.class.php';
        }
        HTTP::sendHeader('HTTP/1.1 503 Service Unavailable');
    }

    $errno = method_exists($exception, 'getSeverity') ? 
        friendlySeverity($exception->getSeverity()) : 
        E_USER_ERROR;
    
    $errorType = [
        'E_ERROR' => 'ERROR',
        'E_WARNING' => 'WARNING',
        'E_PARSE' => 'PARSING ERROR',
        'E_NOTICE' => 'NOTICE',
        'E_CORE_ERROR' => 'CORE ERROR',
        'E_CORE_WARNING' => 'CORE WARNING',
        'E_COMPILE_ERROR' => 'COMPILE ERROR',
        'E_COMPILE_WARNING' => 'COMPILE WARNING',
        'E_USER_ERROR' => 'USER ERROR',
        'E_USER_WARNING' => 'USER WARNING',
        'E_USER_NOTICE' => 'USER NOTICE',
        'E_STRICT' => 'STRICT NOTICE',
        'E_RECOVERABLE_ERROR' => 'RECOVERABLE ERROR',
        'E_DEPRECATED' => 'DEPRECATED'
    ];
    
    $version = file_exists(ROOT_PATH . 'install/VERSION') ? 
        file_get_contents(ROOT_PATH . 'install/VERSION') . ' (FILE)' : 
        'UNKNOWN';
    
    $gameName = '-';
    if (MODE !== 'INSTALL') {
        try {
            $config = Config::get();
            $gameName = $config->game_name;
            $version = $config->VERSION;
        } catch (ErrorException $e) {
            // Ignore config errors during error handling
        }
    }
    
    $dir = MODE == 'INSTALL' ? '..' : '.';
    
    // Modern error page with futuristic design
    ob_start();
    echo '<!DOCTYPE html>
<html lang="en" class="no-js futuristic-error">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($gameName) . ' - System Error</title>
    <meta name="generator" content="2Moons ' . htmlspecialchars($version) . '">
    <style>
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #16213e 100%);
            color: #00ffff;
            font-family: "Courier New", monospace;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            background: rgba(0, 255, 255, 0.1);
            border: 2px solid #00ffff;
            border-radius: 10px;
            padding: 30px;
            max-width: 800px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.3);
            animation: glow 2s ease-in-out infinite alternate;
        }
        @keyframes glow {
            from { box-shadow: 0 0 30px rgba(0, 255, 255, 0.3); }
            to { box-shadow: 0 0 50px rgba(0, 255, 255, 0.6); }
        }
        h1 { color: #ff4444; text-align: center; margin-bottom: 20px; }
        .error-details { margin: 15px 0; }
        .error-details strong { color: #ffff00; }
        .error-details pre { 
            background: rgba(0, 0, 0, 0.5); 
            padding: 15px; 
            border-radius: 5px; 
            overflow-x: auto;
            border-left: 3px solid #ff4444;
        }
        .timestamp { color: #888; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>⚠️ SYSTEM ERROR DETECTED ⚠️</h1>
        <div class="error-details">
            <strong>Message:</strong> ' . htmlspecialchars($exception->getMessage()) . '<br>
            <strong>File:</strong> ' . htmlspecialchars($exception->getFile()) . '<br>
            <strong>Line:</strong> ' . $exception->getLine() . '<br>
            <strong>URL:</strong> ' . htmlspecialchars(PROTOCOL . HTTP_HOST . $_SERVER['REQUEST_URI']) . '<br>
            <strong>PHP Version:</strong> ' . PHP_VERSION . '<br>
            <strong>PHP API:</strong> ' . php_sapi_name() . '<br>
            <strong>2Moons Version:</strong> ' . htmlspecialchars($version) . '<br>
            <strong>Timestamp:</strong> <span class="timestamp">' . date('Y-m-d H:i:s', TIMESTAMP) . '</span><br>
            <strong>Debug Backtrace:</strong><br>
            <pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>
        </div>
    </div>
</body>
</html>';

    echo str_replace([ROOT_PATH, substr(ROOT_PATH, 0, 15)], ['/', 'FILEPATH '], ob_get_clean());
    
    // Log error to file
    $errorText = date('[d-M-Y H:i:s]', TIMESTAMP) . ': "' . strip_tags($exception->getMessage()) . "\"\r\n";
    $errorText .= 'File: ' . $exception->getFile() . ' | Line: ' . $exception->getLine() . "\r\n";
    $errorText .= 'URL: ' . PROTOCOL . HTTP_HOST . $_SERVER['REQUEST_URI'] . ' | Version: ' . $version . "\r\n";
    $errorText .= "Stack trace:\r\n";
    $errorText .= str_replace(ROOT_PATH, '/', htmlspecialchars(str_replace('\\', '/', $exception->getTraceAsString()))) . "\r\n";
    
    if (is_writable('includes/error.log')) {
        file_put_contents('includes/error.log', $errorText, FILE_APPEND);
    }
}

/**
 * Error handler
 *
 * @param int $errno Error number
 * @param string $errstr Error string
 * @param string $errfile Error file
 * @param int $errline Error line
 * @return bool Whether error was handled
 * @throws ErrorException
 */
function errorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
{
    if (!($errno & error_reporting())) {
        return false;
    }
    
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

/**
 * =============================================================================
 * UTILITY FUNCTIONS
 * =============================================================================
 */

/**
 * Display message and exit
 *
 * @param string $message Message to display
 * @param string $destination Redirect destination
 * @param string $time Display time
 * @param bool $topnav Show top navigation
 */
function message(string $message, string $destination = '', string $time = '3', bool $topnav = false): void
{
    require_once 'includes/classes/class.template.php';
    $template = new template();
    $template->message($message, $destination, $time, !$topnav);
    exit;
}

/**
 * Clear GIF response
 */
function clearGif(): void
{
    header('Cache-Control: no-cache');
    header('Content-type: image/gif');
    header('Content-length: 43');
    header('Expires: 0');
    echo "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B";
    exit;
}

/**
 * Array replace recursive fallback for PHP < 5.3
 */
if (!function_exists('array_replace_recursive')) {
    function array_replace_recursive(): array
    {
        if (!function_exists('recurse')) {
            function recurse(array $array, array $array1): array
            {
                foreach ($array1 as $key => $value) {
                    if (!isset($array[$key]) || (isset($array[$key]) && !is_array($array[$key]))) {
                        $array[$key] = [];
                    }

                    if (is_array($value)) {
                        $value = recurse($array[$key], $value);
                    }
                    $array[$key] = $value;
                }
                return $array;
            }
        }

        $args = func_get_args();
        $array = $args[0];
        if (!is_array($array)) {
            return $array;
        }
        
        $count = count($args);
        for ($i = 1; $i < $count; ++$i) {
            if (is_array($args[$i])) {
                $array = recurse($array, $args[$i]);
            }
        }
        return $array;
    }
}