<?php

function loadOfficeXml(string $xml): SimpleXMLElement
{
    if (strlen($xml) > 4 * 1024 * 1024 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
        throw new RuntimeException('Office XML contains a prohibited declaration or exceeds the size limit.');
    }
    $previous = libxml_use_internal_errors(true);
    try {
        return new SimpleXMLElement($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
    } catch (Throwable $error) {
        throw new RuntimeException('Office XML is invalid.', 0, $error);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function extractDocxText(string $path): string
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('DOCX extraction requires PHP ZIP.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('The DOCX archive is corrupt.');
    }
    try {
        $xml = $zip->getFromName('word/document.xml');
        if (!is_string($xml)) {
            throw new RuntimeException('The DOCX archive has no document body.');
        }
        $document = loadOfficeXml($xml);
        $document->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraphs = [];
        foreach ($document->xpath('//w:p') ?: [] as $paragraph) {
            $runs = $paragraph->xpath('.//w:t') ?: [];
            $text = trim(implode('', array_map(static fn($run) => (string)$run, $runs)));
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }
        return implode("\n\n", $paragraphs);
    } finally {
        $zip->close();
    }
}

function extractPptxText(string $path): string
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('PPTX extraction requires PHP ZIP.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('The PPTX archive is corrupt.');
    }
    try {
        $slides = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (preg_match('#^ppt/slides/slide([0-9]+)\.xml$#', (string)$name, $match)) {
                $slides[(int)$match[1]] = $name;
            }
        }
        if (count($slides) > 500) {
            throw new RuntimeException('The presentation exceeds 500 slides.');
        }
        ksort($slides, SORT_NUMERIC);
        $result = [];
        foreach ($slides as $number => $name) {
            $xml = $zip->getFromName($name);
            if (!is_string($xml)) {
                throw new RuntimeException('A presentation slide could not be read.');
            }
            $slide = loadOfficeXml($xml);
            $slide->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $texts = array_map(static fn($run) => trim((string)$run), $slide->xpath('//a:t') ?: []);
            $texts = array_values(array_filter($texts, static fn($text) => $text !== ''));
            if ($texts) {
                $result[] = 'Slide ' . $number . ":\n" . implode("\n", $texts);
            }
        }
        return implode("\n\n", $result);
    } finally {
        $zip->close();
    }
}

function extractDocumentText(string $path, string $extension): string
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    if ($extension === 'pdf') {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new RuntimeException('PDF extraction package is unavailable.');
        }
        $pdf = (new \Smalot\PdfParser\Parser())->parseFile($path);
        $pages = [];
        foreach ($pdf->getPages() as $index => $page) {
            $text = trim($page->getText());
            if ($text !== '') {
                $pages[] = 'Page ' . ($index + 1) . ":\n" . $text;
            }
        }
        return implode("\n\n", $pages);
    }
    if ($extension === 'docx') {
        return extractDocxText($path);
    }
    if ($extension === 'pptx') {
        return extractPptxText($path);
    }
    if ($extension === 'ppt') {
        $converter = getenv('STUDIMATE_LIBREOFFICE') ?: '';
        if ($converter === '' || !is_file($converter) || !function_exists('proc_open')) {
            throw new RuntimeException('Legacy PPT extraction requires LibreOffice configured through STUDIMATE_LIBREOFFICE.');
        }
        $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'studimate-' . bin2hex(random_bytes(8));
        if (!mkdir($temp, 0700, true)) {
            throw new RuntimeException('Could not create a temporary conversion directory.');
        }
        try {
            $process = proc_open([$converter, '--headless', '--convert-to', 'pdf', '--outdir', $temp, $path], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start LibreOffice.');
            }
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $status = proc_close($process);
            $pdf = $temp . DIRECTORY_SEPARATOR . pathinfo($path, PATHINFO_FILENAME) . '.pdf';
            if ($status !== 0 || !is_file($pdf)) {
                throw new RuntimeException('LibreOffice could not convert the PPT file.');
            }
            return extractDocumentText($pdf, 'pdf');
        } finally {
            foreach (glob($temp . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) unlink($file);
            }
            rmdir($temp);
        }
    }
    throw new RuntimeException('Unsupported document type.');
}