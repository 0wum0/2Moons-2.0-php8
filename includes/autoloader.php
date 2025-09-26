<?php

declare(strict_types=1);

/**
 * 2Moons - Modernized PSR-4 Autoloader
 * 
 * Modernized autoloader with PSR-4 compliance, class mapping,
 * and performance optimizations for the futuristic 2Moons game.
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

// Prevent direct access
if (!defined('ROOT_PATH')) {
    die('Direct access not allowed');
}

/**
 * Modernized Autoloader Class
 */
class Autoloader
{
    /**
     * Namespace prefixes and their base directories
     */
    private static array $prefixes = [];

    /**
     * Class map for faster loading
     */
    private static array $classMap = [];

    /**
     * Fallback directories
     */
    private static array $fallbackDirs = [];

    /**
     * Whether the autoloader has been registered
     */
    private static bool $registered = false;

    /**
     * Cache for resolved class paths
     */
    private static array $pathCache = [];

    /**
     * Register the autoloader
     *
     * @param bool $prepend Whether to prepend the autoloader
     * @return bool Registration status
     */
    public static function register(bool $prepend = false): bool
    {
        if (self::$registered) {
            return true;
        }

        // Register PSR-4 autoloader
        $result = spl_autoload_register([self::class, 'loadClass'], true, $prepend);
        
        if ($result) {
            self::$registered = true;
            self::initializeDefaultPrefixes();
        }

        return $result;
    }

    /**
     * Unregister the autoloader
     *
     * @return bool Unregistration status
     */
    public static function unregister(): bool
    {
        if (!self::$registered) {
            return true;
        }

        $result = spl_autoload_unregister([self::class, 'loadClass']);
        
        if ($result) {
            self::$registered = false;
        }

        return $result;
    }

    /**
     * Add a namespace prefix and base directory
     *
     * @param string $prefix Namespace prefix
     * @param string $baseDir Base directory
     * @param bool $prepend Whether to prepend the directory
     */
    public static function addNamespace(string $prefix, string $baseDir, bool $prepend = false): void
    {
        // Normalize namespace prefix
        $prefix = trim($prefix, '\\') . '\\';
        
        // Normalize base directory
        $baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
        
        // Initialize the prefix array if it doesn't exist
        if (!isset(self::$prefixes[$prefix])) {
            self::$prefixes[$prefix] = [];
        }
        
        // Add the base directory
        if ($prepend) {
            array_unshift(self::$prefixes[$prefix], $baseDir);
        } else {
            array_push(self::$prefixes[$prefix], $baseDir);
        }
    }

    /**
     * Add multiple namespace prefixes
     *
     * @param array $prefixes Array of prefix => baseDir mappings
     */
    public static function addNamespaces(array $prefixes): void
    {
        foreach ($prefixes as $prefix => $baseDir) {
            self::addNamespace($prefix, $baseDir);
        }
    }

    /**
     * Add a fallback directory
     *
     * @param string $dir Directory path
     * @param bool $prepend Whether to prepend the directory
     */
    public static function addFallbackDir(string $dir, bool $prepend = false): void
    {
        $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
        
        if ($prepend) {
            array_unshift(self::$fallbackDirs, $dir);
        } else {
            array_push(self::$fallbackDirs, $dir);
        }
    }

    /**
     * Add a class map entry
     *
     * @param string $class Class name
     * @param string $file File path
     */
    public static function addClassMap(string $class, string $file): void
    {
        self::$classMap[$class] = $file;
    }

    /**
     * Add multiple class map entries
     *
     * @param array $classMap Array of class => file mappings
     */
    public static function addClassMaps(array $classMap): void
    {
        self::$classMap = array_merge(self::$classMap, $classMap);
    }

    /**
     * Load a class
     *
     * @param string $class Class name
     * @return bool Loading status
     */
    public static function loadClass(string $class): bool
    {
        // Check class map first (fastest)
        if (isset(self::$classMap[$class])) {
            $file = self::$classMap[$class];
            if (self::requireFile($file)) {
                return true;
            }
        }

        // Check path cache
        if (isset(self::$pathCache[$class])) {
            $file = self::$pathCache[$class];
            if (self::requireFile($file)) {
                return true;
            }
            // Remove from cache if file doesn't exist
            unset(self::$pathCache[$class]);
        }

        // Find the class file
        $file = self::findClassFile($class);
        
        if ($file !== null) {
            self::$pathCache[$class] = $file;
            return self::requireFile($file);
        }

        return false;
    }

    /**
     * Find the file for a class
     *
     * @param string $class Class name
     * @return string|null File path or null if not found
     */
    private static function findClassFile(string $class): ?string
    {
        // Get the relative class name
        $relativeClass = $class;
        
        // Work through the namespace prefixes
        foreach (self::$prefixes as $prefix => $dirs) {
            // Check if the class uses the namespace prefix
            if (str_starts_with($class, $prefix)) {
                // Remove the prefix to get the relative class name
                $relativeClass = substr($class, strlen($prefix));
                
                // Try each directory registered to the namespace prefix
                foreach ($dirs as $dir) {
                    $file = $dir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
                    
                    if (self::fileExists($file)) {
                        return $file;
                    }
                }
            }
        }
        
        // Try fallback directories
        foreach (self::$fallbackDirs as $dir) {
            $file = $dir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
            
            if (self::fileExists($file)) {
                return $file;
            }
        }
        
        return null;
    }

    /**
     * Check if a file exists (with caching)
     *
     * @param string $file File path
     * @return bool File existence
     */
    private static function fileExists(string $file): bool
    {
        static $fileCache = [];
        
        if (!isset($fileCache[$file])) {
            $fileCache[$file] = file_exists($file);
        }
        
        return $fileCache[$file];
    }

    /**
     * Require a file
     *
     * @param string $file File path
     * @return bool Success status
     */
    private static function requireFile(string $file): bool
    {
        if (self::fileExists($file)) {
            require_once $file;
            return true;
        }
        
        return false;
    }

    /**
     * Initialize default namespace prefixes
     */
    private static function initializeDefaultPrefixes(): void
    {
        // 2Moons core namespaces
        self::addNamespace('TwoMoons', ROOT_PATH . 'src');
        self::addNamespace('TwoMoons\\App', ROOT_PATH . 'src/App');
        self::addNamespace('TwoMoons\\Core', ROOT_PATH . 'src/Core');
        self::addNamespace('TwoMoons\\Game', ROOT_PATH . 'src/Game');
        self::addNamespace('TwoMoons\\Admin', ROOT_PATH . 'src/Admin');
        self::addNamespace('TwoMoons\\Api', ROOT_PATH . 'src/Api');
        self::addNamespace('TwoMoons\\Cronjob', ROOT_PATH . 'src/Cronjob');
        self::addNamespace('TwoMoons\\Template', ROOT_PATH . 'src/Template');
        self::addNamespace('TwoMoons\\Database', ROOT_PATH . 'src/Database');
        self::addNamespace('TwoMoons\\Security', ROOT_PATH . 'src/Security');
        self::addNamespace('TwoMoons\\Utils', ROOT_PATH . 'src/Utils');

        // Legacy class support
        self::addFallbackDir(ROOT_PATH . 'includes/classes');
        self::addFallbackDir(ROOT_PATH . 'includes/libs');
        self::addFallbackDir(ROOT_PATH . 'includes/pages');

        // Initialize legacy class map
        self::initializeLegacyClassMap();
    }

    /**
     * Initialize legacy class map for backward compatibility
     */
    private static function initializeLegacyClassMap(): void
    {
        $legacyClasses = [
            'Database' => ROOT_PATH . 'includes/classes/Database.class.php',
            'Session' => ROOT_PATH . 'includes/classes/Session.class.php',
            'Language' => ROOT_PATH . 'includes/classes/Language.class.php',
            'Config' => ROOT_PATH . 'includes/classes/Config.class.php',
            'HTTP' => ROOT_PATH . 'includes/classes/HTTP.class.php',
            'HTTPRequest' => ROOT_PATH . 'includes/classes/HTTPRequest.class.php',
            'Mail' => ROOT_PATH . 'includes/classes/Mail.class.php',
            'Universe' => ROOT_PATH . 'includes/classes/Universe.class.php',
            'PlayerUtil' => ROOT_PATH . 'includes/classes/PlayerUtil.class.php',
            'ArrayUtil' => ROOT_PATH . 'includes/classes/ArrayUtil.class.php',
            'BBCode' => ROOT_PATH . 'includes/classes/BBCode.class.php',
            'Cache' => ROOT_PATH . 'includes/classes/Cache.class.php',
            'Cronjob' => ROOT_PATH . 'includes/classes/Cronjob.class.php',
            'SQLDumper' => ROOT_PATH . 'includes/classes/SQLDumper.class.php',
        ];

        self::addClassMaps($legacyClasses);
    }

    /**
     * Get all registered prefixes
     *
     * @return array Registered prefixes
     */
    public static function getPrefixes(): array
    {
        return self::$prefixes;
    }

    /**
     * Get all registered class maps
     *
     * @return array Registered class maps
     */
    public static function getClassMaps(): array
    {
        return self::$classMap;
    }

    /**
     * Get all fallback directories
     *
     * @return array Fallback directories
     */
    public static function getFallbackDirs(): array
    {
        return self::$fallbackDirs;
    }

    /**
     * Clear the path cache
     */
    public static function clearPathCache(): void
    {
        self::$pathCache = [];
    }

    /**
     * Get path cache statistics
     *
     * @return array Cache statistics
     */
    public static function getPathCacheStats(): array
    {
        return [
            'cached_paths' => count(self::$pathCache),
            'cache_hits' => count(self::$pathCache),
            'registered_prefixes' => count(self::$prefixes),
            'class_maps' => count(self::$classMap),
            'fallback_dirs' => count(self::$fallbackDirs),
        ];
    }

    /**
     * Check if a class is loaded
     *
     * @param string $class Class name
     * @return bool Class loaded status
     */
    public static function isClassLoaded(string $class): bool
    {
        return class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false);
    }

    /**
     * Get all loaded classes
     *
     * @return array Loaded classes
     */
    public static function getLoadedClasses(): array
    {
        return get_declared_classes();
    }

    /**
     * Preload classes for better performance
     *
     * @param array $classes Array of class names to preload
     */
    public static function preloadClasses(array $classes): void
    {
        foreach ($classes as $class) {
            if (!self::isClassLoaded($class)) {
                self::loadClass($class);
            }
        }
    }
}

/**
 * Legacy autoloader function for backward compatibility
 *
 * @param string $class Class name
 * @return bool Loading status
 */
function legacyAutoload(string $class): bool
{
    // Convert class name to file path
    $file = ROOT_PATH . 'includes/classes/' . str_replace('_', '.', $class) . '.class.php';
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    return false;
}

/**
 * Initialize the autoloader
 */
function initializeAutoloader(): void
{
    // Register the modern autoloader
    Autoloader::register();
    
    // Register legacy autoloader for backward compatibility
    spl_autoload_register('legacyAutoload');
    
    // Preload essential classes
    $essentialClasses = [
        'Database',
        'Session',
        'Language',
        'Config',
        'HTTP',
    ];
    
    Autoloader::preloadClasses($essentialClasses);
}

/**
 * Get autoloader instance
 *
 * @return Autoloader Autoloader instance
 */
function getAutoloader(): Autoloader
{
    return Autoloader::class;
}

// Auto-initialize if not in CLI mode
if (PHP_SAPI !== 'cli') {
    initializeAutoloader();
}