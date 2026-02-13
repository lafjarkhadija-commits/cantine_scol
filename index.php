<?php
require_once __DIR__ . '/lib/auth.php';
ensure_session();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cantine scolaire</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/cantine_scolaire/assets/css/app.css?v=2">
</head>
<body>
<main style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div class="section-card" style="max-width:980px;width:100%;">
        <div class="hero" style="margin-bottom:18px;">
            <h1 style="margin-top:0;">Cantine scolaire</h1>
            <p>Choisissez votre espace.</p>
        </div>
        <div class="card-grid">
            <a class="stat-card" href="admin/login.php">
                <div class="stat-title"><span class="stat-icon">&#128203;</span> Espace Admin</div>
                <div class="text-muted">Gerer menus, paiements et suivi global.</div>
            </a>
            <a class="stat-card" href="eleve/login.php">
                <div class="stat-title"><span class="stat-icon">&#127869;</span> Espace Eleve</div>
                <div class="text-muted">Consulter vos repas, commandes et solde.</div>
            </a>
        </div>
    </div>
</main>
</body>
</html>
