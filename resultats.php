<?php
session_start();
require "config.php";

if (!isset($_SESSION["id"]) || ($_SESSION["role"] ?? "") !== "professeur") {
  header("Location: index.php");
  exit();
}

$identifiant   = $_SESSION["identifiant"];
$id_professeur = $_SESSION["id"];

/* ---------------------------
   Vérifier si la colonne analyse existe
--------------------------- */
$checkAnalyse = $conn->query("SHOW COLUMNS FROM g1_dictes_resultats LIKE 'analyse'");
$hasAnalyse = ($checkAnalyse && $checkAnalyse->num_rows > 0);

/* ---------------------------
   Récupérer les filtres
--------------------------- */
$eleve      = $_GET['eleve'] ?? '';
$typeDictee = $_GET['type_dictee'] ?? '';
$typeErreur = $_GET['type_erreur'] ?? '';
$date       = $_GET['date'] ?? '';
$nom_classe_filtre = $_GET['nom_classe'] ?? '';
$dictee_id         = $_GET['id_dictee'] ?? '';
$niveau_filtre     = $_GET['niveau'] ?? '';

/* ---------------------------
   Listes dynamiques pour les filtres
--------------------------- */
$liste_eleves_sql = "
SELECT DISTINCT e.id_eleve, c.nom_classe
FROM g1_dictes_resultats r
JOIN g1_eleve e ON r.id_eleve = e.id_eleve
JOIN g1_classe c ON e.id_classe = c.id_classe
WHERE c.id_professeur = ?
ORDER BY e.id_eleve
";
$stmt_eleves = $conn->prepare($liste_eleves_sql);
$stmt_eleves->bind_param("i", $id_professeur);
$stmt_eleves->execute();
$liste_eleves = $stmt_eleves->get_result()->fetch_all(MYSQLI_ASSOC);

$liste_types_sql = "
SELECT DISTINCT d.type_dictee
FROM g1_dictes_resultats r
JOIN g1_dictee d ON r.id_dictee = d.id_dictee
JOIN g1_eleve e ON r.id_eleve = e.id_eleve
JOIN g1_classe c ON e.id_classe = c.id_classe
WHERE c.id_professeur = ?
ORDER BY d.type_dictee
";
$stmt_types = $conn->prepare($liste_types_sql);
$stmt_types->bind_param("i", $id_professeur);
$stmt_types->execute();
$liste_types = $stmt_types->get_result()->fetch_all(MYSQLI_ASSOC);

// Liste des noms de classes pour le filtre "Nom de classe"
$liste_nom_classes_sql = "
SELECT DISTINCT c.id_classe, c.nom_classe, c.niveau_scolaire
FROM g1_dictes_resultats r
JOIN g1_eleve e ON r.id_eleve = e.id_eleve
JOIN g1_classe c ON e.id_classe = c.id_classe
WHERE c.id_professeur = ?
ORDER BY c.nom_classe
";
$stmt_nom_classes = $conn->prepare($liste_nom_classes_sql);
$stmt_nom_classes->bind_param("i", $id_professeur);
$stmt_nom_classes->execute();
$liste_nom_classes = $stmt_nom_classes->get_result()->fetch_all(MYSQLI_ASSOC);

// Liste des dictées (titres) pour le filtre "Dictée"
$liste_dictees_sql = "
SELECT DISTINCT d.id_dictee, d.titre
FROM g1_dictes_resultats r
JOIN g1_dictee d ON r.id_dictee = d.id_dictee
JOIN g1_eleve e ON r.id_eleve = e.id_eleve
JOIN g1_classe c ON e.id_classe = c.id_classe
WHERE c.id_professeur = ?
ORDER BY d.titre
";
$stmt_dictees = $conn->prepare($liste_dictees_sql);
$stmt_dictees->bind_param("i", $id_professeur);
$stmt_dictees->execute();
$liste_dictees = $stmt_dictees->get_result()->fetch_all(MYSQLI_ASSOC);

// Liste des niveaux scolaires pour le filtre "Niveau scolaire"
$liste_niveaux_sql = "
SELECT DISTINCT d.niveau_scolaire
FROM g1_dictes_resultats r
JOIN g1_dictee d ON r.id_dictee = d.id_dictee
JOIN g1_eleve e ON r.id_eleve = e.id_eleve
JOIN g1_classe c ON e.id_classe = c.id_classe
WHERE c.id_professeur = ?
ORDER BY d.niveau_scolaire
";
$stmt_niveaux = $conn->prepare($liste_niveaux_sql);
$stmt_niveaux->bind_param("i", $id_professeur);
$stmt_niveaux->execute();
$liste_niveaux = $stmt_niveaux->get_result()->fetch_all(MYSQLI_ASSOC);

/* ---------------------------
   SQL principal
--------------------------- */
$sql = "
SELECT
    r.id_resultat,
    r.id_eleve,
    r.id_dictee,
    c.nom_classe,
    d.niveau_scolaire,
    d.type_dictee,
    d.titre,
    d.contenu_reference AS dictee_modele,
    r.dictee_eleve,
";

if ($hasAnalyse) {
  $sql .= "    IFNULL(r.analyse, '') AS analyse,\n";
} else {
  $sql .= "    '' AS analyse,\n";
}

$sql .= "
    r.date_saisie,
    (
        r.faute_orthographique +
        r.mot_ou_ponctuation_manquant +
        r.accord_adjectif +
        r.nom_pluriel +
        r.accord_sujet_verbe
    ) AS nb_erreurs,
    GREATEST(0,
        20 - (
            r.faute_orthographique +
            r.mot_ou_ponctuation_manquant +
            r.accord_adjectif +
            r.nom_pluriel +
            r.accord_sujet_verbe
        )
    ) AS score,
    r.faute_orthographique,
    r.mot_ou_ponctuation_manquant,
    r.accord_adjectif,
    r.nom_pluriel,
    r.accord_sujet_verbe
FROM g1_dictes_resultats r
JOIN g1_dictee d ON r.id_dictee = d.id_dictee
JOIN g1_eleve e ON r.id_eleve = e.id_eleve
JOIN g1_classe c ON e.id_classe = c.id_classe
WHERE c.id_professeur = ?
";

$params = [$id_professeur];
$types  = "i";

if ($eleve !== '') {
  $sql .= " AND r.id_eleve = ?";
  $params[] = (int)$eleve;
  $types .= "i";
}

if ($typeDictee !== '') {
  $sql .= " AND d.type_dictee = ?";
  $params[] = $typeDictee;
  $types .= "s";
}

if ($date !== '') {
  $sql .= " AND r.date_saisie = ?";
  $params[] = $date;
  $types .= "s";
}

if ($typeErreur !== '') {
  if ($typeErreur === 'orthographe') {
    $sql .= " AND r.faute_orthographique > 0";
  } elseif ($typeErreur === 'mot') {
    $sql .= " AND r.mot_ou_ponctuation_manquant > 0";
  } elseif ($typeErreur === 'accord_adjectif') {
    $sql .= " AND r.accord_adjectif > 0";
  } elseif ($typeErreur === 'pluriel') {
    $sql .= " AND r.nom_pluriel > 0";
  } elseif ($typeErreur === 'accord_sujet_verbe') {
    $sql .= " AND r.accord_sujet_verbe > 0";
  }
}

// Filtre par nom de classe
if ($nom_classe_filtre !== '') {
  $sql .= " AND c.id_classe = ?";
  $params[] = (int)$nom_classe_filtre;
  $types .= "i";
}

// Filtre par dictée (titre)
if ($dictee_id !== '') {
  $sql .= " AND r.id_dictee = ?";
  $params[] = (int)$dictee_id;
  $types .= "i";
}

// Filtre par niveau scolaire
if ($niveau_filtre !== '') {
  $sql .= " AND d.niveau_scolaire = ?";
  $params[] = $niveau_filtre;
  $types .= "s";
}

$sql .= " ORDER BY r.date_saisie DESC, r.id_resultat DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
  die("SQL error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <title>Résultats — Gestion des Dictées</title>

  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="resultats.css">
</head>

<body>

  <header>
    <span class="brand">Gestion des Dictées</span>
    <span class="bonjour">Bonjour, <?= htmlspecialchars($identifiant) ?></span>
    <button class="btn-deconnexion" onclick="window.location.href='deconnexion.php'">
      ⇥ Déconnexion
    </button>
  </header>

  <main>

    <a class="retour" href="classes.php">← Retour aux classes</a>

    <div class="top">
      <h1>Résultats</h1>
      <a href="export_csv.php?<?= http_build_query(array_filter([
                                'eleve'       => $eleve,
                                'type_dictee' => $typeDictee,
                                'type_erreur' => $typeErreur,
                                'date'        => $date,
                                'nom_classe'  => $nom_classe_filtre,
                                'id_dictee'   => $dictee_id,
                                'niveau'      => $niveau_filtre,
                              ])) ?>">
        <button class="btn-export">⬇ Exporter CSV</button>
      </a>
    </div>

    <form method="GET">
      <div class="filters">

        <div class="filter-group">
          <label>Élève</label>
          <select name="eleve">
            <option value="">Tous les élèves</option>
            <?php foreach ($liste_eleves as $e): ?>
              <option value="<?= (int)$e["id_eleve"] ?>" <?= $eleve === (string)$e["id_eleve"] ? 'selected' : '' ?>>
                Élève #<?= (int)$e["id_eleve"] ?> — <?= htmlspecialchars($e["nom_classe"]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label>Type de dictée</label>
          <select name="type_dictee">
            <option value="">Tous les types</option>
            <?php foreach ($liste_types as $t): ?>
              <option value="<?= htmlspecialchars($t["type_dictee"]) ?>" <?= $typeDictee === $t["type_dictee"] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t["type_dictee"]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label>Type d'erreur</label>
          <select name="type_erreur">
            <option value="">Toutes les fautes</option>
            <option value="orthographe" <?= $typeErreur === 'orthographe' ? 'selected' : '' ?>>Orthographe</option>
            <option value="mot" <?= $typeErreur === 'mot' ? 'selected' : '' ?>>Mot manquant</option>
            <option value="accord_adjectif" <?= $typeErreur === 'accord_adjectif' ? 'selected' : '' ?>>Accord adjectif</option>
            <option value="pluriel" <?= $typeErreur === 'pluriel' ? 'selected' : '' ?>>Pluriel</option>
            <option value="accord_sujet_verbe" <?= $typeErreur === 'accord_sujet_verbe' ? 'selected' : '' ?>>Accord sujet-verbe</option>
          </select>
        </div>

        <div class="filter-group">
          <label>Date</label>
          <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">
        </div>

        <div class="filter-group">
          <label>Nom de classe</label>
          <select name="nom_classe">
            <option value="">Toutes les classes</option>
            <?php foreach ($liste_nom_classes as $nc): ?>
              <option value="<?= (int)$nc["id_classe"] ?>" <?= $nom_classe_filtre === (string)$nc["id_classe"] ? 'selected' : '' ?>>
                <?= htmlspecialchars($nc["nom_classe"]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label>Dictée</label>
          <select name="id_dictee">
            <option value="">Toutes les dictées</option>
            <?php foreach ($liste_dictees as $dd): ?>
              <option value="<?= (int)$dd["id_dictee"] ?>" <?= $dictee_id === (string)$dd["id_dictee"] ? 'selected' : '' ?>>
                <?= htmlspecialchars($dd["titre"]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label>Niveau scolaire</label>
          <select name="niveau">
            <option value="">Tous les niveaux</option>
            <?php foreach ($liste_niveaux as $nv): ?>
              <option value="<?= htmlspecialchars($nv["niveau_scolaire"]) ?>" <?= $niveau_filtre === $nv["niveau_scolaire"] ? 'selected' : '' ?>>
                <?= htmlspecialchars($nv["niveau_scolaire"]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-actions">
          <button type="submit" class="btn-search">Rechercher</button>
          <a href="resultats.php">
            <button type="button" class="btn-reset">Réinitialiser</button>
          </a>
        </div>

      </div>
    </form>

    <div class="table-container">
      <table id="resultsTable">
        <thead>
          <tr>
            <th>Nom de classe</th>
            <th>Niveau scolaire</th>
            <th>Élève</th>
            <th>Type dictée</th>
            <th>Titre dictée</th>
            <th>Dictée modèle</th>
            <th>Dictée élève</th>
            <th>Analyse</th>
            <th>Date</th>
            <th>Score</th>
            <th>Nb erreurs</th>
            <th>Faute orthographique</th>
            <th>Mot ou ponctuation manquant</th>
            <th>Accord adjectif</th>
            <th>Nom pluriel</th>
            <th>Accord sujet-verbe</th>
          </tr>
        </thead>

        <tbody>
          <?php if ($result->num_rows === 0): ?>
            <tr>
              <td colspan="16">Aucun résultat trouvé.</td>
            </tr>
          <?php endif; ?>

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
              <td><?= htmlspecialchars($row["id_eleve"]) ?></td>
              <td><?= htmlspecialchars($row["type_dictee"]) ?></td>
              <td><?= htmlspecialchars($row["titre"]) ?></td>
              <td class="dictee"><?= htmlspecialchars($row["dictee_modele"]) ?></td>
              <td class="dictee"><?= htmlspecialchars($row["dictee_eleve"]) ?></td>
              <td class="analyse"><?= htmlspecialchars($row["analyse"]) ?></td>
              <td><?= htmlspecialchars($row["date_saisie"]) ?></td>
              <td><?= htmlspecialchars($row["score"]) ?></td>
              <td class="<?= $row["nb_erreurs"] > 0 ? 'error' : '' ?>">
                <?= htmlspecialchars($row["nb_erreurs"]) ?>
              </td>
              <td class="<?= $row["faute_orthographique"] > 0 ? 'error' : '' ?>">
                <?= htmlspecialchars($row["faute_orthographique"]) ?>
              </td>
              <td class="<?= $row["mot_ou_ponctuation_manquant"] > 0 ? 'error' : '' ?>">
                <?= htmlspecialchars($row["mot_ou_ponctuation_manquant"]) ?>
              </td>
              <td class="<?= $row["accord_adjectif"] > 0 ? 'error' : '' ?>">
                <?= htmlspecialchars($row["accord_adjectif"]) ?>
              </td>
              <td class="<?= $row["nom_pluriel"] > 0 ? 'error' : '' ?>">
                <?= htmlspecialchars($row["nom_pluriel"]) ?>
              </td>
              <td class="<?= $row["accord_sujet_verbe"] > 0 ? 'error' : '' ?>">
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