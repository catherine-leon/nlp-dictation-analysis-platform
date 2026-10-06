CREATE TABLE g1_utilisateur (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    identifiant VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('professeur', 'chercheur') NOT NULL,
    institution VARCHAR(255) NULL
);

CREATE TABLE g1_classe (
    id_classe INT AUTO_INCREMENT PRIMARY KEY,
    nom_classe VARCHAR(150) NOT NULL,
    nom_ecole VARCHAR(255) NOT NULL,
    niveau_scolaire VARCHAR(50) NOT NULL,
    annee_scolaire VARCHAR(50) NOT NULL,
    id_professeur INT NOT NULL,
    FOREIGN KEY (id_professeur) REFERENCES g1_utilisateur(id_utilisateur)
        ON DELETE CASCADE
);

CREATE TABLE g1_eleve (
    id_eleve INT AUTO_INCREMENT PRIMARY KEY,
    annee_naissance INT NOT NULL,
    francais_maison TINYINT(1) NOT NULL DEFAULT 0,
    langue_maternelle VARCHAR(100) NULL,
    id_classe INT NOT NULL,
    FOREIGN KEY (id_classe) REFERENCES g1_classe(id_classe)
        ON DELETE CASCADE
);

CREATE TABLE g1_dictee (
    id_dictee INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    niveau_scolaire VARCHAR(50) NOT NULL,
    date_passation DATE NOT NULL,
    type_dictee VARCHAR(100) NULL,
    contenu_reference TEXT NOT NULL,
    id_professeur INT NOT NULL,
    FOREIGN KEY (id_professeur) REFERENCES g1_utilisateur(id_utilisateur)
        ON DELETE CASCADE
);

CREATE TABLE g1_dictes_resultats (
    id_resultat INT AUTO_INCREMENT PRIMARY KEY,
    id_eleve INT NOT NULL,
    id_dictee INT NOT NULL,
    dictee_eleve TEXT NOT NULL,
    date_saisie DATE NOT NULL,
    faute_orthographique INT NOT NULL DEFAULT 0,
    mot_ou_ponctuation_manquant INT NOT NULL DEFAULT 0,
    accord_adjectif INT NOT NULL DEFAULT 0,
    nom_pluriel INT NOT NULL DEFAULT 0,
    accord_sujet_verbe INT NOT NULL DEFAULT 0,
    score INT NOT NULL DEFAULT 20,
    analyse TEXT NULL,
    FOREIGN KEY (id_eleve) REFERENCES g1_eleve(id_eleve) ON DELETE CASCADE,
    FOREIGN KEY (id_dictee) REFERENCES g1_dictee(id_dictee) ON DELETE CASCADE
);
