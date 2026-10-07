<?php $title = 'Manage Orders'; ?>
<?php require View::path('layouts/admin_header'); ?>


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
                    <?php
                    // Refunds and disputes are synced from Stripe by the webhook.
                    $payStatus = (string)($order['payment_status'] ?? '');
                    $payClass  = $payStatus === 'paid' ? 'paid'
                        : (in_array($payStatus, ['refunded', 'partially_refunded', 'disputed', 'dispute_lost'], true) ? 'refunded' : 'pending');
                    ?>
                    <?php if (!empty($order['card_last4'])): ?>
                        <?= ucfirst($order['card_brand'] ?? 'card') ?> •••• <?= htmlspecialchars($order['card_last4']) ?><br>
                    <?php endif; ?>
                    <?php if ($payStatus !== ''): ?>
                        <span class="payment-badge payment-<?= $payClass ?>"><?= htmlspecialchars(str_replace('_', ' ', $payStatus)) ?></span>
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

<?php require View::path('layouts/admin_footer'); ?>
