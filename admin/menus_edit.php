<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');

ensure_session();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$idMenu = (int)($_GET['id_menu'] ?? 0);
$error = null;
$success = null;

if ($idMenu > 0) {
    $stmt = db()->prepare('SELECT * FROM menus WHERE id_menu = ?');
    $stmt->execute([$idMenu]);
    $menu = $stmt->fetch();
    if (!$menu) {
        $error = "Menu introuvable.";
    }
} else {
    $error = "Identifiant de menu manquant.";
    $menu = null;
}

$typeOptions = ['Dejeuner', 'Gouter', 'Diner'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $token = $_POST['csrf_token'] ?? '';
    if ($token !== $csrfToken) {
        $error = "Token CSRF invalide.";
    } else {
        $dateMenu = $_POST['date_menu'] ?? '';
        $typeRepas = $_POST['type_repas'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $calories = $_POST['calories_total'] ?? null;
        $allergenes = trim($_POST['allergenes'] ?? '');

        if (!$dateMenu || !$typeRepas || !$description) {
            $error = "Veuillez remplir la date, le type de repas et la description.";
        } elseif (!in_array($typeRepas, $typeOptions, true)) {
            $error = "Type de repas invalide.";
        } else {
            try {
                $dup = db()->prepare('
                    SELECT 1 FROM menus
                    WHERE date_menu = :d AND type_repas = :t AND id_menu <> :id
                    LIMIT 1
                ');
                $dup->execute([':d' => $dateMenu, ':t' => $typeRepas, ':id' => $idMenu]);
                if ($dup->fetchColumn()) {
                    $error = "Un menu pour cette date et ce type de repas existe deja. Modifiez la date/type ou revenez au menu existant.";
                } else {
                    $stmt = db()->prepare('
                        UPDATE menus
                        SET date_menu = :d, type_repas = :t, description = :desc, calories_total = :c, allergenes = :a
                        WHERE id_menu = :id
                    ');
                    $stmt->execute([
                        ':d' => $dateMenu,
                        ':t' => $typeRepas,
                        ':desc' => $description,
                        ':c' => $calories !== '' ? $calories : null,
                        ':a' => $allergenes,
                        ':id' => $idMenu,
                    ]);
                    $success = "Menu mis a jour.";
                    $menu = [
                        'id_menu' => $idMenu,
                        'date_menu' => $dateMenu,
                        'type_repas' => $typeRepas,
                        'description' => $description,
                        'calories_total' => $calories,
                        'allergenes' => $allergenes,
                    ];
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = "Un menu pour cette date et ce type de repas existe deja. Modifiez la date/type ou revenez au menu existant.";
                } else {
                    $error = "Erreur lors de la mise a jour : " . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Modifier un menu';
$pageSubtitle = 'Mise a jour des informations menu.';
require __DIR__ . '/../partials/layout_start.php';
?>

<section class="section-card">
    <h1>Modifier un menu</h1>
    <?php if ($success): ?><div class="success" style="margin-bottom:12px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="margin-bottom:12px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($menu): ?>
        <form method="post" class="filter-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div>
                <label>Date du menu</label>
                <input type="date" name="date_menu" value="<?= htmlspecialchars($menu['date_menu']) ?>" required>
            </div>
            <div>
                <label>Type de repas</label>
                <select name="type_repas" required>
                    <?php foreach ($typeOptions as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($menu['type_repas'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Description</label>
                <textarea name="description" rows="3" required><?= htmlspecialchars($menu['description'] ?? '') ?></textarea>
            </div>
            <div>
                <label>Calories total</label>
                <input type="number" name="calories_total" min="0" step="1" value="<?= htmlspecialchars($menu['calories_total'] ?? '') ?>">
            </div>
            <div>
                <label>Allergenes</label>
                <input type="text" name="allergenes" value="<?= htmlspecialchars($menu['allergenes'] ?? '') ?>">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                <a class="btn btn-ghost" href="/cantine_scolaire/admin/menus_management.php">Retour</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/layout_end.php'; ?>
