-- VUES: Chiffre d'affaires & popularite des menus
-- Base: cantine_scolaire
-- Source: commandes.statut = 'CONFIRMEE'

CREATE OR REPLACE VIEW v_ca_menus_par_jour AS
SELECT
    m.id_menu,
    m.date_menu,
    m.type_repas,
    m.description,
    COALESCE(SUM(c.quantite), 0) AS quantite_totale,
    COALESCE(t.prix, 0) AS prix_unitaire,
    COALESCE(SUM(c.quantite), 0) * COALESCE(t.prix, 0) AS chiffre_affaire
FROM menus m
LEFT JOIN tarifs t
    ON t.type_repas = m.type_repas
LEFT JOIN commandes c
    ON c.id_menu = m.id_menu
    AND c.statut = 'CONFIRMEE'
GROUP BY m.id_menu, m.date_menu, m.type_repas, m.description, t.prix;

CREATE OR REPLACE VIEW v_ca_total_par_jour AS
SELECT
    date_menu,
    COALESCE(SUM(chiffre_affaire), 0) AS chiffre_affaire
FROM v_ca_menus_par_jour
GROUP BY date_menu;

CREATE OR REPLACE VIEW v_menus_top_flop AS
SELECT
    id_menu,
    type_repas,
    description,
    COALESCE(SUM(quantite_totale), 0) AS quantite_totale,
    COALESCE(SUM(chiffre_affaire), 0) AS chiffre_affaire
FROM v_ca_menus_par_jour
GROUP BY id_menu, type_repas, description;
