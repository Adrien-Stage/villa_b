<?php
/**
 * Fix UTF-8 double-encoding (mojibake) in dashboard.blade.php
 * Strategy: map all known corrupted byte sequences back to correct UTF-8 chars
 */

$file = __DIR__ . '/resources/views/admin/dashboard.blade.php';
$content = file_get_contents($file);
$original_size = strlen($content);

// Complete mapping of double-encoded UTF-8 → correct UTF-8 for French
$replacements = [
    // Uppercase accented
    'Ã‰' => 'É',  // É
    'Ãˆ' => 'È',  // È
    'Ã€' => 'À',  // À
    'Ã‡' => 'Ç',  // Ç
    'ÃŠ' => 'Ê',  // Ê
    'Ã"' => 'Ô',  // Ô
    'Ã›' => 'Û',  // Û
    'ÃŽ' => 'Î',  // Î
    
    // Lowercase accented
    'Ã©' => 'é',
    'Ã¨' => 'è',
    'Ãª' => 'ê',
    'Ã«' => 'ë',
    'Ã¢' => 'â',
    'Ã¤' => 'ä',
    'Ã®' => 'î',
    'Ã¯' => 'ï',
    'Ã´' => 'ô',
    'Ã¶' => 'ö',
    'Ã¹' => 'ù',
    'Ã»' => 'û',
    'Ã¼' => 'ü',
    'Ã§' => 'ç',
    
    // à requires special handling (Ã followed by space or specific chars)
    'Ã ' => 'à',  // Ã + non-breaking space (0xC2 0xA0)
    
    // Punctuation / special
    'â€"' => '–',  // en-dash
    'â€"' => '—',  // em-dash  
    'â€™' => ''',  // right single quote
    'â€˜' => ''',  // left single quote
    'â€œ' => '"',  // left double quote
    'â€' => '"',  // right double quote
    'Å"' => 'œ',  // oe ligature
    'Â°' => '°',  // degree
    'Â«' => '«',  // left guillemet
    'Â»' => '»',  // right guillemet
    'Â ' => ' ',  // non-breaking space doubled
];

foreach ($replacements as $bad => $good) {
    $content = str_replace($bad, $good, $content);
}

// Final pass: fix any remaining "Ã " (Ã + regular space) → "à "
// This is the trickiest one because Ã can be part of other sequences
$content = preg_replace('/Ã(?= [a-zéèêëàâîïôùûüç\d\'"])/u', 'à', $content);
// Also fix Ã at end or before punctuation  
$content = preg_replace('/Ã(?=\s)/u', 'à', $content);

file_put_contents($file, $content);
$new_size = strlen($content);

echo "Done!\n";
echo "Original size: {$original_size} bytes\n";
echo "New size: {$new_size} bytes\n";
echo "Bytes changed: " . ($original_size - $new_size) . "\n";

// Verify: check if any Ã sequences remain
preg_match_all('/Ã[^\x00-\x7F]|Ã /', $content, $matches);
if (!empty($matches[0])) {
    echo "\nWARNING: " . count($matches[0]) . " potential mojibake sequences still found:\n";
    foreach (array_unique($matches[0]) as $seq) {
        echo "  - " . bin2hex($seq) . " => '" . $seq . "'\n";
    }
} else {
    echo "\nAll mojibake sequences have been fixed!\n";
}
