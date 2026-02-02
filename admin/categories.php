<?php include '../database.php'; ?>
<?php include '../admin/sidebar.php'; ?>
<?php require_once __DIR__ . '/category_helpers.php'; ?>
<?php
$datasets = load_category_datasets();

if ($conn) {
    ensure_archive_columns($conn, $datasets);
}

$selectedView = isset($_GET['view']) ? strtolower(trim($_GET['view'])) : 'job_category';
if (!array_key_exists($selectedView, $datasets)) {
    $selectedView = 'job_category';
}

$currentDataset = $datasets[$selectedView];
$columns = $currentDataset['columns'] ?? [];
$formFields = $currentDataset['form_fields'] ?? [];
$primaryKeyConfig = $currentDataset['primary_key'] ?? [];
$primaryKey = $primaryKeyConfig['field'] ?? null;
$primaryAutoIncrement = $primaryKeyConfig['auto_increment'] ?? true;
$tableName = $currentDataset['table'] ?? '';
$tableIdentifier = $tableName !== '' ? wrap_db_identifier($tableName) : null;

$archiveConfig = get_dataset_archive_config($currentDataset);
$archiveEnabled = $archiveConfig['enabled'];

$actionPermissions = array_merge([
    'create' => false,
    'update' => false,
    'delete' => false,
    'archive' => false,
    'restore' => false,
], $currentDataset['actions'] ?? []);
$allowCreate = !empty($actionPermissions['create']);
$allowUpdate = !empty($actionPermissions['update']);
$allowArchive = $archiveEnabled && !empty($actionPermissions['archive']);
$allowRestore = $archiveEnabled && !empty($actionPermissions['restore']);
$hasActions = $allowUpdate || $allowArchive;

$perPage = $currentDataset['per_page'] ?? 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $perPage;
$totalRows = 0;
$totalPages = 1;
$rows = [];
$archivedRows = [];
$tableError = null;

$selectParts = [];
if ($primaryKey) {
    $selectParts[] = $primaryKey;
}
foreach ($columns as $column) {
    $selectParts[] = $column['select'] ?? $column['key'];
}
foreach ($formFields as $fieldName => $fieldConfig) {
    $selectParts[] = $fieldName;
}
$selectParts = array_values(array_unique(array_filter($selectParts)));
$selectClause = $selectParts ? implode(', ', $selectParts) : '*';
$orderBy = $currentDataset['order_by'] ?? ($columns[0]['key'] ?? ($primaryKey ? $primaryKey . ' ASC' : '1'));

if ($conn && $tableError === null && $tableIdentifier === null) {
    $tableError = 'Invalid dataset configuration.';
}

if ($conn && $tableError === null) {
    $whereClauses = [];
    if ($archiveEnabled) {
        $activeCondition = build_archive_condition($archiveConfig, false);
        if ($activeCondition) {
            $whereClauses[] = $activeCondition;
        }
    }

    $whereClauseSql = $whereClauses ? (' WHERE ' . implode(' AND ', $whereClauses)) : '';

    $countSql = 'SELECT COUNT(*) AS total FROM ' . $tableIdentifier . $whereClauseSql;
    $countResult = mysqli_query($conn, $countSql);
    if ($countResult) {
        $totalRows = (int) mysqli_fetch_assoc($countResult)['total'];
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
    } else {
        $tableError = 'Unable to count records.';
    }

    if ($tableError === null) {
        $query = 'SELECT ' . $selectClause . ' FROM ' . $tableIdentifier .
            $whereClauseSql .
            ' ORDER BY ' . $orderBy .
            ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $result = mysqli_query($conn, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        } else {
            $tableError = 'Query error: ' . mysqli_error($conn);
        }

        if ($archiveEnabled && $tableError === null) {
            $archivedCondition = build_archive_condition($archiveConfig, true);
            if ($archivedCondition) {
                $archiveLimit = (int) ($archiveConfig['per_page'] ?? $perPage);
                $archiveLimit = max(1, min(100, $archiveLimit));
                $archiveSql = 'SELECT ' . $selectClause . ' FROM ' . $tableIdentifier .
                    ' WHERE ' . $archivedCondition .
                    ' ORDER BY ' . $orderBy .
                    ' LIMIT ' . $archiveLimit;
                $archiveResult = mysqli_query($conn, $archiveSql);
                if ($archiveResult) {
                    while ($archivedRow = mysqli_fetch_assoc($archiveResult)) {
                        $archivedRows[] = $archivedRow;
                    }
                }
            }
        }
    }
} else {
    $tableError = 'Database connection error.';
}

function formatDatasetValue($value, array $column): string
{
    if ($value === null || $value === '') {
        return htmlspecialchars('N/A', ENT_QUOTES, 'UTF-8');
    }

    $trimmed = is_string($value) ? trim($value) : $value;

    if (!isset($column['format'])) {
        return htmlspecialchars((string) $trimmed, ENT_QUOTES, 'UTF-8');
    }

    $timestamp = strtotime((string) $trimmed);
    if ($timestamp === false) {
        return htmlspecialchars((string) $trimmed, ENT_QUOTES, 'UTF-8');
    }

    switch ($column['format']) {
        case 'date':
            return htmlspecialchars(date('Y-m-d', $timestamp), ENT_QUOTES, 'UTF-8');
        case 'datetime':
            return htmlspecialchars(date('Y-m-d H:i', $timestamp), ENT_QUOTES, 'UTF-8');
        default:
            return htmlspecialchars((string) $trimmed, ENT_QUOTES, 'UTF-8');
    }
}

$columnCount = count($columns) + ($hasActions ? 1 : 0);
$viewQueryString = 'view=' . urlencode($selectedView);
$datasetFieldNames = array_keys($formFields);
$displayField = $currentDataset['display_field'] ?? null;
$defaultSuccessMessage = ($currentDataset['singular'] ?? 'Record') . ' updated successfully.';

$flashPayload = null;
if (isset($_GET['flash_status'])) {
    $status = $_GET['flash_status'] === 'success' ? 'success' : 'error';
    $flashMessageRaw = $_GET['flash_message'] ?? ($status === 'success' ? $defaultSuccessMessage : 'Unable to process the request.');
    $flashPayload = [
        'status' => $status,
        'message' => $flashMessageRaw,
    ];
}

function buildCategoriesPageUrl(int $page): string
{
    global $selectedView;

    $params = $_GET;
    unset($params['flash_status'], $params['flash_message']);
    $params['view'] = $params['view'] ?? $selectedView;
    $params['page'] = max(1, $page);

    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management</title>
    <link rel="stylesheet" href="../admin/styles.css">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
</head>
<body class="admin-page categories-page">
    <?php renderAdminSidebar(); ?>
        <main class="content">
        <div class="header">
            <div>
                <h1>Categories Management</h1>
                <p class="lead">Viewing <?php echo htmlspecialchars($currentDataset['label'] ?? 'Records', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="header-actions" style="display:flex; gap:12px; align-items:center;">
                <form class="dataset-switcher" method="GET" style="display:flex; gap:8px; align-items:center;">
                    <select id="categoryView" name="view" aria-label="Select dataset" onchange="this.form.submit()" class="filter-select" style="min-width:220px;">
                        <?php foreach ($datasets as $key => $datasetOption): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $key === $selectedView ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($datasetOption['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="page" value="1">
                    <noscript>
                        <button type="submit" class="btn btn-primary">Go</button>
                    </noscript>
                </form>
                <?php if ($allowCreate): ?>
                    <button class="btn btn-primary" type="button" id="addRecordBtn">Add <?php echo htmlspecialchars($currentDataset['singular'] ?? 'Entry', ENT_QUOTES, 'UTF-8'); ?></button>
                <?php endif; ?>
                <?php if ($archiveEnabled): ?>
                    <button class="btn btn-secondary" type="button" id="viewArchivedBtn">
                        <i class="fas fa-box-archive"></i>
                        Archived <?php echo htmlspecialchars($currentDataset['label'] ?? 'Records', ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <th><?php echo htmlspecialchars($column['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                    <?php endforeach; ?>
                    <?php if ($hasActions): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($tableError !== null): ?>
                    <tr>
                        <td colspan="<?php echo $columnCount; ?>"><?php echo htmlspecialchars($tableError, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php elseif (empty($rows)): ?>
                    <tr>
                        <td colspan="<?php echo $columnCount; ?>">No records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <?php $columnKey = $column['key']; ?>
                                <td><?php echo formatDatasetValue($row[$columnKey] ?? null, $column); ?></td>
                            <?php endforeach; ?>
                            <?php if ($hasActions): ?>
                                <td class="table-actions">
                                    <?php
                                    $recordIdValue = $primaryKey ? ($row[$primaryKey] ?? '') : '';
                                    $recordIdAttr = htmlspecialchars((string) $recordIdValue, ENT_QUOTES, 'UTF-8');
                                    $displayValue = $displayField ? ($row[$displayField] ?? '') : '';
                                    $displayValueAttr = htmlspecialchars((string) $displayValue, ENT_QUOTES, 'UTF-8');
                                    $fieldAttrFragments = [];
                                    foreach ($datasetFieldNames as $fieldName) {
                                        $attrName = 'data-field-' . $fieldName;
                                        $attrValue = htmlspecialchars((string) ($row[$fieldName] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $fieldAttrFragments[] = $attrName . '="' . $attrValue . '"';
                                    }
                                    if (!$primaryAutoIncrement && $primaryKey) {
                                        $fieldAttrFragments[] = 'data-primary-value="' . $recordIdAttr . '"';
                                    }
                                    $fieldAttrFragments[] = 'data-display-value="' . $displayValueAttr . '"';
                                    $fieldAttrFragments[] = 'data-record-id="' . $recordIdAttr . '"';
                                    $fieldAttrString = implode(' ', $fieldAttrFragments);
                                    ?>
                                    <?php if ($allowUpdate): ?>
                                        <button class="btn btn-secondary edit-record-btn" <?php echo $fieldAttrString; ?>>Edit</button>
                                    <?php endif; ?>
                                    <?php if ($allowArchive): ?>
                                        <button class="btn btn-warning archive-record-btn" data-record-id="<?php echo $recordIdAttr; ?>" data-display-value="<?php echo $displayValueAttr; ?>">
                                            <i class="fas fa-box-archive"></i> Archive
                                        </button>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
                <?php if ($totalPages > 1): ?>
                <?php
                    $maxVisiblePages = 3;
                    $startPage = max(1, $currentPage - intdiv($maxVisiblePages, 2));
                    $endPage = min($totalPages, $startPage + $maxVisiblePages - 1);
                    if (($endPage - $startPage + 1) < $maxVisiblePages) {
                        $startPage = max(1, $endPage - $maxVisiblePages + 1);
                    }
                ?>
                <nav class="pagination-nav" aria-label="Category pagination">
                    <ul class="pagination-list">
                        <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $currentPage === 1 ? '#' : buildCategoriesPageUrl(1); ?>" aria-label="First page">&lt;&lt;</a>
                        </li>
                        <li class="page-item<?php echo $currentPage === 1 ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $currentPage === 1 ? '#' : buildCategoriesPageUrl($currentPage - 1); ?>" aria-label="Previous page">&lt;</a>
                        </li>
                        <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                        <li class="page-item<?php echo $page === $currentPage ? ' active' : ''; ?>">
                            <a class="page-link" href="<?php echo buildCategoriesPageUrl($page); ?>"><?php echo $page; ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $currentPage === $totalPages ? '#' : buildCategoriesPageUrl($currentPage + 1); ?>" aria-label="Next page">&gt;</a>
                        </li>
                        <li class="page-item<?php echo $currentPage === $totalPages ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $currentPage === $totalPages ? '#' : buildCategoriesPageUrl($totalPages); ?>" aria-label="Last page">&gt;&gt;</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
    </main>

    <div id="datasetModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" type="button" aria-label="Close" data-close-modal>&times;</button>
            <div class="modal-header">
                <h2 id="datasetModalTitle">Edit <?php echo htmlspecialchars($currentDataset['singular'] ?? 'Record', ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>
            <form
                id="datasetForm"
                method="POST"
                action="../adminbackend/manage_dataset.php?<?php echo $viewQueryString; ?>"
            >
                <input type="hidden" name="dataset" id="datasetKey" value="<?php echo htmlspecialchars($selectedView, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="operation" id="operation" value="update">
                <input type="hidden" name="record_id" id="recordId" value="">

                <?php if ($primaryKey): ?>
                    <div class="form-group" data-primary-container<?php echo $primaryAutoIncrement ? ' style="display:none;"' : ''; ?>>
                        <label for="primaryField"><?php echo htmlspecialchars($primaryKeyConfig['label'] ?? 'Record ID', ENT_QUOTES, 'UTF-8'); ?></label>
                        <input
                            type="<?php echo htmlspecialchars($primaryKeyConfig['type'] ?? ($primaryAutoIncrement ? 'text' : 'number'), ENT_QUOTES, 'UTF-8'); ?>"
                            id="primaryField"
                            aria-describedby="primaryHelp"
                            data-primary-input
                            <?php echo $primaryAutoIncrement ? 'readonly' : 'required'; ?>
                        >
                        <small id="primaryHelp" class="help-text" data-primary-help></small>
                    </div>
                <?php endif; ?>

                <?php foreach ($formFields as $fieldName => $fieldConfig): ?>
                    <?php
                    $fieldId = 'field_' . $fieldName;
                    $fieldLabel = $fieldConfig['label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
                    $fieldType = $fieldConfig['type'] ?? 'text';
                    $isRequired = !empty($fieldConfig['required']);
                    ?>
                    <div class="form-group">
                        <label for="<?php echo htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                        <?php if ($fieldType === 'textarea'): ?>
                            <textarea
                                id="<?php echo htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'); ?>"
                                name="<?php echo htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8'); ?>"
                                rows="4"
                                <?php echo $isRequired ? 'required' : ''; ?>
                            ></textarea>
                        <?php else: ?>
                            <input
                                type="<?php echo htmlspecialchars($fieldType, ENT_QUOTES, 'UTF-8'); ?>"
                                id="<?php echo htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'); ?>"
                                name="<?php echo htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $isRequired ? 'required' : ''; ?>
                                <?php echo $fieldType === 'number' ? 'step="1"' : ''; ?>
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary" id="datasetSubmitBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($archiveEnabled): ?>
        <div id="archiveModal" class="modal modal-wide">
            <div class="modal-content">
                <button class="modal-close" type="button" aria-label="Close" data-close-archive>&times;</button>
                <div class="modal-header">
                    <h2>Archived <?php echo htmlspecialchars($currentDataset['label'] ?? 'Records', ENT_QUOTES, 'UTF-8'); ?></h2>
                </div>
                <div class="modal-body">
                    <?php if (empty($archivedRows)): ?>
                        <p>No archived <?php echo htmlspecialchars(strtolower($currentDataset['label'] ?? 'records'), ENT_QUOTES, 'UTF-8'); ?> yet.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                        <th><?php echo htmlspecialchars($column['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                                    <?php endforeach; ?>
                                    <?php if ($allowRestore): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($archivedRows as $archivedRow): ?>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <?php $columnKey = $column['key']; ?>
                                            <td><?php echo formatDatasetValue($archivedRow[$columnKey] ?? null, $column); ?></td>
                                        <?php endforeach; ?>
                                        <?php if ($allowRestore): ?>
                                            <?php
                                            $archivedRecordId = $primaryKey ? ($archivedRow[$primaryKey] ?? '') : '';
                                            $archivedRecordAttr = htmlspecialchars((string) $archivedRecordId, ENT_QUOTES, 'UTF-8');
                                            $archivedDisplay = $displayField ? ($archivedRow[$displayField] ?? '') : '';
                                            $archivedDisplayAttr = htmlspecialchars((string) $archivedDisplay, ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <td>
                                                <button class="btn btn-primary restore-record-btn" data-record-id="<?php echo $archivedRecordAttr; ?>" data-display-value="<?php echo $archivedDisplayAttr; ?>">
                                                    Restore
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-archive>Close</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        (function() {
            const datasetMeta = <?php echo json_encode([
                'key' => $selectedView,
                'label' => $currentDataset['label'] ?? 'Records',
                'singular' => $currentDataset['singular'] ?? 'Record',
                'allow' => $actionPermissions,
                'primary' => [
                    'auto_increment' => (bool) $primaryAutoIncrement,
                    'immutable' => !empty($primaryKeyConfig['immutable']),
                    'label' => $primaryKeyConfig['label'] ?? 'Record ID',
                ],
                'archive' => [
                    'enabled' => $archiveEnabled,
                ],
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

            const fieldNames = <?php echo json_encode($datasetFieldNames, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            const manageUrl = '../adminbackend/manage_dataset.php?<?php echo $viewQueryString; ?>';
            const flashPayload = <?php echo json_encode($flashPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

            const modal = document.getElementById('datasetModal');
            const form = document.getElementById('datasetForm');
            const modalTitle = document.getElementById('datasetModalTitle');
            const submitBtn = document.getElementById('datasetSubmitBtn');
            const operationInput = document.getElementById('operation');
            const datasetInput = document.getElementById('datasetKey');
            const recordIdInput = document.getElementById('recordId');
            const addButton = document.getElementById('addRecordBtn');
            const primaryContainer = modal.querySelector('[data-primary-container]');
            const primaryInput = modal.querySelector('[data-primary-input]');
            const primaryHelp = modal.querySelector('[data-primary-help]');
            const archiveModal = document.getElementById('archiveModal');
            const viewArchivedBtn = document.getElementById('viewArchivedBtn');
            const fieldRefs = {};

            fieldNames.forEach((name) => {
                const ref = document.getElementById('field_' + name);
                if (ref) {
                    fieldRefs[name] = ref;
                }
            });

            if (datasetInput) {
                datasetInput.value = datasetMeta.key;
            }

            if (flashPayload) {
                const icon = flashPayload.status === 'success' ? 'success' : 'error';
                Swal.fire({
                    icon,
                    title: icon === 'success' ? 'Success' : 'Error',
                    text: flashPayload.message,
                    confirmButtonColor: '#2563eb',
                    timer: icon === 'success' ? 2600 : undefined,
                    showConfirmButton: icon !== 'success',
                });
            }

            if (primaryHelp) {
                primaryHelp.textContent = datasetMeta.primary.auto_increment
                    ? 'Auto-generated when you save a new record.'
                    : 'Required. Provide a unique value for this record.';
            }

            if (primaryInput && !datasetMeta.primary.auto_increment) {
                primaryInput.addEventListener('input', function() {
                    recordIdInput.value = primaryInput.value.trim();
                });
            }

            const resetModalState = () => {
                form.reset();
                recordIdInput.value = '';
                operationInput.value = 'update';
                submitBtn.textContent = 'Save Changes';
                modalTitle.textContent = 'Edit ' + datasetMeta.singular;

                if (primaryContainer) {
                    if (datasetMeta.primary.auto_increment) {
                        primaryContainer.style.display = 'none';
                    } else {
                        primaryContainer.style.display = '';
                        primaryInput.value = '';
                        primaryInput.readOnly = false;
                        primaryInput.disabled = false;
                    }
                }

                Object.values(fieldRefs).forEach((field) => {
                    field.value = '';
                    field.readOnly = false;
                    field.disabled = false;
                });
            };

            const closeModal = () => {
                modal.classList.remove('show');
                resetModalState();
            };

            const closeArchiveModal = () => {
                archiveModal?.classList.remove('show');
            };

            const openArchiveModal = () => {
                if (!archiveModal) {
                    return;
                }
                archiveModal.classList.add('show');
                const focusTarget = archiveModal.querySelector('.restore-record-btn') || archiveModal.querySelector('[data-close-archive]');
                focusTarget?.focus();
            };

            const openModal = (mode, trigger) => {
                if (mode === 'add') {
                    operationInput.value = 'add';
                    modalTitle.textContent = 'Add ' + datasetMeta.singular;
                    submitBtn.textContent = 'Add ' + datasetMeta.singular;
                    recordIdInput.value = '';

                    if (primaryContainer) {
                        if (datasetMeta.primary.auto_increment) {
                            primaryContainer.style.display = 'none';
                            primaryInput.value = '';
                        } else {
                            primaryContainer.style.display = '';
                            primaryInput.value = '';
                            primaryInput.readOnly = false;
                            primaryInput.disabled = false;
                        }
                    }
                } else {
                    operationInput.value = 'update';
                    modalTitle.textContent = 'Edit ' + datasetMeta.singular;
                    submitBtn.textContent = 'Save Changes';

                    if (trigger) {
                        const recordId = trigger.getAttribute('data-record-id') || '';
                        recordIdInput.value = recordId;

                        if (primaryContainer) {
                            primaryContainer.style.display = '';
                            if (datasetMeta.primary.auto_increment) {
                                primaryInput.value = recordId;
                                primaryInput.readOnly = true;
                                primaryInput.disabled = false;
                            } else {
                                const primaryValue = trigger.getAttribute('data-primary-value') || recordId;
                                primaryInput.value = primaryValue;
                                recordIdInput.value = primaryValue;
                                primaryInput.readOnly = datasetMeta.primary.immutable;
                                primaryInput.disabled = false;
                            }
                        }

                        fieldNames.forEach((name) => {
                            const ref = fieldRefs[name];
                            if (!ref) {
                                return;
                            }
                            const value = trigger.getAttribute('data-field-' + name) ?? '';
                            ref.value = value;
                        });
                    }
                }

                modal.classList.add('show');
                const firstField = primaryContainer && !datasetMeta.primary.auto_increment ? primaryInput : fieldRefs[fieldNames[0]];
                (firstField || submitBtn).focus();
            };

            addButton?.addEventListener('click', () => openModal('add'));

            document.querySelectorAll('.edit-record-btn').forEach((button) => {
                button.addEventListener('click', () => openModal('edit', button));
            });

            modal.querySelectorAll('[data-close-modal]').forEach((closeBtn) => {
                closeBtn.addEventListener('click', closeModal);
            });

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            if (archiveModal) {
                archiveModal.querySelectorAll('[data-close-archive]').forEach((button) => {
                    button.addEventListener('click', closeArchiveModal);
                });

                archiveModal.addEventListener('click', (event) => {
                    if (event.target === archiveModal) {
                        closeArchiveModal();
                    }
                });
            }

            viewArchivedBtn?.addEventListener('click', openArchiveModal);

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    if (modal.classList.contains('show')) {
                        closeModal();
                        return;
                    }
                    if (archiveModal?.classList.contains('show')) {
                        closeArchiveModal();
                    }
                }
            });

            const submitDatasetOperation = (operation, recordId) => {
                if (!recordId) {
                    return;
                }
                const opForm = document.createElement('form');
                opForm.method = 'POST';
                opForm.action = manageUrl;

                const datasetField = document.createElement('input');
                datasetField.type = 'hidden';
                datasetField.name = 'dataset';
                datasetField.value = datasetMeta.key;
                opForm.appendChild(datasetField);

                const operationField = document.createElement('input');
                operationField.type = 'hidden';
                operationField.name = 'operation';
                operationField.value = operation;
                opForm.appendChild(operationField);

                const idField = document.createElement('input');
                idField.type = 'hidden';
                idField.name = 'record_id';
                idField.value = recordId;
                opForm.appendChild(idField);

                document.body.appendChild(opForm);
                opForm.submit();
            };

            document.querySelectorAll('.archive-record-btn').forEach((button) => {
                button.addEventListener('click', async () => {
                    const recordId = button.getAttribute('data-record-id');
                    const displayValue = button.getAttribute('data-display-value') || '';
                    const result = await Swal.fire({
                        icon: 'warning',
                        title: 'Archive ' + datasetMeta.singular + '?',
                        text: displayValue ? '"' + displayValue + '" will move to archive.' : 'This record will be hidden from the table.',
                        showCancelButton: true,
                        confirmButtonColor: '#f97316',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Archive',
                        cancelButtonText: 'Cancel',
                    });

                    if (!result.isConfirmed) {
                        return;
                    }

                    submitDatasetOperation('archive', recordId);
                });
            });

            document.querySelectorAll('.restore-record-btn').forEach((button) => {
                button.addEventListener('click', async () => {
                    const recordId = button.getAttribute('data-record-id');
                    const displayValue = button.getAttribute('data-display-value') || '';
                    const result = await Swal.fire({
                        icon: 'question',
                        title: 'Restore ' + datasetMeta.singular + '?',
                        text: displayValue ? 'Bring back "' + displayValue + '" to the main list.' : 'This record will return to the active table.',
                        showCancelButton: true,
                        confirmButtonColor: '#2563eb',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Restore',
                        cancelButtonText: 'Cancel',
                    });

                    if (!result.isConfirmed) {
                        return;
                    }

                    submitDatasetOperation('restore', recordId);
                });
            });

        })();
    </script>
</body>
</html>