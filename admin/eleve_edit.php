<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');
ensure_session();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$idEleve = (int)($_GET['id_eleve'] ?? 0);
$error = null;
$success = null;

$allowedClasses = ['GI', 'IID', 'GE', 'IRIC', 'GP'];

if ($idEleve > 0) {
    $stmt = db()->prepare('SELECT * FROM eleves WHERE id_eleve = ?');
    $stmt->execute([$idEleve]);
    $eleve = $stmt->fetch();
    if (!$eleve) {
        $error = "Eleve introuvable.";
    }
} else {
    $error = "Identifiant d'eleve manquant.";
    $eleve = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $token = $_POST['csrf_token'] ?? '';
    if ($token !== $csrfToken) {
        $error = "Token CSRF invalide.";
    } else {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $classe = $_POST['classe'] ?? '';
        $allergies = trim($_POST['allergies'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        if (!$nom || !$prenom || !$classe || !$email) {
            $error = "Nom, prenom, classe et email sont obligatoires.";
        } elseif (!in_array($classe, $allowedClasses, true)) {
            $error = "Classe invalide.";
        } else {
            try {
                if ($motDePasse !== '') {
                    $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
                    $stmt = db()->prepare('
                        UPDATE eleves
                        SET nom = :n, prenom = :p, classe = :c, allergies = :a, email = :e, mot_de_passe = :m
                        WHERE id_eleve = :id
                    ');
                    $stmt->execute([
                        ':n' => $nom,
                        ':p' => $prenom,
                        ':c' => $classe,
                        ':a' => $allergies,
                        ':e' => $email,
                        ':m' => $hash,
                        ':id' => $idEleve,
                    ]);
                } else {
                    $stmt = db()->prepare('
                        UPDATE eleves
                        SET nom = :n, prenom = :p, classe = :c, allergies = :a, email = :e
                        WHERE id_eleve = :id
                    ');
                    $stmt->execute([
                        ':n' => $nom,
                        ':p' => $prenom,
                        ':c' => $classe,
                        ':a' => $allergies,
                        ':e' => $email,
                        ':id' => $idEleve,
                    ]);
                }
                $success = "Eleve mis a jour.";
                $eleve = [
                    'id_eleve' => $idEleve,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'classe' => $classe,
                    'allergies' => $allergies,
                    'email' => $email,
                ];
            } catch (PDOException $e) {
                $error = "Erreur lors de la mise a jour : " . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Modifier un eleve';
$pageSubtitle = 'Mise a jour des informations eleve.';
require __DIR__ . '/../partials/layout_start.php';
?>

<section class="section-card">
    <h1>Modifier un eleve</h1>
    <?php if ($success): ?><div class="success" style="margin-bottom:12px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="margin-bottom:12px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($eleve): ?>
        <form method="post" class="filter-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div>
                <label>Nom</label>
                <input type="text" name="nom" value="<?= htmlspecialchars($eleve['nom'] ?? '') ?>" required>
            </div>
            <div>
                <label>Prenom</label>
                <input type="text" name="prenom" value="<?= htmlspecialchars($eleve['prenom'] ?? '') ?>" required>
            </div>
            <div>
                <label>Classe</label>
                <select name="classe" required>
                    <?php foreach ($allowedClasses as $c): ?>
                        <option value="<?= $c ?>" <?= ($eleve['classe'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Allergies</label>
                <input type="text" name="allergies" value="<?= htmlspecialchars($eleve['allergies'] ?? '') ?>">
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($eleve['email'] ?? '') ?>" required>
            </div>
            <div>
                <label>Nouveau mot de passe (laisser vide pour conserver)</label>
                <input type="password" name="mot_de_passe">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a class="btn btn-ghost" href="/cantine_scolaire/admin/eleve_management.php">Retour</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/layout_end.php'; ?>
