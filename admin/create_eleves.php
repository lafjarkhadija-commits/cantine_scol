<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $classe = trim($_POST['classe'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    if ($nom && $prenom && $classe && $email && $motDePasse) {
        try {
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO eleves (nom, prenom, classe, allergies, email, mot_de_passe) VALUES (:n, :p, :c, :a, :e, :m)');
            $stmt->execute([
                ':n' => $nom,
                ':p' => $prenom,
                ':c' => $classe,
                ':a' => $allergies,
                ':e' => $email,
                ':m' => $hash,
            ]);
            $success = "Eleve cree avec succes.";
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez remplir tous les champs obligatoires.";
    }
}

$pageTitle = 'Creer un eleve';
$pageSubtitle = 'Ajout d\'eleves dans la base.';
require __DIR__ . '/../partials/layout_start.php';
?>

<section class="section-card">
    <h1>Creer un eleve</h1>
    <?php if ($success): ?><div class="success" style="margin-bottom:12px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="margin-bottom:12px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" class="filter-grid">
        <div>
            <label>Nom</label>
            <input type="text" name="nom" required>
        </div>
        <div>
            <label>Prenom</label>
            <input type="text" name="prenom" required>
        </div>
        <div>
            <label>Classe</label>
            <input type="text" name="classe" required>
        </div>
        <div>
            <label>Allergies (optionnel)</label>
            <input type="text" name="allergies">
        </div>
        <div>
            <label>Email</label>
            <input type="email" name="email" required>
        </div>
        <div>
            <label>Mot de passe</label>
            <input type="password" name="mot_de_passe" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Creer l'eleve</button>
            <a class="btn btn-ghost" href="/cantine_scolaire/admin/eleve_management.php">Retour</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../partials/layout_end.php'; ?>
