<?php
/*
 * analyse.php
 * ----------
 * Reçoit via POST :
 *   - model        : texte modèle (dictée de référence)
 *   - student      : texte de l'élève
 *   - id_resultat  : ID de la ligne dans g1_dictes_resultats à mettre à jour
 *
 * Exécute analyser_stanza.py, sauvegarde le résultat dans la BDD,
 * et retourne le texte de l'analyse (comportement inchangé pour l'affichage).
 */

include("config.php");

$model       = $_POST['model']       ?? '';
$student     = $_POST['student']     ?? '';
$id_resultat = isset($_POST['id_resultat']) ? (int)$_POST['id_resultat'] : 0;

if ($model === '' || $student === '') {
    echo "Données manquantes";
    exit;
}

$script = __DIR__ . "/analyser_stanza.py";

/*
 * On encode les textes en base64 pour éviter tous les problèmes
 * d'accents / apostrophes / encodage lors du passage PHP -> shell -> Python.
 */
$arg_model_b64   = escapeshellarg(base64_encode($model));
$arg_student_b64 = escapeshellarg(base64_encode($student));

$commande = "python3 " .
            escapeshellarg($script) . " " .
            $arg_model_b64 . " " . $arg_student_b64 . " 2>&1";

$resultat = shell_exec($commande);

if ($resultat === null) {
    $resultat = "Analyse indisponible";
}

$resultat = trim($resultat);

/* ---------------------------
   Sauvegarder dans la BDD
   (seulement si id_resultat est fourni et valide)
--------------------------- */
if ($id_resultat > 0) {
    $stmt = $conn->prepare("
        UPDATE g1_dictes_resultats
        SET analyse = ?
        WHERE id_resultat = ?
    ");
    $stmt->bind_param("si", $resultat, $id_resultat);
    $stmt->execute();
}

echo $resultat;
?>