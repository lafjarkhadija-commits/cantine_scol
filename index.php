<?php
require_once __DIR__ . '/lib/auth.php';
ensure_session();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Cantine scolaire</title>
    <link rel="stylesheet" href="public/styles.css">
</head>

<body>
    <div class="container">
        <h1>Cantine scolaire</h1>
        <p>Choisissez votre espace.</p>
        <div class="card-grid">
            <a class="card" href="admin/login.php">
                <h2>Espace Admin</h2>
                <p>Gérer menus, paiements et suivi global.</p>
            </a>
            <a class="card" href="eleve/login.php">
                <h2>Espace Élève</h2>
                <p>Consulter vos repas, commandes et solde.</p>
            </a>
        </div>
    </div>
</body>

</html>