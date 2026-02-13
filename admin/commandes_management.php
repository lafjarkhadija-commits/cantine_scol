<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');
ensure_session();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$success = null;
$error = null;

// Actions admin : marquer servie / annuler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $idCommande = (int)($_POST['id_commande'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($token !== $csrfToken || !$idCommande) {
        $error = "Requete invalide.";
    } elseif (!in_array($action, ['serve', 'cancel'], true)) {
        $error = "Action inconnue.";
    } else {
        $stmt = db()->prepare('SELECT statut FROM commandes WHERE id_commande = ?');
        $stmt->execute([$idCommande]);
        $row = $stmt->fetch();
        if (!$row) {
            $error = "Commande introuvable.";
        } elseif ($row['statut'] !== 'CONFIRMEE') {
            $error = "Action autorisee uniquement sur une commande CONFIRMEE.";
        } else {
            if ($action === 'serve') {
                $up = db()->prepare('UPDATE commandes SET statut = :s WHERE id_commande = :id');
                $up->execute([':s' => 'SERVIE', ':id' => $idCommande]);
                $success = "Commande marquee SERVIE.";
            } else {
                $up = db()->prepare('UPDATE commandes SET statut = :s WHERE id_commande = :id');
                $up->execute([':s' => 'ANNULEE', ':id' => $idCommande]);
                $success = "Commande annulee.";
            }
        }
    }
}

// Filtres
$q = trim($_GET['q'] ?? '');
$statut = $_GET['statut'] ?? '';
$typeRepas = $_GET['type_repas'] ?? '';
$dateExacte = $_GET['date_exacte'] ?? '';
$dateDebut = $_GET['date_debut'] ?? '';
$dateFin = $_GET['date_fin'] ?? '';
$qteMin = $_GET['qte_min'] ?? '';
$qteMax = $_GET['qte_max'] ?? '';
$orderBy = $_GET['order_by'] ?? 'quantite';
$orderDir = strtoupper($_GET['order_dir'] ?? 'DESC');

$orderWhitelist = [
    'eleve' => 'e.nom, e.prenom',
    'date_menu' => 'm.date_menu',
    'type_repas' => 'm.type_repas',
    'quantite' => 'c.quantite',
    'statut' => 'c.statut',
];
$orderDirWhitelist = ['ASC', 'DESC'];

$orderSql = $orderWhitelist[$orderBy] ?? $orderWhitelist['date_menu'];
$orderDirSql = in_array($orderDir, $orderDirWhitelist, true) ? $orderDir : 'DESC';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(e.nom LIKE :q OR e.prenom LIKE :q OR e.email LIKE :q OR m.description LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

$statuts = ['CONFIRMEE', 'ANNULEE', 'SERVIE'];
if ($statut !== '' && in_array($statut, $statuts, true)) {
    $where[] = 'c.statut = :statut';
    $params[':statut'] = $statut;
}

$types = ['Dejeuner', 'Gouter', 'Diner'];
if ($typeRepas !== '' && in_array($typeRepas, $types, true)) {
    $where[] = 'm.type_repas = :type';
    $params[':type'] = $typeRepas;
}

if ($dateExacte !== '') {
    $where[] = 'm.date_menu = :d_exacte';
    $params[':d_exacte'] = $dateExacte;
} else {
    if ($dateDebut !== '') {
        $where[] = 'm.date_menu >= :d_debut';
        $params[':d_debut'] = $dateDebut;
    }
    if ($dateFin !== '') {
        $where[] = 'm.date_menu <= :d_fin';
        $params[':d_fin'] = $dateFin;
    }
}

if ($qteMin !== '' && is_numeric($qteMin)) {
    $where[] = 'c.quantite >= :qmin';
    $params[':qmin'] = (int)$qteMin;
}
if ($qteMax !== '' && is_numeric($qteMax)) {
    $where[] = 'c.quantite <= :qmax';
    $params[':qmax'] = (int)$qteMax;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        c.id_commande,
        c.quantite,
        c.statut,
        e.nom,
        e.prenom,
        e.classe,
        e.email,
        m.date_menu,
        m.type_repas,
        m.description
    FROM commandes c
    JOIN eleves e ON c.id_eleve = e.id_eleve
    JOIN menus m ON c.id_menu = m.id_menu
    $whereSql
    ORDER BY $orderSql $orderDirSql, e.nom ASC
";

$stmt = db()->prepare($sql);
foreach ($params as $key => $val) {
    if (in_array($key, [':qmin', ':qmax'], true)) {
        $stmt->bindValue($key, (int)$val, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
}
$stmt->execute();
$commandes = $stmt->fetchAll();

$pageTitle = 'Gestion des commandes';
$pageSubtitle = 'Suivi des commandes confirmees et actions.';
require __DIR__ . '/../partials/layout_start.php';
?>

<section class="section-card">
    <h1>Gestion des commandes</h1>
    <p class="text-muted">Recherche eleve/menu, filtres statut/type/date/quantite.</p>

    <?php if ($success): ?><div class="success" style="margin-bottom:12px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="margin-bottom:12px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="get" class="filter-grid">
        <div>
            <label>Recherche (nom, prenom, email, description)</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>">
        </div>
        <div>
            <label>Statut</label>
            <select name="statut">
                <option value="">Tous</option>
                <?php foreach ($statuts as $st): ?>
                    <option value="<?= $st ?>" <?= $statut === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Type de repas</label>
            <select name="type_repas">
                <option value="">Tous</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>" <?= $typeRepas === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Date exacte</label>
            <input type="date" name="date_exacte" value="<?= htmlspecialchars($dateExacte) ?>">
        </div>
        <div>
            <label>Date debut</label>
            <input type="date" name="date_debut" value="<?= htmlspecialchars($dateDebut) ?>">
        </div>
        <div>
            <label>Date fin</label>
            <input type="date" name="date_fin" value="<?= htmlspecialchars($dateFin) ?>">
        </div>
        <div>
            <label>Quantite min</label>
            <input type="number" name="qte_min" min="1" value="<?= htmlspecialchars($qteMin) ?>">
        </div>
        <div>
            <label>Quantite max</label>
            <input type="number" name="qte_max" min="1" value="<?= htmlspecialchars($qteMax) ?>">
        </div>
        <div>
            <label>Tri</label>
            <select name="order_by">
                <option value="date_menu" <?= $orderBy === 'date_menu' ? 'selected' : '' ?>>Date</option>
                <option value="eleve" <?= $orderBy === 'eleve' ? 'selected' : '' ?>>Eleve</option>
                <option value="type_repas" <?= $orderBy === 'type_repas' ? 'selected' : '' ?>>Type repas</option>
                <option value="quantite" <?= $orderBy === 'quantite' ? 'selected' : '' ?>>Quantite</option>
                <option value="statut" <?= $orderBy === 'statut' ? 'selected' : '' ?>>Statut</option>
            </select>
        </div>
        <div>
            <label>Ordre</label>
            <select name="order_dir">
                <option value="DESC" <?= $orderDirSql === 'DESC' ? 'selected' : '' ?>>DESC</option>
                <option value="ASC" <?= $orderDirSql === 'ASC' ? 'selected' : '' ?>>ASC</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a class="btn btn-ghost" href="/cantine_scolaire/admin/commandes_management.php">Reset</a>
        </div>
    </form>
</section>

<section class="section-card">
    <h2>Commandes</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Eleve</th>
                    <th>Classe</th>
                    <th>Menu</th>
                    <th>Quantite</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commandes as $cmd): ?>
                    <?php
                        $badgeClass = 'badge-warning';
                        if ($cmd['statut'] === 'SERVIE') {
                            $badgeClass = 'badge-success';
                        } elseif ($cmd['statut'] === 'ANNULEE') {
                            $badgeClass = 'badge-danger';
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($cmd['id_commande']) ?></td>
                        <td><?= htmlspecialchars($cmd['nom'] . ' ' . $cmd['prenom']) ?></td>
                        <td><?= htmlspecialchars($cmd['classe']) ?></td>
                        <td><?= htmlspecialchars($cmd['date_menu'] . ' - ' . $cmd['type_repas']) ?></td>
                        <td><?= htmlspecialchars($cmd['quantite']) ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($cmd['statut']) ?></span></td>
                        <td>
                            <div class="inline-actions">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="id_commande" value="<?= htmlspecialchars($cmd['id_commande']) ?>">
                                    <input type="hidden" name="action" value="serve">
                                    <button type="submit" class="btn btn-secondary" <?= $cmd['statut'] === 'CONFIRMEE' ? '' : 'disabled' ?>>Marquer SERVIE</button>
                                </form>
                                <form method="post" style="display:inline;" data-confirm="Annuler cette commande ?">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="id_commande" value="<?= htmlspecialchars($cmd['id_commande']) ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-danger" <?= $cmd['statut'] === 'CONFIRMEE' ? '' : 'disabled' ?>>Annuler</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$commandes): ?>
                    <tr><td colspan="7">Aucune commande trouvee.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../partials/layout_end.php'; ?>
