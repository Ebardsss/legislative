<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function cepfmsTableExists(string $table): bool
{
    return tableExists($table);
}

function cepfmsSystemId(): ?int
{
    static $cached = false;

    if ($cached !== false) {
        return $cached;
    }

    try {
        $q = db()->prepare(
            "SELECT id
             FROM systems
             WHERE code='citizen'
             LIMIT 1"
        );
        $q->execute();

        $id = $q->fetchColumn();

        return $cached = $id !== false
            ? (int)$id
            : null;
    } catch (Throwable $e) {
        error_log('[CEPFMS system id] ' . $e->getMessage());
        return $cached = null;
    }
}

function cepfmsHasSystemAccess(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    $systemId = cepfmsSystemId();

    if (!$systemId) {
        return false;
    }

    try {
        $q = db()->prepare(
            'SELECT usa.status
             FROM users u
             JOIN user_system_access usa
               ON usa.user_id=u.id
              AND usa.system_id=:system
             WHERE u.id=:user
               AND u.status="Active"
               AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $q->execute([
            ':system' => $systemId,
            ':user' => $userId,
        ]);

        $status = $q->fetchColumn();

        if ($status !== false) {
            return strcasecmp((string)$status, 'Active') === 0;
        }

        $finalRbac=false;
        if(tableExists('cepfms_schema_migrations')){
            $m=db()->prepare(
                'SELECT COUNT(*)
                 FROM cepfms_schema_migrations
                 WHERE migration_key=:key'
            );
            $m->execute([':key'=>'004_admin_rbac_security']);
            $finalRbac=(int)$m->fetchColumn()>0;
        }

        if($finalRbac){
            return false;
        }

        /*
         * Pre-Migration-004 compatibility only:
         * existing internal legislative users may not yet have an explicit
         * citizen-system row. Migration 004 removes this fallback.
         */
        $role = db()->prepare(
            'SELECT r.name
             FROM users u
             JOIN roles r ON r.id=u.role_id
             WHERE u.id=:user
               AND u.status="Active"
               AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $role->execute([':user' => $userId]);
        $roleName = (string)($role->fetchColumn() ?: '');

        return in_array(
            $roleName,
            ['Administrator','Legislative Staff','Committee Member'],
            true
        );
    } catch (Throwable $e) {
        error_log('[CEPFMS access check] ' . $e->getMessage());
        return false;
    }
}

function cepfmsLogActivity(?int $userId, string $action, string $details = ''): void
{
    try {
        if (!tableExists('activity_logs')) {
            return;
        }

        db()->prepare(
            'INSERT INTO activity_logs
             (user_id,system_id,action,details,ip_address,user_agent,created_at)
             VALUES(:user,:system,:action,:details,:ip,:agent,NOW())'
        )->execute([
            ':user' => $userId ?: null,
            ':system' => cepfmsSystemId(),
            ':action' => $action,
            ':details' => $details ?: null,
            ':ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            ':agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null,
        ]);
    } catch (Throwable $e) {
        error_log('[CEPFMS activity log] ' . $e->getMessage());
    }
}

function cepfmsFoundationTables(): array
{
    return [
        'cepfms_schema_migrations',
        'cef_categories',
        'cef_submissions',
        'cef_feedback_submissions',
        'cef_proposals',
        'cef_complaints',
        'cef_submission_documents',
        'cef_submission_history',
        'cef_moderation_reviews',
        'cef_moderation_checklist',
        'cef_duplicate_matches',
        'cef_assignments',
        'cef_assignment_history',
        'cef_case_updates',
        'cef_escalations',
        'cef_responses',
        'cef_response_history',
        'cef_response_documents',
        'cef_followups',
        'cef_notifications',
        'cef_notification_recipients',
        'cef_ai_analysis',
        'cef_analytics_snapshots',
        'cef_legislative_referrals',
    ];
}

function cepfmsFoundationReady(): bool
{
    foreach (cepfmsFoundationTables() as $table) {
        if (!tableExists($table)) {
            return false;
        }
    }

    return true;
}

function cepfmsGenerateReference(
    PDO $pdo,
    string $table,
    string $column,
    string $prefix = 'CEF',
    ?string $dateValue = null
): string {
    $allowed = [
        'cef_submissions' => 'reference_number',
        'cef_responses' => 'response_reference',
        'cef_notifications' => 'notification_reference',
    ];

    if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
        throw new InvalidArgumentException('Unsupported CEPFMS reference target.');
    }

    $year = $dateValue && strtotime($dateValue) !== false
        ? date('Y', strtotime($dateValue))
        : date('Y');

    $base = $prefix . '-' . $year . '-';

    $q = $pdo->prepare(
        "SELECT `$column`
         FROM `$table`
         WHERE `$column` LIKE :pattern
         ORDER BY id DESC
         LIMIT 1"
    );
    $q->execute([':pattern' => $base . '%']);

    $last = (string)($q->fetchColumn() ?: '');
    $next = 1;

    if ($last !== '' && preg_match('/(\d+)$/', $last, $m)) {
        $next = (int)$m[1] + 1;
    }

    return $base . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}
