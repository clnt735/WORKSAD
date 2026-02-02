<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/log_admin_action.php';
require_once __DIR__ . '/../admin/category_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$datasets = load_category_datasets();

function redirect_with_message(string $status, string $message, string $view): void
{
    $query = http_build_query([
        'view' => $view,
        'flash_status' => $status,
        'flash_message' => $message,
    ]);

    header('Location: ../admin/categories.php?' . $query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('error', 'Invalid request method.', 'job_category');
}

if (!$conn) {
    redirect_with_message('error', 'Database connection error.', 'job_category');
}

$datasetKey = isset($_POST['dataset']) ? strtolower(trim((string) $_POST['dataset'])) : '';
if ($datasetKey === '' || !isset($datasets[$datasetKey])) {
    redirect_with_message('error', 'Unknown dataset.', 'job_category');
}

$dataset = $datasets[$datasetKey];
$singular = $dataset['singular'] ?? 'Record';
$tableName = $dataset['table'] ?? '';
$primaryConfig = $dataset['primary_key'] ?? [];
$primaryKey = $primaryConfig['field'] ?? '';
$primaryType = $primaryConfig['type'] ?? 'number';
$primaryAutoIncrement = $primaryConfig['auto_increment'] ?? true;
$formFields = $dataset['form_fields'] ?? [];
$displayField = $dataset['display_field'] ?? null;
$actions = array_merge([
    'create' => false,
    'update' => false,
    'delete' => false,
    'archive' => false,
    'restore' => false,
], $dataset['actions'] ?? []);

if ($conn) {
    ensure_archive_column($conn, $dataset);
}

if ($tableName === '' || $primaryKey === '') {
    redirect_with_message('error', 'Dataset configuration is incomplete.', $datasetKey);
}

$operation = strtolower(trim((string) ($_POST['operation'] ?? '')));
$operationMap = [
    'add' => 'create',
    'create' => 'create',
    'update' => 'update',
    'delete' => 'delete',
    'archive' => 'archive',
    'restore' => 'restore',
];

if (!isset($operationMap[$operation])) {
    redirect_with_message('error', 'Unsupported operation.', $datasetKey);
}

$permissionKey = $operationMap[$operation];
if (empty($actions[$permissionKey])) {
    redirect_with_message('error', 'Operation not allowed for this dataset.', $datasetKey);
}

$adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

function bind_statement_params(mysqli_stmt $stmt, string $types, array &$values): void
{
    if ($types === '') {
        return;
    }

    $refs = [];
    foreach ($values as $idx => $value) {
        $refs[$idx] = &$values[$idx];
    }

    $stmt->bind_param($types, ...$refs);
}

switch ($operation) {
    case 'add':
    case 'create': {
        $recordId = null;
        $insertFields = [];
        $placeholders = [];
        $bindTypes = '';
        $bindValues = [];

        if (!$primaryAutoIncrement) {
            $recordIdRaw = trim((string) ($_POST['record_id'] ?? ''));
            if ($recordIdRaw === '') {
                redirect_with_message('error', 'Primary identifier is required.', $datasetKey);
            }

            if ($primaryType === 'number') {
                if (!is_numeric($recordIdRaw)) {
                    redirect_with_message('error', 'Primary identifier must be numeric.', $datasetKey);
                }
                $recordId = (int) $recordIdRaw;
                $bindTypes .= 'i';
            } else {
                $recordId = $recordIdRaw;
                $bindTypes .= 's';
            }

            $insertFields[] = $primaryKey;
            $placeholders[] = '?';
            $bindValues[] = $recordId;
        }

        foreach ($formFields as $fieldName => $fieldConfig) {
            $rawValue = $_POST[$fieldName] ?? '';
            $value = is_string($rawValue) ? trim($rawValue) : $rawValue;
            $isRequired = !empty($fieldConfig['required']);
            $fieldType = $fieldConfig['type'] ?? 'text';

            if ($isRequired && $value === '') {
                $label = $fieldConfig['label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
                redirect_with_message('error', $label . ' is required.', $datasetKey);
            }

            if ($fieldType === 'number' && $value !== '') {
                if (!is_numeric($value)) {
                    $label = $fieldConfig['label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
                    redirect_with_message('error', $label . ' must be numeric.', $datasetKey);
                }
                $value = (string) (int) $value;
            }

            $insertFields[] = $fieldName;
            $placeholders[] = '?';
            $bindTypes .= 's';
            $bindValues[] = ($value === '' ? null : $value);
        }

        if ($datasetKey === 'user_status') {
            $today = date('Y-m-d');
            $insertFields[] = 'user_status_created_at';
            $placeholders[] = '?';
            $bindTypes .= 's';
            $bindValues[] = $today;

            $insertFields[] = 'user_status_updated_at';
            $placeholders[] = '?';
            $bindTypes .= 's';
            $bindValues[] = $today;
        }

        $sql = 'INSERT INTO ' . $tableName . ' (' . implode(', ', $insertFields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            redirect_with_message('error', 'Failed to prepare insert statement.', $datasetKey);
        }

        bind_statement_params($stmt, $bindTypes, $bindValues);

        if (!$stmt->execute()) {
            $stmt->close();
            redirect_with_message('error', 'Failed to add record.', $datasetKey);
        }

        $newId = $primaryAutoIncrement ? (int) $conn->insert_id : (int) ($recordId ?? 0);
        $stmt->close();

        $displayValue = '';
        if ($displayField && isset($_POST[$displayField])) {
            $displayValue = trim((string) $_POST[$displayField]);
        }
        $displaySuffix = $displayValue !== '' ? ' "' . $displayValue . '"' : '';
        $message = 'Added ' . $singular . ' #' . $newId . $displaySuffix . '.';

        log_admin_action($conn, $adminId, $message);
        redirect_with_message('success', $message, $datasetKey);
        break;
    }

    case 'update': {
        $recordIdRaw = trim((string) ($_POST['record_id'] ?? ''));
        if ($recordIdRaw === '') {
            redirect_with_message('error', 'Missing record identifier.', $datasetKey);
        }

        if ($primaryType === 'number') {
            if (!is_numeric($recordIdRaw)) {
                redirect_with_message('error', 'Invalid record identifier.', $datasetKey);
            }
            $recordId = (int) $recordIdRaw;
            $recordIdType = 'i';
        } else {
            $recordId = $recordIdRaw;
            $recordIdType = 's';
        }

        $setParts = [];
        $bindTypes = '';
        $bindValues = [];

        foreach ($formFields as $fieldName => $fieldConfig) {
            if (!array_key_exists($fieldName, $_POST)) {
                continue;
            }
            $rawValue = $_POST[$fieldName];
            $value = is_string($rawValue) ? trim($rawValue) : $rawValue;
            $isRequired = !empty($fieldConfig['required']);
            $fieldType = $fieldConfig['type'] ?? 'text';

            if ($isRequired && $value === '') {
                $label = $fieldConfig['label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
                redirect_with_message('error', $label . ' is required.', $datasetKey);
            }

            if ($fieldType === 'number' && $value !== '') {
                if (!is_numeric($value)) {
                    $label = $fieldConfig['label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
                    redirect_with_message('error', $label . ' must be numeric.', $datasetKey);
                }
                $value = (string) (int) $value;
            }

            $setParts[] = $fieldName . ' = ?';
            $bindTypes .= 's';
            $bindValues[] = ($value === '' ? null : $value);
        }

        if ($datasetKey === 'user_status') {
            $setParts[] = 'user_status_updated_at = ?';
            $bindTypes .= 's';
            $bindValues[] = date('Y-m-d');
        } elseif ($datasetKey === 'city_mun') {
            $setParts[] = 'city_mun_updated_at = ?';
            $bindTypes .= 's';
            $bindValues[] = date('Y-m-d H:i:s');
        } elseif ($datasetKey === 'barangay') {
            $setParts[] = 'barangay_updated_at = ?';
            $bindTypes .= 's';
            $bindValues[] = date('Y-m-d H:i:s');
        }

        if (empty($setParts)) {
            redirect_with_message('error', 'No changes provided to update.', $datasetKey);
        }

        $setClause = implode(', ', $setParts);
        $sql = 'UPDATE ' . $tableName . ' SET ' . $setClause . ' WHERE ' . $primaryKey . ' = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            redirect_with_message('error', 'Failed to prepare update statement.', $datasetKey);
        }

        $bindTypes .= $recordIdType;
        $bindValues[] = $recordId;

        bind_statement_params($stmt, $bindTypes, $bindValues);

        if (!$stmt->execute()) {
            $stmt->close();
            redirect_with_message('error', 'Failed to update record.', $datasetKey);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        $displayValue = '';
        if ($displayField && isset($_POST[$displayField])) {
            $displayValue = trim((string) $_POST[$displayField]);
        }
        $displaySuffix = $displayValue !== '' ? ' "' . $displayValue . '"' : '';

        if ($affected > 0) {
            $message = 'Updated ' . $singular . ' #' . $recordId . $displaySuffix . '.';
            log_admin_action($conn, $adminId, $message);
            redirect_with_message('success', $message, $datasetKey);
        }

        redirect_with_message('success', 'No changes detected.', $datasetKey);
        break;
    }

    case 'delete': {
        $recordIdRaw = trim((string) ($_POST['record_id'] ?? ''));
        if ($recordIdRaw === '') {
            redirect_with_message('error', 'Missing record identifier.', $datasetKey);
        }

        if ($primaryType === 'number') {
            if (!is_numeric($recordIdRaw)) {
                redirect_with_message('error', 'Invalid record identifier.', $datasetKey);
            }
            $recordId = (int) $recordIdRaw;
            $recordIdType = 'i';
        } else {
            $recordId = $recordIdRaw;
            $recordIdType = 's';
        }

        $displayValue = '';
        if ($displayField) {
            $lookupSql = 'SELECT ' . $displayField . ' FROM ' . $tableName . ' WHERE ' . $primaryKey . ' = ? LIMIT 1';
            $lookupStmt = $conn->prepare($lookupSql);
            if ($lookupStmt) {
                $tempValue = $recordId;
                $lookupStmt->bind_param($recordIdType, $tempValue);
                if ($lookupStmt->execute()) {
                    $result = $lookupStmt->get_result();
                    if ($result && $result->num_rows > 0) {
                        $displayValue = trim((string) $result->fetch_row()[0]);
                    }
                }
                $lookupStmt->close();
            }
        }

        $sql = 'DELETE FROM ' . $tableName . ' WHERE ' . $primaryKey . ' = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            redirect_with_message('error', 'Failed to prepare delete statement.', $datasetKey);
        }

        $tempId = $recordId;
        $stmt->bind_param($recordIdType, $tempId);

        if (!$stmt->execute()) {
            $stmt->close();
            redirect_with_message('error', 'Failed to delete record.', $datasetKey);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            redirect_with_message('error', $singular . ' not found.', $datasetKey);
        }

        $displaySuffix = $displayValue !== '' ? ' "' . $displayValue . '"' : '';
        $message = 'Deleted ' . $singular . ' #' . $recordId . $displaySuffix . '.';
        log_admin_action($conn, $adminId, $message);
        redirect_with_message('success', $message, $datasetKey);
        break;
    }

    case 'archive':
    case 'restore': {
        $recordIdRaw = trim((string) ($_POST['record_id'] ?? ''));
        if ($recordIdRaw === '') {
            redirect_with_message('error', 'Missing record identifier.', $datasetKey);
        }

        $archiveConfig = get_dataset_archive_config($dataset);
        if (!$archiveConfig['enabled'] || empty($archiveConfig['column'])) {
            redirect_with_message('error', 'Archiving is not enabled for this dataset.', $datasetKey);
        }

        if ($primaryType === 'number') {
            if (!is_numeric($recordIdRaw)) {
                redirect_with_message('error', 'Invalid record identifier.', $datasetKey);
            }
            $recordId = (int) $recordIdRaw;
            $recordIdType = 'i';
        } else {
            $recordId = $recordIdRaw;
            $recordIdType = 's';
        }

        $displayValue = '';
        if ($displayField) {
            $lookupSql = 'SELECT ' . $displayField . ' FROM ' . $tableName . ' WHERE ' . $primaryKey . ' = ? LIMIT 1';
            $lookupStmt = $conn->prepare($lookupSql);
            if ($lookupStmt) {
                $tempValue = $recordId;
                $lookupStmt->bind_param($recordIdType, $tempValue);
                if ($lookupStmt->execute()) {
                    $result = $lookupStmt->get_result();
                    if ($result && $result->num_rows > 0) {
                        $displayValue = trim((string) $result->fetch_row()[0]);
                    }
                }
                $lookupStmt->close();
            }
        }

        $columnName = $archiveConfig['column'];
        $targetValue = $operation === 'archive'
            ? (int) $archiveConfig['archived_value']
            : (int) $archiveConfig['active_value'];

        $sql = 'UPDATE ' . $tableName . ' SET `' . $columnName . '` = ? WHERE ' . $primaryKey . ' = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            redirect_with_message('error', 'Failed to prepare archive statement.', $datasetKey);
        }

        $bindTypes = 'i' . $recordIdType;
        $params = [$targetValue, $recordId];
        bind_statement_params($stmt, $bindTypes, $params);

        if (!$stmt->execute()) {
            $stmt->close();
            redirect_with_message('error', 'Failed to update archive status.', $datasetKey);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            redirect_with_message('error', $singular . ' not found.', $datasetKey);
        }

        $displaySuffix = $displayValue !== '' ? ' "' . $displayValue . '"' : '';
        $verb = $operation === 'archive' ? 'Archived' : 'Restored';
        $message = $verb . ' ' . $singular . ' #' . $recordId . $displaySuffix . '.';
        log_admin_action($conn, $adminId, $message);
        redirect_with_message('success', $message, $datasetKey);
        break;
    }

    default:
        redirect_with_message('error', 'Unsupported operation.', $datasetKey);
}
