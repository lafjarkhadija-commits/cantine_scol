<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
?>
<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle menu">&#9776;</button>
        <div>
            <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
            <?php if ($pageSubtitle): ?>
                <div style="color:var(--muted);font-size:13px;">
                    <?= htmlspecialchars($pageSubtitle) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="topbar-actions">
        <span class="badge badge-warning">Admin</span>
    </div>
</header>
