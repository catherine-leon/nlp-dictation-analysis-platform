<?php
include("config.php");

/* ---------------------------
   Vérifier si la colonne analyse existe
--------------------------- */
$checkAnalyse = $conn->query("SHOW COLUMNS FROM g1_dictes_resultats LIKE 'analyse'");
$hasAnalyse = ($checkAnalyse && $checkAnalyse->num_rows > 0);

/* ---------------------------
   Récupérer les filtres (mêmes que resultats.php)
--------------------------- */
$eleve      = $_GET['eleve']       ?? '';
$typeDictee = $_GET['type_dictee'] ?? '';
$typeErreur = $_GET['type_erreur'] ?? '';
$date       = $_GET['date']        ?? '';
$nom_classe_filtre = $_GET['nom_classe'] ?? '';
$dictee_id         = $_GET['id_dictee']  ?? '';
$niveau_filtre     = $_GET['niveau']     ?? '';

/* ---------------------------
   SQL (identique à resultats.php)
--------------------------- */
$sql = "
SELECT
    r.id_resultat,
    r.id_eleve,
    c.nom_classe,
    d.niveau_scolaire,
    d.titre,
    d.type_dictee,
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
JOIN g1_dictee d
    ON r.id_dictee = d.id_dictee
JOIN g1_eleve e
    ON r.id_eleve = e.id_eleve
JOIN g1_classe c
    ON e.id_classe = c.id_classe
WHERE 1=1
";

if ($eleve !== '') {
    $eleveSafe = (int)$eleve;
    $sql .= " AND r.id_eleve = $eleveSafe";
}

if ($typeDictee !== '') {
    $typeDicteeSafe = $conn->real_escape_string($typeDictee);
    $sql .= " AND d.type_dictee = '$typeDicteeSafe'";
}

if ($date !== '') {
    $dateSafe = $conn->real_escape_string($date);
    $sql .= " AND r.date_saisie = '$dateSafe'";
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
    $safe = (int)$nom_classe_filtre;
    $sql .= " AND c.id_classe = $safe";
}

// Filtre par dictée
if ($dictee_id !== '') {
    $safe = (int)$dictee_id;
    $sql .= " AND r.id_dictee = $safe";
}

// Filtre par niveau scolaire
if ($niveau_filtre !== '') {
    $safe = $conn->real_escape_string($niveau_filtre);
    $sql .= " AND d.niveau_scolaire = '$safe'";
}

$sql .= " ORDER BY r.date_saisie DESC, r.id_resultat DESC";

$result = $conn->query($sql);

if (!$result) {
    die("SQL error: " . $conn->error);
}

/* ---------------------------
   Générer le nom du fichier avec la date du jour
--------------------------- */
$filename = 'resultats_dictes_' . date('Y-m-d') . '.csv';

/* ---------------------------
   Headers HTTP pour forcer le téléchargement
--------------------------- */
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

/* ---------------------------
   BOM UTF-8 pour que Excel ouvre correctement les accents
--------------------------- */
echo "\xEF\xBB\xBF";

/* ---------------------------
   Écriture du CSV
--------------------------- */
$output = fopen('php://output', 'w');

// En-têtes des colonnes
fputcsv($output, [
    'Nom de classe',
    'Niveau scolaire',
    'Élève',
    'Type dictée',
    'Titre dictée',
    'Dictée modèle',
    'Dictée élève',
    'Analyse',
    'Date',
    'Score',
    'Nb erreurs',
    'Faute orthographique',
    'Mot ou ponctuation manquant',
    'Accord adjectif',
    'Nom pluriel',
    'Accord sujet-verbe',
], ';');

// Lignes de données
while ($row = $result->fetch_assoc()) {
    // Remplacer les sauts de ligne dans l'analyse par un espace
    // pour éviter que le CSV soit mal parsé par Excel
    $analyse_clean = str_replace(["\r\n", "\r", "\n"], ' | ', $row['analyse'] ?? '');

    fputcsv($output, [
        $row['nom_classe'],
        $row['niveau_scolaire'],
        $row['id_eleve'],
        $row['type_dictee'],
        $row['titre'],
        $row['dictee_modele'],
        $row['dictee_eleve'],
        $analyse_clean,
        $row['date_saisie'],
        $row['score'],
        $row['nb_erreurs'],
        $row['faute_orthographique'],
        $row['mot_ou_ponctuation_manquant'],
        $row['accord_adjectif'],
        $row['nom_pluriel'],
        $row['accord_sujet_verbe'],
    ], ';');
}

fclose($output);
exit;
