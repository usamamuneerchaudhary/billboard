<?php

namespace Billboard\Stores;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Statamic\Facades\Blink;
use Statamic\Facades\YAML;

class YamlDirectory
{
    public const string KEY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/';

    /**
     * Create the store for a directory of YAML files.
     */
    public function __construct(
        private Filesystem $files,
        private Cache $cache,
        private string $path,
    ) {}

    /**
     * Return the directory path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Return every parsed record, cached for the request.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return Blink::once($this->blinkKey(), fn () => $this->load());
    }

    /**
     * Load every valid YAML record, cached until a file name, size, or modification time changes.
     *
     * @return array<string, array<string, mixed>>
     */
    private function load(): array
    {
        $paths = $this->files->isDirectory($this->path)
            ? $this->files->glob($this->path.'/*.yaml')
            : [];

        sort($paths);

        $fingerprint = md5(implode('|', array_map(
            fn (string $file) => basename($file).':'.@filesize($file).':'.@filemtime($file),
            $paths
        )));

        $cacheKey = 'billboard:yaml:'.md5($this->path).':'.$fingerprint;

        return $this->cache->remember($cacheKey, now()->addDay(), function () use ($paths) {
            $records = [];

            foreach ($paths as $file) {
                $key = basename($file, '.yaml');

                if (! preg_match(self::KEY_PATTERN, $key)) {
                    continue;
                }

                $data = YAML::file($file)->parse();
                $records[$key] = is_array($data) ? $data : [];
            }

            return $records;
        });
    }

    /**
     * Return one record by key.
     */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Write a record atomically and forget the request cache.
     */
    public function put(string $key, array $data): void
    {
        $this->guardKey($key);

        $this->files->ensureDirectoryExists($this->path);

        $target = $this->filePath($key);
        $temporary = $target.'.'.bin2hex(random_bytes(4)).'.tmp';

        $this->files->put($temporary, YAML::dump($data));
        $this->files->move($temporary, $target);

        Blink::forget($this->blinkKey());
    }

    /**
     * Delete a record and forget the request cache.
     */
    public function delete(string $key): void
    {
        $this->guardKey($key);

        $this->files->delete($this->filePath($key));

        Blink::forget($this->blinkKey());
    }

    /**
     * Decide whether a valid key already has a record.
     */
    public function exists(string $key): bool
    {
        return preg_match(self::KEY_PATTERN, $key) === 1 && $this->get($key) !== null;
    }

    /**
     * Return the request-cache key for this directory.
     */
    private function blinkKey(): string
    {
        return 'billboard:yaml:'.md5($this->path);
    }

    /**
     * Return the YAML file path for a record key.
     */
    private function filePath(string $key): string
    {
        return $this->path.'/'.$key.'.yaml';
    }

    /**
     * Reject record keys that do not match the allowed pattern.
     */
    private function guardKey(string $key): void
    {
        if (! preg_match(self::KEY_PATTERN, $key)) {
            throw new InvalidArgumentException("Invalid Billboard record key [{$key}].");
        }
    }
}
