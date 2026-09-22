<?php
namespace App\Helpers;

/**
 * Cache Helper
 * Provides a simple caching interface. Uses Redis if available in the environment,
 * otherwise falls back to a simple file-based cache for scalability in XAMPP environments.
 */
class Cache
{
    private static $redis = null;
    private static bool $initialized = false;
    private static string $fileCacheDir = ROOT_PATH . '/storage/cache';

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $redisHost = \App\Helpers\Env::get('REDIS_HOST');
        if ($redisHost) {
            try {
                // If the predis package or PHP redis extension is available, we would use it here.
                // Assuming basic PHP Redis extension:
                if (class_exists('Redis')) {
                    self::$redis = new \Redis();
                    self::$redis->connect($redisHost, (int)\App\Helpers\Env::get('REDIS_PORT', 6379));
                }
            } catch (\Exception $e) {
                // Fallback to file cache
                error_log("Redis connection failed: " . $e->getMessage());
            }
        }

        if (!self::$redis && !is_dir(self::$fileCacheDir)) {
            mkdir(self::$fileCacheDir, 0750, true);
        }

        self::$initialized = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::init();

        if (self::$redis) {
            $val = self::$redis->get($key);
            return $val !== false ? json_decode($val, true) : $default;
        }

        // File cache fallback
        $file = self::$fileCacheDir . '/' . md5($key) . '.cache';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data['expires'] === 0 || $data['expires'] > time()) {
                return $data['value'];
            }
            unlink($file); // Expired
        }

        return $default;
    }

    public static function set(string $key, mixed $value, int $ttlSeconds = 0): void
    {
        self::init();

        if (self::$redis) {
            if ($ttlSeconds > 0) {
                self::$redis->setex($key, $ttlSeconds, json_encode($value));
            } else {
                self::$redis->set($key, json_encode($value));
            }
            return;
        }

        // File cache fallback
        $file = self::$fileCacheDir . '/' . md5($key) . '.cache';
        $data = [
            'value' => $value,
            'expires' => $ttlSeconds > 0 ? time() + $ttlSeconds : 0
        ];
        file_put_contents($file, json_encode($data));
    }

    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        self::set($key, $value, $ttlSeconds);
        
        return $value;
    }

    public static function delete(string $key): void
    {
        self::init();

        if (self::$redis) {
            self::$redis->del($key);
            return;
        }

        $file = self::$fileCacheDir . '/' . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
