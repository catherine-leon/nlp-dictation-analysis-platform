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

if (!isset($_SESSION["id"]) || ($_SESSION["role"] ?? "") !== "professeur") {
  header("Location: index.php");
  exit();
}

$identifiant   = $_SESSION["identifiant"];
$id_professeur = $_SESSION["id"];

// =====================================================
// RÉCUPÉRATION DE L'ID DE LA CLASSE (URL)
// =====================================================

if (!isset($_GET["id"])) {
  header("Location: classes.php");
  exit();
}

$id_classe = $_GET["id"];

// =====================================================
// RÉCUPÉRATION DES INFORMATIONS DE LA CLASSE
// =====================================================

$sql  = "SELECT * FROM g1_classe 
         WHERE id_classe = ? 
         AND id_professeur = ?";
$stmt = $conn->prepare($sql);

// "ii" indicates two integers
$stmt->bind_param("ii", $id_classe, $id_professeur);
$stmt->execute();

$result = $stmt->get_result();
$classe = $result->fetch_assoc();

// Si la classe n'existe pas ou n'appartient pas au professeur
if (!$classe) {
  header("Location: classes.php");
  exit();
}

// =====================================================
// SUPPRESSION D'UN ÉLÈVE
// =====================================================

if (isset($_GET["supprimer"])) {
  $id_eleve = trim($_GET["supprimer"]);

  $sql  = "DELETE FROM g1_eleve 
             WHERE id_eleve = ? 
             AND id_classe  = ?";
  $stmt = $conn->prepare($sql);

  // "ii" indicates two integers
  $stmt->bind_param("ii", $id_eleve, $id_classe);
  $stmt->execute();

  header("Location: eleves.php?id=" . $id_classe);
  exit();
}

// =====================================================
// AJOUT D'UN NOUVEL ÉLÈVE
// =====================================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "nouvel_eleve") {

  $annee_naissance   = trim($_POST["annee_naissance"]);
  $francais_maison   = isset($_POST["francais_maison"]) ? (int)$_POST["francais_maison"] : 0;
  $langue_maternelle = trim($_POST["langue_maternelle"]) ?: "Français";

  if (empty($annee_naissance)) {
    header("Location: eleves.php?id=" . $id_classe . "&erreur=champs_vides");
    exit();
  }

  $sql  = "INSERT INTO g1_eleve (annee_naissance, francais_maison, langue_maternelle, id_classe)
         VALUES (?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);

  // "i" indicate integers, s strings
  $stmt->bind_param("iisi", $annee_naissance, $francais_maison, $langue_maternelle, $id_classe);
  $stmt->execute();

  header("Location: eleves.php?id=" . $id_classe);
  exit();
}

// =====================================================
// RÉCUPÉRATION DES ÉLÈVES DE LA CLASSE
// =====================================================

$sql  = "SELECT * FROM g1_eleve 
         WHERE id_classe = ? 
         ORDER BY id_eleve";
$stmt = $conn->prepare($sql);

// "i" indicates one integer
$stmt->bind_param("i", $id_classe);
$stmt->execute();

$result = $stmt->get_result();
$eleves = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <title>Élèves — Gestion des Dictées</title>

  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="eleves.css">
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

    <a class="retour" href="classes.php">← Retour</a>

    <div class="top">
      <div>
        <h1><?= htmlspecialchars($classe["nom_classe"]) ?> — <?= htmlspecialchars($classe["nom_ecole"]) ?></h1>
        <p class="subtitle">
          <?= htmlspecialchars($classe["annee_scolaire"]) ?> ·
          <?= count($eleves) ?> <?= count($eleves) == 1 ? "élève" : "élèves" ?>
        </p>
      </div>

      <div class="actions-top">
        <button class="btn-dark" onclick="window.location.href='dictee.php?id=<?= $id_classe ?>'">
          📄 Nouvelle Dictée
        </button>
        <button class="btn-green" onclick="window.location.href='saisir_reponses.php?id=<?= $id_classe ?>'">
          📝 Saisir Réponses
        </button>
      </div>
    </div>

    <!-- TABLEAU DES ÉLÈVES -->
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Élève (ID)</th>
            <th>Année de naissance</th>
            <th>Langue maternelle</th>
            <th>Français à la maison</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          <?php if (count($eleves) == 0) { ?>
            <tr>
              <td colspan="4" style="text-align:center; color:#6B8299; font-style:italic;">
                Aucun élève dans cette classe.
              </td>
            </tr>
          <?php } ?>

          <?php foreach ($eleves as $eleve) { ?>
            <tr>
              <td>Élève #<?= $eleve["id_eleve"] ?></td>
              <td><?= htmlspecialchars($eleve["annee_naissance"]) ?></td>
              <td><?= htmlspecialchars($eleve["langue_maternelle"] ?? "—") ?></td>
              <td><?= $eleve["francais_maison"] ? "Oui" : "Non" ?></td>
              <td>
                <button class="btn-delete"
                  onclick="ouvrirModalSupprimer(<?= $eleve["id_eleve"] ?>)">
                  🗑 Supprimer
                </button>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>

    <button class="btn-add" onclick="ouvrirModalNouvelEleve()">+ Nouvel élève</button>

  </main>

  <!-- MODAL SUPPRESSION -->
  <div class="modal-overlay" id="modal-supprimer">
    <div class="modal">
      <div class="modal-header">
        <h3>Supprimer l'élève</h3>
        <span class="close" onclick="fermerModalSupprimer()">×</span>
      </div>
      <p>Êtes-vous sûr de vouloir supprimer cet élève ? Cette action est irréversible.</p>

      <div class="modal-actions">
        <button class="btn-annuler" onclick="fermerModalSupprimer()">Annuler</button>
        <button class="btn-confirmer" id="btn-confirmer" onclick="confirmerSuppression()">Supprimer</button>
      </div>
    </div>
  </div>

  <!-- MODAL NOUVEL ÉLÈVE -->
  <div class="modal-overlay" id="modal-nouvel-eleve">
    <div class="modal">
      <div class="modal-header">
        <h3>Nouvel élève</h3>
        <span class="close" onclick="fermerModalNouvelEleve()">×</span>
      </div>

      <form action="eleves.php?id=<?= $id_classe ?>" method="POST">
        <input type="hidden" name="action" value="nouvel_eleve">

        <label>Année de naissance</label>
        <input type="number" name="annee_naissance" placeholder="Ex : 2016" min="2000" max="2020" required>

        <label>Langue maternelle</label>
        <select name="langue_maternelle">
          <option value="Français">Français</option>
          <option value="Anglais">Anglais</option>
          <option value="Arabe">Arabe</option>
          <option value="Espagnol">Espagnol</option>
          <option value="Portugais">Portugais</option>
          <option value="Russe">Russe</option>
          <option value="Chinois">Chinois</option>
          <option value="Autre">Autre</option>
        </select>

        <label>Français parlé à la maison ?</label>
        <select name="francais_maison">
          <option value="1">Oui</option>
          <option value="0">Non</option>
        </select>

        <div class="modal-actions">
          <button type="button" class="btn-annuler" onclick="fermerModalNouvelEleve()">Annuler</button>
          <button type="submit" class="btn-creer">Ajouter</button>
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

    function ouvrirModalSupprimer(id) {
      urlSupprimer = "eleves.php?id=<?= $id_classe ?>&supprimer=" + id;
      document.getElementById("modal-supprimer").classList.add("open");
    }

    function fermerModalSupprimer() {
      document.getElementById("modal-supprimer").classList.remove("open");
    }

    function confirmerSuppression() {
      window.location.href = urlSupprimer;
    }

    // Modal nouvel élève
    function ouvrirModalNouvelEleve() {
      document.getElementById("modal-nouvel-eleve").classList.add("open");
    }

    function fermerModalNouvelEleve() {
      document.getElementById("modal-nouvel-eleve").classList.remove("open");
    }

    // Fermer les modals en cliquant en dehors
    document.getElementById("modal-supprimer").addEventListener("click", function(e) {
      if (e.target === this) fermerModalSupprimer();
    });

    document.getElementById("modal-nouvel-eleve").addEventListener("click", function(e) {
      if (e.target === this) fermerModalNouvelEleve();
    });
  </script>

</body>

</html>
