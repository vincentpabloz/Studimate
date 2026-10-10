<?php
require __DIR__ . '/../includes/document_extractor.php';

function requireText(string $expected, string $actual): void
{
    if (!str_contains($actual, $expected)) {
        throw new RuntimeException('Expected extracted text not found: ' . $expected);
    }
}

function createZipFixture(string $path, array $entries): void
{
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create document fixture.');
    }
    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
}

function createPdfFixture(string $path, string $text): void
{
    $stream = 'BT /F1 18 Tf 72 720 Td (' . $text . ') Tj ET';
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF";
    file_put_contents($path, $pdf);
}

$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'studimate-parser-test-' . bin2hex(random_bytes(8));
mkdir($temp, 0700);
try {
    $pdf = $temp . DIRECTORY_SEPARATOR . 'source.pdf';
    createPdfFixture($pdf, 'PDF fixture text');
    requireText('PDF fixture text', extractDocumentText($pdf, 'pdf'));

    $docx = $temp . DIRECTORY_SEPARATOR . 'source.docx';
    createZipFixture($docx, [
        'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>DOCX fixture text</w:t></w:r></w:p></w:body></w:document>',
    ]);
    requireText('DOCX fixture text', extractDocumentText($docx, 'docx'));

    $pptx = $temp . DIRECTORY_SEPARATOR . 'source.pptx';
    createZipFixture($pptx, [
        'ppt/slides/slide2.xml' => '<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:t>Second slide fixture</a:t></p:sld>',
        'ppt/slides/slide1.xml' => '<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:t>First slide fixture</a:t></p:sld>',
    ]);
    $slides = extractDocumentText($pptx, 'pptx');
    requireText('Slide 1:', $slides);
    requireText('Slide 2:', $slides);
    if (strpos($slides, 'First slide fixture') > strpos($slides, 'Second slide fixture')) {
        throw new RuntimeException('PPTX slides must be extracted in numeric order.');
    }

    echo "Document extraction tests passed.\n";
} finally {
    foreach (glob($temp . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($temp);
}