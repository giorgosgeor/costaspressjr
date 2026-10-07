<?php $title = 'Pickup Points'; ?>
<?php require View::path('layouts/admin_header'); ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<style>
.pp-status { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 24px; }
.pp-status div { border: 1px solid var(--border); background: var(--paper-2); padding: 12px 14px; }
.pp-status strong { display: block; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink-muted); margin-bottom: 4px; }
.pp-ok { color: #166534; font-weight: 600; }
.pp-missing { color: #991B1B; font-weight: 600; }
.pp-flash { padding: 10px 14px; margin-bottom: 18px; border: 1px solid; }
.pp-flash.success { color: #166534; border-color: rgba(34,197,94,.4); background: rgba(34,197,94,.1); }
.pp-flash.error { color: #991B1B; border-color: rgba(239,68,68,.4); background: rgba(239,68,68,.1); }
.pp-grid { display: grid; grid-template-columns: 380px 1fr; gap: 24px; align-items: start; }
#ppMap { height: 320px; border: 1px solid var(--border); margin-bottom: 12px; }
.pp-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.pp-table th, .pp-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
.pp-table tr.inactive td { opacity: 0.5; }
.pp-kind { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid var(--border); padding: 1px 6px; }
.pp-actions form { display: inline; }
@media (max-width: 900px) { .pp-grid { grid-template-columns: 1fr; } }
</style>

<div class="admin-header">
    <h1>Pickup Points</h1>
</div>

<?php if ($flash): ?>
<div class="pp-flash <?= $flash[0] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div>
<?php endif; ?>

<?php if (!$migrated): ?>
<div class="pp-flash error">The pickup tables don't exist yet. Run <code>php database/migrate.php</code>.</div>
<?php endif; ?>

<div class="pp-status">
    <div>
        <strong>ACS pickup fee</strong>
        <?php if ($acsFee !== null): ?>
            <span class="pp-ok">€<?= number_format($acsFee, 2) ?></span>
        <?php else: ?>
            <span class="pp-missing">Not set</span> — ACS pickup is hidden at checkout until <code>ACS_PICKUP_FEE</code> is in .env.
        <?php endif; ?>
    </div>
    <div>
        <strong>Store pickup address</strong>
        <?php if ($storeAddress !== ''): ?>
            <?= htmlspecialchars($storeAddress) ?>
        <?php else: ?>
            <span class="pp-missing">Not set</span> — set <code>STORE_PICKUP_ADDRESS</code> in .env so customers know where to go.
        <?php endif; ?>
    </div>
    <div>
        <strong>ACS web services</strong>
        <?php if ($acsConfigured): ?>
            <span class="pp-ok">Connected</span>
            <form method="post" action="/admin/pickup-points/sync" style="margin-top:6px;">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-sm">Sync points from ACS</button>
            </form>
        <?php else: ?>
            <span class="pp-missing">No credentials</span> — ask ACS for web-services access (API key, company and user IDs), then add them to .env. Until then, add points by hand.
        <?php endif; ?>
    </div>
</div>

<div class="pp-grid">
    <div class="form-section">
        <h3 class="form-section-title">Add a point by hand</h3>
        <p class="form-hint">Click the map to place it, then fill in the details.</p>
        <div id="ppMap"></div>
        <form method="post" action="/admin/pickup-points/add">
            <?= Csrf::field() ?>
            <div class="form-group">
                <label for="ppName">Name</label>
                <input id="ppName" name="name" required maxlength="150" placeholder="e.g. ACS Strovolos">
            </div>
            <div class="form-group">
                <label for="ppKind">Type</label>
                <select id="ppKind" name="kind">
                    <option value="store">ACS store</option>
                    <option value="shop">Shop-in-a-shop</option>
                    <option value="locker">Smartpoint locker</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ppAddress">Address</label>
                <input id="ppAddress" name="address" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="ppCity">Town</label>
                <input id="ppCity" name="city" maxlength="100">
            </div>
            <div class="form-group">
                <label for="ppHours">Opening hours</label>
                <input id="ppHours" name="hours" maxlength="120" placeholder="e.g. Mon–Fri 08:00–19:00">
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1;">
                    <label for="ppStation">ACS station code</label>
                    <input id="ppStation" name="station_code" maxlength="20" placeholder="optional">
                </div>
                <div class="form-group" style="flex:1;">
                    <label for="ppBranch">Branch code</label>
                    <input id="ppBranch" name="branch_code" maxlength="20" placeholder="optional">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1;">
                    <label for="ppLat">Latitude</label>
                    <input id="ppLat" name="lat" required inputmode="decimal">
                </div>
                <div class="form-group" style="flex:1;">
                    <label for="ppLng">Longitude</label>
                    <input id="ppLng" name="lng" required inputmode="decimal">
                </div>
            </div>
            <button type="submit" class="btn btn-success">Add point</button>
        </form>
    </div>

    <div class="form-section">
        <h3 class="form-section-title">Points (<?= count($points) ?>)</h3>
        <?php if (!$points): ?>
            <p class="form-hint">No points yet. Customers can only choose store pickup until there is at least one active point.</p>
        <?php else: ?>
        <table class="pp-table">
            <thead><tr><th>Point</th><th>Type</th><th>Source</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($points as $p): ?>
            <tr class="<?= $p['active'] ? '' : 'inactive' ?>">
                <td>
                    <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                    <?= htmlspecialchars($p['address']) ?><?= $p['city'] ? ', ' . htmlspecialchars($p['city']) : '' ?>
                    <?php if ($p['station_code'] || $p['branch_code']): ?><br><small>Code: <?= htmlspecialchars(trim($p['station_code'] . ' ' . $p['branch_code'])) ?></small><?php endif; ?>
                </td>
                <td><span class="pp-kind"><?= htmlspecialchars($p['kind']) ?></span></td>
                <td><?= $p['source'] === 'acs_api' ? 'ACS sync' : 'Manual' ?></td>
                <td class="pp-actions">
                    <form method="post" action="/admin/pickup-points/toggle">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn btn-sm"><?= $p['active'] ? 'Hide' : 'Show' ?></button>
                    </form>
                    <?php if ($p['source'] === 'manual'): ?>
                    <form method="post" action="/admin/pickup-points/delete" onsubmit="return confirm('Delete this point?');">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    if (typeof L === 'undefined') return;
    const map = L.map('ppMap').setView([35.05, 33.2], 8);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const existing = <?= json_encode(array_map(fn($p) => [
        'lat' => (float)$p['lat'], 'lng' => (float)$p['lng'], 'name' => $p['name'], 'active' => (bool)$p['active'],
    ], $points), JSON_UNESCAPED_UNICODE) ?>;
    existing.forEach(p => {
        L.circleMarker([p.lat, p.lng], { radius: 6, color: p.active ? '#16130F' : '#9CA3AF', fillOpacity: 0.8 })
            .bindTooltip(p.name).addTo(map);
    });

    let marker = null;
    map.on('click', e => {
        const { lat, lng } = e.latlng;
        document.getElementById('ppLat').value = lat.toFixed(6);
        document.getElementById('ppLng').value = lng.toFixed(6);
        if (marker) marker.setLatLng(e.latlng);
        else marker = L.circleMarker(e.latlng, { radius: 8, color: '#C83017', fillOpacity: 0.9 }).addTo(map);
    });
})();
</script>

<?php require View::path('layouts/admin_footer'); ?>
