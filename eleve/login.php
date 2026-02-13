<?php
require_once __DIR__ . '/../lib/auth.php';
ensure_session();
redirect_if_logged_in();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['mot_de_passe'] ?? '';

    $stmt = db()->prepare('SELECT id_eleve, mot_de_passe FROM eleves WHERE email = ?');
    $stmt->execute([$email]);
    $eleve = $stmt->fetch();

    if ($eleve && password_verify($password, $eleve['mot_de_passe'])) {
        $_SESSION['role'] = 'eleve';
        $_SESSION['id_eleve'] = $eleve['id_eleve'];
        header('Location: /cantine_scolaire/eleve/dashboard.php');
        exit;
    } else {
        $error = "Identifiants invalides.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion eleve</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/cantine_scolaire/assets/css/app.css?v=1">
</head>
<body>
<main style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div class="section-card" style="max-width:420px;width:100%;">
        <h1 style="margin-top:0;">Connexion eleve</h1>
        <p class="text-muted">Acces reserve aux eleves inscrits.</p>
        <?php if ($error): ?>
            <div class="alert" style="margin-bottom:12px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" class="filter-grid">
            <div>
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div>
                <label>Mot de passe</label>
                <input type="password" name="mot_de_passe" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Se connecter</button>
                <a class="btn btn-ghost" href="/cantine_scolaire/index.php">Retour</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
