<?php
/**
 * CARI-IPTV Image Service
 * Handles image upload, compression, and WebP conversion
 */

namespace CariIPTV\Services;

class ImageService
{
    // Default image sizes for different contexts
    public const SIZES = [
        'channel' => [
            'thumb' => ['width' => 64, 'height' => 64],
            'medium' => ['width' => 200, 'height' => 200],
            'large' => ['width' => 400, 'height' => 400],
            'landscape' => ['width' => 500, 'height' => 296],
        ],
        'vod' => [
            'thumb' => ['width' => 150, 'height' => 225],
            'poster' => ['width' => 342, 'height' => 513],
            'backdrop' => ['width' => 780, 'height' => 439],
        ],
        'avatar' => [
            'thumb' => ['width' => 64, 'height' => 64],
            'medium' => ['width' => 200, 'height' => 200],
        ],
        'logo' => [
            'small' => ['width' => 120, 'height' => 60],
            'medium' => ['width' => 200, 'height' => 100],
        ],
        'layout' => [
            'thumb' => ['width' => 150, 'height' => 225],
            'banner' => ['width' => 1280, 'height' => 720],
            'poster' => ['width' => 342, 'height' => 513],
        ],
    ];

    private int $quality = 85;
    private bool $keepOriginal = true;

    /**
     * Process uploaded image - creates multiple sizes and converts to WebP
     */
    public function processUpload(
        array $uploadedFile,
        string $context,
        string|int $entityId,
        string $type = 'logo'
    ): array {
        // Validate upload
        $validation = $this->validateUpload($uploadedFile);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        // Create base directory
        $baseDir = BASE_PATH . "/public/uploads/{$context}/{$entityId}";
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }

        // Save original if configured
        $originalPath = null;
        if ($this->keepOriginal) {
            $originalDir = $baseDir . '/original';
            if (!is_dir($originalDir)) {
                mkdir($originalDir, 0775, true);
            }
            $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
            $originalPath = $originalDir . '/' . $type . '.' . $extension;

            // Use move_uploaded_file for actual uploads, copy for downloaded files
            if (is_uploaded_file($uploadedFile['tmp_name'])) {
                move_uploaded_file($uploadedFile['tmp_name'], $originalPath);
            } else {
                copy($uploadedFile['tmp_name'], $originalPath);
            }
        } else {
            $originalPath = $uploadedFile['tmp_name'];
        }

        // Get sizes for this context
        $sizes = self::SIZES[$context] ?? self::SIZES['channel'];

        // Generate variants
        $variants = [];
        foreach ($sizes as $sizeName => $dimensions) {
            $outputPath = $baseDir . '/' . $type . '_' . $sizeName . '.webp';
            $result = $this->createVariant(
                $originalPath,
                $outputPath,
                $dimensions['width'],
                $dimensions['height']
            );

            if ($result) {
                $variants[$sizeName] = '/uploads/' . $context . '/' . $entityId . '/' . $type . '_' . $sizeName . '.webp';
            }
        }

        // Clean up temp file if we didn't keep original
        if (!$this->keepOriginal && file_exists($uploadedFile['tmp_name'])) {
            unlink($uploadedFile['tmp_name']);
        }

        return [
            'success' => true,
            'base_path' => '/uploads/' . $context . '/' . $entityId . '/' . $type,
            'variants' => $variants,
            'original' => $this->keepOriginal ? '/uploads/' . $context . '/' . $entityId . '/original/' . $type . '.' . pathinfo($uploadedFile['name'], PATHINFO_EXTENSION) : null,
        ];
    }

    /**
     * Process image from URL - download and create variants
     */
    public function processFromUrl(
        string $url,
        string $context,
        string|int|null $entityId = null,
        string $type = 'logo'
    ): array {
        // Generate a unique ID if none provided (for new entities)
        if ($entityId === null) {
            $entityId = time() . '_' . bin2hex(random_bytes(4));
        }
        // Download image to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'img_');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($imageData)) {
            return ['success' => false, 'error' => 'Failed to download image'];
        }

        file_put_contents($tempFile, $imageData);

        // Determine extension from content type
        $extension = match (true) {
            str_contains($contentType, 'jpeg') => 'jpg',
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'gif') => 'gif',
            str_contains($contentType, 'webp') => 'webp',
            default => 'jpg',
        };

        // Create fake upload array
        $uploadedFile = [
            'tmp_name' => $tempFile,
            'name' => 'downloaded.' . $extension,
            'type' => $contentType,
            'size' => strlen($imageData),
            'error' => 0,
        ];

        // Process the downloaded image
        $result = $this->processUpload($uploadedFile, $context, $entityId, $type);

        // Clean up temp file
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        return $result;
    }

    /**
     * Create a single image variant
     */
    public function createVariant(
        string $sourcePath,
        string $outputPath,
        int $targetWidth,
        int $targetHeight,
        bool $crop = true
    ): bool {
        if (!file_exists($sourcePath)) {
            return false;
        }

        // Get image info
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }

        [$sourceWidth, $sourceHeight, $imageType] = $imageInfo;

        // Create source image resource
        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            default => null,
        };

        if (!$sourceImage) {
            return false;
        }

        // Calculate dimensions
        if ($crop) {
            // Crop to fit (cover)
            $sourceRatio = $sourceWidth / $sourceHeight;
            $targetRatio = $targetWidth / $targetHeight;

            if ($sourceRatio > $targetRatio) {
                // Source is wider - crop width
                $resizeHeight = $targetHeight;
                $resizeWidth = (int) ($sourceWidth * ($targetHeight / $sourceHeight));
                $cropX = (int) (($resizeWidth - $targetWidth) / 2);
                $cropY = 0;
            } else {
                // Source is taller - crop height
                $resizeWidth = $targetWidth;
                $resizeHeight = (int) ($sourceHeight * ($targetWidth / $sourceWidth));
                $cropX = 0;
                $cropY = (int) (($resizeHeight - $targetHeight) / 2);
            }
        } else {
            // Fit within dimensions (contain)
            $sourceRatio = $sourceWidth / $sourceHeight;
            $targetRatio = $targetWidth / $targetHeight;

            if ($sourceRatio > $targetRatio) {
                $resizeWidth = $targetWidth;
                $resizeHeight = (int) ($targetWidth / $sourceRatio);
            } else {
                $resizeHeight = $targetHeight;
                $resizeWidth = (int) ($targetHeight * $sourceRatio);
            }
            $cropX = 0;
            $cropY = 0;
            $targetWidth = $resizeWidth;
            $targetHeight = $resizeHeight;
        }

        // Create destination image
        $destImage = imagecreatetruecolor($targetWidth, $targetHeight);

        // Preserve transparency for PNG
        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
        $transparent = imagecolorallocatealpha($destImage, 0, 0, 0, 127);
        imagefill($destImage, 0, 0, $transparent);

        // Resize
        if ($crop) {
            // First resize to intermediate size
            $tempImage = imagecreatetruecolor($resizeWidth, $resizeHeight);
            imagealphablending($tempImage, false);
            imagesavealpha($tempImage, true);
            imagefill($tempImage, 0, 0, $transparent);

            imagecopyresampled(
                $tempImage, $sourceImage,
                0, 0, 0, 0,
                $resizeWidth, $resizeHeight,
                $sourceWidth, $sourceHeight
            );

            // Then crop to target size
            imagecopy(
                $destImage, $tempImage,
                0, 0,
                $cropX, $cropY,
                $targetWidth, $targetHeight
            );

            imagedestroy($tempImage);
        } else {
            imagecopyresampled(
                $destImage, $sourceImage,
                0, 0, 0, 0,
                $targetWidth, $targetHeight,
                $sourceWidth, $sourceHeight
            );
        }

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        // Save as WebP
        $result = imagewebp($destImage, $outputPath, $this->quality);

        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($destImage);

        return $result;
    }

    /**
     * Validate uploaded file
     */
    public function validateUpload(array $file): array
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum size',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            ];
            return [
                'valid' => false,
                'error' => $errors[$file['error']] ?? 'Upload error',
            ];
        }

        // Check file size (max 10MB)
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File exceeds maximum size of 10MB'];
        }

        // Validate MIME type
        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP'];
        }

        // Validate image dimensions
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            return ['valid' => false, 'error' => 'Invalid image file'];
        }

        return ['valid' => true];
    }

    /**
     * Delete all variants of an image
     */
    public function deleteImage(string $context, int $entityId, string $type): bool
    {
        $baseDir = BASE_PATH . "/public/uploads/{$context}/{$entityId}";

        if (!is_dir($baseDir)) {
            return true;
        }

        // Delete all variants
        $files = glob($baseDir . '/' . $type . '_*.webp');
        foreach ($files as $file) {
            unlink($file);
        }

        // Delete original if exists
        $originalDir = $baseDir . '/original';
        if (is_dir($originalDir)) {
            $originalFiles = glob($originalDir . '/' . $type . '.*');
            foreach ($originalFiles as $file) {
                unlink($file);
            }
        }

        // Remove directories if empty
        if (is_dir($originalDir) && count(glob($originalDir . '/*')) === 0) {
            rmdir($originalDir);
        }
        if (is_dir($baseDir) && count(glob($baseDir . '/*')) === 0) {
            rmdir($baseDir);
        }

        return true;
    }

    /**
     * Regenerate all variants from original
     */
    public function regenerateVariants(string $context, int $entityId, string $type): array
    {
        $baseDir = BASE_PATH . "/public/uploads/{$context}/{$entityId}";
        $originalDir = $baseDir . '/original';

        // Find original file
        $originalFiles = glob($originalDir . '/' . $type . '.*');
        if (empty($originalFiles)) {
            return ['success' => false, 'error' => 'Original file not found'];
        }

        $originalPath = $originalFiles[0];

        // Get sizes for this context
        $sizes = self::SIZES[$context] ?? self::SIZES['channel'];

        // Generate variants
        $variants = [];
        foreach ($sizes as $sizeName => $dimensions) {
            $outputPath = $baseDir . '/' . $type . '_' . $sizeName . '.webp';
            $result = $this->createVariant(
                $originalPath,
                $outputPath,
                $dimensions['width'],
                $dimensions['height']
            );

            if ($result) {
                $variants[$sizeName] = '/uploads/' . $context . '/' . $entityId . '/' . $type . '_' . $sizeName . '.webp';
            }
        }

        return [
            'success' => true,
            'variants' => $variants,
        ];
    }

    /**
     * Get image URL for specific size
     */
    public static function getImageUrl(string $basePath, string $size = 'medium'): string
    {
        if (empty($basePath)) {
            return '';
        }

        // If basePath already includes size suffix, return as-is
        if (str_contains($basePath, '_' . $size . '.webp') || str_contains($basePath, '.webp')) {
            return $basePath;
        }

        return $basePath . '_' . $size . '.webp';
    }

    /**
     * Check if WebP is supported by GD
     */
    public static function isWebPSupported(): bool
    {
        if (!function_exists('imagecreatefromwebp')) {
            return false;
        }

        $gdInfo = gd_info();
        return !empty($gdInfo['WebP Support']);
    }

    /**
     * Set quality for WebP output
     */
    public function setQuality(int $quality): void
    {
        $this->quality = max(1, min(100, $quality));
    }

    /**
     * Set whether to keep original files
     */
    public function setKeepOriginal(bool $keep): void
    {
        $this->keepOriginal = $keep;
    }
}
