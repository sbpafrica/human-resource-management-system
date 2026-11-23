<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class EmployeeImageService
{
    /**
     * Resolves the local absolute path for an employee image.
     * If the file is not on the local disk (e.g. running on new VPS / server),
     * it automatically downloads it from the legacy portal (hub.sbpafrica.group)
     * and saves it to local public disk so future requests are instantaneous.
     */
    public static function getLocalPathOrFetch(?string $filename, ?string $type = null): ?string
    {
        if (empty($filename) || $filename === '0' || $filename === 'N/A' || trim($filename) === '') {
            return null;
        }

        $filename = trim($filename);

        // If filename is already a full URL
        if (preg_match('/^https?:\/\//i', $filename)) {
            $parsedPath = parse_url($filename, PHP_URL_PATH);
            $cleanName = basename($parsedPath);
            $localCandidate = public_path('images/melcomcon/' . $cleanName);
            if (file_exists($localCandidate) && filesize($localCandidate) > 0) {
                return $localCandidate;
            }
            return self::downloadAndSave($filename, $localCandidate);
        }

        // Check potential local paths
        $cleanBasename = basename($filename);
        $candidates = [
            public_path('images/melcomcon/' . $cleanBasename),
            public_path('images/signatures/' . $cleanBasename),
            public_path('images/' . $filename),
            public_path('images/' . $cleanBasename),
            public_path('storage/' . $filename),
            public_path($filename),
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand) && is_file($cand) && filesize($cand) > 0) {
                return $cand;
            }
        }

        // Determine local target destination to store cached image
        $targetDir = public_path('images/melcomcon');
        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true, true);
        }

        $targetPath = str_contains($filename, '/')
            ? public_path('images/' . ltrim($filename, '/'))
            : public_path('images/melcomcon/' . $cleanBasename);

        // URLs to probe on legacy hub
        $urlsToTry = [];
        if (str_contains($filename, '/')) {
            $trimmed = ltrim($filename, '/');
            $urlsToTry[] = 'https://hub.sbpafrica.group/' . $trimmed;
            $urlsToTry[] = 'https://hub.sbpafrica.group/melcomcon/' . $trimmed;
            $urlsToTry[] = 'http://hub.sbpafrica.group/' . $trimmed;
            $urlsToTry[] = 'http://hub.sbpafrica.group/melcomcon/' . $trimmed;
        } else {
            // Match old portal pattern (melcomcon/ is the primary folder used by raw PHP portal)
            $urlsToTry[] = 'https://hub.sbpafrica.group/melcomcon/' . $cleanBasename;
            $urlsToTry[] = 'http://hub.sbpafrica.group/melcomcon/' . $cleanBasename;
            $urlsToTry[] = 'https://hub.sbpafrica.group/' . $cleanBasename;
            $urlsToTry[] = 'https://hub.sbpafrica.group/signatures/' . $cleanBasename;
            $urlsToTry[] = 'https://hub.sbpafrica.group/images/' . $cleanBasename;
            $urlsToTry[] = 'https://hub.sbpafrica.group/images1/' . $cleanBasename;
            $urlsToTry[] = 'https://hub.sbpafrica.group/images2/' . $cleanBasename;
        }

        foreach ($urlsToTry as $url) {
            $saved = self::downloadAndSave($url, $targetPath);
            if ($saved) {
                return $saved;
            }
        }

        return null;
    }

    /**
     * Download from remote URL and save to target local path with generous timeout for large phone camera photos.
     */
    protected static function downloadAndSave(string $url, string $targetPath): ?string
    {
        try {
            $response = Http::timeout(25)
                ->connectTimeout(10)
                ->withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ])
                ->get($url);

            if ($response->successful() && strlen($response->body()) > 100) {
                $dir = dirname($targetPath);
                if (!File::isDirectory($dir)) {
                    File::makeDirectory($dir, 0755, true, true);
                }
                file_put_contents($targetPath, $response->body());
                return $targetPath;
            }
        } catch (\Throwable $e) {
            Log::debug("Download attempt failed for {$url}: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Convert local or fetched image to Base64 string for PDF rendering.
     */
    public static function getBase64Image(?string $filename, ?string $type = null): ?string
    {
        $path = self::getLocalPathOrFetch($filename, $type);
        if (!$path || !file_exists($path) || filesize($path) === 0) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        $data = file_get_contents($path);
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
