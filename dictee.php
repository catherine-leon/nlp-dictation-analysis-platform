<?php
session_start();
require "config.php";

// Redirect if not logged in or if the user is not a professor
if (!isset($_SESSION["id"]) || ($_SESSION["role"] ?? "") !== "professeur") {
    header("Location: index.php");
    exit();
}

$id_professeur = $_SESSION["id"];
$identifiant   = $_SESSION["identifiant"];

// Capture class ID from URL to allow returning to the class view later
$id_classe = isset($_GET['id']) ? $_GET['id'] : null;

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titre             = trim($_POST["titre"]);
    $niveau_scolaire   = trim($_POST["niveau_scolaire"]);
    $date_passation    = $_POST["date_passation"];
    $type_dictee       = trim($_POST["type_dictee"]);
    $contenu_reference = trim($_POST["contenu_reference"]);

    if (!empty($titre) && !empty($niveau_scolaire) && !empty($contenu_reference)) {
        $sql = "INSERT INTO g1_dictee (titre, niveau_scolaire, date_passation, type_dictee, contenu_reference, id_professeur) 
            VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $titre, $niveau_scolaire, $date_passation, $type_dictee, $contenu_reference, $id_professeur);

        if ($stmt->execute()) {
            // Redirect back to students page if id_classe exists, otherwise to classes
            if ($id_classe) {
                header("Location: eleves.php?id=" . $id_classe);
            } else {
                header("Location: classes.php");
            }
            exit();
        } else {
            $message = "Erreur lors de l'enregistrement : " . $conn->error;
        }
    } else {
        $message = "Veuillez remplir tous les champs obligatoires.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Nouvelle Dictée — Gestion des Dictées</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dictee.css">
</head>

<body>

    <header>
        <span class="brand">Gestion des Dictées</span>
        <span class="bonjour">Bonjour, <?= htmlspecialchars($identifiant) ?></span>
        <button class="btn-deconnexion" onclick="window.location.href='deconnexion.php'">⇥ Déconnexion</button>
    </header>

    <main>
        <a class="retour" href="<?= $id_classe ? 'eleves.php?id=' . $id_classe : 'classes.php' ?>">← Retour</a>

        <div class="form-container">
            <h1>Créer une nouvelle dictée</h1>

            <?php if ($message): ?>
                <p class="message-error"><?= $message ?></p>
            <?php endif; ?>

            <form action="dictee.php<?= $id_classe ? '?id=' . $id_classe : '' ?>" method="POST">
                <div class="form-group">
                    <label for="titre">Titre de la dictée</label>
                    <input type="text" name="titre" id="titre" placeholder="ex : Dictée du printemps" required>
                </div>

                <div class="form-group">
                    <label for="niveau_scolaire">Niveau Scolaire</label>
                    <select name="niveau_scolaire" id="niveau_scolaire" required>
                        <option value="">-- Sélectionner --</option>
                        <option value="CP">CP</option>
                        <option value="CE1">CE1</option>
                        <option value="CE2">CE2</option>
                        <option value="CM1">CM1</option>
                        <option value="CM2">CM2</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_passation">Date de passation</label>
                        <input type="date" name="date_passation" id="date_passation" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="type_dictee">Type de dictée</label>
                        <input type="text" name="type_dictee" id="type_dictee" placeholder="ex: Texte, Phrases, Mots" value="texte">
                    </div>
                </div>

                <div class="form-group">
                    <label for="contenu_reference">Contenu de la dictée (Texte de référence)</label>
                    <textarea name="contenu_reference" id="contenu_reference" rows="10" placeholder="Saisissez ici le texte original de la dictée..." required></textarea>
                </div>

                <button type="submit" class="btn-submit">Enregistrer la dictée</button>
            </form>
        </div>
    </main>

    <footer>
        Gestion des Dictées — Université Grenoble Alpes<br>
        M1 Industrie de la Langue — 2025-2026
    </footer>

</body>

</html>
