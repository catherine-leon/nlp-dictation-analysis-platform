"""
analyser_stanza.py
------------------
Ce script compare deux textes français — la dictée modèle et la dictée
de l'élève — et identifie les erreurs phrase par phrase.
 
Il utilise Stanza, un outil d'analyse linguistique, pour comprendre
la nature grammaticale de chaque mot (nom, verbe, adjectif...) et
ainsi distinguer une faute d'orthographe d'un problème d'accord.
 
Usage depuis la ligne de commande :
    python3 analyser_stanza.py <modele_en_base64> <eleve_en_base64>
 
Les textes sont passés encodés en base64 pour éviter les problèmes
d'accents et d'apostrophes dans le terminal.
"""

import sys
import base64
import os

import logging
logging.getLogger("stanza").setLevel(logging.ERROR)

import stanza
from difflib import SequenceMatcher

# On charge le pipeline Stanza une seule fois au démarrage du script.
# Il analyse le français avec trois étapes : découpage en tokens,
# identification grammaticale (POS), et lemmatisation (forme de base du mot).

stanza_model_dir = os.getenv("STANZA_MODEL_DIR")

pipeline_kwargs = {
    "lang": "fr",
    "processors": "tokenize,pos,lemma",
    "use_gpu": False,
}
if stanza_model_dir:
    pipeline_kwargs["dir"] = stanza_model_dir
    pipeline_kwargs["download_method"] = None

nlp = stanza.Pipeline(**pipeline_kwargs)


def decode_b64_arg(arg):
    """
    Décode un argument passé en base64 depuis la ligne de commande.
    On encode les textes en base64 avant de les passer au script pour
    éviter que les accents, guillemets et apostrophes ne causent des
    problèmes dans le terminal.
    """
    return base64.b64decode(arg).decode("utf-8")


def get_tokens_from_sentence(sentence):
    """
    Extrait la liste des tokens (mots et ponctuations) d'une phrase
    analysée par Stanza. Chaque token contient le texte du mot ainsi
    que ses informations grammaticales.
    """
    tokens = []
    for tok in sentence.tokens:
        tokens.append(tok)
    return tokens


def classify_error(token_model, token_response):
    """
    Compare deux tokens — un du texte modèle, l'autre du texte élève —
    et détermine le type d'erreur commise.

    La classification suit cette logique :
    - Si les deux mots sont de la même catégorie grammaticale :
        * Pour un adjectif : si les traits grammaticaux diffèrent
          (genre, nombre), c'est un problème d'accord adjectif.
        * Pour un nom : si les feats diffèrent OU si les textes diffèrent
          en nombre (l'un se termine en s/x, l'autre non), c'est un pluriel
          mal formé ; sinon c'est une faute d'orthographe.
          Note : Stanza peut en contexte donner les mêmes feats à un mot
          mal orthographié (ex. "village" au lieu de "villages"), d'où la
          vérification sur le texte brut.
        * Pour un verbe : si les traits diffèrent (personne, nombre),
          c'est un problème d'accord sujet-verbe ; sinon orthographe.
        * Pour un déterminant (DET) : si les feats diffèrent en nombre
          (leurs/leur), c'est un accord ; sinon orthographe.
    - Dans tous les autres cas, on considère que c'est une faute d'orthographe.
    """
    word_model = token_model.words[0]
    word_response = token_response.words[0]

    text_m = token_model.text.lower()
    text_r = token_response.text.lower()

    if word_model.upos == word_response.upos:

        if word_model.upos == "ADJ":
            if word_model.feats != word_response.feats:
                return "accord adjectif"
            if word_model.lemma == word_response.lemma and text_m != text_r:
                return "accord adjectif"

        if word_model.upos == "NOUN":
            if word_model.feats != word_response.feats:
                return "pluriel nom"
            return "faute orthographique"

        if word_model.upos == "VERB":
            if word_model.feats != word_response.feats:
                return "accord sujet-verbe"
            elif word_model.lemma == word_response.lemma:
                return "faute orthographique"
            return "faute orthographique"

        # Déterminants : leurs/leur, ces/ce, etc.
        if word_model.upos == "DET":
            if word_model.feats != word_response.feats:
                return "accord adjectif"
            return "faute orthographique"

    return "faute orthographique"


def analyse_phrase_tokens(model_tokens, response_tokens):
    """
    Compare token par token une phrase du modèle avec la phrase de l'élève,
    et retourne la liste des erreurs trouvées.
 
    On utilise SequenceMatcher (comme un diff de texte) pour aligner
    les deux séquences de tokens et repérer les différences :
    - "replace" : un mot a été remplacé par un autre → on classifie l'erreur
    - "delete"  : un mot du modèle est absent chez l'élève → mot manquant
    - "insert"  : l'élève a ajouté un mot qui n'est pas dans le modèle → mot en trop
 
    On fait attention à ne pas confondre un mot avec une ponctuation
    lors des comparaisons, car cela fausserait la classification.
    """
    erreurs = []

    model_texts = [t.text for t in model_tokens]
    response_texts = [t.text for t in response_tokens]

    matcher = SequenceMatcher(None, model_texts, response_texts)

    for tag, i1, i2, j1, j2 in matcher.get_opcodes():

        if tag == "equal":
            continue

        if tag == "replace":

            i = i1
            j = j1

            while i < i2 and j < j2:

                tm = model_tokens[i]
                tr = response_tokens[j]

                tm_punct = tm.words[0].upos == "PUNCT"
                tr_punct = tr.words[0].upos == "PUNCT"

                
                if tm_punct != tr_punct:
                    if tm_punct:
                        erreurs.append(
                            "ponctuation manquante :\n"
                            + "attendu \"" + tm.text + "\""
                        )
                        i += 1
                    else:
                        erreurs.append(
                            "mot en trop :\n"
                            + "reçu \"" + tr.text + "\""
                        )
                        j += 1
                    continue

                
                erreur = classify_error(tm, tr)

                erreurs.append(
                    erreur + " :\n"
                    + "attendu \"" + tm.text + "\",\n"
                    + "reçu \"" + tr.text + "\""
                )

                i += 1
                j += 1

            while i < i2:
                tm = model_tokens[i]
                if tm.words[0].upos == "PUNCT":
                    erreurs.append(
                        "ponctuation manquante :\n"
                        + "attendu \"" + tm.text + "\""
                    )
                else:
                    erreurs.append(
                        "mot manquant :\n"
                        + "attendu \"" + tm.text + "\""
                    )
                i += 1

            while j < j2:
                tr = response_tokens[j]
                if tr.words[0].upos == "PUNCT":
                    erreurs.append(
                        "ponctuation en trop :\n"
                        + "reçu \"" + tr.text + "\""
                    )
                else:
                    erreurs.append(
                        "mot en trop :\n"
                        + "reçu \"" + tr.text + "\""
                    )
                j += 1

        elif tag == "delete":
            for k in range(i1, i2):
                tm = model_tokens[k]
                if tm.words[0].upos == "PUNCT":
                    erreurs.append(
                        "ponctuation manquante :\n"
                        + "attendu \"" + tm.text + "\""
                    )
                else:
                    erreurs.append(
                        "mot manquant :\n"
                        + "attendu \"" + tm.text + "\""
                    )

        elif tag == "insert":
            for k in range(j1, j2):
                tr = response_tokens[k]
                if tr.words[0].upos == "PUNCT":
                    erreurs.append(
                        "ponctuation en trop :\n"
                        + "reçu \"" + tr.text + "\""
                    )
                else:
                    erreurs.append(
                        "mot en trop :\n"
                        + "reçu \"" + tr.text + "\""
                    )

    return erreurs


def analyse_texte(model_text, student_text):
    """
    Analyse complète de deux textes : le texte modèle et le texte de l'élève.
 
    On découpe les deux textes en phrases grâce à Stanza, puis on compare
    chaque paire de phrases une par une. Les erreurs sont regroupées par
    phrase pour faciliter la lecture du résultat.
 
    Retourne une liste de chaînes de caractères décrivant toutes les erreurs
    trouvées, avec le numéro de la phrase concernée.
    """
    doc_model = nlp(model_text)
    doc_student = nlp(student_text)

    erreurs_globales = []

    for i, (s_model, s_student) in enumerate(zip(doc_model.sentences, doc_student.sentences)):
        model_tokens = get_tokens_from_sentence(s_model)
        student_tokens = get_tokens_from_sentence(s_student)

        erreurs = analyse_phrase_tokens(model_tokens, student_tokens)

        if erreurs:
            erreurs_globales.append("PHRASE " + str(i + 1))
            erreurs_globales.extend(erreurs)
            erreurs_globales.append("")

    return erreurs_globales


if __name__ == "__main__":
    """
    Point d'entrée du script quand il est appelé depuis la ligne de commande.
    On attend deux arguments : le texte modèle et le texte élève, tous les
    deux encodés en base64. Le résultat est affiché ligne par ligne.
    """

    if len(sys.argv) < 3:
        print("Donnees manquantes")
        sys.exit(1)

    try:
        model_text = decode_b64_arg(sys.argv[1])
        student_text = decode_b64_arg(sys.argv[2])
    except Exception as e:
        print("Erreur décodage : " + str(e))
        sys.exit(1)

    erreurs = analyse_texte(model_text, student_text)

    if not erreurs:
        print("Aucune erreur")
    else:
        print("\n".join(erreurs))