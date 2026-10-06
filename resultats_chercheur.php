<?php
session_start();
require "config.php";

if (!isset($_SESSION["id"]) || ($_SESSION["role"] ?? "") !== "chercheur") {
    header("Location: index.php");
    exit();
}

$identifiant = $_SESSION["identifiant"];

$filtre_langue = $_GET["langue"] ?? "";
$filtre_annee = $_GET["annee_naissance"] ?? "";
$filtre_type_erreur = $_GET["type_erreur"] ?? "";
$filtre_date = $_GET["date"] ?? "";
$filtre_niveau = $_GET["niveau"] ?? "";
$filtre_ecole  = $_GET["ecole"] ?? "";
$filtre_nom_classe = $_GET["nom_classe"] ?? "";
$filtre_dictee_id  = $_GET["id_dictee_filtre"] ?? "";
$filtre_eleve = $_GET["eleve"] ?? "";

$liste_langues_result = $conn->query("SELECT DISTINCT langue_maternelle FROM g1_eleve WHERE langue_maternelle IS NOT NULL ORDER BY langue_maternelle");
$liste_langues = $liste_langues_result ? $liste_langues_result->fetch_all(MYSQLI_ASSOC) : [];

$liste_titres_result = $conn->query("SELECT id_dictee, titre, niveau_scolaire FROM g1_dictee ORDER BY titre");
$liste_titres = $liste_titres_result ? $liste_titres_result->fetch_all(MYSQLI_ASSOC) : [];

$filtre_dictee = $_GET["id_dictee"] ?? "";

$liste_annees_result = $conn->query("SELECT DISTINCT annee_naissance FROM g1_eleve ORDER BY annee_naissance");
$liste_annees = $liste_annees_result ? $liste_annees_result->fetch_all(MYSQLI_ASSOC) : [];

// Liste des élèves (ID) pour le filtre
$liste_eleves_result = $conn->query("
    SELECT DISTINCT e.id_eleve, c.nom_classe
    FROM g1_eleve e
    JOIN g1_classe c ON e.id_classe = c.id_classe
    JOIN g1_dictes_resultats r ON r.id_eleve = e.id_eleve
    ORDER BY e.id_eleve
");
$liste_eleves = $liste_eleves_result ? $liste_eleves_result->fetch_all(MYSQLI_ASSOC) : [];

// Liste des niveaux scolaires
$liste_niveaux_result = $conn->query("
    SELECT DISTINCT c.niveau_scolaire
    FROM g1_classe c
    JOIN g1_eleve e ON e.id_classe = c.id_classe
    JOIN g1_dictes_resultats r ON r.id_eleve = e.id_eleve
    ORDER BY c.niveau_scolaire
");
$liste_niveaux = $liste_niveaux_result ? $liste_niveaux_result->fetch_all(MYSQLI_ASSOC) : [];

// Liste des écoles
$liste_ecoles_result = $conn->query("
    SELECT DISTINCT c.nom_ecole
    FROM g1_classe c
    JOIN g1_eleve e ON e.id_classe = c.id_classe
    JOIN g1_dictes_resultats r ON r.id_eleve = e.id_eleve
    WHERE c.nom_ecole IS NOT NULL AND c.nom_ecole != ''
    ORDER BY c.nom_ecole
");
$liste_ecoles = $liste_ecoles_result ? $liste_ecoles_result->fetch_all(MYSQLI_ASSOC) : [];

// Liste des noms de classes (pour le filtre séparé)
$liste_nom_classes_result = $conn->query("
    SELECT DISTINCT c.id_classe, c.nom_classe, c.niveau_scolaire
    FROM g1_classe c
    JOIN g1_eleve e ON e.id_classe = c.id_classe
    JOIN g1_dictes_resultats r ON r.id_eleve = e.id_eleve
    ORDER BY c.nom_classe
");
$liste_nom_classes = $liste_nom_classes_result ? $liste_nom_classes_result->fetch_all(MYSQLI_ASSOC) : [];

$sql = "
SELECT
    r.id_resultat,
    r.id_eleve,
    e.annee_naissance,
    e.langue_maternelle,
    e.francais_maison,
    c.id_classe,
    c.nom_classe,
    c.niveau_scolaire,
    c.nom_ecole,
    d.titre,
    d.type_dictee,
    d.contenu_reference AS dictee_modele,
    r.dictee_eleve,
    r.analyse,
    r.date_saisie,
    COALESCE(
        r.score,
        GREATEST(
            0,
            20 - (
                r.faute_orthographique +
                r.mot_ou_ponctuation_manquant +
                r.accord_adjectif +
                r.nom_pluriel +
                r.accord_sujet_verbe
            )
        )
    ) AS score,
    (
        r.faute_orthographique +
        r.mot_ou_ponctuation_manquant +
        r.accord_adjectif +
        r.nom_pluriel +
        r.accord_sujet_verbe
    ) AS nb_erreurs,
    r.faute_orthographique,
    r.mot_ou_ponctuation_manquant,
    r.accord_adjectif,
    r.nom_pluriel,
    r.accord_sujet_verbe
FROM g1_dictes_resultats r
JOIN g1_dictee d ON r.id_dictee = d.id_dictee
JOIN g1_eleve e ON r.id_eleve = e.id_eleve
JOIN g1_classe c ON e.id_classe = c.id_classe
WHERE 1=1
";

if ($filtre_eleve !== "") {
    $safe = (int)$filtre_eleve;
    $sql .= " AND r.id_eleve = $safe";
}
if ($filtre_langue !== "") {
    $safe = $conn->real_escape_string($filtre_langue);
    $sql .= " AND e.langue_maternelle = '$safe'";
}
if ($filtre_annee !== "") {
    $safe = (int)$filtre_annee;
    $sql .= " AND e.annee_naissance = $safe";
}

if ($filtre_dictee !== "") {
    $safe = (int)$filtre_dictee;
    $sql .= " AND r.id_dictee = $safe";
}

if ($filtre_date !== "") {
    $safe = $conn->real_escape_string($filtre_date);
    $sql .= " AND r.date_saisie = '$safe'";
}
if ($filtre_type_erreur !== "") {
    if ($filtre_type_erreur === "orthographe") {
        $sql .= " AND r.faute_orthographique > 0";
    }
    if ($filtre_type_erreur === "mot") {
        $sql .= " AND r.mot_ou_ponctuation_manquant > 0";
    }
    if ($filtre_type_erreur === "accord_adjectif") {
        $sql .= " AND r.accord_adjectif > 0";
    }
    if ($filtre_type_erreur === "pluriel") {
        $sql .= " AND r.nom_pluriel > 0";
    }
    if ($filtre_type_erreur === "accord_sujet_verbe") {
        $sql .= " AND r.accord_sujet_verbe > 0";
    }
}

// Filtre par niveau scolaire
if ($filtre_niveau !== "") {
    $safe = $conn->real_escape_string($filtre_niveau);
    $sql .= " AND c.niveau_scolaire = '$safe'";
}

// Filtre par école
if ($filtre_ecole !== "") {
    $safe = $conn->real_escape_string($filtre_ecole);
    $sql .= " AND c.nom_ecole = '$safe'";
}

// Filtre par nom de classe
if ($filtre_nom_classe !== "") {
    $safe = (int)$filtre_nom_classe;
    $sql .= " AND c.id_classe = $safe";
}

// Filtre par dictée (titre)
if ($filtre_dictee_id !== "") {
    $safe = (int)$filtre_dictee_id;
    $sql .= " AND r.id_dictee = $safe";
}

$sql .= " ORDER BY r.date_saisie DESC, r.id_resultat DESC";
$result = $conn->query($sql);
if (!$result) {
    die("Erreur SQL : " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Espace chercheur — Gestion des Dictées</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="resultats.css">
</head>

<body>

    <header>
        <span class="brand">Gestion des Dictées</span>
        <span class="bonjour">Bonjour, <?= htmlspecialchars($identifiant) ?> (chercheur)</span>
        <button class="btn-deconnexion" onclick="window.location.href='deconnexion.php'">⇥ Déconnexion</button>
    </header>

    <main>

        <div class="top">
            <h1>Espace chercheur</h1>
            <button class="btn-export" id="exportCSVBtn" onclick="exportCSV()">
                ⬇ Exporter CSV
            </button>
        </div>

        <form method="GET">
            <div class="filters">

                <div class="filter-group">
                    <label>Nom de classe</label>
                    <select name="nom_classe">
                        <option value="">Toutes les classes</option>
                        <?php foreach ($liste_nom_classes as $nc) { ?>
                            <option value="<?= $nc["id_classe"] ?>"
                                <?= $filtre_nom_classe === (string)$nc["id_classe"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($nc["nom_classe"]) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Élève (ID)</label>
                    <select name="eleve">
                        <option value="">Tous les élèves</option>
                        <?php foreach ($liste_eleves as $el) { ?>
                            <option value="<?= (int)$el["id_eleve"] ?>"
                                <?= $filtre_eleve === (string)$el["id_eleve"] ? "selected" : "" ?>>
                                Élève #<?= (int)$el["id_eleve"] ?> — <?= htmlspecialchars($el["nom_classe"]) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Langue maternelle</label>
                    <select name="langue">
                        <option value="">Toutes les langues</option>
                        <?php foreach ($liste_langues as $l) { ?>
                            <option value="<?= htmlspecialchars($l["langue_maternelle"]) ?>"
                                <?= $filtre_langue === $l["langue_maternelle"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($l["langue_maternelle"]) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Année de naissance</label>
                    <select name="annee_naissance">
                        <option value="">Toutes les années</option>
                        <?php foreach ($liste_annees as $a) { ?>
                            <option value="<?= $a["annee_naissance"] ?>"
                                <?= $filtre_annee === (string)$a["annee_naissance"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($a["annee_naissance"]) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Dictée</label>
                    <select name="id_dictee">
                        <option value="">Toutes les dictées</option>
                        <?php foreach ($liste_titres as $t) { ?>
                            <option value="<?= $t["id_dictee"] ?>"
                                <?= $filtre_dictee === (string)$t["id_dictee"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($t["titre"] ?: $t["niveau_scolaire"]) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Type d'erreur</label>
                    <select name="type_erreur">
                        <option value="">Toutes les erreurs</option>
                        <option value="orthographe" <?= $filtre_type_erreur === "orthographe" ? "selected" : "" ?>>Orthographe</option>
                        <option value="mot" <?= $filtre_type_erreur === "mot" ? "selected" : "" ?>>Mot manquant</option>
                        <option value="accord_adjectif" <?= $filtre_type_erreur === "accord_adjectif" ? "selected" : "" ?>>Accord adjectif</option>
                        <option value="pluriel" <?= $filtre_type_erreur === "pluriel" ? "selected" : "" ?>>Pluriel</option>
                        <option value="accord_sujet_verbe" <?= $filtre_type_erreur === "accord_sujet_verbe" ? "selected" : "" ?>>Accord sujet-verbe</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($filtre_date) ?>">
                </div>

                <div class="filter-group">
                    <label>Niveau scolaire</label>
                    <select name="niveau">
                        <option value="">Tous les niveaux</option>
                        <?php foreach ($liste_niveaux as $n) { ?>
                            <option value="<?= htmlspecialchars($n["niveau_scolaire"]) ?>"
                                <?= $filtre_niveau === $n["niveau_scolaire"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($n["niveau_scolaire"]) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>École</label>
                    <select name="ecole">
                        <option value="">Toutes les écoles</option>
                        <?php foreach ($liste_ecoles as $ec) { ?>
                            <option value="<?= htmlspecialchars($ec["nom_ecole"]) ?>"
                                <?= $filtre_ecole === $ec["nom_ecole"] ? "selected" : "" ?>>
                                <?= htmlspecialchars($ec["nom_ecole"]) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-search">Rechercher</button>
                    <button type="button" class="btn-reset" onclick="window.location.href='resultats_chercheur.php'">Réinitialiser</button>
                </div>

            </div>
        </form>

        <div class="table-container">
            <table id="resultsTable">
                <thead>
                    <tr>
                        <th>Nom de classe</th>
                        <th>Niveau scolaire</th>
                        <th>École</th>
                        <th>Élève (ID)</th>
                        <th>Année naissance</th>
                        <th>Langue maternelle</th>
                        <th>Français à la maison</th>
                        <th>Dictée</th>
                        <th>Type dictée</th>
                        <th>Dictée modèle</th>
                        <th>Dictée élève</th>
                        <th>Analyse</th>
                        <th>Date</th>
                        <th>Score /20</th>
                        <th>Nb erreurs</th>
                        <th>Orthographe</th>
                        <th>Mot manquant</th>
                        <th>Accord adjectif</th>
                        <th>Nom pluriel</th>
                        <th>Accord sujet-verbe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0) { ?>
                        <tr>
                            <td colspan="20" style="text-align:center;">Aucun résultat pour ces filtres.</td>
                        </tr>
                    <?php } ?>

                    <?php while ($row = $result->fetch_assoc()) {

                        if (empty($row["analyse"])) {
                            $ch = curl_init("http://localhost" . dirname($_SERVER['PHP_SELF']) . "/analyse.php");
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                                'model'       => $row['dictee_modele'],
                                'student'     => $row['dictee_eleve'],
                                'id_resultat' => $row['id_resultat'],
                            ]);
                            $row["analyse"] = trim(curl_exec($ch));
                            curl_close($ch);
                        }

                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row["nom_classe"]) ?></td>
                            <td><?= htmlspecialchars($row["niveau_scolaire"]) ?></td>
                            <td><?= htmlspecialchars($row["nom_ecole"] ?? "—") ?></td>
                            <td>Élève #<?= htmlspecialchars($row["id_eleve"]) ?></td>
                            <td><?= htmlspecialchars($row["annee_naissance"]) ?></td>
                            <td><?= htmlspecialchars($row["langue_maternelle"] ?? "—") ?></td>
                            <td><?= $row["francais_maison"] ? "Oui" : "Non" ?></td>
                            <td><?= htmlspecialchars($row["titre"]) ?></td>
                            <td><?= htmlspecialchars($row["type_dictee"]) ?></td>
                            <td class="dictee" data-analyse-model><?= htmlspecialchars($row["dictee_modele"]) ?></td>
                            <td class="dictee" data-analyse-student><?= htmlspecialchars($row["dictee_eleve"]) ?></td>
                            <td class="analyse"><?= nl2br(htmlspecialchars($row["analyse"] ?? "")) ?></td>
                            <td><?= htmlspecialchars($row["date_saisie"]) ?></td>
                            <td><?= htmlspecialchars($row["score"]) ?></td>
                            <td class="<?= $row["nb_erreurs"] > 0 ? "error" : "" ?>">
                                <?= htmlspecialchars($row["nb_erreurs"]) ?>
                            </td>
                            <td class="<?= $row["faute_orthographique"] > 0 ? "error" : "" ?>">
                                <?= htmlspecialchars($row["faute_orthographique"]) ?>
                            </td>
                            <td class="<?= $row["mot_ou_ponctuation_manquant"] > 0 ? "error" : "" ?>">
                                <?= htmlspecialchars($row["mot_ou_ponctuation_manquant"]) ?>
                            </td>
                            <td class="<?= $row["accord_adjectif"] > 0 ? "error" : "" ?>">
                                <?= htmlspecialchars($row["accord_adjectif"]) ?>
                            </td>
                            <td class="<?= $row["nom_pluriel"] > 0 ? "error" : "" ?>">
                                <?= htmlspecialchars($row["nom_pluriel"]) ?>
                            </td>
                            <td class="<?= $row["accord_sujet_verbe"] > 0 ? "error" : "" ?>">
                                <?= htmlspecialchars($row["accord_sujet_verbe"]) ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </main>

    <footer>
        Gestion des Dictées — Université Grenoble Alpes<br>
M1 Industrie de la Langue — 2025-2026
    </footer>

    <script src="resultats.js"></script>


</body>

</html>