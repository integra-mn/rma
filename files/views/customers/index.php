<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- Add + Search -->
  <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;flex-wrap:wrap;">
    <?php if (can('customers', 'create')): ?>
      <button type="button" class="btn btn-primary" style="min-width:140px;"
              onclick="document.getElementById('add-form').style.display=document.getElementById('add-form').style.display==='none'?'block':'none'">
        <?= __('customers.add') ?>
      </button>
    <?php endif; ?>
    <div style="width:300px;flex-shrink:0;">
      <input type="text" id="cust-search" data-list-state placeholder="<?= __('customers.search') ?>"
             oninput="filterCustomers(this.value)" style="width:100%;">
    </div>
  </div>

  <!-- Add form (hidden) -->
  <?php if (can('customers', 'create')): ?>
  <div id="add-form" style="display:none;margin-bottom:1.25rem;" class="card">
    <h2 style="font-size:15px;font-weight:500;margin-bottom:1rem;"><?= __('customers.new') ?></h2>
    <form method="POST" action="/customers/store">
      <?= csrf_field() ?>
      <div class="form-grid" style="">
        <div class="field"><label><?= __('label.name') ?> *</label><input type="text" name="name" required></div>
        <div class="field"><label><?= __('label.address') ?></label><input type="text" name="address"></div>
        <div class="field"><label><?= __('customers.zip_code') ?></label><input type="text" name="zip_code"></div>
        <div class="field"><label><?= __('label.city') ?></label><input type="text" name="city"></div>
        <div class="field"><label><?= __('label.country') ?></label><input type="text" name="country"></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone"></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email"></div>
      </div>
      <div class="field"><label><?= __('label.notes') ?></label><textarea name="notes" rows="2"></textarea></div>
      <div style="display:flex;gap:8px;margin-top:4px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('add-form').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if (empty($customers)): ?>
    <p style="font-size:13px;color:var(--text-muted);"><?= __('customers.no_results') ?></p>
  <?php else: ?>
    <table class="data-table" id="cust-table" style="table-layout:fixed;width:100%;">
      <thead>
        <tr>
          <th style="width:18%;"><?= __('label.name') ?></th>
          <th style="width:18%;"><?= __('label.address') ?></th>
          <th style="width:16%;"><?= __('label.phone') ?></th>
          <th style="width:16%;"><?= __('label.email') ?></th>
          <th style="width:12%;"><?= __('label.city') ?></th>
          <th style="width:10%;"><?= __('label.country') ?></th>
          <th style="width:10%;text-align:right;"><?= __('label.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
          <tr onclick="window.location='/customers/<?= (int)$c['id'] ?>/edit'"
              data-name="<?= strtolower(htmlspecialchars($c['name'])) ?>"
              data-email="<?= strtolower(htmlspecialchars($c['email'] ?? '')) ?>"
              data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>">
            <td style="font-weight:500;"><?= htmlspecialchars($c['name']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['address'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['email'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['city'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['country'] ?? '—') ?></td>
            <td style="text-align:right;"><a href="/customers/<?= (int)$c['id'] ?>/edit" onclick="event.stopPropagation()" class="btn-link"><?= __('btn.edit') ?></a></td>
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
  /* Ellipsis-on-overflow so long emails/addresses don't wrap rows into 2 lines. */
  #cust-table td, #cust-table th {
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
</style>

<script>
function filterCustomers(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#cust-table tbody tr').forEach(row => {
    const match = !q || row.dataset.name.includes(q) || row.dataset.email.includes(q) || row.dataset.phone.includes(q);
    row.style.display = match ? '' : 'none';
  });
}
</script>
