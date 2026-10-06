<?php
// =====================================================
// DECONNEXION.PHP — Gestion des Dictées
// Destruction de la session et retour à l'accueil
// =====================================================

session_start();

// Destruction de toutes les variables de session
session_unset();

// Destruction de la session
session_destroy();

// Redirection vers la page de connexion
header("Location: index.php");
exit();
?>
