<?php
$envFile = dirname(__DIR__) . '/.env';
$environment = is_file($envFile) ? parse_ini_file($envFile, false, INI_SCANNER_RAW) : [];

foreach (['GEMINI_API_KEY', 'GEMINI_MODEL', 'HF_TOKEN', 'HUGGINGFACE_API_TOKEN'] as $name) {
	if (isset($environment[$name]) && getenv($name) === false) {
		$_ENV[$name] = trim((string)$environment[$name]);
	}
}