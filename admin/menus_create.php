<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');

$success = null;
$error = null;

$today = date('Y-m-d');

// ⚠️ adapte selon ton ENUM exact dans la DB
$allowed_types = ['Dejeuner', 'Gouter', 'Diner'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['date_menu'] ?? '');
    $type = trim($_POST['type_repas'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $calories = $_POST['calories_total'] ?? 0;
    $allergenes = trim($_POST['allergenes'] ?? '');

    // 1) Validations simples (débutant-friendly)
    if ($date === '' || $type === '' || $desc === '') {
        $error = "Veuillez remplir la date, le type et la description.";
    } elseif ($date < $today) {
        $error = "Impossible d'ajouter un menu pour une date passée.";
    } elseif (!in_array($type, $allowed_types, true)) {
        $error = "Type de repas invalide. Choisissez un type proposé.";
    } else {
        // calories en entier >= 0
        $calories = (int)$calories;
        if ($calories < 0) $calories = 0;

        try {
            // Vérifie explicitement s'il existe déjà un menu pour cette date + type
            $dup = db()->prepare('SELECT 1 FROM menus WHERE date_menu = :d AND type_repas = :t LIMIT 1');
            $dup->execute([':d' => $date, ':t' => $type]);
            if ($dup->fetchColumn()) {
                $error = "Un menu pour cette date et ce type de repas existe déjà. Utilisez la modification (ou changez la date/type).";
                throw new Exception('Duplicate menu for date/type');
            }

            // 2) INSERT + gestion erreur doublon UNIQUE(date_menu, type_repas)
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

            $success = "Menu ajouté avec succès.";

        } catch (PDOException $e) {
            // 23000 = violation contrainte (UNIQUE/FK/etc)
            if ($e->getCode() === '23000') {
                $error = "Un menu pour cette date et ce type de repas existe déjà. Utilisez la modification (ou changez la date/type).";
            } else {
                $error = "Erreur lors de l'ajout du menu : " . $e->getMessage();
            }
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un menu</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>
<body>
<div class="container">
    <nav>
        <a class="btn" href="/cantine_scolaire/admin/dashboard.php">Retour dashboard</a>
        <a class="btn" href="/cantine_scolaire/logout.php">Déconnexion</a>
    </nav>

    <h1>Ajouter un menu</h1>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Date du menu</label>
        <input type="date" name="date_menu" required min="<?= htmlspecialchars($today) ?>">

        <label>Type de repas</label>
        <select name="type_repas" required>
            <option value="">-- Choisir --</option>
            <?php foreach ($allowed_types as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Description</label>
        <textarea name="description" rows="3" required></textarea>

        <label>Calories total</label>
        <input type="number" name="calories_total" min="0" step="1" value="0">

        <label>Allergènes (séparés par virgule)</label>
        <input type="text" name="allergenes" placeholder="ex: gluten,lait">

        <button type="submit">Enregistrer</button>
    </form>
</div>
</body>
</html>
