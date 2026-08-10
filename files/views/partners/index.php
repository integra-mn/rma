<?php defined('RMS') or die('Direct access not permitted'); ?>

<!-- Same width tier as Administration (admin/index.php caps its tabs the
     same way), so this page lines up with Korisnici rather than running
     to the full window. -->
<div style="padding:1.5rem;max-width:var(--w-content);">

  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- Add + Search -->
  <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;flex-wrap:wrap;">
    <?php if (can('partners', 'edit')): ?>
      <button type="button" class="btn btn-primary" style="min-width:140px;"
              onclick="document.getElementById('add-form').style.display=document.getElementById('add-form').style.display==='none'?'block':'none'">
        <?= __('partners.add') ?>
      </button>
    <?php endif; ?>
    <div style="width:300px;flex-shrink:0;">
      <input type="text" id="partner-search" data-list-state placeholder="<?= __('partners.search') ?>"
             oninput="filterPartners(this.value)" style="width:100%;">
    </div>
  </div>

  <!-- Add form (hidden) -->
  <?php if (can('partners', 'edit')): ?>
  <div id="add-form" style="display:none;margin-bottom:1.25rem;" class="card">
    <h2 style="font-size:15px;font-weight:500;margin-bottom:1rem;"><?= __('partners.new') ?></h2>
    <form method="POST" action="/partners/store">
      <?= csrf_field() ?>
      <!-- Fixed 4-column rows, matching Korisnici and Lokacije. The default
           form-grid is auto-fit, so the column count drifted with the window
           width and the card never lined up with the others. -->
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field"><label><?= __('partners.company') ?></label><input type="text" name="name" required></div>
        <div class="field"><label><?= __('partners.contact_person') ?></label><input type="text" name="contact_person"></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone"></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email"></div>
      </div>
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field"><label><?= __('label.address') ?></label><input type="text" name="address"></div>
        <div class="field"><label><?= __('partners.zip_code') ?></label><input type="text" name="zip_code"></div>
        <div class="field"><label><?= __('label.city') ?></label><input type="text" name="city"></div>
        <div class="field"><label><?= __('label.country') ?></label><input type="text" name="country"></div>
      </div>
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field"><label><?= __('partners.tax_id') ?></label><input type="text" name="tax_id"></div>
        <!-- One line and not resizable, so the row keeps the same height as the
             fields beside it; textareas are min-height 36px and resize:vertical
             by default, which made this cell taller than its neighbours. -->
        <div class="field" style="grid-column:span 3;">
          <label><?= __('label.notes') ?></label>
          <textarea name="notes" rows="1" style="height:36px;min-height:36px;resize:none;overflow:auto;"></textarea>
        </div>
      </div>
      <div style="display:flex;gap:8px;margin-top:4px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('add-form').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if (empty($partners)): ?>
    <p style="font-size:13px;color:var(--text-muted);"><?= __('partners.no_results') ?></p>
  <?php else: ?>
    <table class="data-table" id="partner-table" style="table-layout:fixed;width:100%;">
      <thead>
        <tr>
          <th style="width:18%;"><?= __('partners.company') ?></th>
          <th style="width:18%;"><?= __('partners.contact_person') ?></th>
          <th style="width:16%;"><?= __('label.phone') ?></th>
          <th style="width:16%;"><?= __('label.email') ?></th>
          <th style="width:12%;"><?= __('label.city') ?></th>
          <th style="width:10%;"><?= __('label.country') ?></th>
          <th style="width:10%;text-align:right;"><?= __('label.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($partners as $p): ?>
          <tr onclick="window.location='/partners/<?= (int)$p['id'] ?>/edit'"
              data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>"
              data-email="<?= strtolower(htmlspecialchars($p['email'] ?? '')) ?>">
            <td style="font-weight:500;"><?= htmlspecialchars($p['name']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($p['contact_person'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($p['phone'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($p['email'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($p['city'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($p['country'] ?? '—') ?></td>
            <td style="text-align:right;"><a href="/partners/<?= (int)$p['id'] ?>/edit" onclick="event.stopPropagation()" class="btn-link"><?= __('btn.edit') ?></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($total > $per_page): ?>
      <div style="margin-top:1rem;display:flex;gap:6px;font-size:13px;">
        <?php $pages = ceil($total / $per_page); for ($i = 1; $i <= $pages; $i++): ?>
          <a href="?page=<?= $i ?><?= $search ? '&q='.urlencode($search) : '' ?>"
             style="padding:5px 10px;border:0.5px solid <?= $i===$page?'var(--accent)':'var(--border)' ?>;border-radius:6px;color:<?= $i===$page?'var(--accent)':'var(--text-primary)' ?>;text-decoration:none;">
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<style>
  /* Ellipsis-on-overflow so long emails/names don't wrap rows into 2 lines. */
  #partner-table td, #partner-table th {
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
</style>

<script>
function filterPartners(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#partner-table tbody tr').forEach(row => {
    const match = !q || row.dataset.name.includes(q) || row.dataset.email.includes(q);
    row.style.display = match ? '' : 'none';
  });
}
</script>
