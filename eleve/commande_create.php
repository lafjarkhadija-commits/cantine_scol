<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('eleve');

$idEleve = $_SESSION['id_eleve'] ?? 0;
$today = date('Y-m-d');
$menus = db()->query("SELECT id_menu, date_menu, type_repas, description FROM menus WHERE date_menu >= CURDATE() ORDER BY date_menu")->fetchAll();

$success = null;
$error = null;
$resume = null;

function fetchSolde(int $idEleve): float {
    $stmt = db()->prepare('SELECT solde FROM v_solde_eleve WHERE id_eleve = :id');
    $stmt->execute([':id' => $idEleve]);
    $row = $stmt->fetch();
    return isset($row['solde']) ? (float)$row['solde'] : 0.0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idMenu = (int)($_POST['id_menu'] ?? 0);
    $quantite = (int)($_POST['quantite'] ?? 1);

    if (!$idMenu) {
        $error = "Veuillez choisir un menu.";
    } elseif ($quantite < 1) {
        $error = "La quantite doit etre au moins 1.";
    } else {
        $stmtMenu = db()->prepare('
            SELECT m.id_menu, m.date_menu, m.type_repas, t.prix
            FROM menus m
            JOIN tarifs t ON t.type_repas = m.type_repas
            WHERE m.id_menu = :id
        ');
        $stmtMenu->execute([':id' => $idMenu]);
        $menuRow = $stmtMenu->fetch();

        if (!$menuRow) {
            $error = "Menu introuvable.";
        } elseif (strtotime($menuRow['date_menu']) < strtotime($today)) {
            $error = "Vous ne pouvez pas commander un menu passe.";
        } else {
            $montantCommande = $quantite * (float)$menuRow['prix'];
            $soldeAvant = fetchSolde($idEleve);

            try {
                $stmt = db()->prepare('INSERT INTO commandes (id_eleve, id_menu, quantite) VALUES (:e, :m, :q)');
                $stmt->execute([
                    ':e' => $idEleve,
                    ':m' => $idMenu,
                    ':q' => $quantite,
                ]);

                $soldeApres = fetchSolde($idEleve);

                $resume = [
                    'avant' => $soldeAvant,
                    'montant_commande' => $montantCommande,
                    'apres' => $soldeApres,
                ];

                $success = "Commande enregistree. Votre solde global est recalcule automatiquement.";
            } catch (PDOException $e) {
                $error = "Erreur lors de l'enregistrement de la commande.";
            }
        }
    }
}

$pageTitle = 'Passer une commande';
$pageSubtitle = 'Choisir un menu et une quantite.';
require __DIR__ . '/../partials/layout_start_eleve.php';
?>

<section class="section-card">
    <h1>Passer une commande</h1>
    <?php if ($success): ?><div class="success" style="margin-bottom:12px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="margin-bottom:12px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($resume): ?>
        <div class="section-card" style="margin-bottom:12px;">
            <h3>Resume</h3>
            <p>Solde avant : <?= htmlspecialchars(number_format($resume['avant'], 2)) ?></p>
            <p>Montant ajoute : <?= htmlspecialchars(number_format($resume['montant_commande'], 2)) ?></p>
            <p>Solde apres : <?= htmlspecialchars(number_format($resume['apres'], 2)) ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="filter-grid">
        <div>
            <label>Menu</label>
            <select name="id_menu" required>
                <option value="">-- Choisir un menu --</option>
                <?php foreach ($menus as $menu): ?>
                    <option value="<?= $menu['id_menu'] ?>" <?= isset($idMenu) && (int)$idMenu === (int)$menu['id_menu'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($menu['date_menu'] . ' - ' . $menu['type_repas'] . ' - ' . $menu['description']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Quantite</label>
            <input type="number" name="quantite" value="<?= htmlspecialchars($_POST['quantite'] ?? '1') ?>" min="1" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Commander</button>
            <a class="btn btn-ghost" href="/cantine_scolaire/eleve/dashboard.php">Retour</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../partials/layout_end.php'; ?>
