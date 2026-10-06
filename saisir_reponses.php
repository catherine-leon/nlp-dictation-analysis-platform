<?php
session_start();
require "config.php";

// Security: only logged-in professors can access this page
if (!isset($_SESSION["id"]) || ($_SESSION["role"] ?? "") !== "professeur") {
    header("Location: index.php");
    exit();
}

$id_professeur = $_SESSION["id"];
$identifiant   = $_SESSION["identifiant"];

// =====================================================
// INTERCEPTEUR AJAX : ANALYSE AUTOMATIQUE (PYTHON)
// =====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "analyser_texte") {
    header('Content-Type: application/json');
    
    $id_dictee_ajax    = (int)$_POST["id_dictee"];
    $dictee_eleve_ajax = trim($_POST["dictee_eleve"]);
    
    if (empty($id_dictee_ajax) || empty($dictee_eleve_ajax)) {
        echo json_encode(["error" => "Veuillez sélectionner une dictée et saisir le texte."]);
        exit();
    }
    
    // Récupérer le texte de référence depuis la BD
    $stmt_ref = $conn->prepare("SELECT contenu_reference FROM g1_dictee WHERE id_dictee = ?");
    $stmt_ref->bind_param("i", $id_dictee_ajax);
    $stmt_ref->execute();
    $res_ref = $stmt_ref->get_result()->fetch_assoc();
    
    if (!$res_ref) {
        echo json_encode(["error" => "Dictée introuvable pour l'analyse."]);
        exit();
    }
    
    $contenu_reference = $res_ref["contenu_reference"];
    
    // Encoder en base64 pour Python (même méthode que analyse.php)
    $arg_model_b64   = escapeshellarg(base64_encode($contenu_reference));
    $arg_student_b64 = escapeshellarg(base64_encode($dictee_eleve_ajax));
    $script          = __DIR__ . "/analyser_stanza.py";
    
    $commande = "python3 " .
                escapeshellarg($script) . " " .
                $arg_model_b64 . " " . $arg_student_b64 . " 2>&1";
    
    $output = shell_exec($commande);
    $output = $output !== null ? trim($output) : "";
    
    // Initialiser les compteurs
    $counts = [
        "faute_orthographique"       => 0,
        "mot_ou_ponctuation_manquant" => 0,
        "accord_adjectif"            => 0,
        "nom_pluriel"                => 0,
        "accord_sujet_verbe"         => 0
    ];
    
    // Parser la sortie du script Python pour compter les erreurs par type
    if ($output && $output !== "Aucune erreur") {
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $lineLower = strtolower(trim($line));
            if (strpos($lineLower, "faute orthographique :") !== false) {
                $counts["faute_orthographique"]++;
            }
            if (strpos($lineLower, "mot manquant :") !== false ||
                strpos($lineLower, "ponctuation manquante :") !== false ||
                strpos($lineLower, "mot en trop :") !== false ||
                strpos($lineLower, "ponctuation en trop :") !== false) {
                $counts["mot_ou_ponctuation_manquant"]++;
            }
            if (strpos($lineLower, "accord adjectif :") !== false) {
                $counts["accord_adjectif"]++;
            }
            if (strpos($lineLower, "pluriel nom :") !== false) {
                $counts["nom_pluriel"]++;
            }
            if (strpos($lineLower, "accord sujet-verbe :") !== false) {
                $counts["accord_sujet_verbe"]++;
            }
        }
    }
    
    echo json_encode([
        "success" => true,
        "counts"  => $counts,
        "analyse" => $output   // on renvoie aussi le texte brut pour le stocker ensuite
    ]);
    exit();
}
// =====================================================

// Get the class ID from URL
if (!isset($_GET["id"])) {
    header("Location: classes.php");
    exit();
}
$id_classe = (int)$_GET["id"];

// 1. Fetch Class Info to display name and level
$sql_classe = "SELECT id_classe, nom_classe, niveau_scolaire FROM g1_classe WHERE id_classe = ? AND id_professeur = ?";
$stmt_c = $conn->prepare($sql_classe);
$stmt_c->bind_param("ii", $id_classe, $id_professeur);
$stmt_c->execute();
$res_c = $stmt_c->get_result();
$classe = $res_c->fetch_assoc();

if (!$classe) {
    header("Location: classes.php");
    exit();
}

// 2. Fetch Students in this class
$sql_eleves = "SELECT id_eleve FROM g1_eleve WHERE id_classe = ? ORDER BY id_eleve";
$stmt_e = $conn->prepare($sql_eleves);
$stmt_e->bind_param("i", $id_classe);
$stmt_e->execute();
$eleves = $stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Fetch Dictations created by this professor for the same school level as the class
$sql_dictees = "SELECT id_dictee, titre, niveau_scolaire, date_passation, type_dictee FROM g1_dictee WHERE id_professeur = ? AND niveau_scolaire = ? ORDER BY date_passation DESC, id_dictee DESC";
$stmt_d = $conn->prepare($sql_dictees);
$stmt_d->bind_param("is", $id_professeur, $classe["niveau_scolaire"]);
$stmt_d->execute();
$dictees = $stmt_d->get_result()->fetch_all(MYSQLI_ASSOC);

$ids_eleves_autorises = array_map("intval", array_column($eleves, "id_eleve"));
$ids_dictees_autorisees = array_map("intval", array_column($dictees, "id_dictee"));

// 4. Handle Form Submission
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST["action"])) { // Ignore AJAX posts
    $id_eleve             = (int)$_POST["id_eleve"];
    $id_dictee            = (int)$_POST["id_dictee"];
    $dictee_eleve         = trim($_POST["dictee_eleve"]);
    $date_saisie          = date("Y-m-d");
    $faute_ortho          = (int)$_POST["faute_orthographique"];
    $manquant             = (int)$_POST["mot_ou_ponctuation_manquant"];
    $accord_adj           = (int)$_POST["accord_adjectif"];
    $nom_pluriel          = (int)$_POST["nom_pluriel"];
    $accord_sv            = (int)$_POST["accord_sujet_verbe"];
    $analyse_text         = trim($_POST["analyse_result"] ?? "");

    // Use professor's manually entered score, OR calculate it if left blank
    if (isset($_POST["score"]) && $_POST["score"] !== "") {
        $score = (int)$_POST["score"];
    } else {
        $nb_erreurs_total = $faute_ortho + $manquant + $accord_adj + $nom_pluriel + $accord_sv;
        $score = max(0, 20 - $nb_erreurs_total);
    }

    if (!in_array($id_eleve, $ids_eleves_autorises, true) || !in_array($id_dictee, $ids_dictees_autorisees, true)) {
        $message = "selection_invalide";
    } else {
        $sql_ins = "INSERT INTO g1_dictes_resultats 
                (id_eleve, id_dictee, dictee_eleve, date_saisie, faute_orthographique, mot_ou_ponctuation_manquant, accord_adjectif, nom_pluriel, accord_sujet_verbe, score, analyse) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_i = $conn->prepare($sql_ins);
        $stmt_i->bind_param("iissiiiiiis", $id_eleve, $id_dictee, $dictee_eleve, $date_saisie, $faute_ortho, $manquant, $accord_adj, $nom_pluriel, $accord_sv, $score, $analyse_text);

        if ($stmt_i->execute()) {
            $message = "succes";
        } else {
            $message = "erreur";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Saisir Réponses — Gestion des Dictées</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="saisir_reponses.css?v=2.0">
    
    
</head>
<body>

    <header>
        <span class="brand">Gestion des Dictées</span>
        <span class="bonjour">Bonjour, <?= htmlspecialchars($identifiant) ?></span>
        <button class="btn-deconnexion" onclick="window.location.href='deconnexion.php'">⇥ Déconnexion</button>
    </header>

    <main>
        <a class="retour" href="eleves.php?id=<?= $id_classe ?>">← Retour à la classe</a>

        <div class="container">
            <h1>Saisie des résultats : <?= htmlspecialchars($classe["nom_classe"]) ?></h1>

            <?php if ($message == "succes"): ?>
                <p class="msg succes">Le résultat a été enregistré avec succès !</p>
            <?php elseif ($message == "selection_invalide"): ?>
                <p class="msg erreur">La sélection élève/dictée ne correspond pas à votre classe.</p>
            <?php elseif ($message == "erreur"): ?>
                <p class="msg erreur">Une erreur est survenue lors de l'enregistrement.</p>
            <?php endif; ?>

            <form action="saisir_reponses.php?id=<?= $id_classe ?>" method="POST">
                <div class="grid-top">
                    <div class="form-group">
                        <label>Sélectionner l'élève</label>
                        <select name="id_eleve" required>
                            <option value="">-- Choisir un élève --</option>
                            <?php foreach ($eleves as $e): ?>
                                <option value="<?= $e["id_eleve"] ?>">Élève #<?= $e["id_eleve"] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sélectionner la dictée</label>
                        <select id="dictee_select" name="id_dictee" required>
                            <option value="">-- Choisir une dictée --</option>
                            <?php foreach ($dictees as $d): ?>
                                <option value="<?= $d["id_dictee"] ?>">
                                    <?= htmlspecialchars($d["titre"] ?: $d["niveau_scolaire"]) ?> — <?= htmlspecialchars($d["date_passation"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Texte produit par l'élève</label>
                    <a href="ocr.php?id=<?= $id_classe ?>" style="font-size:0.85rem; color:#3D8C84; text-decoration:underline; display:inline-block; margin-bottom:8px;">
                        📷 Transcrire depuis une image
                    </a>
                    <textarea id="dictee_eleve_text" name="dictee_eleve" rows="8" placeholder="Copiez ici la dictée de l'élève..." required></textarea>
                    
                    <button type="button" id="btn-analyser" onclick="analyserTexteAutomatiquement()" class="btn-analyser">
                        Analyser automatiquement
                    </button>
                </div>

                <h3>Décompte des erreurs (Modifiable)</h3>
                <div class="errors-grid" id="errors-container">
                    <div class="form-group">
                        <label>Orthographe lexicale</label>
                        <input type="number" name="faute_orthographique" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Mot/ponctuation manquant</label>
                        <input type="number" name="mot_ou_ponctuation_manquant" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Accord adjectif</label>
                        <input type="number" name="accord_adjectif" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Nom pluriel</label>
                        <input type="number" name="nom_pluriel" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Accord sujet-verbe</label>
                        <input type="number" name="accord_sujet_verbe" value="0" min="0">
                    </div>
                </div>

                <!-- Champ caché pour stocker le texte d'analyse retourné par Python -->
                <input type="hidden" id="analyse_result" name="analyse_result" value="">

                <!-- Aperçu de l'analyse (visible après clic sur "Analyser automatiquement") -->
                <div id="analyse-preview" style="display:none; margin-top:20px; background:#F0F7FF; border:1px solid #C8DEFA; border-radius:8px; padding:16px 20px;">
                    <p style="font-weight:600; color:#1E3F5A; margin-bottom:10px;">📋 Analyse détaillée :</p>
                    <pre id="analyse-preview-text" style="white-space:pre-wrap; font-family:inherit; font-size:0.88rem; color:#3A5A72; line-height:1.6;"></pre>
                </div>

                <div class="score-box">
                    <label>Score Final (/20)</label>
                    <p style="font-size:0.85rem; color:#666; margin-bottom: 8px;">Calculé automatiquement, mais modifiable si besoin.</p>
                    <input type="number" id="score_final" name="score" value="20" min="0" max="20">
                </div>

                <button type="submit" class="btn-save">Enregistrer les résultats</button>
            </form>
        </div>
    </main>

    <footer>
        Gestion des Dictées — Université Grenoble Alpes<br>
        M1 Industrie de la Langue — 2025-2026
    </footer>

    <script>
        // 1. Récupération OCR (existant)
        if (<?= isset($_GET['ocr']) && $_GET['ocr'] == '1' ? 'true' : 'false' ?>) {
            var texteOcr = sessionStorage.getItem("ocr_texte");
            if (texteOcr) {
                document.getElementById("dictee_eleve_text").value = texteOcr;
                sessionStorage.removeItem("ocr_texte");
            }
        }

        // 2. Recalcul automatique du score
        function recalculerScore() {
            const f1 = parseInt(document.querySelector("input[name='faute_orthographique']").value) || 0;
            const f2 = parseInt(document.querySelector("input[name='mot_ou_ponctuation_manquant']").value) || 0;
            const f3 = parseInt(document.querySelector("input[name='accord_adjectif']").value) || 0;
            const f4 = parseInt(document.querySelector("input[name='nom_pluriel']").value) || 0;
            const f5 = parseInt(document.querySelector("input[name='accord_sujet_verbe']").value) || 0;
            
            const totalErreurs = f1 + f2 + f3 + f4 + f5;
            let nouveauScore = 20 - totalErreurs;
            
            if (nouveauScore < 0) nouveauScore = 0;
            document.getElementById('score_final').value = nouveauScore;
        }

        // Ajouter les écouteurs d'événements pour mettre à jour le score dès qu'on tape un nombre
        document.querySelectorAll('#errors-container input').forEach(input => {
            input.addEventListener('input', recalculerScore);
        });

        // 3. Appel AJAX vers Python Stanza
        function analyserTexteAutomatiquement() {
            const idDictee = document.getElementById("dictee_select").value;
            const dicteeEleve = document.getElementById("dictee_eleve_text").value;
            
            if (!idDictee || !dicteeEleve.trim()) {
                alert("Veuillez sélectionner une dictée et saisir le texte de l'élève avant de lancer l'analyse.");
                return;
            }

            const btn = document.getElementById('btn-analyser');
            btn.innerHTML = "⏳ Analyse Stanza en cours (cela peut prendre quelques secondes)...";
            btn.disabled = true;

            // Cacher l'aperçu précédent
            document.getElementById("analyse-preview").style.display = "none";
            document.getElementById("analyse_result").value = "";

            const formData = new FormData();
            formData.append('action', 'analyser_texte');
            formData.append('id_dictee', idDictee);
            formData.append('dictee_eleve', dicteeEleve);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = "✨ Analyser automatiquement";
                btn.disabled = false;
                
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                if (data.success) {
                    // Remplissage automatique des champs d'erreurs
                    document.querySelector("input[name='faute_orthographique']").value = data.counts.faute_orthographique;
                    document.querySelector("input[name='mot_ou_ponctuation_manquant']").value = data.counts.mot_ou_ponctuation_manquant;
                    document.querySelector("input[name='accord_adjectif']").value = data.counts.accord_adjectif;
                    document.querySelector("input[name='nom_pluriel']").value = data.counts.nom_pluriel;
                    document.querySelector("input[name='accord_sujet_verbe']").value = data.counts.accord_sujet_verbe;
                    
                    // Stocker le texte d'analyse dans le champ caché (sera sauvegardé en BDD)
                    document.getElementById("analyse_result").value = data.analyse || "";

                    // Afficher l'aperçu de l'analyse
                    if (data.analyse && data.analyse !== "Aucune erreur") {
                        document.getElementById("analyse-preview-text").textContent = data.analyse;
                        document.getElementById("analyse-preview").style.display = "block";
                    } else {
                        document.getElementById("analyse-preview-text").textContent = "Aucune erreur détectée ✓";
                        document.getElementById("analyse-preview").style.display = "block";
                    }
                    
                    // Mise à jour visuelle du score
                    recalculerScore();
                }
            })
            .catch(err => {
                console.error("Erreur Fetch:", err);
                alert("Une erreur de communication avec le serveur d'analyse s'est produite.");
                btn.innerHTML = "✨ Analyser automatiquement";
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>