<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OptimizeImages extends Command
{
    protected $signature = 'images:optimize
        {--quality=82 : WebP quality 0-100}
        {--force : Re-convert even if .webp is newer than .png}';

    protected $description = 'Convert PNG images in public/Images to WebP for fast page loads';

    public function handle(): int
    {
        $dir = public_path('Images');

        if (!is_dir($dir)) {
            $this->error("Directory not found: $dir");
            return 1;
        }

        $quality = max(0, min(100, (int) $this->option('quality')));
        $force   = (bool) $this->option('force');

        $pngs = glob($dir . DIRECTORY_SEPARATOR . '*.png') ?: [];
        if (!$pngs) {
            $this->warn("No PNG files found in $dir");
            return 0;
        }

        $hasImagick = class_exists(\Imagick::class);
        $hasGdWebp  = function_exists('imagewebp');

        if (!$hasImagick && !$hasGdWebp) {
            $this->error('Neither Imagick nor GD-WebP is available. Install one to convert images.');
            return 1;
        }

        $this->info(sprintf('Using %s for conversion. Quality: %d.',
            $hasImagick ? 'Imagick' : 'GD imagewebp()', $quality));
        $this->newLine();

        $totalIn = 0;
        $totalOut = 0;
        $converted = 0;
        $skipped = 0;

        foreach ($pngs as $png) {
            $webp = preg_replace('/\.png$/i', '.webp', $png);
            $inSize = filesize($png);
            $totalIn += $inSize;

            if (!$force && file_exists($webp) && filemtime($webp) >= filemtime($png)) {
                $totalOut += filesize($webp);
                $skipped++;
                $this->line(sprintf('  skip   %s (already current)', basename($png)));
                continue;
            }

            $ok = $hasImagick
                ? $this->convertWithImagick($png, $webp, $quality)
                : $this->convertWithGd($png, $webp, $quality);

            if (!$ok || !file_exists($webp)) {
                $this->warn(sprintf('  fail   %s', basename($png)));
                continue;
            }

            $outSize = filesize($webp);
            $totalOut += $outSize;
            $converted++;

            $pct = $inSize > 0 ? (int) round(100 - ($outSize / $inSize * 100)) : 0;
            $this->info(sprintf('  ok     %-24s %8s -> %8s  (-%d%%)',
                basename($png),
                $this->fmt($inSize),
                $this->fmt($outSize),
                $pct
            ));
        }

        $this->newLine();
        $saved = $totalIn - $totalOut;
        $overallPct = $totalIn > 0 ? (int) round(100 - ($totalOut / $totalIn * 100)) : 0;
        $this->info(sprintf(
            'Done. %d converted, %d skipped. Total %s -> %s  (saved %s, -%d%%).',
            $converted, $skipped,
            $this->fmt($totalIn), $this->fmt($totalOut),
            $this->fmt($saved), $overallPct
        ));

        return 0;
    }

    private function convertWithImagick(string $src, string $dest, int $quality): bool
    {
        try {
            $img = new \Imagick($src);
            $img->setImageFormat('webp');
            $img->setImageCompressionQuality($quality);
            $img->setOption('webp:method', '6');
            $img->writeImage($dest);
            $img->clear();
            $img->destroy();
            return true;
        } catch (\Throwable $e) {
            $this->warn('Imagick error: ' . $e->getMessage());
            return false;
        }
    }

    private function convertWithGd(string $src, string $dest, int $quality): bool
    {
        $img = @imagecreatefrompng($src);
        if (!$img) return false;

        // Preserve transparency so logos with alpha stay clean
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $ok = @imagewebp($img, $dest, $quality);
        imagedestroy($img);
        return (bool) $ok;
    }

    private function fmt(int $bytes): string
    {
        if ($bytes < 1024)    return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024) . ' KB';
        return round($bytes / 1048576, 2) . ' MB';
    }
}
