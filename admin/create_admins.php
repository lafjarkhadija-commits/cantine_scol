<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

ensure_session();

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

$pageTitle = 'Gestion admins';
$pageSubtitle = 'Administration des comptes.';
require __DIR__ . '/../partials/layout_start.php';
?>

<section class="section-card">
    <h1>Gestion admins</h1>
    <p class="text-muted">Mise a jour du profil et creation d'administrateurs.</p>
</section>

<section class="section-card">
    <h2>Profil admin courant</h2>
    <?php if ($successProfile): ?>
        <div class="success" style="margin-bottom:12px;"><?= htmlspecialchars($successProfile) ?></div>
    <?php endif; ?>
    <?php if ($errorProfile): ?>
        <div class="alert" style="margin-bottom:12px;"><?= htmlspecialchars($errorProfile) ?></div>
    <?php endif; ?>

    <form method="post" class="filter-grid">
        <input type="hidden" name="action" value="profile">
        <div>
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($currentAdmin['email'] ?? '') ?>" required>
        </div>
        <div>
            <label>Mot de passe (laisser vide pour ne pas changer)</label>
            <input type="password" name="mot_de_passe" placeholder="Nouveau mot de passe">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mettre a jour</button>
        </div>
    </form>
</section>

<section class="section-card">
    <h2>Creer un nouvel admin</h2>
    <?php if ($successCreate): ?>
        <div class="success" style="margin-bottom:12px;"><?= htmlspecialchars($successCreate) ?></div>
    <?php endif; ?>
    <?php if ($errorCreate): ?>
        <div class="alert" style="margin-bottom:12px;"><?= htmlspecialchars($errorCreate) ?></div>
    <?php endif; ?>

    <form method="post" class="filter-grid">
        <input type="hidden" name="action" value="create">
        <div>
            <label>Email</label>
            <input type="email" name="email" required>
        </div>
        <div>
            <label>Mot de passe</label>
            <input type="password" name="mot_de_passe" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Creer</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../partials/layout_end.php'; ?>
