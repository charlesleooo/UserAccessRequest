<?php
/**
 * Simple Vite helpers for non-Laravel applications
 * This mimics Laravel's @vite directive functionality
 */

function vite_assets($assets) {
    $manifestPath = __DIR__ . '/public/build/.vite/manifest.json';
    
    // Check if we're in development mode (dev server running)
    if (is_dev_server_running()) {
        $output = '';
        foreach ($assets as $asset) {
            if (str_ends_with($asset, '.jsx') || str_ends_with($asset, '.js')) {
                $output .= "<script type=\"module\" src=\"http://localhost:5173/{$asset}\"></script>\n";
            } elseif (str_ends_with($asset, '.css')) {
                $output .= "<link rel=\"stylesheet\" href=\"http://localhost:5173/{$asset}\">\n";
            }
        }
        return $output;
    }
    
    // Production mode - use manifest
    if (!file_exists($manifestPath)) {
        return "<!-- Vite manifest not found. Run 'npm run build' to generate assets. -->\n";
    }
    
    $manifest = json_decode(file_get_contents($manifestPath), true);
    
    $output = '';
    foreach ($assets as $asset) {
        if (isset($manifest[$asset])) {
            $file = $manifest[$asset];
            
            // Add main JS file
            if (isset($file['file'])) {
                $output .= "<script type=\"module\" src=\"/build/{$file['file']}\"></script>\n";
            }
            
            // Add CSS imports
            if (isset($file['css'])) {
                foreach ($file['css'] as $css) {
                    $output .= "<link rel=\"stylesheet\" href=\"/build/{$css}\">\n";
                }
            }
        }
    }
    
    return $output;
}

function is_dev_server_running() {
    $context = stream_context_create(['http' => ['timeout' => 1]]);
    $result = @file_get_contents('http://localhost:5173', false, $context);
    return $result !== false;
}

// For backward compatibility with Laravel-style syntax
function vite($assets) {
    return vite_assets(is_array($assets) ? $assets : [$assets]);
}
?>