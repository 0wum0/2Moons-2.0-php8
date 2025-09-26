<?php

declare(strict_types=1);

/**
 * 2Moons - Modernized Session Class
 * 
 * Modernized session management with PHP 8.3 compatibility,
 * enhanced security, CSRF protection, and advanced session handling.
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

class Session
{
    /**
     * Singleton instance
     */
    private static ?Session $instance = null;

    /**
     * Whether session settings have been initialized
     */
    private static bool $initialized = false;

    /**
     * Session data
     */
    private array $data = [];

    /**
     * Session ID
     */
    private string $sessionId = '';

    /**
     * User ID
     */
    private ?int $userId = null;

    /**
     * Session start time
     */
    private int $startTime = 0;

    /**
     * Last activity timestamp
     */
    private int $lastActivity = 0;

    /**
     * User IP address
     */
    private string $userIpAddress = '';

    /**
     * User agent
     */
    private string $userAgent = '';

    /**
     * CSRF token
     */
    private string $csrfToken = '';

    /**
     * Session fingerprint
     */
    private string $fingerprint = '';

    /**
     * Whether session is valid
     */
    private bool $isValid = false;

    /**
     * Session regeneration counter
     */
    private int $regenerationCounter = 0;

    /**
     * Maximum session lifetime
     */
    private int $maxLifetime = 0;

    /**
     * Session regeneration interval
     */
    private int $regenerationInterval = 300; // 5 minutes

    /**
     * Initialize session settings
     *
     * @return bool Success status
     */
    public static function init(): bool
    {
        if (self::$initialized) {
            return true;
        }

        // Set secure session parameters
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.auto_start', '0');
        ini_set('session.serialize_handler', 'php');
        ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '1000');
        ini_set('session.bug_compat_warn', '0');
        ini_set('session.bug_compat_42', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', HTTPS ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.save_path', CACHE_PATH . 'sessions');
        ini_set('upload_tmp_dir', CACHE_PATH . 'sessions');

        // Create sessions directory if it doesn't exist
        $sessionsDir = CACHE_PATH . 'sessions';
        if (!is_dir($sessionsDir)) {
            mkdir($sessionsDir, 0755, true);
        }

        // Set session cookie parameters
        $httpRoot = MODE === 'INSTALL' ? dirname(HTTP_ROOT) : HTTP_ROOT;
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => $httpRoot,
            'domain' => null,
            'secure' => HTTPS,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        session_cache_limiter('nocache');
        session_name('2Moons_Futuristic');

        self::$initialized = true;
        return true;
    }

    /**
     * Create a new session
     *
     * @return Session Session instance
     */
    public static function create(): Session
    {
        if (!self::existsActiveSession()) {
            self::$instance = new self();
            register_shutdown_function([self::$instance, 'save']);
            session_start();
        }

        return self::$instance;
    }

    /**
     * Load existing session
     *
     * @return Session Session instance
     */
    public static function load(): Session
    {
        if (!self::existsActiveSession()) {
            self::init();
            session_start();

            if (isset($_SESSION['session_obj'])) {
                self::$instance = unserialize($_SESSION['session_obj']);
                register_shutdown_function([self::$instance, 'save']);
            } else {
                self::create();
            }
        }

        return self::$instance;
    }

    /**
     * Check if active session exists
     *
     * @return bool Session existence
     */
    public static function existsActiveSession(): bool
    {
        return isset(self::$instance);
    }

    /**
     * Get singleton instance
     *
     * @return Session Session instance
     */
    public static function getInstance(): Session
    {
        if (self::$instance === null) {
            self::$instance = self::load();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        self::init();
        $this->startTime = time();
        $this->lastActivity = time();
        $this->sessionId = session_id();
        $this->userIpAddress = self::getClientIp();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $this->maxLifetime = SESSION_LIFETIME;
        $this->generateFingerprint();
        $this->generateCsrfToken();
    }

    /**
     * Magic method for setting data
     *
     * @param string $name Property name
     * @param mixed $value Property value
     */
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * Magic method for getting data
     *
     * @param string $name Property name
     * @return mixed Property value
     */
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Magic method for checking if property exists
     *
     * @param string $name Property name
     * @return bool Property existence
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Magic method for unsetting property
     *
     * @param string $name Property name
     */
    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    /**
     * Serialize session data
     *
     * @return array Serializable data
     */
    public function __sleep(): array
    {
        return ['data', 'userId', 'startTime', 'lastActivity', 'userIpAddress', 'userAgent', 'csrfToken', 'fingerprint', 'regenerationCounter'];
    }

    /**
     * Unserialize session data
     */
    public function __wakeup(): void
    {
        $this->sessionId = session_id();
        $this->validateSession();
    }

    /**
     * Save session data
     */
    public function save(): void
    {
        $sessionId = session_id();
        if (empty($sessionId)) {
            return;
        }

        // Don't save empty sessions
        if (empty($this->userId)) {
            $this->delete();
            return;
        }

        // Update last activity
        $this->lastActivity = time();

        // Check if session needs regeneration
        if ($this->shouldRegenerateSession()) {
            $this->regenerateSession();
        }

        // Save session to database
        $this->saveToDatabase();

        // Update user online time
        $this->updateUserOnlineTime();

        // Store session object
        $_SESSION['session_obj'] = serialize($this);

        session_write_close();
    }

    /**
     * Delete session
     */
    public function delete(): void
    {
        $sql = 'DELETE FROM %%SESSION%% WHERE sessionID = :sessionId';
        Database::get()->delete($sql, [':sessionId' => session_id()]);

        // Clear session data
        $this->data = [];
        $this->userId = null;
        $this->isValid = false;

        session_destroy();
    }

    /**
     * Validate session
     *
     * @return bool Validation result
     */
    public function isValidSession(): bool
    {
        if (!$this->isValid) {
            return false;
        }

        // Check IP address
        if (!$this->compareIpAddress($this->userIpAddress, self::getClientIp(), COMPARE_IP_BLOCKS)) {
            $this->isValid = false;
            return false;
        }

        // Check session lifetime
        if ($this->lastActivity < time() - $this->maxLifetime) {
            $this->isValid = false;
            return false;
        }

        // Check fingerprint
        if (!$this->validateFingerprint()) {
            $this->isValid = false;
            return false;
        }

        // Check if session exists in database
        $sql = 'SELECT COUNT(*) as record FROM %%SESSION%% WHERE sessionID = :sessionId';
        $sessionCount = Database::get()->selectSingle($sql, [':sessionId' => session_id()], 'record');

        if ($sessionCount == 0) {
            $this->isValid = false;
            return false;
        }

        return true;
    }

    /**
     * Select active planet
     */
    public function selectActivePlanet(): void
    {
        $httpData = HTTP::_GP('cp', 0);

        if (!empty($httpData)) {
            $sql = 'SELECT id FROM %%PLANETS%% WHERE id = :planetId AND id_owner = :userId';
            $planetId = Database::get()->selectSingle($sql, [
                ':userId' => $this->userId,
                ':planetId' => $httpData,
            ], 'id');

            if (!empty($planetId)) {
                $this->data['planetId'] = $planetId;
            }
        }
    }

    /**
     * Generate CSRF token
     *
     * @return string CSRF token
     */
    public function generateCsrfToken(): string
    {
        $this->csrfToken = bin2hex(random_bytes(32));
        return $this->csrfToken;
    }

    /**
     * Get CSRF token
     *
     * @return string CSRF token
     */
    public function getCsrfToken(): string
    {
        return $this->csrfToken;
    }

    /**
     * Validate CSRF token
     *
     * @param string $token Token to validate
     * @return bool Validation result
     */
    public function validateCsrfToken(string $token): bool
    {
        return hash_equals($this->csrfToken, $token);
    }

    /**
     * Regenerate session ID
     *
     * @return bool Success status
     */
    public function regenerateSession(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $this->sessionId = session_id();
            $this->regenerationCounter++;
            $this->generateCsrfToken();
            return true;
        }

        return false;
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    public static function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs (from proxies)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }

    /**
     * Get session data
     *
     * @return array Session data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set session data
     *
     * @param array $data Session data
     */
    public function setData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Get user ID
     *
     * @return int|null User ID
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Set user ID
     *
     * @param int $userId User ID
     */
    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
        $this->data['userId'] = $userId;
    }

    /**
     * Get session ID
     *
     * @return string Session ID
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Get session start time
     *
     * @return int Start time
     */
    public function getStartTime(): int
    {
        return $this->startTime;
    }

    /**
     * Get last activity time
     *
     * @return int Last activity time
     */
    public function getLastActivity(): int
    {
        return $this->lastActivity;
    }

    /**
     * Get user IP address
     *
     * @return string User IP address
     */
    public function getUserIpAddress(): string
    {
        return $this->userIpAddress;
    }

    /**
     * Get user agent
     *
     * @return string User agent
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Get session fingerprint
     *
     * @return string Session fingerprint
     */
    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * Check if session should be regenerated
     *
     * @return bool Regeneration needed
     */
    private function shouldRegenerateSession(): bool
    {
        return ($this->lastActivity + $this->regenerationInterval) < time();
    }

    /**
     * Generate session fingerprint
     */
    private function generateFingerprint(): void
    {
        $components = [
            $this->userIpAddress,
            $this->userAgent,
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
        ];

        $this->fingerprint = hash('sha256', implode('|', $components));
    }

    /**
     * Validate session fingerprint
     *
     * @return bool Validation result
     */
    private function validateFingerprint(): bool
    {
        $currentFingerprint = hash('sha256', implode('|', [
            $this->userIpAddress,
            $this->userAgent,
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
        ]));

        return hash_equals($this->fingerprint, $currentFingerprint);
    }

    /**
     * Save session to database
     */
    private function saveToDatabase(): void
    {
        $sql = 'REPLACE INTO %%SESSION%% SET
            sessionID = :sessionId,
            userID = :userId,
            lastonline = :lastActivity,
            userIP = :userAddress,
            userAgent = :userAgent,
            fingerprint = :fingerprint,
            csrfToken = :csrfToken,
            data = :data';

        Database::get()->replace($sql, [
            ':sessionId' => $this->sessionId,
            ':userId' => $this->userId,
            ':lastActivity' => $this->lastActivity,
            ':userAddress' => $this->userIpAddress,
            ':userAgent' => $this->userAgent,
            ':fingerprint' => $this->fingerprint,
            ':csrfToken' => $this->csrfToken,
            ':data' => json_encode($this->data)
        ]);
    }

    /**
     * Update user online time
     */
    private function updateUserOnlineTime(): void
    {
        $sql = 'UPDATE %%USERS%% SET
            onlinetime = :lastActivity,
            user_lastip = :userAddress,
            user_agent = :userAgent
            WHERE id = :userId';

        Database::get()->update($sql, [
            ':userAddress' => $this->userIpAddress,
            ':lastActivity' => $this->lastActivity,
            ':userAgent' => $this->userAgent,
            ':userId' => $this->userId
        ]);
    }

    /**
     * Compare IP addresses
     *
     * @param string $ip1 First IP address
     * @param string $ip2 Second IP address
     * @param int $blockCount Number of blocks to compare
     * @return bool Comparison result
     */
    private function compareIpAddress(string $ip1, string $ip2, int $blockCount): bool
    {
        if (str_contains($ip2, ':') && str_contains($ip1, ':')) {
            $s_ip = $this->shortIpv6($ip1, $blockCount);
            $u_ip = $this->shortIpv6($ip2, $blockCount);
        } else {
            $s_ip = implode('.', array_slice(explode('.', $ip1), 0, $blockCount));
            $u_ip = implode('.', array_slice(explode('.', $ip2), 0, $blockCount));
        }

        return $s_ip === $u_ip;
    }

    /**
     * Shorten IPv6 address
     *
     * @param string $ip IPv6 address
     * @param int $length Length to keep
     * @return string Shortened IPv6 address
     */
    private function shortIpv6(string $ip, int $length): string
    {
        if ($length < 1) {
            return '';
        }

        $blocks = substr_count($ip, ':') + 1;
        if ($blocks < 9) {
            $ip = str_replace('::', ':' . str_repeat('0000:', 9 - $blocks), $ip);
        }
        if ($ip[0] === ':') {
            $ip = '0000' . $ip;
        }
        if ($length < 4) {
            $ip = implode(':', array_slice(explode(':', $ip), 0, 1 + $length));
        }

        return $ip;
    }

    /**
     * Get request path
     *
     * @return string Request path
     */
    private function getRequestPath(): string
    {
        return HTTP_ROOT . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    }

    /**
     * Validate session on wakeup
     */
    private function validateSession(): void
    {
        $this->isValid = $this->isValidSession();
    }

    /**
     * Clean up expired sessions
     */
    public static function cleanupExpiredSessions(): void
    {
        $sql = 'DELETE FROM %%SESSION%% WHERE lastonline < :expireTime';
        Database::get()->delete($sql, [':expireTime' => time() - SESSION_LIFETIME]);
    }

    /**
     * Get session statistics
     *
     * @return array Session statistics
     */
    public static function getSessionStats(): array
    {
        $sql = 'SELECT COUNT(*) as total_sessions FROM %%SESSION%%';
        $totalSessions = Database::get()->selectSingle($sql, [], 'total_sessions');

        $sql = 'SELECT COUNT(*) as active_sessions FROM %%SESSION%% WHERE lastonline > :activeTime';
        $activeSessions = Database::get()->selectSingle($sql, [':activeTime' => time() - 300], 'active_sessions');

        return [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'expired_sessions' => $totalSessions - $activeSessions
        ];
    }
}