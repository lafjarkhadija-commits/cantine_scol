<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

ensure_session();

// Seul un admin connecté peut gérer les admins
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /cantine_scolaire/admin/login.php');
    exit;
}

$successCreate = null;
$errorCreate = null;
$successProfile = null;
$errorProfile = null;

$id_admin = (int)($_SESSION['id_admin'] ?? 0);
$stmt = db()->prepare('SELECT email FROM admins WHERE id_admin = ?');
$stmt->execute([$id_admin]);
$currentAdmin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['mot_de_passe'] ?? '';
        if ($email === '' || $password === '') {
            $errorCreate = "Veuillez remplir tous les champs.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare('INSERT INTO admins (email, mot_de_passe) VALUES (?, ?)');
                $stmt->execute([$email, $hash]);
                $successCreate = "Admin cree avec succes.";
            } catch (Exception $e) {
                $errorCreate = "Erreur : " . $e->getMessage();
            }
        }
    }

    if ($action === 'profile') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['mot_de_passe'] ?? '';
        if ($email === '') {
            $errorProfile = "Email obligatoire.";
        } else {
            try {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = db()->prepare('UPDATE admins SET email = ?, mot_de_passe = ? WHERE id_admin = ?');
                    $stmt->execute([$email, $hash, $id_admin]);
                } else {
                    $stmt = db()->prepare('UPDATE admins SET email = ? WHERE id_admin = ?');
                    $stmt->execute([$email, $id_admin]);
                }
                $successProfile = "Profil admin mis a jour.";
                $currentAdmin['email'] = $email;
            } catch (Exception $e) {
                $errorProfile = "Erreur : " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Gestion Admins</title>
    <link rel="stylesheet" href="/cantine_scolaire/public/styles.css">
</head>

<body>
    <div class="container">
        <h2>Gestion admins</h2>

        <h3>Profil admin courant</h3>
        <?php if ($successProfile): ?>
            <div class="success"><?= htmlspecialchars($successProfile) ?></div>
        <?php endif; ?>
        <?php if ($errorProfile): ?>
            <div class="alert"><?= htmlspecialchars($errorProfile) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="action" value="profile">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($currentAdmin['email'] ?? '') ?>" required>

            <label>Mot de passe (laisser vide pour ne pas changer)</label>
            <input type="password" name="mot_de_passe" placeholder="Nouveau mot de passe">

            <button type="submit">Mettre a jour</button>
        </form>

        <h3 style="margin-top:24px;">Creer un nouvel admin</h3>
        <?php if ($successCreate): ?>
            <div class="success"><?= htmlspecialchars($successCreate) ?></div>
        <?php endif; ?>
        <?php if ($errorCreate): ?>
            <div class="alert"><?= htmlspecialchars($errorCreate) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="action" value="create">
            <label>Email</label>
            <input type="email" name="email" required>

            <label>Mot de passe</label>
            <input type="password" name="mot_de_passe" required>

            <button type="submit">Creer</button>
        </form>

        <p><a href="/cantine_scolaire/admin/dashboard.php">Retour au dashboard</a></p>
    </div>
</body>

</html>
