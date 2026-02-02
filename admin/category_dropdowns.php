<?php include '../database.php'; ?>
<?php include '../admin/sidebar.php'; ?>
<?php require_once __DIR__ . '/category_helpers.php'; ?>
<?php

$datasets = load_category_datasets();
$flashStatus = null;
$flashMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_label') {
    $datasetKey = strtolower(trim($_POST['dataset_key'] ?? ''));
    $newLabel = trim($_POST['label'] ?? '');

    if ($datasetKey === '' || !isset($datasets[$datasetKey])) {
        $flashStatus = 'danger';
        $flashMessage = 'Dataset not found.';
    } elseif ($newLabel === '') {
        $flashStatus = 'danger';
        $flashMessage = 'Label cannot be empty.';
    } else {
        $datasets[$datasetKey]['label'] = $newLabel;
        if (isset($datasets[$datasetKey]['singular'])) {
            $datasets[$datasetKey]['singular'] = dataset_generate_singular_label($newLabel);
        }

        if (save_category_datasets($datasets)) {
            $flashStatus = 'success';
            $flashMessage = 'Label updated.';
            $datasets = load_category_datasets();
        } else {
            $flashStatus = 'danger';
            $flashMessage = 'Unable to save label changes.';
        }
    }
}

$overviewRows = [];

function format_dataset_count($value)
{
    return is_numeric($value) ? number_format((int) $value) : 'N/A';
}

foreach ($datasets as $datasetKey => $dataset) {
    $label = $dataset['label'] ?? ucwords(str_replace('_', ' ', $datasetKey));
    $tableName = $dataset['table'] ?? '';
    $tableIdentifier = $tableName !== '' ? wrap_db_identifier($tableName) : null;
    $archiveConfig = get_dataset_archive_config($dataset);
    $supportsArchive = dataset_supports_archive($dataset);

    $row = [
        'key' => $datasetKey,
        'label' => $label,
        'table' => $tableName,
        'total' => null,
        'active' => null,
        'archived' => null,
        'supportsArchive' => $supportsArchive,
        'error' => null,
    ];

    if ($conn && $tableIdentifier !== null) {
        $countSql = 'SELECT COUNT(*) AS total FROM ' . $tableIdentifier;
        $countResult = mysqli_query($conn, $countSql);
        if ($countResult instanceof mysqli_result) {
            $row['total'] = (int) (mysqli_fetch_assoc($countResult)['total'] ?? 0);
            mysqli_free_result($countResult);
        } else {
            $row['error'] = 'Unable to count records for this dataset.';
        }

        if ($row['error'] === null && $supportsArchive) {
            $activeCondition = build_archive_condition($archiveConfig, false);
            $archivedCondition = build_archive_condition($archiveConfig, true);

            if ($activeCondition && $archivedCondition) {
                $activeSql = 'SELECT COUNT(*) AS total FROM ' . $tableIdentifier . ' WHERE ' . $activeCondition;
                $activeResult = mysqli_query($conn, $activeSql);
                if ($activeResult instanceof mysqli_result) {
                    $row['active'] = (int) (mysqli_fetch_assoc($activeResult)['total'] ?? 0);
                    mysqli_free_result($activeResult);
                } else {
                    $row['error'] = 'Unable to count active records.';
                }

                if ($row['error'] === null) {
                    $archivedSql = 'SELECT COUNT(*) AS total FROM ' . $tableIdentifier . ' WHERE ' . $archivedCondition;
                    $archivedResult = mysqli_query($conn, $archivedSql);
                    if ($archivedResult instanceof mysqli_result) {
                        $row['archived'] = (int) (mysqli_fetch_assoc($archivedResult)['total'] ?? 0);
                        mysqli_free_result($archivedResult);
                    } else {
                        $row['error'] = 'Unable to count archived records.';
                    }
                }
            }
        }
    } else {
        $row['error'] = $conn ? 'Invalid dataset table configuration.' : 'Database connection error.';
    }

    $overviewRows[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dropdown Categories Overview</title>
    <link rel="stylesheet" href="../admin/styles.css">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
</head>
<body class="admin-page dropdown-overview-page">
    <?php renderAdminSidebar(); ?>
    <main class="content">
        <div class="header">
            <div>
                <h1>Dropdown Categories</h1>
                <p class="lead">Overview of all categoryView dropdown datasets.</p>
            </div>
            <div class="header-actions" style="display:flex; gap:12px; align-items:center;">
                <a class="btn btn-primary" href="add_dataset.php">Add Datasets</a>
            </div>
        </div>

        <?php if ($flashStatus !== null): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flashStatus, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <table class="table">
            <thead>
                <tr>
                    <th>Dropdown Label</th>
                    <th>Dataset Key</th>
                    <th>Table Name</th>
                    <th>Total Records</th>
                    <th>Active Records</th>
                    <th>Archived Records</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($overviewRows as $row): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($row['error'] !== null): ?>
                                <div class="status-pill danger" style="margin-top:6px;">
                                    <?php echo htmlspecialchars($row['error'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['key'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $row['table'] !== '' ? htmlspecialchars($row['table'], ENT_QUOTES, 'UTF-8') : 'N/A'; ?></td>
                        <td><?php echo format_dataset_count($row['total']); ?></td>
                        <td>
                            <?php
                            if ($row['supportsArchive']) {
                                echo format_dataset_count($row['active']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($row['supportsArchive']) {
                                echo format_dataset_count($row['archived']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a class="btn btn-secondary" href="categories.php?view=<?php echo urlencode($row['key']); ?>">Open Dataset</a>
                                <button
                                    type="button"
                                    class="btn btn-outline edit-label-btn"
                                    data-dataset-key="<?php echo htmlspecialchars($row['key'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-current-label="<?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    Edit Label
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <div id="editLabelModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" type="button" aria-label="Close" data-close-modal>&times;</button>
            <div class="modal-header">
                <h2>Edit Dropdown Label</h2>
            </div>
            <form id="editLabelForm" method="POST" class="modal-body">
                <input type="hidden" name="action" value="edit_label">
                <input type="hidden" name="dataset_key" id="editLabelKey">
                <div class="form-group">
                    <label for="editLabelValue">Label</label>
                    <input type="text" id="editLabelValue" name="label" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const modal = document.getElementById('editLabelModal');
            const keyInput = document.getElementById('editLabelKey');
            const labelInput = document.getElementById('editLabelValue');

            const closeModal = () => {
                modal.classList.remove('show');
                keyInput.value = '';
                labelInput.value = '';
            };

            document.querySelectorAll('.edit-label-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    keyInput.value = button.getAttribute('data-dataset-key') || '';
                    labelInput.value = button.getAttribute('data-current-label') || '';
                    modal.classList.add('show');
                    labelInput.focus();
                });
            });

            modal.querySelectorAll('[data-close-modal]').forEach((closeButton) => {
                closeButton.addEventListener('click', closeModal);
            });

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('show')) {
                    closeModal();
                }
            });
        })();
    </script>
</body>
</html>
