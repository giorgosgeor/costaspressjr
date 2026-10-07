<?php $title = 'Pickup Points'; ?>
<?php $extraCss[] = '/css/admin/pickup-points.css'; require View::path('layouts/admin_header'); ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">

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
                    <form method="post" action="/admin/pickup-points/delete" data-confirm="Delete this point?">
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
<?= View::json('pickup-points-data', array_map(fn($p) => ['lat' => (float)$p['lat'], 'lng' => (float)$p['lng'], 'name' => $p['name'], 'active' => (bool)$p['active']], $points)) ?>
<?= View::script('/js/admin/pickup-points.js') ?>

<?php require View::path('layouts/admin_footer'); ?>
