<?php
$files = [];
$dir = new RecursiveDirectoryIterator('resources/views');
$iter = new RecursiveIteratorIterator($dir);
foreach ($iter as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $files[] = $file->getPathname();
    }
}

foreach ($files as $file) {
    $content = file_get_contents($file);
    $orig = $content;
    
    // We only process files with modals
    if (strpos($content, 'fixed inset-0 z-50') === false) continue;
    
    // 1. Reset
    $content = str_replace(' flex flex-col max-h-[90vh]', '', $content);
    $content = str_replace(' shrink-0', '', $content);
    $content = str_replace(' flex flex-col flex-1 min-h-0 overflow-hidden', '', $content);
    
    // Replace <div class="bg-white rounded-... max-w-..." with flex flex-col max-h-[90vh]
    // We look for bg-white and max-w- and add it at the end of the class string
    $content = preg_replace_callback('/(<div[^>]*class=")([^"]*bg-white[^"]*max-w-[^"]*)(")/i', function($matches) {
        // If it already has it, don't add
        if (strpos($matches[2], 'flex-col max-h-[90vh]') !== false) return $matches[0];
        return $matches[1] . $matches[2] . ' flex flex-col max-h-[90vh]' . $matches[3];
    }, $content);
    
    // Replace Header. Usually contains items-center justify-between and border-b OR mb-4
    $content = preg_replace_callback('/(<div[^>]*class=")([^"]*flex\s+items-center\s+justify-between(?:[^"]*border-b|[^"]*mb-4)[^"]*)(")/i', function($matches) {
        if (strpos($matches[2], 'shrink-0') !== false) return $matches[0];
        return $matches[1] . $matches[2] . ' shrink-0' . $matches[3];
    }, $content);
    
    // Replace form wrapper.
    $content = preg_replace_callback('/(<form[^>]*class=")([^"]*)(")/i', function($matches) {
        if (strpos($matches[2], 'overflow-hidden') !== false) return $matches[0];
        return $matches[1] . $matches[2] . ' flex flex-col flex-1 min-h-0 overflow-hidden' . $matches[3];
    }, $content);

    // Some forms don't have class attribute!
    $content = preg_replace('/(<form(?![^>]*class=)[^>]*)(>)/i', '$1 class="flex flex-col flex-1 min-h-0 overflow-hidden"$2', $content);

    // Extract body and footer!
    // A form contains @csrf (optional) then some content, then a footer <div class="flex justify-end...
    // The problem is finding the start of the body and the footer accurately.
    // We can do it by replacing the first occurrence of @csrf or the first <div inside the form:
    // Let's replace the form's immediate content wrapper manually using regex for all files.
    // Actually, I can just use DOMDocument if I suppress errors, but Blade makes it hard.
    
    // Let's just output which files were matched and I will use the x-modal for them manually.
}
