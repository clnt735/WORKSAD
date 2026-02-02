<?php
if (!function_exists('log_admin_action')) {
    /**
     * Store a descriptive admin action in the audit log.
     */
    function log_admin_action(mysqli $conn, ?int $adminId, string $action): void
    {
        if (!$adminId || $adminId <= 0 || trim($action) === '') {
            return;
        }

        $stmt = $conn->prepare('INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())');
        if ($stmt === false) {
            return;
        }

        $trimmedAction = trim($action);
        $stmt->bind_param('is', $adminId, $trimmedAction);
        $stmt->execute();
        $stmt->close();
    }
}
