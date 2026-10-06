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
// VÉRIFICATION DE SESSION
// =====================================================

// Si pas connecté ou si le rôle n'est pas professeur → retour à la page de connexion
if (!isset($_SESSION["id"]) || ($_SESSION["role"] ?? "") !== "professeur") {
    header("Location: index.php");
    exit();
}

// Récupération des informations de la session
$id_professeur = $_SESSION["id"];
$identifiant   = $_SESSION["identifiant"];

// =====================================================
// SUPPRESSION D'UNE CLASSE
// =====================================================

if (isset($_GET["supprimer"])) {
    $id_classe = trim($_GET["supprimer"]);

    
    $sql  = "DELETE FROM g1_classe 
             WHERE id_classe     = ? 
             AND   id_professeur = ?";
    
    $stmt = $conn->prepare($sql);
    
    
    $stmt->bind_param("ii", $id_classe, $id_professeur);
    $stmt->execute();

    header("Location: classes.php");
    exit();
}

// =====================================================
// CRÉATION D'UNE NOUVELLE CLASSE
// =====================================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "nouvelle") {

    // Récupération et nettoyage des valeurs du formulaire
    $nom_classe      = trim($_POST["nom_classe"]);
    $nom_ecole       = trim($_POST["nom_ecole"]);
    $niveau_scolaire = trim($_POST["niveau_scolaire"]);
    $annee_scolaire  = trim($_POST["annee_scolaire"]);

    // Validation — champs obligatoires
    if (empty($nom_classe) || empty($nom_ecole) || empty($niveau_scolaire) || empty($annee_scolaire)) {

        // Retour avec erreur — à améliorer si nécessaire
        header("Location: classes.php?erreur=champs_vides");
        exit();

    } else {

        
        $sql  = "INSERT INTO g1_classe (nom_classe, nom_ecole, niveau_scolaire, annee_scolaire, id_professeur)
                VALUES (?, ?, ?, ?, ?)";
                
        $stmt = $conn->prepare($sql);

        
        $stmt->bind_param("ssssi", $nom_classe, $nom_ecole, $niveau_scolaire, $annee_scolaire, $id_professeur);

        $stmt->execute();

        header("Location: classes.php");
        exit();
            }
}

// =====================================================
// RÉCUPÉRATION DES CLASSES DU PROFESSEUR CONNECTÉ
// =====================================================


$sql  = "SELECT g1_classe.*, 
                COUNT(g1_eleve.id_eleve) AS nb_eleves
         FROM   g1_classe
         LEFT JOIN g1_eleve ON g1_eleve.id_classe = g1_classe.id_classe
         WHERE  g1_classe.id_professeur = ?
         GROUP BY g1_classe.id_classe
         ORDER BY g1_classe.nom_classe";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_professeur);
$stmt->execute();

// Use get_result() and fetch_all() to get the array for the foreach loop
$result = $stmt->get_result();
$classes = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Mes classes — Gestion des Dictées</title>

  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="classes.css">
</head>

<body>

<!-- EN-TÊTE -->
<header>
  <span class="brand">Gestion des Dictées</span>
  <span class="bonjour">Bonjour, <?= htmlspecialchars($identifiant) ?></span>
  <button class="btn-deconnexion" onclick="window.location.href='deconnexion.php'">⇥ Déconnexion</button>
</header>

<!-- CONTENU PRINCIPAL -->
<main>

  <div class="top">
    <h1>Mes classes</h1>
    <button class="btn-nouvelle" onclick="ouvrirModalNouvelle()">+ Nouvelle classe</button>
  </div>

  <!-- GRILLE DES CLASSES -->
  <div class="grid">

    <?php if (count($classes) == 0) { ?>
      <p class="vide">Aucune classe pour le moment. Créez votre première classe !</p>
    <?php } ?>

    <?php foreach ($classes as $classe) { ?>
      <div class="card">
        <h2><?= htmlspecialchars($classe["nom_classe"]) ?></h2>
        <p class="ecole"><?= htmlspecialchars($classe["nom_ecole"]) ?></p>
        <p class="niveau"><?= htmlspecialchars($classe["niveau_scolaire"]) ?></p>
        <p class="annee"><?= htmlspecialchars($classe["annee_scolaire"]) ?></p>
        <p class="eleves">
          <?= $classe["nb_eleves"] ?>
          <?= $classe["nb_eleves"] == 1 ? "élève" : "élèves" ?>
        </p>

        <div class="card-actions">
          <button class="btn-ouvrir"
                  onclick="window.location.href='eleves.php?id=<?= $classe["id_classe"] ?>'">
            📁 Ouvrir
          </button>
          <button class="btn-supprimer"
                  onclick="ouvrirModalSupprimer(<?= $classe["id_classe"] ?>, '<?= htmlspecialchars($classe["nom_classe"]) ?>')">
            🗑 Supprimer
          </button>
        </div>
      </div>
    <?php } ?>

  </div>

  <button class="btn-resultats" onclick="window.location.href='resultats.php'">
    Voir les résultats
  </button>

</main>

<!-- MODAL SUPPRESSION -->
<div class="modal-overlay" id="modal-supprimer">
  <div class="modal">
    <div class="modal-header">
      <h3>Supprimer la classe</h3>
      <span class="close" onclick="fermerModalSupprimer()">×</span>
    </div>
    <p id="modal-text"></p>

    <div class="modal-actions">
      <button class="btn-annuler" onclick="fermerModalSupprimer()">Annuler</button>
      <button class="btn-confirmer" id="btn-confirmer" onclick="confirmerSuppression()">Supprimer</button>
    </div>
  </div>
</div>

<!-- MODAL NOUVELLE CLASSE -->
<div class="modal-overlay" id="modal-nouvelle">
  <div class="modal">
    <div class="modal-header">
      <h3>Nouvelle classe</h3>
      <span class="close" onclick="fermerModalNouvelle()">×</span>
    </div>

    <form action="classes.php" method="POST">
      <input type="hidden" name="action" value="nouvelle">

      <label>Nom de la classe</label>
      <input type="text" name="nom_classe" placeholder="Ex : CM2 A" required>

      <label>Niveau scolaire</label>
      <select name="niveau_scolaire" required>
        <option value="">-- Choisir --</option>
        <option value="CP">CP</option>
        <option value="CE1">CE1</option>
        <option value="CE2">CE2</option>
        <option value="CM1">CM1</option>
        <option value="CM2">CM2</option>
      </select>

      <label>École</label>
      <input type="text" name="nom_ecole" placeholder="Ex : École Jean Moulin" required>

      <label>Année scolaire</label>
      <input type="text" name="annee_scolaire" value="2025-2026" required>

      <div class="modal-actions">
        <button type="button" class="btn-annuler" onclick="fermerModalNouvelle()">Annuler</button>
        <button type="submit" class="btn-creer">Créer</button>
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
  // Modal suppression
  var urlSupprimer = "";

  function ouvrirModalSupprimer(id, nom) {
    document.getElementById("modal-text").textContent =
      "Êtes-vous sûr de vouloir supprimer la classe " + nom + " ?";
    urlSupprimer = "classes.php?supprimer=" + id;
    document.getElementById("modal-supprimer").classList.add("open");
  }

  function confirmerSuppression() {
    window.location.href = urlSupprimer;
  }

  function fermerModalSupprimer() {
    document.getElementById("modal-supprimer").classList.remove("open");
  }

  // Modal nouvelle classe
  function ouvrirModalNouvelle() {
    document.getElementById("modal-nouvelle").classList.add("open");
  }

  function fermerModalNouvelle() {
    document.getElementById("modal-nouvelle").classList.remove("open");
  }

  // Fermer les modals en cliquant en dehors
  document.getElementById("modal-supprimer").addEventListener("click", function(e) {
    if (e.target === this) fermerModalSupprimer();
  });

  document.getElementById("modal-nouvelle").addEventListener("click", function(e) {
    if (e.target === this) fermerModalNouvelle();
  });
</script>

</body>
</html>
