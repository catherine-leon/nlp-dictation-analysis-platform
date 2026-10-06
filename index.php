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
// TRAITEMENT DU FORMULAIRE DE CONNEXION
// =====================================================

$erreur = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  // Récupération et nettoyage des valeurs du formulaire
  $identifiant  = trim($_POST["identifiant"]);
  $mot_de_passe = trim($_POST["mot_de_passe"]);

  // Validation — champs obligatoires
  if (empty($identifiant) || empty($mot_de_passe)) {

    $erreur = "Tous les champs sont obligatoires.";
  } else {

    $sql = "SELECT id_utilisateur, role, identifiant, mot_de_passe
            FROM g1_utilisateur
            WHERE identifiant = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $identifiant);
    $stmt->execute();

    $result = $stmt->get_result();
    $utilisateur = $result->fetch_assoc();

    if ($utilisateur && password_verify($mot_de_passe, $utilisateur["mot_de_passe"])) {
      $_SESSION["id"]          = $utilisateur["id_utilisateur"];
      $_SESSION["role"]        = $utilisateur["role"];
      $_SESSION["identifiant"] = $utilisateur["identifiant"];

      // Redirection selon le rôle
      if ($utilisateur["role"] == "professeur") {
        header("Location: classes.php");
        exit();
      } elseif ($utilisateur["role"] == "chercheur") {
        header("Location: resultats_chercheur.php");
        exit();
      } else {
        header("Location: resultats.php");
        exit();
      }
    } else {
      $erreur = "Identifiant ou mot de passe incorrect.";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <title>Connexion — Gestion des Dictées</title>

  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="index.css">
</head>

<body>

  <div class="container">

    <!-- CÔTÉ GAUCHE -->
    <div class="left">
      <div class="uga">
        🎓 UNIVERSITÉ GRENOBLE ALPES — M1 IDL
      </div>

      <h1 class="main-title">
        Gestion des Dictées
      </h1>

      <p class="description">
        Cette application permet aux enseignant(e)s et chercheurs de gérer, saisir et analyser des dictées d'élèves,
        dans le cadre de recherches sur les compétences orthographiques.
      </p>
    </div>

    <!-- CÔTÉ DROIT -->
    <div class="right">
      <div class="card">

        <h2>Connexion</h2>

        <!-- Formulaire envoyé en POST vers index.php -->
        <form action="index.php" method="POST">

          <label>Identifiant</label>
          <input type="text" name="identifiant" placeholder="Votre identifiant">

          <label>Mot de passe</label>
          <input type="password" name="mot_de_passe" placeholder="••••••••">

          <!-- Message d'erreur -->
          <?php if ($erreur != "") { ?>
            <p class="error" style="display:block"><?= $erreur ?></p>
          <?php } ?>

          <button type="submit" class="btn-login">Se connecter</button>

        </form>

        <a class="forgot">Mot de passe oublié ?</a>

        <div class="separator">
          <span>ou</span>
        </div>

        <!-- Redirection vers la page d'inscription -->
        <button class="btn-register" onclick="window.location.href='inscription.php'">
          Créer un compte
        </button>

        <p class="hint">
          Si professeur, essayez : <strong>prof_dupont</strong><br>
          Si chercheur, essayez : <strong>chercheur_leroy</strong><br>
          Mot de passe : <strong>123456</strong>
        </p>

      </div>
    </div>

  </div>

  <!-- PIED DE PAGE -->
  <footer>
    <strong>Gestion des Dictées — Université Grenoble Alpes</strong><br>
M1 Industrie de la Langue — 2025-2026
  </footer>

</body>

</html>