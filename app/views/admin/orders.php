<?php $title = 'Manage Orders'; ?>
<?php require __DIR__ . '/../layouts/admin_header.php'; ?>


<div class="admin-header">
    <h1>Manage Orders</h1>
</div>

<?php if (empty($orders)): ?>
<div style="text-align:center; padding:60px 20px; color:#999;">
    <h2>No orders yet</h2>
    <p>Orders will appear here once customers complete checkout.</p>
</div>
<?php else: ?>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Total</th>
                <th>Items</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td><strong>#<?= htmlspecialchars($order['id']) ?></strong></td>
                <td>
                    <?= htmlspecialchars($order['username'] ?? 'Guest') ?>
                    <br><small style="color:#999;"><?= htmlspecialchars($order['email'] ?? '') ?></small>
                </td>
                <?php $statusLabels = ['pending'=>'Pending','processing'=>'Processing','in-transit'=>'In Transit','delivered'=>'Delivered','cancelled'=>'Cancelled']; ?>
                <td><span class="status status-<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($statusLabels[$order['status']] ?? ucfirst($order['status'])) ?></span></td>
                <td>
                    <?php if (!empty($order['card_last4'])): ?>
                        <?= ucfirst($order['card_brand'] ?? 'card') ?> •••• <?= htmlspecialchars($order['card_last4']) ?>
                        <br><span class="payment-badge payment-<?= ($order['payment_status'] ?? '') === 'paid' ? 'paid' : 'pending' ?>"><?= htmlspecialchars($order['payment_status'] ?? '—') ?></span>
                    <?php else: ?>
                        <span style="color:#999;">—</span>
                    <?php endif; ?>
                </td>
                <td><strong>€<?= number_format($order['total_price'], 2) ?></strong></td>
                <td><?= htmlspecialchars($order['total_products']) ?></td>
                <td><?= htmlspecialchars($order['created_at']) ?></td>
                <td><a href="/admin/orders/view/<?= $order['id'] ?>" class="btn btn-sm">View</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
