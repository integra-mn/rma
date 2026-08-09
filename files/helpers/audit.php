<?php
defined('RMS') or die('Direct access not permitted');

function audit(string $action, ?string $entity_type = null, ?int $entity_id = null, array $data = []): void {
    try {
        db_insert('audit_log', [
            'user_id'     => current_user_id(),
            'action'      => $action,
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'old_values'  => isset($data['old']) ? json_encode($data['old']) : null,
            'new_values'  => isset($data['new']) ? json_encode($data['new']) : null,
            'ip_address'  => client_ip(),
        ]);
    } catch (Throwable) {
        // Never let audit failure break the request
    }
}

function audit_change(string $entity_type, int $entity_id, array $old, array $new): void {
    $changed = array_filter($new, fn($v, $k) => ($old[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH);
    if (empty($changed)) return;

    $old_subset = array_intersect_key($old, $changed);
    audit('updated', $entity_type, $entity_id, ['old' => $old_subset, 'new' => $changed]);
}
