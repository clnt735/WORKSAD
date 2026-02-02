<?php include '../database.php'; ?>
<?php include '../admin/sidebar.php'; ?>
<?php require_once __DIR__ . '/category_helpers.php'; ?>
<?php
if (!function_exists('dataset_admin_humanize_label')) {
    function dataset_admin_humanize_label(string $value): string
    {
        $normalized = str_replace('_', ' ', trim($value));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        if ($normalized === null) {
            $normalized = '';
        }

        return $normalized === '' ? 'Field' : ucwords($normalized);
    }
}

if (!function_exists('dataset_admin_infer_field_type')) {
    function dataset_admin_infer_field_type(string $dbType): string
    {
        $type = strtoupper(trim($dbType));

        if ($type === 'TEXT') {
            return 'textarea';
        }

        if (strpos($type, 'INT') !== false || strpos($type, 'DECIMAL') !== false) {
            return 'number';
        }

        if ($type === 'DATE') {
            return 'date';
        }

        if ($type === 'DATETIME' || $type === 'TIMESTAMP') {
            return 'datetime-local';
        }

        return 'text';
    }
}

if (!function_exists('dataset_admin_generate_singular')) {
    function dataset_admin_generate_singular(string $label): string
    {
        return dataset_generate_singular_label($label);
    }
}

if (!function_exists('dataset_admin_build_definition')) {
    function dataset_admin_build_definition(string $datasetKey, string $label, string $tableName, array $columns, bool $archiveEnabled): array
    {
        $columnsConfig = [];
        $formFields = [];
        $seen = [];

        foreach ($columns as $column) {
            $name = $column['name'] ?? '';
            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $labelText = dataset_admin_humanize_label($name);
            $columnsConfig[] = [
                'key' => $name,
                'label' => $labelText,
            ];

            $formFields[$name] = [
                'label' => $labelText,
                'type' => dataset_admin_infer_field_type($column['type'] ?? ''),
                'required' => true,
            ];

            $seen[$name] = true;
        }

        if ($archiveEnabled && !isset($seen['is_archived'])) {
            $columnsConfig[] = [
                'key' => 'is_archived',
                'label' => 'Archived',
            ];
            $seen['is_archived'] = true;
        }

        foreach ([
            'created_at' => ['label' => 'Created At', 'format' => 'datetime'],
            'updated_at' => ['label' => 'Updated At', 'format' => 'datetime'],
        ] as $key => $meta) {
            if (isset($seen[$key])) {
                continue;
            }
            $columnsConfig[] = array_merge(['key' => $key], $meta);
            $seen[$key] = true;
        }

        $displayField = $columns[0]['name'] ?? 'id';

        return [
            'label' => $label,
            'singular' => dataset_admin_generate_singular($label),
            'table' => $tableName,
            'order_by' => $displayField . ' ASC',
            'per_page' => 10,
            'primary_key' => [
                'field' => 'id',
                'auto_increment' => true,
                'label' => 'ID',
            ],
            'display_field' => $displayField,
            'actions' => [
                'create' => true,
                'update' => true,
                'delete' => false,
                'archive' => $archiveEnabled,
                'restore' => $archiveEnabled,
            ],
            'archive' => [
                'enabled' => $archiveEnabled,
                'column' => $archiveEnabled ? 'is_archived' : '',
            ],
            'columns' => $columnsConfig,
            'form_fields' => $formFields,
        ];
    }
}

$existingDatasets = load_category_datasets();

$errors = [];
$successMessage = '';
$allowedColumnTypes = [
    'VARCHAR(255)' => 'Text (255)',
    'TEXT' => 'Long Text',
    'INT' => 'Integer',
    'BIGINT' => 'Big Integer',
    'DECIMAL(10,2)' => 'Decimal (10,2)',
    'DATE' => 'Date',
    'DATETIME' => 'Date & Time',
    'TINYINT(1)' => 'Tiny Integer (0/1)',
];

$columnValues = [];
$formValues = [
    'dataset_key' => '',
    'label' => '',
    'table_name' => '',
    'archive_enabled' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datasetKey = strtolower(trim($_POST['dataset_key'] ?? ''));
    $label = trim($_POST['label'] ?? '');
    $tableName = strtolower(trim($_POST['table_name'] ?? ''));
    $archiveEnabled = isset($_POST['archive_enabled']);
    $columnsInput = $_POST['columns'] ?? [];

    $formValues = [
        'dataset_key' => $datasetKey,
        'label' => $label,
        'table_name' => $tableName,
        'archive_enabled' => $archiveEnabled,
    ];

    if ($datasetKey === '' || !preg_match('/^[a-z0-9_]+$/', $datasetKey)) {
        $errors[] = 'Dataset key must use lowercase letters, numbers, and underscores.';
    } elseif (isset($existingDatasets[$datasetKey])) {
        $errors[] = 'Dataset key already exists in the current configuration.';
    }

    if ($label === '') {
        $errors[] = 'Display label is required.';
    }

    $tableIdentifier = wrap_db_identifier($tableName);
    if ($tableIdentifier === null) {
        $errors[] = 'Table name must use alphanumeric characters and underscores.';
    }

    if (!$conn) {
        $errors[] = 'Database connection error.';
    }

    $columnValues = [];
    foreach ($columnsInput as $column) {
        $columnName = trim($column['name'] ?? '');
        $columnType = strtoupper(trim($column['type'] ?? 'VARCHAR(255)'));

        if ($columnName === '') {
            continue;
        }

        $columnIdentifier = wrap_db_identifier($columnName);
        if ($columnIdentifier === null) {
            $errors[] = 'Invalid column name: ' . $columnName;
            continue;
        }

        if (!array_key_exists($columnType, $allowedColumnTypes)) {
            $errors[] = 'Unsupported column type selected for ' . $columnName . '.';
            continue;
        }

        $columnValues[] = [
            'name' => $columnName,
            'identifier' => $columnIdentifier,
            'type' => $columnType,
        ];
    }

    if (count($columnValues) === 0) {
        $errors[] = 'Add at least one custom column.';
    }

    if (!$errors && $conn) {
        $escapedTable = mysqli_real_escape_string($conn, $tableName);
        $existingTableResult = mysqli_query($conn, "SHOW TABLES LIKE '{$escapedTable}'");
        if ($existingTableResult instanceof mysqli_result) {
            if ($existingTableResult->num_rows > 0) {
                $errors[] = 'A table with that name already exists.';
            }
            mysqli_free_result($existingTableResult);
        }
    }

    if (!$errors && $conn) {
        $columnDefinitions = ['`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'];

        foreach ($columnValues as $column) {
            $columnDefinitions[] = $column['identifier'] . ' ' . $column['type'] . ' NULL';
        }

        if ($archiveEnabled) {
            $columnDefinitions[] = '`is_archived` TINYINT(1) NOT NULL DEFAULT 0';
        }

        $columnDefinitions[] = '`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP';
        $columnDefinitions[] = '`updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP';

        $createSql = 'CREATE TABLE ' . $tableIdentifier . ' (
            ' . implode(",\r\n            ", $columnDefinitions) . '
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        if (mysqli_query($conn, $createSql)) {
            $allDatasets = load_category_datasets();
            $allDatasets[$datasetKey] = dataset_admin_build_definition($datasetKey, $label, $tableName, $columnValues, $archiveEnabled);

            if (save_category_datasets($allDatasets)) {
                $successMessage = 'Dataset "' . $label . '" created successfully and is now available in Categories Management.';
                $existingDatasets = $allDatasets;
                $columnValues = [];
                $formValues = [
                    'dataset_key' => '',
                    'label' => '',
                    'table_name' => '',
                    'archive_enabled' => false,
                ];
            } else {
                $errors[] = 'Dataset table was created, but updating category_datasets.php failed.';
            }
        } else {
            $errors[] = 'Failed to create dataset table: ' . mysqli_error($conn);
        }
    }
}

if (count($columnValues) === 0) {
    $columnValues[] = ['name' => '', 'type' => 'VARCHAR(255)'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Dataset</title>
    <link rel="stylesheet" href="../admin/styles.css">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
</head>
<body class="admin-page">
    <?php renderAdminSidebar(); ?>
    <main class="content">
        <div class="header">
            <div>
                <h1>Add Dataset</h1>
                <p class="lead">Create a new dropdown dataset and its backing table.</p>
            </div>
            <div class="header-actions" style="display:flex; gap:12px; align-items:center;">
                <a class="btn btn-secondary" href="category_dropdowns.php">Back to Overview</a>
            </div>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin:0; padding-left:20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form-container" autocomplete="off">
            <div class="form-group">
                <label for="dataset_key">Dataset Key<span style="color:#e02424;">*</span></label>
                <input
                    type="text"
                    id="dataset_key"
                    name="dataset_key"
                    required
                    pattern="[a-z0-9_]+"
                    title="Use lowercase letters, numbers, and underscores"
                    value="<?php echo htmlspecialchars($formValues['dataset_key'], ENT_QUOTES, 'UTF-8'); ?>"
                >
            </div>

            <div class="form-group">
                <label for="label">Display Label<span style="color:#e02424;">*</span></label>
                <input
                    type="text"
                    id="label"
                    name="label"
                    required
                    value="<?php echo htmlspecialchars($formValues['label'], ENT_QUOTES, 'UTF-8'); ?>"
                >
            </div>

            <div class="form-group">
                <label for="table_name">Table Name<span style="color:#e02424;">*</span></label>
                <input
                    type="text"
                    id="table_name"
                    name="table_name"
                    required
                    pattern="[a-z0-9_]+"
                    title="Use lowercase letters, numbers, and underscores"
                    value="<?php echo htmlspecialchars($formValues['table_name'], ENT_QUOTES, 'UTF-8'); ?>"
                >
            </div>

            <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" id="archive_enabled" name="archive_enabled" <?php echo $formValues['archive_enabled'] ? 'checked' : ''; ?>>
                <label for="archive_enabled" style="margin:0;">Include archive column (is_archived)</label>
            </div>

            <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <label style="margin:0;">Custom Columns<span style="color:#e02424;">*</span></label>
                    <button type="button" class="btn btn-secondary" onclick="addColumn()">Add Column</button>
                </div>
                <div id="columns-container" style="display:flex; flex-direction:column; gap:12px; background:#f5f7fb; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                    <?php foreach ($columnValues as $index => $column): ?>
                        <div class="column-row" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                            <input
                                type="text"
                                name="columns[<?php echo (int) $index; ?>][name]"
                                placeholder="Column name"
                                required
                                pattern="[A-Za-z0-9_]+"
                                title="Use letters, numbers, and underscores"
                                value="<?php echo htmlspecialchars($column['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                style="flex:1 1 240px; min-width:200px;"
                            >
                            <select name="columns[<?php echo (int) $index; ?>][type]" style="flex:0 0 200px; min-width:160px;">
                                <?php foreach ($allowedColumnTypes as $typeValue => $typeLabel): ?>
                                    <option value="<?php echo htmlspecialchars($typeValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($column['type'] === $typeValue) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-danger" onclick="removeColumn(this)" style="flex:0 0 auto;">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions" style="display:flex; gap:12px; justify-content:flex-start;">
                <button type="submit" class="btn btn-primary">Create Dataset</button>
                <a class="btn btn-light" href="category_dropdowns.php">Cancel</a>
            </div>
        </form>
    </main>

    <script>
        let columnIndex = <?php echo count($columnValues); ?>;
        const columnTypes = <?php echo json_encode(array_keys($allowedColumnTypes)); ?>;
        const columnLabels = <?php echo json_encode(array_values($allowedColumnTypes)); ?>;

        function addColumn() {
            const container = document.getElementById('columns-container');
            const wrapper = document.createElement('div');
            wrapper.className = 'column-row';
            wrapper.style.display = 'flex';
            wrapper.style.flexWrap = 'wrap';
            wrapper.style.gap = '12px';
            wrapper.style.alignItems = 'center';

            const nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.name = `columns[${columnIndex}][name]`;
            nameInput.placeholder = 'Column name';
            nameInput.required = true;
            nameInput.pattern = '[A-Za-z0-9_]+';
            nameInput.title = 'Use letters, numbers, and underscores';
            nameInput.style.flex = '1 1 240px';
            nameInput.style.minWidth = '200px';

            const typeSelect = document.createElement('select');
            typeSelect.name = `columns[${columnIndex}][type]`;
            typeSelect.style.flex = '0 0 200px';
            typeSelect.style.minWidth = '160px';

            columnTypes.forEach((type, i) => {
                const option = document.createElement('option');
                option.value = type;
                option.textContent = columnLabels[i];
                typeSelect.appendChild(option);
            });

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn btn-danger';
            removeButton.textContent = 'Remove';
            removeButton.style.flex = '0 0 auto';
            removeButton.addEventListener('click', () => removeColumn(removeButton));

            wrapper.appendChild(nameInput);
            wrapper.appendChild(typeSelect);
            wrapper.appendChild(removeButton);

            container.appendChild(wrapper);
            columnIndex += 1;
        }

        function removeColumn(button) {
            const container = document.getElementById('columns-container');
            if (container.children.length <= 1) {
                return;
            }
            button.parentElement.remove();
        }
    </script>
</body>
</html>
