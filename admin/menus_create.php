<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');

$success = null;
$error = null;

$today = date('Y-m-d');

$allowed_types = ['Dejeuner', 'Gouter', 'Diner'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['date_menu'] ?? '');
    $type = trim($_POST['type_repas'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $calories = $_POST['calories_total'] ?? 0;
    $allergenes = trim($_POST['allergenes'] ?? '');

    if ($date === '' || $type === '' || $desc === '') {
        $error = "Veuillez remplir la date, le type et la description.";
    } elseif ($date < $today) {
        $error = "Impossible d'ajouter un menu pour une date passee.";
    } elseif (!in_array($type, $allowed_types, true)) {
        $error = "Type de repas invalide. Choisissez un type propose.";
    } else {
        $calories = (int)$calories;
        if ($calories < 0) $calories = 0;

        try {
            $dup = db()->prepare('SELECT 1 FROM menus WHERE date_menu = :d AND type_repas = :t LIMIT 1');
            $dup->execute([':d' => $date, ':t' => $type]);
            if ($dup->fetchColumn()) {
                $error = "Un menu pour cette date et ce type de repas existe deja. Utilisez la modification (ou changez la date/type).";
                throw new Exception('Duplicate menu for date/type');
            }

            $stmt = db()->prepare(
                'INSERT INTO menus (date_menu, type_repas, description, calories_total, allergenes)
                 VALUES (:d, :t, :ds, :c, :a)'
            );

            $stmt->execute([
                ':d' => $date,
                ':t' => $type,
                ':ds' => $desc,
                ':c' => $calories,
                ':a' => ($allergenes === '' ? null : $allergenes),
            ]);

            $success = "Menu ajoute avec succes.";

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = "Un menu pour cette date et ce type de repas existe deja. Utilisez la modification (ou changez la date/type).";
            } else {
                $error = "Erreur lors de l'ajout du menu : " . $e->getMessage();
            }
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

$pageTitle = 'Ajouter un menu';
$pageSubtitle = 'Creation de menus.';
require __DIR__ . '/../partials/layout_start.php';
?>

<section class="section-card">
    <h1>Ajouter un menu</h1>

    <?php if ($success): ?>
        <div class="success" style="margin-bottom:12px;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert" style="margin-bottom:12px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="filter-grid">
        <div>
            <label>Date du menu</label>
            <input type="date" name="date_menu" required min="<?= htmlspecialchars($today) ?>">
        </div>
        <div>
            <label>Type de repas</label>
            <select name="type_repas" required>
                <option value="">-- Choisir --</option>
                <?php foreach ($allowed_types as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Description</label>
            <textarea name="description" rows="3" required></textarea>
        </div>
        <div>
            <label>Calories total</label>
            <input type="number" name="calories_total" min="0" step="1" value="0">
        </div>
        <div>
            <label>Allergenes (separes par virgule)</label>
            <input type="text" name="allergenes" placeholder="ex: gluten,lait">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a class="btn btn-ghost" href="/cantine_scolaire/admin/menus_management.php">Retour</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../partials/layout_end.php'; ?>
