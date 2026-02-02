<?php
/**
 * Shared helpers for category datasets (archive support, identifier safety, etc.).
 */

if (!function_exists('get_category_dataset_file_path')) {
    function get_category_dataset_file_path(): string
    {
        return __DIR__ . '/category_datasets.php';
    }
}

if (!function_exists('load_category_datasets')) {
    function load_category_datasets(): array
    {
        $file = get_category_dataset_file_path();
        $data = is_file($file) ? require $file : [];

        return is_array($data) ? $data : [];
    }
}

if (!function_exists('save_category_datasets')) {
    function save_category_datasets(array $datasets): bool
    {
        if (!empty($datasets)) {
            ksort($datasets);
        }

        $export = var_export($datasets, true);
        $export = preg_replace('/^([ ]*)array \(/m', '$1[', $export);
        $export = preg_replace('/\)(,?)$/m', ']$1', $export);

        $content = "<?php\nreturn " . $export . ";\n";

        $file = get_category_dataset_file_path();
        $bytes = @file_put_contents($file, $content, LOCK_EX);
        if ($bytes === false) {
            return false;
        }

        clearstatcache(true, $file);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }

        return true;
    }
}

if (!function_exists('dataset_generate_singular_label')) {
    function dataset_generate_singular_label(string $label): string
    {
        $trimmed = trim($label);
        if ($trimmed === '') {
            return 'Record';
        }

        if (preg_match('/ies$/i', $trimmed)) {
            $converted = preg_replace('/ies$/i', 'y', $trimmed);
            return $converted === null ? $trimmed : $converted;
        }

        if (preg_match('/ses$/i', $trimmed)) {
            $converted = preg_replace('/es$/i', '', $trimmed);
            return $converted === null ? $trimmed : $converted;
        }

        if (preg_match('/s$/i', $trimmed)) {
            return rtrim($trimmed, "sS");
        }

        return $trimmed;
    }
}

if (!function_exists('wrap_db_identifier')) {
    function wrap_db_identifier(string $name): ?string
    {
        $trimmed = trim($name);
        if ($trimmed === '' || !preg_match('/^[A-Za-z0-9_]+$/', $trimmed)) {
            return null;
        }

        return '`' . $trimmed . '`';
    }
}

if (!function_exists('get_dataset_archive_config')) {
    function get_dataset_archive_config(array $dataset): array
    {
        $config = $dataset['archive'] ?? [];
        $enabled = !empty($config['enabled']);
        $column = isset($config['column']) ? trim((string) $config['column']) : '';

        if (!$enabled || $column === '' || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return [
                'enabled' => false,
                'column' => '',
                'active_value' => 0,
                'archived_value' => 1,
                'timestamp_column' => '',
                'per_page' => 25,
            ];
        }

        return [
            'enabled' => true,
            'column' => $column,
            'active_value' => isset($config['active_value']) ? (int) $config['active_value'] : 0,
            'archived_value' => isset($config['archived_value']) ? (int) $config['archived_value'] : 1,
            'timestamp_column' => isset($config['timestamp_column']) ? trim((string) $config['timestamp_column']) : '',
            'per_page' => isset($config['per_page']) ? max(1, (int) $config['per_page']) : 25,
        ];
    }
}

if (!function_exists('dataset_supports_archive')) {
    function dataset_supports_archive(array $dataset): bool
    {
        $config = get_dataset_archive_config($dataset);
        return $config['enabled'] && $config['column'] !== '';
    }
}

if (!function_exists('build_archive_condition')) {
    function build_archive_condition(array $archiveConfig, bool $archived): ?string
    {
        if (empty($archiveConfig['enabled']) || $archiveConfig['column'] === '') {
            return null;
        }

        $column = wrap_db_identifier($archiveConfig['column']);
        if ($column === null) {
            return null;
        }

        $value = $archived ? (int) $archiveConfig['archived_value'] : (int) $archiveConfig['active_value'];
        return $column . ' = ' . $value;
    }
}

if (!function_exists('ensure_archive_columns')) {
    function ensure_archive_columns(mysqli $conn, array $datasets): void
    {
        foreach ($datasets as $dataset) {
            ensure_archive_column($conn, $dataset);
        }
    }
}

if (!function_exists('ensure_archive_column')) {
    function ensure_archive_column(mysqli $conn, array $dataset): void
    {
        $archiveConfig = get_dataset_archive_config($dataset);
        if (!$archiveConfig['enabled']) {
            return;
        }

        $tableName = $dataset['table'] ?? '';
        if ($tableName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
            return;
        }

        $columnName = $archiveConfig['column'];
        $columnEscaped = $conn->real_escape_string($columnName);
        $result = $conn->query("SHOW COLUMNS FROM `{$tableName}` LIKE '{$columnEscaped}'");

        if ($result instanceof mysqli_result) {
            $exists = $result->num_rows > 0;
            $result->free();
        } else {
            $exists = false;
        }

        if (!$exists) {
            $default = (int) $archiveConfig['active_value'];
            $conn->query("ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName}` TINYINT(1) NOT NULL DEFAULT {$default}");
        }
    }
}
