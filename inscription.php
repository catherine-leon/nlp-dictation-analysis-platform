<?php
// =====================================================
// DÉMARRAGE DE LA SESSION
// =====================================================

session_start();

// =====================================================
// CONNEXION À LA BASE DE DONNÉES
// =====================================================

require "config.php";

// =====================================================
// TRAITEMENT DU FORMULAIRE D'INSCRIPTION
// =====================================================

$erreur = "";
$succes = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Récupération et nettoyage des valeurs du formulaire
    $identifiant  = trim($_POST["identifiant"]);
    $email        = trim($_POST["email"]);
    $mot_de_passe = $_POST["mot_de_passe"];
    $role         = trim($_POST["role"]);
    $institution  = isset($_POST["institution"]) ? trim($_POST["institution"]) : NULL;

    // Validation — champs obligatoires
    if (empty($identifiant) || empty($email) || empty($mot_de_passe)) {

        $erreur = "Tous les champs sont obligatoires.";

    } else {
        // Vérification si l'identifiant existe déjà
        $sql  = "SELECT id_utilisateur FROM g1_utilisateur WHERE identifiant = ?";
        $stmt = $conn->prepare($sql);

        
        $stmt->bind_param("s", $identifiant);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $erreur = "Cet identifiant existe déjà.";
      } else {

            // Store a one-way password hash rather than the plaintext password.
            $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

            $sql  = "INSERT INTO g1_utilisateur (identifiant, email, mot_de_passe, role, institution)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $identifiant, $email, $mot_de_passe_hash, $role, $institution);

            if ($stmt->execute()) {
                $succes = "Compte créé avec succès !";
            } else {
                $erreur = "Une erreur est survenue lors de l'inscription.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Créer un compte — Gestion des Dictées</title>

  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="inscription.css">
</head>

<body>

<div class="page">
  <div class="card">

    <!-- Bouton fermer → retour à la page de connexion -->
    <button class="btn-close" onclick="window.location.href='index.php'">×</button>

    <h2>Créer un compte</h2>

    <!-- Message d'erreur -->
    <?php if ($erreur != "") { ?>
      <p class="erreur"><?= $erreur ?></p>
    <?php } ?>

    <!-- Message de succès -->
    <?php if ($succes != "") { ?>
      <p class="succes"><?= $succes ?> <a href="index.php">Se connecter</a></p>
    <?php } ?>

    <form action="inscription.php" method="POST">

      <label>Identifiant</label>
      <input type="text" name="identifiant" placeholder="Choisissez un identifiant" required>

      <label>Email</label>
      <input type="email" name="email" placeholder="votre@email.fr" required>

      <label>Mot de passe</label>
      <input type="password" name="mot_de_passe" placeholder="••••••••" required>

      <label>Rôle</label>
      <select name="role" onchange="afficherInstitution(this)">
        <option value="professeur">Professeur</option>
        <option value="chercheur">Chercheur</option>
      </select>

      <!-- Champ institution — visible seulement pour chercheur -->
      <div class="institution-group" id="institution-group">
        <label>Institution</label>
        <input type="text" name="institution" placeholder="ex : Université Grenoble Alpes">
      </div>

      <div class="actions">
        <button type="button" class="btn-annuler" onclick="window.location.href='index.php'">Annuler</button>
        <button type="submit" class="btn-inscrire">S'inscrire</button>
      </div>

    </form>

  </div>
</div>

<!-- PIED DE PAGE -->
<footer>
  Gestion des Dictées — Université Grenoble Alpes<br>
M1 Industrie de la Langue — 2025-2026
</footer>

<script>
  function afficherInstitution(select) {
    const groupe = document.getElementById("institution-group");
    groupe.style.display = select.value === "chercheur" ? "block" : "none";
  }
</script>

</body>
</html>
