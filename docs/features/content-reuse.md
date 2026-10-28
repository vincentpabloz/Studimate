# Content Reuse Setup

Apply the additive schema changes from the project root:

```powershell
php database/migrations/migrate_content_reuse.php
```

Install parser dependencies with Composer after enabling PHP ZIP:

```powershell
composer install
```

The project locks `smalot/pdfparser` for PDF text extraction. DOCX and PPTX text is read from bounded OOXML parts using PHP ZIP and XML support. Legacy `.ppt` requires LibreOffice; set `STUDIMATE_LIBREOFFICE` to its executable path. Original files are stored in the protected `uploads/` directory by default. To use another private writable directory, set `STUDIMATE_PRIVATE_STORAGE` in the PHP process environment.

AI generation happens only after an explicit output request. Set `HUGGINGFACE_API_TOKEN` or `HF_TOKEN` in the server environment to enable it. Uploading or saving a lesson does not generate outputs.

Optional semantic duplicate review for manually entered lessons uses a local Ollama-compatible embedding endpoint. Set `STUDIMATE_EMBEDDING_URL` to a loopback endpoint such as `http://127.0.0.1:11434/api/embed` and optionally set `STUDIMATE_EMBEDDING_MODEL`. Only loopback hosts are accepted; similarity is a user-confirmed suggestion, not an automatic merge. Exact and partial chunk reuse is scoped to the signed-in user's own content.

This PHP/MySQL project does not currently include an admin/shared curriculum catalog or semantic duplicate checks during file upload. Lessons are stored under user-owned subjects; uploaded documents remain private and require an authenticated download.