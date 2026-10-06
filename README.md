# NLP-Assisted Dictation Analysis Platform

A web application for managing and analysing French school dictations. The platform combines a PHP/MySQL application with a Python NLP pipeline based on Stanza and optional OCR through Google Cloud Vision.

Developed as a team project for the M1 *Industrie de la Langue* programme at Université Grenoble Alpes (2025–2026).

## Features

### Teacher interface
- account creation and authentication;
- class and student management;
- creation of reference dictations;
- manual entry or OCR transcription of student dictations;
- automatic linguistic analysis of a student's text;
- editable error counts and automatic score calculation;
- result filtering and CSV export.

### Researcher interface
- access to aggregated dictation results;
- filtering by school level, class, dictation, student metadata and error type;
- detailed access to reference text, student text and automatic analysis;
- CSV export for further analysis.

## NLP pipeline

`analyser_stanza.py` compares a reference dictation with a student's production.

1. Both texts are processed with the French Stanza pipeline (`tokenize`, `pos`, `lemma`).
2. Token sequences are aligned with Python's `difflib.SequenceMatcher`.
3. Insertions, deletions and substitutions are identified.
4. POS tags, lemmas and morphological features are used to distinguish several error types:
   - lexical spelling errors;
   - missing or extra words;
   - missing or extra punctuation;
   - adjective agreement;
   - noun plural errors;
   - subject–verb agreement.
5. The analysis is returned to the PHP application, where error counters and the score can be reviewed and edited by the teacher.

The error classification is rule-based and intended as an educational NLP prototype rather than a general-purpose grammar checker.

## OCR

The application can send an uploaded image to Google Cloud Vision `DOCUMENT_TEXT_DETECTION`. The extracted text is then transferred to the dictation form and can be analysed by the same NLP pipeline.

## Architecture

```text
Browser
  │
  ├── PHP / JavaScript interface
  │       │
  │       ├── MySQL
  │       │   ├── users
  │       │   ├── classes
  │       │   ├── students
  │       │   ├── dictations
  │       │   └── results
  │       │
  │       ├── Python / Stanza
  │       │   └── automatic error analysis
  │       │
  │       └── Google Cloud Vision API
  │           └── OCR
  │
  └── CSV export
```

## Technologies

**NLP:** Python, Stanza, morphological analysis, sequence alignment  
**Backend:** PHP, MySQL  
**Frontend:** HTML, CSS, JavaScript  
**OCR:** Google Cloud Vision API

## Setup

### 1. Database

Create a MySQL database and import:

```bash
mysql -u USER -p DATABASE_NAME < database/schema.sql
```

### 2. Configuration

Copy the example configuration:

```bash
cp config.example.php config.php
```

Set the following environment variables (or adapt your local `config.php`):

```text
DB_HOST
DB_USER
DB_PASSWORD
DB_NAME
GOOGLE_VISION_KEY
```

`config.php` is ignored by Git and should never be committed.

### 3. Python dependencies

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python -c "import stanza; stanza.download('fr')"
```

If Stanza models are stored in a custom directory, set `STANZA_MODEL_DIR`.

### 4. Web server

Serve the repository with a PHP-capable web server and make sure PHP can execute `python3`.

## Repository notes

- No API keys, database credentials, student records or exported result data are included.
- Temporary installation/debugging scripts used during development are excluded from the public repository.
- The database schema contains structure only, with no project data.

## Project context

This is a university team project developed for M1 *Industrie de la Langue* at Université Grenoble Alpes. The public repository focuses on the technical implementation and NLP integration.
