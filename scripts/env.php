<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| .env loader (no dependencies, shared-hosting friendly)
|--------------------------------------------------------------------------
| Looks for a .env file in this order and loads the FIRST one found:
|   1. one folder ABOVE the project (recommended: outside the public web root)
|   2. the project root (protected by the root .htaccess, but less safe)
|
| Real environment variables (set by the host) always win over .env.
| Read values with eh_env('EH_DB_HOST', 'default'). Works even when the
| host disables putenv(), because values are also kept in memory.
*/

if (!function_exists('eh_env')) {

    /** @return array<string,string> */
    function &eh_env_store(): array
    {
        static $store = [];
        return $store;
    }

    function eh_env_file(): string
    {
        $project = dirname(__DIR__);
        foreach ([dirname($project) . '/.env', $project . '/.env'] as $file) {
            // @: open_basedir may forbid looking above the web root.
            if (@is_file($file) && @is_readable($file)) return $file;
        }
        return '';
    }

    function eh_load_env(): void
    {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;

        $file = eh_env_file();
        if ($file === '') return;

        $store = &eh_env_store();
        $canPutenv = function_exists('putenv')
            && !in_array('putenv', array_map('trim', explode(',', strtolower((string)ini_get('disable_functions')))), true);

        foreach (preg_split('/\R/', (string)file_get_contents($file)) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (str_starts_with($line, 'export ')) $line = trim(substr($line, 7));
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m)) continue;

            [$key, $value] = [$m[1], $m[2]];
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $q = $value[0];
                $end = strpos($value, $q, 1);
                $value = $end === false ? substr($value, 1) : substr($value, 1, $end - 1);
                if ($q === '"') $value = strtr($value, ['\\n' => "\n", '\\"' => '"', '\\\\' => '\\']);
            } else {
                $value = trim((string)preg_replace('/\s+#.*$/', '', $value)); // strip inline comment
            }

            // Real environment variables take precedence over the file.
            $real = getenv($key);
            if ($real !== false && $real !== '') continue;

            $store[$key] = $value;
            $_ENV[$key] = $value;
            if ($canPutenv) @putenv($key . '=' . $value);
        }
    }

    /** Read a setting: real env var, then .env, then $default. */
    function eh_env(string $key, ?string $default = null): ?string
    {
        eh_load_env();
        $real = getenv($key);
        if ($real !== false && $real !== '') return $real;
        $store = &eh_env_store();
        if (isset($store[$key]) && $store[$key] !== '') return $store[$key];
        return $default;
    }
}

eh_load_env();
