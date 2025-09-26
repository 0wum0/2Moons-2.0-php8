<?php

declare(strict_types=1);

/**
 * 2Moons - Modernized Futuristic Space Strategy Game
 * 
 * Modernized version of the classic OGame clone with PHP 8.3, Twig templates,
 * PDO database access, and futuristic UI design.
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

// Set timezone if server timezone is not correct
// Uncomment and adjust as needed for your server
// date_default_timezone_set('Europe/Berlin');

/**
 * =============================================================================
 * TEMPLATE & THEME SETTINGS
 * =============================================================================
 */

// Default theme - now using modern futuristic theme
define('DEFAULT_THEME', 'futuristic');

// Protocol detection with modern security
define('HTTPS', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
define('PROTOCOL', HTTPS ? 'https://' : 'http://');

/**
 * =============================================================================
 * URL & PATH CONFIGURATION
 * =============================================================================
 */

if (PHP_SAPI === 'cli') {
    // CLI mode configuration
    $requestUrl = str_replace([dirname(dirname(__FILE__)), '\\'], ['', '/'], $_SERVER['PHP_SELF']);
    
    define('HTTP_BASE', str_replace(['\\', '//'], '/', dirname($_SERVER['SCRIPT_NAME']) . '/'));
    define('HTTP_ROOT', str_replace(basename($_SERVER['SCRIPT_FILENAME']), '', parse_url($requestUrl, PHP_URL_PATH)));
    define('HTTP_FILE', basename($_SERVER['SCRIPT_NAME']));
    define('HTTP_HOST', '127.0.0.1');
    define('HTTP_PATH', PROTOCOL . HTTP_HOST . HTTP_ROOT);
} else {
    // Web mode configuration
    define('HTTP_BASE', str_replace(['\\', '//'], '/', dirname($_SERVER['SCRIPT_NAME']) . '/'));
    define('HTTP_ROOT', str_replace(basename($_SERVER['SCRIPT_FILENAME']), '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
    define('HTTP_FILE', basename($_SERVER['SCRIPT_NAME']));
    define('HTTP_HOST', $_SERVER['HTTP_HOST']);
    define('HTTP_PATH', PROTOCOL . HTTP_HOST . HTTP_ROOT);
}

/**
 * =============================================================================
 * PATH CONFIGURATION
 * =============================================================================
 */

if (!defined('AJAX_CHAT_PATH')) {
    define('AJAX_CHAT_PATH', ROOT_PATH . 'chat/');
}

if (!defined('CACHE_PATH')) {
    define('CACHE_PATH', ROOT_PATH . 'cache/');
}

/**
 * =============================================================================
 * GAME ENGINE CONFIGURATION
 * =============================================================================
 */

// Combat engine type
define('COMBAT_ENGINE', 'xnova');

// Default language for error messages
define('DEFAULT_LANG', 'de');

// Wildcard domain support for multi-universe
define('UNIS_WILDCAST', false);

/**
 * =============================================================================
 * PLANET & MOON CONFIGURATION
 * =============================================================================
 */

// Fields added per lunar base level
define('FIELDS_BY_MOONBASIS_LEVEL', 3);

// Fields added per terraformer level
define('FIELDS_BY_TERRAFORMER', 5);

/**
 * =============================================================================
 * PLAYER ACTIVITY & TIMING
 * =============================================================================
 */

// Time in seconds for player to appear as inactive (i) on galaxy
define('INACTIVE', 604800); // 7 days

// Time in seconds for player to appear as long inactive (i I) on galaxy
define('INACTIVE_LONG', 2419200); // 28 days

// Time in seconds after which username can be changed again
define('USERNAME_CHANGETIME', 604800); // 7 days

// Maximum user session lifetime in seconds
define('SESSION_LIFETIME', 43200); // 12 hours

/**
 * =============================================================================
 * FLEET & COMBAT CONFIGURATION
 * =============================================================================
 */

// Fee factor for canceling shipyard queue (60% refund)
define('FACTOR_CANCEL_SHIPYARD', 0.6);

// Minimum fleet flight time in seconds
define('MIN_FLEET_TIME', 5);

// Deuterium cost for phalanx scan
define('PHALANX_DEUTERIUM', 5000);

// Maximum combat rounds
define('MAX_ATTACK_ROUNDS', 6);

// Fleet log retention time in seconds
define('FLEETLOG_AGE', 86400); // 24 hours

/**
 * =============================================================================
 * UI & PAGINATION SETTINGS
 * =============================================================================
 */

// Maximum results in search page (-1 = disable)
define('SEARCH_LIMIT', 25);

// Messages per page in message list
define('MESSAGES_PER_PAGE', 10);

// Banned users per page in ban list
define('BANNED_USERS_PER_PAGE', 25);

/**
 * =============================================================================
 * SECURITY & FEATURES
 * =============================================================================
 */

// IP block comparison level (1=AAA, 2=AAA.BBB, 3=AAA.BBB.CCC)
define('COMPARE_IP_BLOCKS', 2);

// Enable simulator link on spy reports
define('ENABLE_SIMULATOR_LINK', true);

// Enable multi-alert on fleet sending
define('ENABLE_MULTIALERT', true);

// UTF-8 support for non-English characters
define('UTF8_SUPPORT', true);

/**
 * =============================================================================
 * SPY & INTELLIGENCE CONFIGURATION
 * =============================================================================
 */

// Spy difficulty factor for intelligence missions
// Higher values make spying more difficult
define('SPY_DIFFENCE_FACTOR', 1);

// Spy view factor for different information levels
// Fleet = base spies, Defense = +1, Buildings = +3, Technology = +5
define('SPY_VIEW_FACTOR', 1);

/**
 * =============================================================================
 * BASH PROTECTION SYSTEM
 * =============================================================================
 */

// Enable bash protection system
define('BASH_ON', false);

// Number of attacks before bash protection kicks in
define('BASH_COUNT', 6);

// Bash protection duration in seconds
define('BASH_TIME', 86400); // 24 hours

// Bash rule during wars (0 = normal, 1 = disabled during wars)
define('BASH_WAR', 0);

/**
 * =============================================================================
 * SYSTEM ROOT CONFIGURATION
 * =============================================================================
 */

// Root universe ID
define('ROOT_UNI', 1);

// Root user ID
define('ROOT_USER', 1);

/**
 * =============================================================================
 * AUTHORIZATION LEVELS
 * =============================================================================
 */

// Administrator level
define('AUTH_ADM', 3);

// Operator level
define('AUTH_OPS', 2);

// Moderator level
define('AUTH_MOD', 1);

// User level
define('AUTH_USR', 0);

/**
 * =============================================================================
 * GAME MODULE IDENTIFIERS
 * =============================================================================
 */

// Total number of modules
define('MODULE_AMOUNT', 43);

// Core game modules
define('MODULE_ALLIANCE', 0);           // Alliance/Cooperation system
define('MODULE_BANLIST', 21);           // Banned users list
define('MODULE_BANNER', 37);            // Banner system
define('MODULE_BATTLEHALL', 12);        // Battle hall
define('MODULE_BUDDYLIST', 6);          // Buddy list
define('MODULE_BUILDING', 2);           // Building system
define('MODULE_CHAT', 7);               // Global chat
define('MODULE_DMEXTRAS', 8);           // Dark matter extras
define('MODULE_FLEET_EVENTS', 10);      // Fleet events
define('MODULE_FLEET_TABLE', 9);        // Fleet table
define('MODULE_FLEET_TRADER', 38);      // Fleet trader
define('MODULE_GALAXY', 11);            // Galaxy view
define('MODULE_IMPERIUM', 15);          // Imperium overview
define('MODULE_INFORMATION', 14);       // Information center
define('MODULE_MESSAGES', 16);          // Message system
define('MODULE_MISSILEATTACK', 40);     // Missile attacks
define('MODULE_NOTICE', 17);            // Notice system
define('MODULE_OFFICIER', 18);          // Officer system
define('MODULE_PHALANX', 19);           // Phalanx scanner
define('MODULE_PLAYERCARD', 20);        // Player card
define('MODULE_RECORDS', 22);           // Records
define('MODULE_RESEARCH', 3);           // Research system
define('MODULE_RESSOURCE_LIST', 23);    // Resource list
define('MODULE_SEARCH', 26);            // Search system
define('MODULE_SHIPYARD_FLEET', 4);     // Shipyard - fleet
define('MODULE_SHIPYARD_DEFENSIVE', 5); // Shipyard - defensive
define('MODULE_SHORTCUTS', 41);         // Shortcuts
define('MODULE_SIMULATOR', 39);         // Combat simulator
define('MODULE_STATISTICS', 25);        // Statistics
define('MODULE_SUPPORT', 27);           // Support system
define('MODULE_TECHTREE', 28);          // Technology tree
define('MODULE_TRADER', 13);            // Resource trader

// Mission modules
define('MODULE_MISSION_ATTACK', 1);     // Attack missions
define('MODULE_MISSION_ACS', 42);       // ACS missions
define('MODULE_MISSION_COLONY', 35);    // Colony missions
define('MODULE_MISSION_DARKMATTER', 31); // Dark matter missions
define('MODULE_MISSION_DESTROY', 29);   // Destroy missions
define('MODULE_MISSION_EXPEDITION', 30); // Expedition missions
define('MODULE_MISSION_HOLD', 33);      // Hold missions
define('MODULE_MISSION_RECYCLE', 32);   // Recycle missions
define('MODULE_MISSION_SPY', 24);       // Spy missions
define('MODULE_MISSION_STATION', 36);   // Station missions
define('MODULE_MISSION_TRANSPORT', 34); // Transport missions

/**
 * =============================================================================
 * FLEET STATE CONSTANTS
 * =============================================================================
 */

// Fleet is flying outward to destination
define('FLEET_OUTWARD', 0);

// Fleet is returning to origin
define('FLEET_RETURN', 1);

// Fleet is holding position
define('FLEET_HOLD', 2);

/**
 * =============================================================================
 * ELEMENT TYPE FLAGS
 * =============================================================================
 */

// Building elements (ID 0-99)
define('ELEMENT_BUILD', 1);

// Technology elements (ID 101-199)
define('ELEMENT_TECH', 2);

// Fleet elements (ID 201-399)
define('ELEMENT_FLEET', 4);

// Defensive elements (ID 401-599)
define('ELEMENT_DEFENSIVE', 8);

// Officer elements (ID 601-699)
define('ELEMENT_OFFICIER', 16);

// Bonus elements (ID 701-799)
define('ELEMENT_BONUS', 32);

// Race elements (ID 801-899)
define('ELEMENT_RACE', 64);

// Planet resource elements (ID 901-949)
define('ELEMENT_PLANET_RESOURCE', 128);

// User resource elements (ID 951-999)
define('ELEMENT_USER_RESOURCE', 256);

/**
 * =============================================================================
 * ELEMENT BEHAVIOR FLAGS
 * =============================================================================
 */

// Production elements
define('ELEMENT_PRODUCTION', 65536);

// Storage elements
define('ELEMENT_STORAGE', 131072);

// One per planet elements
define('ELEMENT_ONEPERPLANET', 262144);

// Bonus elements
define('ELEMENT_BOUNS', 524288);

// Buildable on planets
define('ELEMENT_BUILD_ON_PLANET', 1048576);

// Buildable on moons
define('ELEMENT_BUILD_ON_MOONS', 2097152);

// Resource on terraformer
define('ELEMENT_RESOURCE_ON_TF', 4194304);

// Resource on fleet
define('ELEMENT_RESOURCE_ON_FLEET', 8388608);

// Resource on steal
define('ELEMENT_RESOURCE_ON_STEAL', 16777216);
