<?php
session_start();
require "config.php";

if (!isset($_SESSION["id"]) || ($_SESSION["role"] ?? "") !== "professeur") {
    header("Location: index.php");
    exit();
}

$resultat_ocr = "";
$erreur_ocr   = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["image_dictee"])) {

    $fichier = $_FILES["image_dictee"];

    $types_autorises = ["image/jpeg", "image/png", "image/gif", "image/webp"];
    if (!in_array($fichier["type"], $types_autorises)) {
        $erreur_ocr = "Format non supporté. Utilisez JPG, PNG ou WEBP.";
    } elseif ($fichier["size"] > 5 * 1024 * 1024) {
        $erreur_ocr = "Image trop grande (maximum 5MB).";
    } else {

        $image_data   = file_get_contents($fichier["tmp_name"]);
        $image_base64 = base64_encode($image_data);
        $mime_type    = $fichier["type"];

        $api_key = GOOGLE_VISION_KEY;

        $request_body = json_encode([
            "requests" => [
                [
                    "image" => [
                        "content" => $image_base64
                    ],
                    "features" => [
                        [
                            "type"       => "DOCUMENT_TEXT_DETECTION",
                            "maxResults" => 1
                        ]
                    ],
                    "imageContext" => [
                        "languageHints" => ["fr"]
                    ]
                ]
            ]
        ]);

        $ch = curl_init("https://vision.googleapis.com/v1/images:annotate?key=" . $api_key);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $erreur_ocr = "Erreur réseau : " . $err;
        } else {
            $json = json_decode($response, true);
            if (isset($json["responses"][0]["fullTextAnnotation"]["text"])) {
                $resultat_ocr = $json["responses"][0]["fullTextAnnotation"]["text"];
            } elseif (isset($json["error"])) {
                $erreur_ocr = "Erreur API : " . $json["error"]["message"];
            } else {
                $erreur_ocr = "Aucun texte détecté dans l'image.";
            }
        }
    }
}

$id_classe = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>OCR Dictée — Gestion des Dictées</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="saisir_reponses.css">
    <style>
        .conseils-box {
            background: #F0F7FF;
            border: 1px solid #C8DEFA;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }

        .conseils-box h3 {
            color: #1E3F5A;
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0 0 12px 0;
        }

        .conseils-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .conseils-list li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.88rem;
            color: #3A5A72;
            line-height: 1.4;
        }

        .conseils-list li span.icon {
            font-size: 1rem;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.85);
            z-index: 9999;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #C8DEFA;
            border-top-color: #1E3F5A;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading-text {
            color: #1E3F5A;
            font-family: 'Roboto', sans-serif;
            font-size: 1rem;
            font-weight: 500;
        }

        .loading-subtext {
            color: #6B8299;
            font-size: 0.85rem;
            margin-top: -12px;
        }

        .btn-utiliser {
            display: inline-block;
            margin-top: 12px;
            margin-left: 12px;
            background: #2E7D5E;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            font-family: 'Roboto', sans-serif;
            transition: background 0.2s;
        }

        .btn-utiliser:hover {
            background: #236149;
            color: white;
        }

        .resultat-section {
            margin-top: 30px;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .resultat-section h3 {
            color: #1E3F5A;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge-succes {
            background: #E6F4EF;
            color: #2E7D5E;
            font-size: 0.75rem;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 500;
        }

        .actions-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        @media (max-width: 600px) {
            .conseils-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- Indicateur de chargement -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <div class="loading-text">Analyse de l'image en cours...</div>
        <div class="loading-subtext">Google Vision transcrit le texte manuscrit</div>
    </div>

    <header>
        <span class="brand">Gestion des Dictées</span>
        <span class="bonjour">Bonjour, <?= htmlspecialchars($_SESSION["identifiant"]) ?></span>
        <button class="btn-deconnexion" onclick="window.location.href='deconnexion.php'">⇥ Déconnexion</button>
    </header>

    <main>
        <a class="retour" href="saisir_reponses.php<?= $id_classe ? '?id=' . $id_classe : '' ?>">
            ← Retour à la saisie
        </a>

        <div class="container">
            <h1>Transcrire une dictée par image</h1>

            <!-- Conseils pour une bonne photo -->
            <div class="conseils-box">
                <h3>📷 Conseils pour une meilleure reconnaissance</h3>
                <ul class="conseils-list">
                    <li><span class="icon">📐</span> Posez la feuille à plat sur une surface stable</li>
                    <li><span class="icon">☀️</span> Utilisez une bonne lumière naturelle ou artificielle uniforme</li>
                    <li><span class="icon">🚫</span> Évitez les ombres sur le texte (tenez le téléphone au-dessus)</li>
                    <li><span class="icon">📱</span> Photographiez perpendiculairement, sans angle ni déformation</li>
                    <li><span class="icon">🔍</span> Assurez-vous que le texte est net et lisible</li>
                    <li><span class="icon">✂️</span> Cadrez uniquement le texte de la dictée, sans éléments parasites</li>
                </ul>
            </div>

            <!-- Formulaire upload -->
            <form id="ocrForm"
                action="ocr.php<?= $id_classe ? '?id=' . $id_classe : '' ?>"
                method="POST"
                enctype="multipart/form-data"
                onsubmit="afficherChargement()">

                <div class="form-group">
                    <label>Image de la dictée (JPG, PNG, WEBP — max 5MB)</label>
                    <input type="file" name="image_dictee" accept="image/*" required
                        style="padding:10px; background:#FAFBFC; border:1px solid #D6E0EA;
                              border-radius:8px; width:100%; margin-top:6px;">
                </div>

                <button type="submit" class="btn-save" style="margin-top:15px;">
                    🔍 Transcrire l'image
                </button>
            </form>

            <!-- Message d'erreur -->
            <?php if ($erreur_ocr): ?>
                <p class="msg erreur" style="margin-top:20px;">
                    <?= htmlspecialchars($erreur_ocr) ?>
                </p>
            <?php endif; ?>

            <!-- Résultat OCR -->
            <?php if ($resultat_ocr): ?>
                <div class="resultat-section">
                    <h3>
                        Texte reconnu
                        <span class="badge-succes">✓ Transcription réussie</span>
                    </h3>

                    <textarea id="texte_ocr" rows="10"
                        style="width:100%; padding:12px; border:1px solid #D6E0EA;
                                 border-radius:8px; background:#FAFBFC; font-family:inherit;
                                 font-size:0.95rem; line-height:1.6; box-sizing:border-box;"><?= htmlspecialchars($resultat_ocr) ?></textarea>

                    <p style="color:#6B8299; font-size:0.85rem; margin-top:8px;">
                        Vérifiez et corrigez si nécessaire avant d'utiliser le texte.
                    </p>

                    <div class="actions-row">
                        <button onclick="copierTexte()" class="btn-save">
                            📋 Copier le texte
                        </button>

                        <?php if ($id_classe): ?>
                            <button onclick="utiliserTexte()" class="btn-utiliser">
                                ✅ Utiliser ce texte dans la saisie
                            </button>
                        <?php else: ?>
                            <span style="color:#6B8299; font-size:0.85rem;">
                                (Revenez depuis une classe pour utiliser directement le texte)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <footer>
        Gestion des Dictées — Université Grenoble Alpes<br>
        M1 Industrie de la Langue — 2025-2026
    </footer>

    <script>
        function afficherChargement() {
            document.getElementById("loadingOverlay").classList.add("active");
        }

        function copierTexte() {
            var textarea = document.getElementById("texte_ocr");
            textarea.select();
            document.execCommand("copy");
            alert("Texte copié !");
        }

        function utiliserTexte() {
            var texte = document.getElementById("texte_ocr").value;
            // Stocker dans sessionStorage pour récupérer dans saisir_reponses.php
            sessionStorage.setItem("ocr_texte", texte);
            window.location.href = "saisir_reponses.php?id=<?= $id_classe ?>&ocr=1";
        }
    </script>

</body>

</html>