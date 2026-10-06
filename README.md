# NLP-Assisted Dictation Analysis Platform

A web application for managing and analysing French school dictations, with a **Python NLP pipeline for automatic error detection and classification** at its core.

The project combines **Stanza-based linguistic analysis**, token-sequence alignment and rule-based error classification with a PHP/MySQL web interface. It was developed as a team project for the M1 *Industrie de la Langue* programme at Université Grenoble Alpes (2025–2026).

## Python NLP pipeline

The main NLP component is implemented in [`analyser_stanza.py`](analyser_stanza.py).

Given a **reference dictation** and a **student transcription**, the script:

1. processes both texts with the French **Stanza** pipeline;
2. extracts tokens together with their lemmas, POS tags and morphological features;
3. aligns the reference and student token sequences;
4. detects insertions, deletions and substitutions;
5. uses linguistic information to classify detected differences into error categories;
6. returns a structured analysis to the web application.

### Linguistic processing

The Stanza pipeline uses:

```python
processors="tokenize,pos,lemma"
```

For each token, the analysis can therefore use:

- surface form;
- lemma;
- Universal POS tag (`UPOS`);
- morphological features such as gender, number and verb agreement information.

This makes it possible to distinguish a simple spelling difference from morphologically motivated errors.

### Sequence alignment

Reference and student token sequences are compared with Python's `difflib.SequenceMatcher`.

The alignment identifies four operation types:

- `equal`
- `replace`
- `delete`
- `insert`

These operations provide the basis for locating differences between the expected text and the student's production before linguistic classification.

### Error classification

For substitutions, the script compares the Stanza analyses of the reference and student tokens. The rule-based classifier distinguishes several pedagogically relevant error types, including:

- spelling errors;
- adjective agreement errors;
- noun plural errors;
- subject–verb agreement errors.

Alignment operations are additionally used to identify:

- missing words;
- extra words;
- missing punctuation;
- extra punctuation.

The output keeps the expected and observed forms so that the detected error can be inspected rather than represented only as a numeric score.

### Example pipeline

```text
Reference text
      │
      ├──────────────┐
      │              │
      ▼              ▼
 Stanza NLP      Student text
      │              │
      └──────┬───────┘
             ▼
     Token alignment
     SequenceMatcher
             │
             ▼
   Linguistic comparison
 POS · lemma · morphology
             │
             ▼
     Error classification
             │
             ▼
 Detailed analysis + error counts
```

The system is an **educational NLP prototype**, not a general-purpose French grammar checker. Its purpose is to combine text alignment with explicit linguistic features to analyse differences in controlled dictation tasks.

## Python ↔ web integration

The NLP component runs as a standalone Python script and is called from PHP.

Texts are encoded in Base64 before being passed to Python, which avoids problems with accents, apostrophes and other UTF-8 characters when transferring French text between the PHP application and the command line.

The Python analysis is then returned to the application and can be:

- displayed to the teacher;
- used to populate error counters;
- stored with the dictation result;
- reviewed and manually corrected when necessary.

This separates the linguistic analysis from the web interface while allowing it to be used directly inside the application workflow.

## OCR integration

The application also supports OCR for handwritten or scanned dictations through Google Cloud Vision `DOCUMENT_TEXT_DETECTION`.

The OCR output is inserted into the student-text field and can then be processed by the **same Python/Stanza analysis pipeline**.

```text
Image → OCR → student text → Python NLP analysis → error categories
```

No API key is included in this repository.

## Web application

The surrounding PHP/MySQL application provides the interface required to use the NLP component in an educational setting.

### Teacher interface

Teachers can:

- manage classes and students;
- create reference dictations;
- enter or import student transcriptions;
- run automatic NLP analysis;
- inspect and edit detected error counts;
- calculate scores;
- browse previous results;
- export results to CSV.

### Researcher interface

The researcher view supports filtering and inspecting collected results by attributes such as school level, class, dictation and error type, as well as CSV export for further analysis.

## Project structure

```text
.
├── analyser_stanza.py       # Core Python NLP pipeline
├── analyse.php              # PHP ↔ Python analysis bridge
├── saisir_reponses.php      # Dictation entry and analysis interface
├── ocr.php                  # Google Cloud Vision OCR
├── resultats.php            # Teacher results
├── resultats_chercheur.php  # Research-oriented results view
├── export_csv.php           # Dataset export
├── classes.php              # Class management
├── eleves.php               # Student management
├── dictee.php               # Reference dictation management
├── database/
│   └── schema.sql
├── config.example.php
└── requirements.txt
```

## Technologies

**Python / NLP**
- Python
- Stanza
- tokenization
- lemmatization
- POS tagging
- morphological analysis
- `difflib.SequenceMatcher`
- rule-based error classification

**Application**
- PHP
- MySQL
- JavaScript
- HTML/CSS

**OCR**
- Google Cloud Vision API

## Setup

### Python environment

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python -c "import stanza; stanza.download('fr')"
```

If the Stanza models are stored in a custom directory, set `STANZA_MODEL_DIR`.

### Database

Create a MySQL database and import the schema:

```bash
mysql -u USER -p DATABASE_NAME < database/schema.sql
```

### Configuration

Copy the public configuration template:

```bash
cp config.example.php config.php
```

Configure:

```text
DB_HOST
DB_USER
DB_PASSWORD
DB_NAME
GOOGLE_VISION_KEY
```

`config.php` is excluded through `.gitignore` and must not be committed.

### Web server

Serve the project with a PHP-capable web server and ensure that PHP can execute `python3`.

## Data and credentials

The public repository contains **no student records, database exports, API keys or database credentials**. The included SQL file contains only the database schema.
