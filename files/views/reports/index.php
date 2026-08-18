<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <!-- Filters -->
  <form method="GET" action="/reports" style="background:#fff;border:0.5px solid var(--border);border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

    <div class="field" style="margin-bottom:0;">
      <label><?= __('reports.from') ?></label>
      <input type="text" class="datefield" data-name="from" style="width:130px;"
             data-value="<?= htmlspecialchars($date_from) ?>">
    </div>
    <div class="field" style="margin-bottom:0;">
      <label><?= __('reports.to') ?></label>
      <input type="text" class="datefield" data-name="to" style="width:130px;"
             data-value="<?= htmlspecialchars($date_to) ?>" data-min-from="from">
    </div>

    <?php if (count($locations) > 1): ?>
    <div class="field" style="margin-bottom:0;">
      <label><?= __('label.location') ?></label>
      <select name="location">
        <option value=""><?= __('reports.all_locations') ?></option>
        <?php foreach ($locations as $l): ?>
          <option value="<?= (int)$l['id'] ?>" <?= $location_id==(int)$l['id']?'selected':'' ?>><?= htmlspecialchars($l['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="field" style="margin-bottom:0;">
      <label><?= __('reports.brand') ?></label>
      <select name="brand">
        <option value=""><?= __('reports.all_brands') ?></option>
        <?php foreach ($brands as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= $brand_id==(int)$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Quick presets, right after the filters -->
    <button type="button" class="btn" onclick="setRange('last_month')"><?= __('reports.last_month') ?></button>
    <button type="button" class="btn" onclick="setRange('last_year')"><?= __('reports.last_year') ?></button>

    <!-- Actions — pushed to the right, all green. No Generate button: the
         report re-runs automatically whenever a filter changes (see script). -->
    <?php // Only the tabs export() actually knows about. It whitelists
          // rma/repairs/parts and silently falls back to 'rma' for anything
          // else, so on Ponovljena popravka these buttons would have handed
          // somebody an RMA report wearing the wrong title. ?>
    <?php if (in_array($tab, ['rma', 'repairs', 'parts'], true)): $qs = 'tab=' . $tab . '&from=' . urlencode($date_from) . '&to=' . urlencode($date_to) . '&brand=' . $brand_id . '&location=' . $location_id; ?>
    <div style="display:flex;gap:6px;align-items:flex-end;margin-left:auto;">
      <a class="btn btn-primary" href="/reports/export?format=xls&<?= $qs ?>"><?= __('reports.export_xls') ?></a>
      <a class="btn btn-primary" href="/reports/export?format=pdf&<?= $qs ?>" target="_blank"><?= __('reports.export_pdf') ?></a>
    </div>
    <?php endif; ?>
  </form>

  <!-- Tabs -->
  <div class="tab-bar">
    <?php foreach (['rma'=>__('nav.rma'),'repairs'=>__('reports.repairs'),'parts'=>__('nav.parts'),'repeat'=>__('settings.repeat'),'financial'=>__('reports.financial')] as $t => $l): ?>
      <a href="?tab=<?= $t ?>&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>&brand=<?= $brand_id ?>&location=<?= $location_id ?>"
         class="tab<?= $tab===$t ? ' active' : '' ?>">
        <?= $l ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ── RMA ── -->
  <?php if ($tab === 'rma'): ?>

    <!-- Summary cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:1.5rem;align-items:stretch;grid-auto-rows:1fr;">
      <?php
      $cards = [
        ['label'=>__('reports.total_rmas'),    'value'=> $total_rma,   'color'=>'var(--accent)'],
        ['label'=>__('reports.open'),          'value'=> $open_rma,    'color'=>'#e8860a'],
        ['label'=>__('reports.closed'),        'value'=> $closed_rma,  'color'=>'#1D9E75'],
        ['label'=>__('reports.avg_days_open'), 'value'=> ($avg_days ?? '—'), 'color'=>'var(--text-secondary)'],
      ];
      foreach ($cards as $c): ?>
        <div class="card" style="text-align:center;display:flex;flex-direction:column;justify-content:center;margin-top:0;">
          <div style="font-size:28px;font-weight:600;color:<?= $c['color'] ?>;"><?= $c['value'] ?></div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $c['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.25rem;">

      <!-- By status -->
      <div>
        <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.by_status') ?></h3>
        <?php if (empty($by_status)): ?>
          <p style="font-size:13px;color:var(--text-muted);"><?= __('reports.no_data') ?></p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th><?= __('label.status') ?></th><th style="text-align:right;"><?= __('reports.count') ?></th></tr></thead>
            <tbody>
              <?php foreach ($by_status as $r): ?>
                <tr>
                  <td><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($r['color']??'#888') ?>;margin-right:6px;"></span><?= htmlspecialchars($r['label']) ?></td>
                  <td style="text-align:right;font-weight:500;"><?= (int)$r['cnt'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- By brand -->
      <div>
        <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.by_brand') ?></h3>
        <?php if (empty($by_brand)): ?>
          <p style="font-size:13px;color:var(--text-muted);"><?= __('reports.no_data') ?></p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th><?= __('reports.brand') ?></th><th style="text-align:right;"><?= __('reports.count') ?></th></tr></thead>
            <tbody>
              <?php foreach ($by_brand as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['brand']) ?></td>
                  <td style="text-align:right;font-weight:500;"><?= (int)$r['cnt'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- By location -->
      <div>
        <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.by_location') ?></h3>
        <?php if (empty($by_location)): ?>
          <p style="font-size:13px;color:var(--text-muted);"><?= __('reports.no_data') ?></p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th><?= __('label.location') ?></th><th style="text-align:right;"><?= __('reports.count') ?></th></tr></thead>
            <tbody>
              <?php foreach ($by_location as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['name'] ?? '—') ?></td>
                  <td style="text-align:right;font-weight:500;"><?= (int)$r['cnt'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </div>

    <!-- By partner branch (poslovnica). Full width because it names both the
         partner and the branch. Hidden until branches are actually in use, so
         the page doesn't grow an empty table for shops that don't have any. -->
    <?php if (!empty($by_branch)): ?>
    <div style="margin-top:1.5rem;">
      <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.by_branch') ?></h3>
      <table class="data-table">
        <thead><tr><th><?= __('rma.partner') ?></th><th><?= __('partners.branch') ?></th><th style="text-align:right;"><?= __('reports.count') ?></th></tr></thead>
        <tbody>
          <?php foreach ($by_branch as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['partner_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['branch_name']) ?></td>
              <td style="text-align:right;font-weight:500;"><?= (int)$r['cnt'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Monthly trend -->
    <?php if (!empty($monthly)): ?>
    <div style="margin-top:1.5rem;">
      <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.monthly_trend') ?></h3>
      <table class="data-table">
        <thead><tr><th><?= __('reports.month') ?></th><th style="text-align:right;"><?= __('reports.rmas') ?></th></tr></thead>
        <tbody>
          <?php foreach ($monthly as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['label']) ?></td>
              <td style="text-align:right;font-weight:500;"><?= (int)$r['cnt'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  <!-- ── REPAIRS ── -->
  <?php elseif ($tab === 'repairs'): ?>

    <!-- Summary cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:1.5rem;align-items:stretch;grid-auto-rows:1fr;">
      <?php
      $cards = [
        ['label'=>__('reports.total_repairs'),    'value'=> $total_repairs,     'color'=>'var(--accent)'],
        ['label'=>__('reports.completed'),        'value'=> $completed_repairs, 'color'=>'#1D9E75'],
        ['label'=>__('reports.in_progress'),      'value'=> $total_repairs - $completed_repairs, 'color'=>'#e8860a'],
        ['label'=>__('reports.avg_days_close'),'value'=> ($avg_repair_days ?? '—'), 'color'=>'var(--text-secondary)'],
      ];
      foreach ($cards as $c): ?>
        <div class="card" style="text-align:center;display:flex;flex-direction:column;justify-content:center;margin-top:0;">
          <div style="font-size:28px;font-weight:600;color:<?= $c['color'] ?>;"><?= $c['value'] ?></div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $c['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

      <!-- By technician -->
      <div>
        <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.by_technician') ?></h3>
        <?php if (empty($by_technician)): ?>
          <p style="font-size:13px;color:var(--text-muted);"><?= __('reports.no_data') ?></p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th><?= __('rma.technician') ?></th><th style="text-align:right;"><?= __('label.total') ?></th><th style="text-align:right;"><?= __('reports.done') ?></th><th style="text-align:right;"><?= __('reports.avg_days') ?></th></tr></thead>
            <tbody>
              <?php foreach ($by_technician as $r): ?>
                <tr>
                  <td style="font-weight:500;"><?= htmlspecialchars($r['tech']) ?></td>
                  <td style="text-align:right;"><?= (int)$r['total'] ?></td>
                  <td style="text-align:right;color:var(--accent);"><?= (int)$r['completed'] ?></td>
                  <td style="text-align:right;color:var(--text-muted);"><?= $r['avg_days'] ?? '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- By brand -->
      <div>
        <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.by_brand') ?></h3>
        <?php if (empty($by_brand)): ?>
          <p style="font-size:13px;color:var(--text-muted);"><?= __('reports.no_data') ?></p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th><?= __('reports.brand') ?></th><th style="text-align:right;"><?= __('reports.count') ?></th></tr></thead>
            <tbody>
              <?php foreach ($by_brand as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['brand']) ?></td>
                  <td style="text-align:right;font-weight:500;"><?= (int)$r['cnt'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </div>

    <!-- Top 10 models + monthly -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-top:1.25rem;">

      <div>
        <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.top_models') ?></h3>
        <?php if (empty($by_model)): ?>
          <p style="font-size:13px;color:var(--text-muted);"><?= __('reports.no_data') ?></p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th><?= __('reports.model') ?></th><th><?= __('reports.brand') ?></th><th style="text-align:right;"><?= __('reports.count') ?></th></tr></thead>
            <tbody>
              <?php foreach ($by_model as $r): ?>
                <tr>
                  <td style="font-weight:500;"><?= htmlspecialchars($r['model']) ?></td>
                  <td style="color:var(--text-muted);"><?= htmlspecialchars($r['brand']) ?></td>
                  <td style="text-align:right;"><?= (int)$r['cnt'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div>
        <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.monthly_trend') ?></h3>
        <?php if (empty($monthly)): ?>
          <p style="font-size:13px;color:var(--text-muted);"><?= __('reports.no_data') ?></p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th><?= __('reports.month') ?></th><th style="text-align:right;"><?= __('reports.repairs') ?></th></tr></thead>
            <tbody>
              <?php foreach ($monthly as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['label']) ?></td>
                  <td style="text-align:right;font-weight:500;"><?= (int)$r['cnt'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </div>

  <!-- ── PARTS ── -->
  <?php elseif ($tab === 'parts'): ?>

    <!-- Summary cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:1.5rem;align-items:stretch;grid-auto-rows:1fr;">
      <?php
      $cards = [
        ['label'=>__('reports.parts_used'), 'value'=> ($total_used ?? 0),   'color'=>'var(--accent)'],
        ['label'=>__('reports.unique_parts'),     'value'=> ($unique_parts ?? 0), 'color'=>'var(--text-secondary)'],
        ['label'=>__('reports.low_stock_items'),  'value'=> count($low_stock),    'color'=> count($low_stock) > 0 ? '#a32d2d' : '#1D9E75'],
      ];
      foreach ($cards as $c): ?>
        <div class="card" style="text-align:center;display:flex;flex-direction:column;justify-content:center;margin-top:0;">
          <div style="font-size:28px;font-weight:600;color:<?= $c['color'] ?>;"><?= $c['value'] ?></div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $c['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

      <!-- Top parts used -->
      <div>
        <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.most_used_parts') ?></h3>
        <?php if (empty($top_parts)): ?>
          <p style="font-size:13px;color:var(--text-muted);"><?= __('reports.no_data') ?></p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th><?= __('reports.part') ?></th><th>SKU</th><th style="text-align:right;"><?= __('reports.qty') ?></th></tr></thead>
            <tbody>
              <?php foreach ($top_parts as $r): ?>
                <tr>
                  <td style="font-weight:500;"><?= htmlspecialchars($r['name']) ?></td>
                  <td style="color:var(--text-muted);font-size:12px;"><?= htmlspecialchars($r['internal_sku'] ?? '—') ?></td>
                  <td style="text-align:right;"><?= (int)$r['qty'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Low stock -->
      <div>
        <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.low_stock_alert') ?></h3>
        <?php if (empty($low_stock)): ?>
          <p style="font-size:13px;color:var(--accent);"><?= __('reports.parts_stocked') ?></p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th><?= __('reports.part') ?></th><th style="text-align:right;"><?= __('reports.stock') ?></th><th style="text-align:right;"><?= __('reports.min') ?></th></tr></thead>
            <tbody>
              <?php foreach ($low_stock as $r): ?>
                <tr>
                  <td style="font-weight:500;"><?= htmlspecialchars($r['name']) ?></td>
                  <td style="text-align:right;color:#a32d2d;font-weight:600;"><?= (int)$r['stock'] ?></td>
                  <td style="text-align:right;color:var(--text-muted);"><?= (int)$r['min_stock'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </div>

    <!-- Monthly parts usage -->
    <?php if (!empty($monthly)): ?>
    <div style="margin-top:1.25rem;">
      <h3 style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('reports.monthly_usage') ?></h3>
      <table class="data-table" style="max-width:400px;">
        <thead><tr><th><?= __('reports.month') ?></th><th style="text-align:right;"><?= __('reports.qty_used') ?></th></tr></thead>
        <tbody>
          <?php foreach ($monthly as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['label']) ?></td>
              <td style="text-align:right;font-weight:500;"><?= (int)$r['qty'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  <!-- ── FINANCIAL ── -->
  <?php elseif ($tab === 'financial'): ?>
    <div class="card" style="text-align:center;padding:3rem;">
      <p style="font-size:15px;font-weight:500;color:var(--text-secondary);margin-bottom:8px;"><?= __('reports.financial_reports') ?></p>
      <p style="font-size:13px;color:var(--text-muted);"><?= __('reports.financial_soon') ?></p>
    </div>

  <?php endif; ?>


  <!-- ── Repeated repairs ── -->
  <?php if ($tab === 'repeat'): ?>
    <?php
      // Grouped by model, because three of the same model coming back points at
      // a part or a procedure. Deliberately not grouped by technician: whoever
      // takes the hard jobs would top that list, and there is no money riding
      // on this to justify reading it that way.
      $by_model = [];
      foreach ($repeat_rows as $r) {
          $k = trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? '')) ?: '—';
          if (!isset($by_model[$k])) $by_model[$k] = ['count' => 0, 'days' => []];
          $by_model[$k]['count']++;
          $by_model[$k]['days'][] = (int)$r['days'];
      }
      uasort($by_model, fn($a, $b) => $b['count'] <=> $a['count']);
    ?>

    <!-- Summary cards. Same grid and card rules as the other tabs — the rows
         stretch and the card centres its contents, so a two-line label does not
         make one card taller than the rest. -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:1.5rem;align-items:stretch;grid-auto-rows:1fr;">
      <?php
      $cards = [
        ['label'=>__('reports.repeat_count'),  'value'=> count($repeat_rows),   'color'=>'#e8860a'],
        ['label'=>__('reports.repeat_window'), 'value'=> repeat_repair_window(), 'color'=>'var(--text-secondary)'],
      ];
      foreach ($cards as $c): ?>
        <div class="card" style="text-align:center;display:flex;flex-direction:column;justify-content:center;margin-top:0;">
          <div style="font-size:28px;font-weight:600;color:<?= $c['color'] ?>;"><?= $c['value'] ?></div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $c['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (!$repeat_rows): ?>
      <div class="card"><p style="font-size:13px;color:var(--text-muted);margin:0;"><?= __('reports.repeat_none') ?></p></div>
    <?php else: ?>

      <div class="card" style="margin-bottom:1rem;">
        <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('reports.repeat_by_model') ?></h2>
        <table class="data-table">
          <thead><tr>
            <th><?= __('rma.model') ?></th>
            <th style="width:120px;text-align:center;"><?= __('reports.repeat_count') ?></th>
            <th style="width:160px;text-align:center;"><?= __('reports.repeat_fastest') ?></th>
          </tr></thead>
          <tbody>
            <?php foreach ($by_model as $model => $g): ?>
              <tr>
                <td style="font-weight:500;"><?= htmlspecialchars($model) ?></td>
                <td style="text-align:center;"><?= $g['count'] ?></td>
                <td style="text-align:center;color:var(--text-muted);"><?= min($g['days']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card">
        <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('reports.repeat_each') ?></h2>
        <table class="data-table">
          <thead><tr>
            <th style="width:110px;"><?= __('label.rma') ?></th>
            <th><?= __('rma.model') ?></th>
            <th style="width:150px;"><?= __('rma.imei') ?> / <?= __('rma.sn') ?></th>
            <th style="width:110px;"><?= __('reports.repeat_previous') ?></th>
            <th style="width:90px;text-align:center;"><?= __('reports.repeat_days') ?></th>
            <th><?= __('pdf.works_done') ?></th>
          </tr></thead>
          <tbody>
            <?php foreach ($repeat_rows as $r): ?>
              <tr>
                <td><a href="/rma/<?= (int)($r['id'] ?? 0) ?>" style="color:var(--accent);text-decoration:none;"><?= htmlspecialchars($r['rma_number']) ?></a></td>
                <td><?= htmlspecialchars(trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? '')) ?: '—') ?></td>
                <td style="font-size:12px;"><a href="/device/<?= rawurlencode((string)$r['ident']) ?>" style="color:var(--accent);text-decoration:none;"><?= htmlspecialchars((string)$r['ident']) ?></a></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars((string)$r['prev_rma']) ?></td>
                <td style="text-align:center;">
                  <span class="badge" style="background:#faeeda;color:#633806;border:0.5px solid #ef9f27;"><?= (int)$r['days'] ?></span>
                </td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars(mb_strimwidth((string)($r['prev_works'] ?? ''), 0, 90, '…')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php endif; ?>
  <?php endif; ?>

</div>

<script>
function setRange(preset) {
  const today = new Date();
  let from, to;
  const pad = n => String(n).padStart(2,'0');
  const fmt = d => d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());

  if (preset === 'last_month') {
    from = new Date(today.getFullYear(), today.getMonth()-1, 1);
    to   = new Date(today.getFullYear(), today.getMonth(), 0);
  } else if (preset === 'last_year') {
    from = new Date(today.getFullYear()-1, 0, 1);
    to   = new Date(today.getFullYear()-1, 11, 31);
  }

  document.querySelector('input[name="from"]').value = fmt(from);
  document.querySelector('input[name="to"]').value   = fmt(to);
  document.querySelector('form').submit();
}

// Auto-run the report whenever a filter changes — no Generate button needed.
// The date picker fires a bubbling 'change' on the field when a day is picked
// (not while typing); the Location/Brand selects fire 'change' natively.
document.querySelector('form[action="/reports"]').addEventListener('change', function () {
  this.submit();
});
</script>
