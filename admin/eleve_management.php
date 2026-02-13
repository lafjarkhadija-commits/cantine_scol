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

// Suppression eleve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $idEleve = (int)($_POST['id_eleve'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';
    if (!$idEleve || $token !== $csrfToken) {
        $error = "Requete invalide (token ou identifiant manquant).";
    } else {
        try {
            $stmt = db()->prepare('DELETE FROM eleves WHERE id_eleve = ?');
            $stmt->execute([$idEleve]);
            $success = "Eleve supprime.";
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = "Suppression impossible : eleve lie a des commandes/paiements. Supprimez ou annulez ces enregistrements.";
            } else {
                $error = "Erreur lors de la suppression : " . $e->getMessage();
            }
        }
    }
}

$q = trim($_GET['q'] ?? '');
$classe = $_GET['classe'] ?? '';
$order = $_GET['order'] ?? 'nom_asc';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$allowedClasses = ['GI', 'IID', 'GE', 'IRIC', 'GP'];
$orderWhitelist = [
    'nom_asc' => 'nom ASC, prenom ASC',
    'nom_desc' => 'nom DESC, prenom DESC',
    'prenom_asc' => 'prenom ASC, nom ASC',
    'prenom_desc' => 'prenom DESC, nom DESC',
];
$orderSql = $orderWhitelist[$order] ?? $orderWhitelist['nom_asc'];

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(nom LIKE :q OR prenom LIKE :q OR allergies LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

if ($classe !== '' && in_array($classe, $allowedClasses, true)) {
    $where[] = 'classe = :classe';
    $params[':classe'] = $classe;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT id_eleve, nom, prenom, classe, allergies, email
    FROM eleves
    $whereSql
    ORDER BY $orderSql
    LIMIT :limit OFFSET :offset
";

$stmt = db()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$eleves = $stmt->fetchAll();

$hasNext = count($eleves) === $perPage;

$pageTitle = 'Gestion des eleves';
$pageSubtitle = 'Recherche, filtres et actions securisees.';
require __DIR__ . '/../partials/layout_start.php';
?>

<section class="section-card">
    <h1>Gestion des eleves</h1>
    <p class="text-muted">Recherche par nom, prenom, allergies et filtre par classe.</p>

    <?php if ($success): ?><div class="success" style="margin-bottom:12px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="margin-bottom:12px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="get" class="filter-grid">
        <div>
            <label>Recherche</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nom, prenom ou allergies">
        </div>
        <div>
            <label>Classe</label>
            <select name="classe">
                <option value="">Toutes</option>
                <?php foreach ($allowedClasses as $c): ?>
                    <option value="<?= $c ?>" <?= $classe === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Tri</label>
            <select name="order">
                <option value="nom_asc" <?= $order === 'nom_asc' ? 'selected' : '' ?>>Nom (A-Z)</option>
                <option value="nom_desc" <?= $order === 'nom_desc' ? 'selected' : '' ?>>Nom (Z-A)</option>
                <option value="prenom_asc" <?= $order === 'prenom_asc' ? 'selected' : '' ?>>Prenom (A-Z)</option>
                <option value="prenom_desc" <?= $order === 'prenom_desc' ? 'selected' : '' ?>>Prenom (Z-A)</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a class="btn btn-ghost" href="/cantine_scolaire/admin/eleve_management.php">Reset</a>
            <a class="btn btn-secondary" href="/cantine_scolaire/admin/create_eleves.php">Creer un eleve</a>
        </div>
    </form>
</section>

<section class="section-card">
    <h2>Resultats</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Prenom</th>
                    <th>Classe</th>
                    <th>Allergies</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eleves as $eleve): ?>
                    <tr>
                        <td><?= htmlspecialchars($eleve['id_eleve']) ?></td>
                        <td><?= htmlspecialchars($eleve['nom']) ?></td>
                        <td><?= htmlspecialchars($eleve['prenom']) ?></td>
                        <td><?= htmlspecialchars($eleve['classe']) ?></td>
                        <td><?= htmlspecialchars($eleve['allergies']) ?></td>
                        <td><?= htmlspecialchars($eleve['email']) ?></td>
                        <td>
                            <div class="inline-actions">
                                <a class="btn btn-ghost" href="/cantine_scolaire/admin/eleve_edit.php?id_eleve=<?= urlencode($eleve['id_eleve']) ?>">Modifier</a>
                                <form method="post" data-confirm="Supprimer cet eleve ?" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_eleve" value="<?= htmlspecialchars($eleve['id_eleve']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <button type="submit" class="btn btn-danger">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$eleves): ?>
                    <tr><td colspan="7">Aucun eleve trouve.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions" style="margin-top:12px;">
        <?php if ($page > 1): ?>
            <a class="btn btn-ghost" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Page precedente</a>
        <?php endif; ?>
        <?php if ($hasNext): ?>
            <a class="btn btn-ghost" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Page suivante</a>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../partials/layout_end.php'; ?>
