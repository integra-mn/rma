<?php
/**
 * Evidence / Photos card
 * Usage: include with $ev_repair_id, $ev_rma_id, $ev_stage, $ev_card_id
 */
$ev_photos = db_rows(
    'SELECT * FROM repair_evidence WHERE '
    . ($ev_repair_id ? 'repair_job_id = ?' : 'rma_id = ?')
    . ' AND stage = ? AND deleted_at IS NULL ORDER BY created_at ASC',
    [$ev_repair_id ?: $ev_rma_id, $ev_stage]
);
$ev_count = count($ev_photos);
?>
<div class="card" id="ev-card-<?= $ev_card_id ?>" style="margin-bottom:1rem;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:<?= $ev_count ? '1rem' : '0' ?>;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin:0;">
      <?= __('misc.photo_evidence') ?>
    </h2>
    <div style="display:flex;gap:6px;align-items:center;">
      <button type="button" class="btn" title="<?= __('misc.upload_from_phone') ?>"
        onclick="evQR('<?= $ev_card_id ?>', <?= (int)$ev_repair_id ?>, <?= (int)$ev_rma_id ?>, '<?= $ev_stage ?>')"
        style="min-width:120px;justify-content:center;display:flex;align-items:center;gap:5px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="3" height="3"/>
        </svg>
        <?= __('label.phone') ?>
      </button>
      <label for="ev-input-<?= $ev_card_id ?>" class="btn" style="min-width:120px;justify-content:center;cursor:pointer;margin:0;display:flex;align-items:center;gap:5px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <?= __('btn.add') ?>
      </label>
      <input type="file" id="ev-input-<?= $ev_card_id ?>" accept="image/*" multiple style="display:none;"
        data-card="<?= $ev_card_id ?>"
        data-repair="<?= (int)$ev_repair_id ?>"
        data-rma="<?= (int)$ev_rma_id ?>"
        data-stage="<?= $ev_stage ?>">
    </div>
  </div>

  <div id="ev-grid-<?= $ev_card_id ?>" style="<?= $ev_count ? 'display:grid;' : 'display:none;' ?>grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:8px;">
    <?php foreach ($ev_photos as $ev): ?>
      <div class="ev-thumb" data-id="<?= $ev['id'] ?>" style="position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;background:var(--bg-subtle);">
        <img src="/storage/<?= htmlspecialchars($ev['filename']) ?>"
          style="width:100%;height:100%;object-fit:cover;cursor:pointer;"
          onclick="evLightbox(this.src, '<?= htmlspecialchars(addslashes($ev['original_name'])) ?>')">
        <button onclick="evDelete(<?= $ev['id'] ?>, '<?= $ev_card_id ?>')"
          class="ev-del-btn" style="position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.55);border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;opacity:0;transition:opacity 0.15s;">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    <?php endforeach; ?>
  </div>

  <div id="ev-progress-<?= $ev_card_id ?>" style="display:none;margin-top:8px;">
    <div style="height:3px;background:var(--bg-subtle);border-radius:2px;overflow:hidden;">
      <div id="ev-bar-<?= $ev_card_id ?>" style="height:100%;background:var(--accent);width:0%;transition:width 0.3s;"></div>
    </div>
    <p id="ev-status-<?= $ev_card_id ?>" style="font-size:12px;color:var(--text-muted);margin:4px 0 0;"></p>
  </div>
</div>

<!-- QR Modal -->
<div id="ev-qr-modal-<?= $ev_card_id ?>"
  style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9000;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:16px;padding:2rem;max-width:320px;width:90%;text-align:center;position:relative;box-shadow:0 8px 40px rgba(0,0,0,0.2);">
    <button onclick="evQRClose('<?= $ev_card_id ?>')"
      style="position:absolute;top:12px;right:12px;background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:20px;line-height:1;padding:4px;">✕</button>
    <h3 style="font-size:15px;font-weight:500;margin-bottom:4px;"><?= __('misc.upload_from_phone') ?></h3>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:1.25rem;"><?= __('misc.scan_phone') ?></p>
    <div id="ev-qr-img-<?= $ev_card_id ?>" style="display:flex;justify-content:center;align-items:center;min-height:210px;margin-bottom:1rem;">
      <p style="font-size:12px;color:var(--text-muted);"><?= __('misc.generating') ?></p>
    </div>
    <p style="font-size:11px;color:var(--text-muted);margin-bottom:8px;line-height:1.5;"><?= __('misc.qr_valid') ?><br><?= __('misc.qr_valid2') ?></p>
    <div id="ev-qr-poll-<?= $ev_card_id ?>" style="display:none;font-size:12px;color:var(--accent);"><?= __('misc.waiting_photos') ?></div>
  </div>
</div>
<style>.ev-thumb:hover .ev-del-btn { opacity:1 !important; }</style>
