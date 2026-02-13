<?php
require_once __DIR__ . '/../lib/auth.php';
require_role('admin');

function ensure_ca_views(PDO $pdo): bool {
    $sqlFile = __DIR__ . '/../setup/views_ca.sql';
    if (!file_exists($sqlFile)) {
        return false;
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        return false;
    }

    $lines = preg_split('/\R/', $sql);
    $filtered = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $filtered[] = $line;
    }
    $sqlClean = implode("\n", $filtered);
    $statements = array_filter(array_map('trim', explode(';', $sqlClean)));

    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
    return count($statements) > 0;
}

$today = date('Y-m-d');
$filterDate = $_GET['date'] ?? $today;

// Planification des repas (uniquement commandes confirmees et dates futures)
$sqlPlanif = "
  SELECT
    m.date_menu,
    m.type_repas,
    SUM(c.quantite) AS quantite_totale,
    m.description,
    m.calories_total,
    m.allergenes
  FROM commandes c
  JOIN menus m ON c.id_menu = m.id_menu
  WHERE m.date_menu >= CURDATE()
    AND c.statut = 'CONFIRMEE'
";
if (!empty($filterDate)) {
    $sqlPlanif .= " AND m.date_menu = :date_filter";
}
$sqlPlanif .= "
  GROUP BY m.date_menu, m.type_repas, m.description, m.calories_total, m.allergenes
  ORDER BY m.date_menu ASC
";
$stmtPlanif = db()->prepare($sqlPlanif);
if (!empty($filterDate)) {
    $stmtPlanif->bindValue(':date_filter', $filterDate, PDO::PARAM_STR);
}
$stmtPlanif->execute();
$planifications = $stmtPlanif->fetchAll();
$menusAVenir = count($planifications);

// Chiffre d'affaires & popularite (views)
$caJour = 0.0;
$caTotal = 0.0;
$topMenu = null;
$flopMenu = null;
$caMenus = [];
$caError = null;
$caHasData = false;
$caDate = trim($_GET['ca_date'] ?? '');
if ($caDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $caDate)) {
    $caDate = '';
}
$loadCa = function () use (&$caJour, &$caTotal, &$caMenus, &$caHasData, &$topMenu, &$flopMenu, $caDate) {
    if ($caDate !== '') {
        $caJourStmt = db()->prepare("SELECT chiffre_affaire FROM v_ca_total_par_jour WHERE date_menu = :ca_date");
        $caJourStmt->execute([':ca_date' => $caDate]);
    } else {
        $caJourStmt = db()->prepare("SELECT chiffre_affaire FROM v_ca_total_par_jour WHERE date_menu = CURDATE()");
        $caJourStmt->execute();
    }
    $caJour = (float)($caJourStmt->fetchColumn() ?? 0);

    if ($caDate !== '') {
        $caTotalStmt = db()->prepare("SELECT COALESCE(SUM(chiffre_affaire), 0) FROM v_ca_total_par_jour WHERE date_menu = :ca_date");
        $caTotalStmt->execute([':ca_date' => $caDate]);
    } else {
        $caTotalStmt = db()->prepare("SELECT COALESCE(SUM(chiffre_affaire), 0) FROM v_ca_total_par_jour");
        $caTotalStmt->execute();
    }
    $caTotal = (float)($caTotalStmt->fetchColumn() ?? 0);

    $caSql = "
        SELECT date_menu, type_repas, description, quantite_totale, prix_unitaire, chiffre_affaire
        FROM v_ca_menus_par_jour
    ";
    $caParams = [];
    if ($caDate !== '') {
        $caSql .= " WHERE date_menu = :ca_date";
        $caParams[':ca_date'] = $caDate;
    }
    $caSql .= " ORDER BY date_menu DESC, chiffre_affaire DESC";
    $caStmt = db()->prepare($caSql);
    foreach ($caParams as $k => $v) {
        $caStmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $caStmt->execute();
    $caMenus = $caStmt->fetchAll();

    foreach ($caMenus as $row) {
        if ((int)$row['quantite_totale'] > 0) {
            $caHasData = true;
            break;
        }
    }

    if ($caHasData) {
        if ($caDate !== '') {
            $topStmt = db()->prepare("
                SELECT description, type_repas, quantite_totale, chiffre_affaire
                FROM v_ca_menus_par_jour
                WHERE date_menu = :ca_date
                ORDER BY quantite_totale DESC
                LIMIT 1
            ");
            $topStmt->execute([':ca_date' => $caDate]);
            $topMenu = $topStmt->fetch();

            $flopStmt = db()->prepare("
                SELECT description, type_repas, quantite_totale, chiffre_affaire
                FROM v_ca_menus_par_jour
                WHERE date_menu = :ca_date
                ORDER BY quantite_totale ASC
                LIMIT 1
            ");
            $flopStmt->execute([':ca_date' => $caDate]);
            $flopMenu = $flopStmt->fetch();
        } else {
            $topMenu = db()->query("SELECT description, type_repas, quantite_totale, chiffre_affaire FROM v_menus_top_flop ORDER BY quantite_totale DESC LIMIT 1")->fetch();
            $flopMenu = db()->query("SELECT description, type_repas, quantite_totale, chiffre_affaire FROM v_menus_top_flop ORDER BY quantite_totale ASC LIMIT 1")->fetch();
        }
    }
};

try {
    $loadCa();
} catch (PDOException $e) {
    $caError = "Vues CA manquantes. Execute setup/views_ca.sql.";
    try {
        if (ensure_ca_views(db())) {
            $caError = null;
            $loadCa();
        }
    } catch (PDOException $e2) {
        $caError = "Erreur CA: " . $e2->getMessage();
    }
}

// Alertes allergenes (filtre sur la date si choisie)
$sqlAlertes = "
  SELECT
    m.date_menu,
    CONCAT(e.nom, ' ', e.prenom) AS eleve,
    e.classe,
    m.allergenes AS allergene_menu,
    1 AS alerte
  FROM commandes c
  JOIN eleves e ON c.id_eleve = e.id_eleve
  JOIN menus m ON c.id_menu = m.id_menu
  WHERE c.statut = 'CONFIRMEE'
    AND m.allergenes IS NOT NULL AND m.allergenes <> ''
    AND e.allergies IS NOT NULL AND e.allergies <> ''
    AND m.allergenes REGEXP CONCAT('(^|,)[[:space:]]*', REPLACE(e.allergies, ',', '|'), '([[:space:]]*,|$)')
";
if (!empty($filterDate)) {
    $sqlAlertes .= " AND m.date_menu = :date_filter";
}
$stmt = db()->prepare($sqlAlertes);
if (!empty($filterDate)) {
    $stmt->bindValue(':date_filter', $filterDate, PDO::PARAM_STR);
}
$stmt->execute();
$alertes = $stmt->fetchAll();
$alertesCount = count($alertes);

// Soldes financiers (tous les eleves)
$soldeStmt = db()->query('
    SELECT e.nom, e.prenom, e.classe, COALESCE(v.solde, 0) AS solde
    FROM eleves e
    LEFT JOIN v_solde_eleve v ON v.id_eleve = e.id_eleve
    ORDER BY solde DESC, e.nom, e.prenom
');
$soldeRows = $soldeStmt->fetchAll();

// Commandes confirmees (statique)
$cmdCountStmt = db()->query("SELECT COUNT(*) AS total FROM commandes WHERE statut = 'CONFIRMEE'");
$cmdConfirmed = (int)($cmdCountStmt->fetch()['total'] ?? 0);

// Menus les plus demandes (CONFIRMEE/SERVIE)
$menuTypes = ['Dejeuner', 'Gouter', 'Diner'];
$menuTypeFilter = $_GET['menu_type'] ?? '';
if (!in_array($menuTypeFilter, $menuTypes, true)) {
    $menuTypeFilter = '';
}
$menusQteSql = "
    SELECT
        m.id_menu,
        m.date_menu,
        m.type_repas,
        m.description,
        COALESCE(SUM(c.quantite), 0) AS quantite_totale
    FROM menus m
    LEFT JOIN commandes c
        ON c.id_menu = m.id_menu
        AND c.statut IN ('CONFIRMEE', 'SERVIE')
";
$menusQteParams = [];
if ($menuTypeFilter !== '') {
    $menusQteSql .= " WHERE m.type_repas = :menu_type";
    $menusQteParams[':menu_type'] = $menuTypeFilter;
}
$menusQteSql .= "
    GROUP BY m.id_menu, m.date_menu, m.type_repas, m.description
    ORDER BY quantite_totale DESC, m.date_menu DESC, m.id_menu DESC
";
$menusQteStmt = db()->prepare($menusQteSql);
foreach ($menusQteParams as $key => $val) {
    $menusQteStmt->bindValue($key, $val, PDO::PARAM_STR);
}
$menusQteStmt->execute();
$menusParQte = $menusQteStmt->fetchAll();

$pageTitle = 'Dashboard Admin';
$pageSubtitle = 'Vision globale des menus, commandes et alertes.';
require __DIR__ . '/../partials/layout_start.php';
?>

<section class="hero">
    <h1>Dashboard Admin</h1>
    <p>Suivi des menus, commandes, alertes et finances.</p>
</section>

<section class="card-grid">
    <div class="stat-card">
        <div class="stat-title"><span class="stat-icon">&#127869;</span> Menus a venir</div>
        <div class="stat-value"><?= htmlspecialchars($menusAVenir) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title"><span class="stat-icon">&#128203;</span> Commandes confirmees</div>
        <div class="stat-value"><?= htmlspecialchars($cmdConfirmed) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title"><span class="stat-icon">&#9888;</span> Alertes allergenes</div>
        <div class="stat-value"><?= htmlspecialchars($alertesCount) ?></div>
    </div>
</section>

<section class="section-card">
    <h2>Chiffre d'affaires et popularite des menus</h2>
    <?php if ($caError): ?>
        <div class="alert" style="margin-bottom:12px;"><?= htmlspecialchars($caError) ?></div>
    <?php elseif (!$caHasData): ?>
        <div class="alert" style="margin-bottom:12px;">Aucune commande confirmee.</div>
    <?php endif; ?>

    <form method="get" class="filter-grid" style="margin-bottom:16px;">
        <div>
            <label>Filtrer CA par date</label>
            <input type="date" name="ca_date" value="<?= htmlspecialchars($caDate) ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a class="btn btn-ghost" href="/cantine_scolaire/admin/dashboard.php">Reset</a>
        </div>
    </form>

    <div class="card-grid" style="margin-bottom:16px;">
        <div class="stat-card">
            <div class="stat-title"><span class="stat-icon">&#128200;</span> CA du jour</div>
            <div class="stat-value"><?= htmlspecialchars(number_format($caJour, 2)) ?> DH</div>
        </div>
        <div class="stat-card">
            <div class="stat-title"><span class="stat-icon">&#128176;</span> CA accumule</div>
            <div class="stat-value"><?= htmlspecialchars(number_format($caTotal, 2)) ?> DH</div>
        </div>
        <?php if ($caHasData): ?>
            <div class="stat-card">
                <div class="stat-title"><span class="stat-icon">&#127873;</span> Menu le plus commande</div>
                <div class="stat-value"><?= htmlspecialchars($topMenu['description'] ?? '-') ?></div>
                <div class="text-muted" style="font-size:13px;">
                    <?= htmlspecialchars($topMenu['type_repas'] ?? '-') ?> - <?= htmlspecialchars($topMenu['quantite_totale'] ?? 0) ?> commandes
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><span class="stat-icon">&#128465;</span> Menu le moins commande</div>
                <div class="stat-value"><?= htmlspecialchars($flopMenu['description'] ?? '-') ?></div>
                <div class="text-muted" style="font-size:13px;">
                    <?= htmlspecialchars($flopMenu['type_repas'] ?? '-') ?> - <?= htmlspecialchars($flopMenu['quantite_totale'] ?? 0) ?> commandes
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div style="margin-bottom:12px;">
        <label for="ca-search">Recherche (date, type, description)</label>
        <input id="ca-search" type="text" placeholder="Ex: 2026-01-10, Dejeuner, Poulet...">
    </div>

    <div class="table-wrap">
        <table id="ca-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Quantite</th>
                    <th>Prix unitaire</th>
                    <th>Chiffre d'affaire</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($caMenus as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['date_menu']) ?></td>
                        <td><?= htmlspecialchars($row['type_repas']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars($row['quantite_totale']) ?></td>
                        <td><?= htmlspecialchars(number_format((float)$row['prix_unitaire'], 2)) ?></td>
                        <td><?= htmlspecialchars(number_format((float)$row['chiffre_affaire'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$caMenus): ?>
                    <tr><td colspan="6">Aucune commande confirmee.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-card">
    <h2>Menus les plus demandes</h2>
    <p class="text-muted">Classement par quantite commandee, du plus demande au moins demande.</p>
    <form method="get" class="filter-grid" style="margin-bottom:12px;">
        <div>
            <label>Filtrer par type</label>
            <select name="menu_type">
                <option value="">Tous</option>
                <?php foreach ($menuTypes as $mt): ?>
                    <option value="<?= $mt ?>" <?= $menuTypeFilter === $mt ? 'selected' : '' ?>><?= $mt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a class="btn btn-ghost" href="/cantine_scolaire/admin/dashboard.php">Reset</a>
        </div>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Quantite</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($menusParQte as $menu): ?>
                    <tr>
                        <td><?= htmlspecialchars($menu['id_menu']) ?></td>
                        <td><?= htmlspecialchars($menu['date_menu']) ?></td>
                        <td><?= htmlspecialchars($menu['type_repas']) ?></td>
                        <td><?= htmlspecialchars($menu['description']) ?></td>
                        <td><?= htmlspecialchars($menu['quantite_totale']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$menusParQte): ?>
                    <tr><td colspan="5">Aucun menu trouve.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-card">
    <h2>Planification des repas</h2>
    <form method="get" class="filter-grid" style="margin-bottom:12px;">
        <div>
            <label>Filtrer par date</label>
            <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a class="btn btn-ghost" href="/cantine_scolaire/admin/dashboard.php">Reset</a>
        </div>
        <div>
            <label>Recherche globale</label>
            <input id="filter-global" type="text" placeholder="Rechercher dans les tableaux...">
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Quantite</th>
                    <th>Description</th>
                    <th>Calories</th>
                    <th>Allergenes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($planifications as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['date_menu']) ?></td>
                        <td><?= htmlspecialchars($p['type_repas']) ?></td>
                        <td><strong><?= (int)$p['quantite_totale'] ?></strong></td>
                        <td><?= htmlspecialchars($p['description']) ?></td>
                        <td><?= htmlspecialchars($p['calories_total']) ?></td>
                        <td><?= htmlspecialchars($p['allergenes'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-card">
    <h2>Alertes allergenes</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Eleve</th>
                    <th>Classe</th>
                    <th>Allergene</th>
                    <th>Alerte</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alertes as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['date_menu']) ?></td>
                        <td><?= htmlspecialchars($a['eleve']) ?></td>
                        <td><?= htmlspecialchars($a['classe']) ?></td>
                        <td><?= htmlspecialchars($a['allergene_menu'] ?: '-') ?></td>
                        <td><?= ((int)$a['alerte'] === 1) ? '1' : '0' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-card">
    <h2>Soldes financiers</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Eleve</th>
                    <th>Classe</th>
                    <th>Solde</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($soldeRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nom'] ?? '') ?> <?= htmlspecialchars($row['prenom'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['classe'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['solde'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$soldeRows): ?>
                    <tr>
                        <td colspan="3">Aucun solde trouve.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filter = document.getElementById('filter-global');
    if (filter) {
        filter.addEventListener('input', () => {
            const q = filter.value.toLowerCase();
            document.querySelectorAll('table tbody tr').forEach(tr => {
                const text = tr.innerText.toLowerCase();
                tr.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    const caSearch = document.getElementById('ca-search');
    const caTable = document.getElementById('ca-table');
    if (caSearch && caTable) {
        caSearch.addEventListener('input', () => {
            const q = caSearch.value.toLowerCase().trim();
            const rows = Array.from(caTable.querySelectorAll('tbody tr'));
            rows.forEach(tr => {
                const dateTxt = (tr.children[0]?.innerText || '').toLowerCase().trim();
                const typeTxt = (tr.children[1]?.innerText || '').toLowerCase().trim();
                const descTxt = (tr.children[2]?.innerText || '').toLowerCase().trim();
                const match = !q || dateTxt.startsWith(q) || typeTxt.startsWith(q) || descTxt.startsWith(q);
                tr.style.display = match ? '' : 'none';
            });
        });
    }
});
</script>

<?php require __DIR__ . '/../partials/layout_end.php'; ?>


