<?php
$currentPath = $currentPath ?? ($_SERVER['PHP_SELF'] ?? '');
$navItems = [
    [
        'label' => 'Dashboard',
        'href' => '/cantine_scolaire/admin/dashboard.php',
        'icon' => '&#128202;',
    ],
    [
        'label' => 'Menus',
        'href' => '/cantine_scolaire/admin/menus_management.php',
        'icon' => '&#127869;',
    ],
    [
        'label' => 'Eleves',
        'href' => '/cantine_scolaire/admin/eleve_management.php',
        'icon' => '&#127891;',
    ],
    [
        'label' => 'Commandes',
        'href' => '/cantine_scolaire/admin/commandes_management.php',
        'icon' => '&#128203;',
    ],
    [
        'label' => 'Paiements',
        'href' => '/cantine_scolaire/admin/paiements_create.php',
        'icon' => '&#128179;',
    ],
    [
        'label' => 'Admins',
        'href' => '/cantine_scolaire/admin/create_admins.php',
        'icon' => '&#129534;',
    ],
    [
        'label' => 'Deconnexion',
        'href' => '/cantine_scolaire/logout.php',
        'icon' => '&#128682;',
    ],
];
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span>CS</span>
        Cantine Scolaire
    </div>
    <div class="sidebar-section">
        <div class="sidebar-title">Navigation</div>
        <?php foreach ($navItems as $item): ?>
            <?php $active = strpos($currentPath, basename($item['href'])) !== false ? 'active' : ''; ?>
            <a class="sidebar-link <?= $active ?>" href="<?= $item['href'] ?>">
                <span><?= $item['icon'] ?></span>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</aside>
