<?php
$pageTitle = $pageTitle ?? 'Cantine Scolaire';
$pageSubtitle = $pageSubtitle ?? '';
$currentPath = $currentPath ?? ($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/cantine_scolaire/assets/css/app.css?v=2">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="main">
        <?php require __DIR__ . '/topbar.php'; ?>
        <main class="content">
            <div class="page">
