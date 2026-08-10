<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$config = load_config();

$type = trim((string) ($_GET['type'] ?? ''));
$slug = trim((string) ($_GET['slug'] ?? ($_GET['amp;slug'] ?? '')));

$post = null;
$page = null;

if ($slug !== '') {
    if ($type === 'page') {
        $page = get_page_by_slug($slug);
        $post = $page ? null : get_post_by_slug($slug);
    } else {
        $post = get_post_by_slug($slug);
        $page = $post ? null : get_page_by_slug($slug);
    }

    if ($post) {
        $postTitle = trim((string) ($post['title'] ?? ''));
        if ($postTitle !== '') {
            $title = $postTitle;
        } elseif (!empty($post['description'])) {
            $title = trim((string) $post['description']);
        } elseif (!empty($post['content'])) {
            $cleanContent = trim(strip_tags(render_markdown((string) $post['content'])));
            $title = $cleanContent !== '' ? mb_substr($cleanContent, 0, 120) : $slug;
        } else {
            $title = $slug;
        }
    } elseif ($page) {
        $pageTitle = trim((string) ($page['title'] ?? ''));
        if ($pageTitle !== '') {
            $title = $pageTitle;
        } elseif (!empty($page['description'])) {
            $title = trim((string) $page['description']);
        } else {
            $title = $slug;
        }
    } else {
        $title = $slug;
    }
} else {
    $siteDesc = trim((string) ($config['site_description'] ?? ''));
    $title = $siteDesc !== '' ? $siteDesc : (string) ($config['site_title'] ?? 'Pure Blog');
}

$siteTitle = trim((string) ($config['site_title'] ?? 'Pure Blog'));

// Determine theme colours
$colorMode = (string) ($config['theme']['color_mode'] ?? 'light');
if ($colorMode === 'dark') {
    $bgColor = (string) ($config['theme']['background_color_dark'] ?? '#1a1a1a');
    $textColor = (string) ($config['theme']['text_color_dark'] ?? '#f2f2f2');
} else {
    $bgColor = (string) ($config['theme']['background_color'] ?? '#f2f2f2');
    $textColor = (string) ($config['theme']['text_color'] ?? '#1a1a1a');
}

// Fallback safety for empty colour strings
if ($bgColor === '') {
    $bgColor = $colorMode === 'dark' ? '#1a1a1a' : '#f2f2f2';
}
if ($textColor === '') {
    $textColor = $colorMode === 'dark' ? '#f2f2f2' : '#1a1a1a';
}

// Determine font path
$fontStack = (string) ($config['theme']['font_stack'] ?? 'sans');
$fontPath = get_og_image_font_path($fontStack, [
    'type'  => $type,
    'slug'  => $slug,
    'post'  => $post,
    'page'  => $page,
]);

// Determine favicon path
$faviconPath = null;
$faviconHref = trim((string) ($config['assets']['favicon'] ?? ''));
if ($faviconHref !== '') {
    if ($faviconHref[0] === '/') {
        $resolved = PUREBLOG_BASE_PATH . $faviconHref;
        if (is_file($resolved) && is_readable($resolved)) {
            $faviconPath = $resolved;
        }
    }
}

// Cache check
$cacheDir = PUREBLOG_CACHE_PATH . '/og';
$cacheKey = md5(implode(':', [
    $type,
    $slug,
    $title,
    $siteTitle,
    $bgColor,
    $textColor,
    (string) $fontPath,
    (string) $faviconPath,
]));

$cacheFile = $cacheDir . '/' . $cacheKey . '.png';

if (is_file($cacheFile) && is_readable($cacheFile)) {
    header('Content-Type: image/png');
    header('Content-Length: ' . (string) filesize($cacheFile));
    header('Cache-Control: public, max-age=86400');
    readfile($cacheFile);
    exit;
}

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$width = 1360;
$height = 712;
$cleanTitle = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$cleanSiteTitle = html_entity_decode($siteTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');

if (class_exists('Imagick')) {
    try {
        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel($bgColor), 'png');

        $draw = new ImagickDraw();
        if ($fontPath !== null) {
            $draw->setFont($fontPath);
        }
        $draw->setFillColor(new ImagickPixel($textColor));

        $marginLeft = 80;
        $marginRight = 80;
        $marginTop = 160;
        $maxTextWidth = $width - $marginLeft - $marginRight;
        $maxY = 480;
        $lineHeight = 76;

        $draw->setFontSize(56);

        // Multi-line word wrap
        $rawLines = explode("\n", $cleanTitle);
        $lines = [];
        foreach ($rawLines as $rawLine) {
            $words = preg_split('/\s+/u', trim($rawLine)) ?: [];
            $currentLine = '';
            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }
                $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
                $metrics = $image->queryFontMetrics($draw, $testLine);
                if ($metrics['textWidth'] > $maxTextWidth && $currentLine !== '') {
                    $lines[] = $currentLine;
                    $currentLine = $word;
                } else {
                    $currentLine = $testLine;
                }
            }
            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
        }

        $y = $marginTop;
        $lineCount = count($lines);
        foreach ($lines as $i => $line) {
            if ($y + $lineHeight > $maxY && $i < $lineCount - 1) {
                while (mb_strlen($line) > 0) {
                    $testLine = $line . '…';
                    $m = $image->queryFontMetrics($draw, $testLine);
                    if ($m['textWidth'] <= $maxTextWidth) {
                        $line = $testLine;
                        break;
                    }
                    $line = mb_substr($line, 0, -1);
                }
                $image->annotateImage($draw, $marginLeft, $y, 0, $line);
                break;
            }
            $image->annotateImage($draw, $marginLeft, $y, 0, $line);
            $y += $lineHeight;
        }

        // Footer section (bottom left)
        $footerMarginBottom = 80;
        $faviconSize = 64;
        $faviconX = $marginLeft;
        $faviconY = $height - $footerMarginBottom - $faviconSize;
        $textX = $faviconX;

        if ($faviconPath !== null) {
            try {
                $fav = new Imagick($faviconPath);
                if ($fav->getNumberImages() > 1) {
                    $fav = $fav->coalesceImages();
                }
                $fav->setImageFormat('png');
                $fav->thumbnailImage($faviconSize, $faviconSize, true);
                $image->compositeImage($fav, Imagick::COMPOSITE_OVER, $faviconX, $faviconY);
                $fav->destroy();
                $textX = $faviconX + $faviconSize + 24;
            } catch (Throwable $e) {
                $textX = $faviconX;
            }
        }

        $draw->setFontSize(42);
        $siteY = $faviconY + 49;
        $image->annotateImage($draw, $textX, $siteY, 0, $cleanSiteTitle);

        $image->setImageFormat('png');
        $image->writeImage($cacheFile);
        $blob = $image->getImageBlob();
        $image->destroy();
        $draw->destroy();

        header('Content-Type: image/png');
        header('Content-Length: ' . (string) strlen($blob));
        header('Cache-Control: public, max-age=86400');
        echo $blob;
        exit;
    } catch (Throwable $e) {
        // Fall through to GD or default
    }
}

// GD Fallback
if (function_exists('imagecreatetruecolor')) {
    $img = imagecreatetruecolor($width, $height);
    if ($img !== false) {
        $hexToRgb = static function (string $hex): array {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            return [
                (int) hexdec(substr($hex, 0, 2)),
                (int) hexdec(substr($hex, 2, 2)),
                (int) hexdec(substr($hex, 4, 2)),
            ];
        };

        [$bgR, $bgG, $bgB] = $hexToRgb($bgColor);
        [$txR, $txG, $txB] = $hexToRgb($textColor);

        $bgCol = imagecolorallocate($img, $bgR, $bgG, $bgB);
        $txCol = imagecolorallocate($img, $txR, $txG, $txB);
        imagefilledrectangle($img, 0, 0, $width, $height, $bgCol);

        $marginLeft = 80;
        $marginRight = 80;
        $marginTop = 160;
        $maxTextWidth = $width - $marginLeft - $marginRight;
        $maxY = 480;
        $lineHeight = 76;
        $titleFontSize = 42; // pt for GD imagettftext

        if ($fontPath !== null && function_exists('imagettftext')) {
            $rawLines = explode("\n", $cleanTitle);
            $lines = [];
            foreach ($rawLines as $rawLine) {
                $words = preg_split('/\s+/u', trim($rawLine)) ?: [];
                $currentLine = '';
                foreach ($words as $word) {
                    if ($word === '') {
                        continue;
                    }
                    $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
                    $bbox = imagettfbbox($titleFontSize, 0, $fontPath, $testLine);
                    $lineWidth = $bbox !== false ? abs($bbox[4] - $bbox[0]) : 0;
                    if ($lineWidth > $maxTextWidth && $currentLine !== '') {
                        $lines[] = $currentLine;
                        $currentLine = $word;
                    } else {
                        $currentLine = $testLine;
                    }
                }
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                }
            }

            $y = $marginTop;
            $lineCount = count($lines);
            foreach ($lines as $i => $line) {
                if ($y + $lineHeight > $maxY && $i < $lineCount - 1) {
                    while (mb_strlen($line) > 0) {
                        $testLine = $line . '…';
                        $bbox = imagettfbbox($titleFontSize, 0, $fontPath, $testLine);
                        $lineWidth = $bbox !== false ? abs($bbox[4] - $bbox[0]) : 0;
                        if ($lineWidth <= $maxTextWidth) {
                            $line = $testLine;
                            break;
                        }
                        $line = mb_substr($line, 0, -1);
                    }
                    imagettftext($img, $titleFontSize, 0, $marginLeft, $y, $txCol, $fontPath, $line);
                    break;
                }
                imagettftext($img, $titleFontSize, 0, $marginLeft, $y, $txCol, $fontPath, $line);
                $y += $lineHeight;
            }

            // Footer
            $footerMarginBottom = 80;
            $faviconSize = 64;
            $faviconX = $marginLeft;
            $faviconY = $height - $footerMarginBottom - $faviconSize;
            $textX = $faviconX;

            if ($faviconPath !== null) {
                $favMime = mime_content_type($faviconPath) ?: '';
                $favImg = match ($favMime) {
                    'image/png'  => @imagecreatefrompng($faviconPath),
                    'image/jpeg' => @imagecreatefromjpeg($faviconPath),
                    'image/gif'  => @imagecreatefromgif($faviconPath),
                    'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($faviconPath) : null,
                    default      => null,
                };
                if ($favImg !== null && $favImg !== false) {
                    imagealphablending($img, true);
                    imagecopyresampled($img, $favImg, $faviconX, $faviconY, 0, 0, $faviconSize, $faviconSize, imagesx($favImg), imagesy($favImg));
                    imagedestroy($favImg);
                    $textX = $faviconX + $faviconSize + 24;
                }
            }

            imagettftext($img, 32, 0, $textX, $faviconY + 49, $txCol, $fontPath, $cleanSiteTitle);
        } else {
            imagestring($img, 5, $marginLeft, $marginTop, $cleanTitle, $txCol);
            imagestring($img, 4, $marginLeft, $height - 100, $cleanSiteTitle, $txCol);
        }

        imagepng($img, $cacheFile);
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        imagepng($img);
        imagedestroy($img);
        exit;
    }
}

http_response_code(500);
echo 'Error generating image.';
