<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('eleve');

ensure_session();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$idEleve = $_SESSION['id_eleve'] ?? 0;
$success = null;
$error = null;

// Action annuler commande (autorisee seulement si CONFIRMEE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $token = $_POST['csrf_token'] ?? '';
    $idCommande = (int)($_POST['id_commande'] ?? 0);
    if ($token !== $csrfToken || !$idCommande) {
        $error = "Requete invalide.";
    } else {
        $stmt = db()->prepare('SELECT statut FROM commandes WHERE id_commande = :id AND id_eleve = :e');
        $stmt->execute([':id' => $idCommande, ':e' => $idEleve]);
        $row = $stmt->fetch();
        if (!$row) {
            $error = "Commande introuvable.";
        } elseif ($row['statut'] !== 'CONFIRMEE') {
            $error = "Annulation autorisee uniquement si la commande est CONFIRMEE.";
        } else {
            $up = db()->prepare('UPDATE commandes SET statut = :s WHERE id_commande = :id AND id_eleve = :e');
            $up->execute([':s' => 'ANNULEE', ':id' => $idCommande, ':e' => $idEleve]);
            $success = "Commande annulee.";
        }
    }
}

$cmdStmt = db()->prepare('
    SELECT c.id_commande, c.quantite, c.statut, m.date_menu, m.type_repas, m.description
    FROM commandes c
    JOIN menus m ON c.id_menu = m.id_menu
    WHERE c.id_eleve = :id
    ORDER BY m.date_menu DESC
');
$cmdStmt->execute([':id' => $idEleve]);
$commandes = $cmdStmt->fetchAll();

$nutriStmt = db()->prepare('SELECT * FROM v_nutrition_eleve WHERE id_eleve = :id');
$nutriStmt->execute([':id' => $idEleve]);
$nutriRows = $nutriStmt->fetchAll();

$soldeStmt = db()->prepare('SELECT * FROM v_solde_eleve WHERE id_eleve = :id');
$soldeStmt->execute([':id' => $idEleve]);
$solde = $soldeStmt->fetch();

$totalAPayerStmt = db()->prepare('SELECT total_a_payer FROM v_total_a_payer_par_eleve WHERE id_eleve = :id');
$totalAPayerStmt->execute([':id' => $idEleve]);
$totalAPayerRow = $totalAPayerStmt->fetch();
$totalAPayer = $totalAPayerRow['total_a_payer'] ?? 0;

$totalPayeStmt = db()->prepare('SELECT total_paye FROM v_total_paye_par_eleve WHERE id_eleve = :id');
$totalPayeStmt->execute([':id' => $idEleve]);
$totalPayeRow = $totalPayeStmt->fetch();
$totalPaye = $totalPayeRow['total_paye'] ?? 0;

$menusDisponibles = db()->query("SELECT id_menu, date_menu, type_repas, description FROM menus WHERE date_menu >= CURDATE() ORDER BY date_menu")->fetchAll();

$pageTitle = 'Dashboard eleve';
$pageSubtitle = 'Suivi des commandes et menus disponibles.';
require __DIR__ . '/../partials/layout_start_eleve.php';
?>

<section class="hero">
    <h1>Bonjour</h1>
    <p>Suivez vos commandes, soldes et menus disponibles.</p>
</section>

<section class="card-grid">
    <div class="stat-card">
        <div class="stat-title"><span class="stat-icon">&#128179;</span> Solde actuel</div>
        <div class="stat-value"><?= htmlspecialchars(number_format($solde['solde'] ?? 0, 2)) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title"><span class="stat-icon">&#128204;</span> Total a payer</div>
        <div class="stat-value"><?= htmlspecialchars(number_format($totalAPayer, 2)) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title"><span class="stat-icon">&#9989;</span> Total paye</div>
        <div class="stat-value"><?= htmlspecialchars(number_format($totalPaye, 2)) ?></div>
    </div>
</section>

<?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<section class="section-card">
    <h2>Menus disponibles</h2>
    <div class="filter-grid" style="margin-bottom:12px;">
        <div>
            <label>Recherche</label>
            <input id="menus-search" type="text" placeholder="Rechercher un menu...">
        </div>
        <div>
            <label>Type de repas</label>
            <select id="menus-type">
                <option value="">Tous les types</option>
                <option value="Dejeuner">Dejeuner</option>
                <option value="Gouter">Gouter</option>
                <option value="Diner">Diner</option>
            </select>
        </div>
    </div>
    <div class="card-grid" id="menus-grid">
        <?php foreach ($menusDisponibles as $menu): ?>
            <div class="stat-card menu-card" data-type="<?= htmlspecialchars($menu['type_repas']) ?>">
                <div class="stat-title"><?= htmlspecialchars($menu['date_menu']) ?> - <?= htmlspecialchars($menu['type_repas']) ?></div>
                <div><?= htmlspecialchars($menu['description']) ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$menusDisponibles): ?>
            <div class="stat-card">Aucun menu disponible.</div>
        <?php endif; ?>
    </div>
</section>

<section class="section-card">
    <h2>Mes commandes</h2>
    <div class="filter-grid" style="margin-bottom:12px;">
        <div>
            <label>Recherche</label>
            <input id="cmd-search" type="text" placeholder="Rechercher une commande...">
        </div>
        <div>
            <label>Statut</label>
            <select id="cmd-status">
                <option value="">Tous statuts</option>
                <option value="CONFIRMEE">CONFIRMEE</option>
                <option value="SERVIE">SERVIE</option>
                <option value="ANNULEE">ANNULEE</option>
            </select>
        </div>
    </div>

    <div class="table-wrap">
        <table id="cmd-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Quantite</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commandes as $row): ?>
                    <?php
                        $badgeClass = 'badge-warning';
                        if (($row['statut'] ?? '') === 'SERVIE') {
                            $badgeClass = 'badge-success';
                        } elseif (($row['statut'] ?? '') === 'ANNULEE') {
                            $badgeClass = 'badge-danger';
                        }
                    ?>
                    <tr data-statut="<?= htmlspecialchars($row['statut'] ?? '') ?>">
                        <td><?= htmlspecialchars($row['date_menu'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['type_repas'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['quantite'] ?? '') ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['statut'] ?? '') ?></span></td>
                        <td>
                            <form method="post" style="display:inline;" data-confirm="Annuler cette commande ?">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="id_commande" value="<?= htmlspecialchars($row['id_commande']) ?>">
                                <button type="submit" class="btn btn-danger" <?= ($row['statut'] === 'CONFIRMEE') ? '' : 'disabled' ?>>Annuler</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$commandes): ?>
                    <tr><td colspan="6">Aucune commande.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-card">
    <h2>Suivi nutritionnel</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <?php if (!empty($nutriRows)): ?>
                        <?php foreach (array_keys($nutriRows[0]) as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nutriRows as $row): ?>
                    <tr>
                        <?php foreach ($row as $val): ?>
                            <td><?= htmlspecialchars($val ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$nutriRows): ?>
                    <tr><td>Pas de donnees nutritionnelles.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-card">
    <h2>Votre solde</h2>
    <?php if ($solde): ?>
        <p>Solde actuel : <strong><?= htmlspecialchars($solde['solde'] ?? '0') ?></strong></p>
    <?php else: ?>
        <p>Solde non disponible.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/layout_end.php'; ?>
