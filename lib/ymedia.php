<?php

class rex_ymedia extends rex_yform_manager_dataset
{
    /**
     * Get media by filename
     */
    public static function getByFilename(string $filename): ?self
    {
        $filenameNormalized = rex_string::normalize($filename, '_', '.-@');
        return self::query()->where('media', $filename)->findOne();
    }

    /**
     * Get frontend URL path
     */
    public static function getFrontendPath(string $file = ''): string
    {
        return rex_url::frontend('ymedia/' . $file);
    }

    /**
     * Get filesystem path
     */
    public static function getPath(string $file = ''): string
    {
        return rex_path::frontend('ymedia/' . $file);
    }

    /**
     * Get media title
     */
    public function getTitle(): string
    {
        return $this->getValue('title') ?? '';
    }

    /**
     * Get filename
     */
    public function getFilename(): string
    {
        return $this->getValue('media') ?? '';
    }

    /**
     * Get file size in bytes
     */
    public function getSize(): int
    {
        $file = $this->getFullPath();
        return file_exists($file) ? filesize($file) : 0;
    }

    /**
     * Get file MIME type
     */
    public function getMimeType(): string
    {
        $file = $this->getFullPath();
        return file_exists($file) ? mime_content_type($file) : '';
    }

    /**
     * Get file extension
     */
    public function getExtension(): string
    {
        return pathinfo($this->getFilename(), PATHINFO_EXTENSION);
    }

    /**
     * Get media type (image, document, video, etc.)
     */
    public function getMediaType(): string
    {
        $extension = strtolower($this->getExtension());
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        $documentTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        $videoTypes = ['mp4', 'mov', 'avi', 'wmv'];
        $audioTypes = ['mp3', 'wav', 'ogg', 'wma'];

        if (in_array($extension, $imageTypes)) {
            return 'image';
        }
        if (in_array($extension, $documentTypes)) {
            return 'document';
        }
        if (in_array($extension, $videoTypes)) {
            return 'video';
        }
        if (in_array($extension, $audioTypes)) {
            return 'audio';
        }
        return 'file';
    }

    /**
     * Get full filesystem path
     */
    public function getFullPath(): string
    {
        return self::getPath($this->getFilename());
    }

    /**
     * Get media manager URL
     */
    public function getMediaManagerImageUrl(string $type = 'rex_ymedia_preview'): string
    {
        if ($this->getMediaType() === 'image') {
            return rex_url::media($type . '/' . $this->getFilename());
        }
        return '';
    }

    /**
     * Get base media configuration
     */
    public static function getMediaConfiguration(): array
    {
        return [
            'path' => self::getPath()
        ];
    }

    /**
     * Check if media is in use
     */
    public static function mediaIsInUse(string $filename): bool|string
    {
        // Simple check for now - can be expanded later
        $sql = rex_sql::factory();
        
        // Check if file is used in articles
        $query = 'SELECT id FROM ' . rex::getTable('article_slice') . ' WHERE media LIKE :filename OR medialist LIKE :filename';
        $sql->setQuery($query, ['filename' => '%' . $filename . '%']);

        return $sql->getRows() > 0;
    }

    /**
     * Check permissions for current user
     */
    public function checkPerm(): bool
    {
        // Basic permission check - can be expanded later
        $user = rex::getUser();
        if (!$user) {
            return false;
        }
        
        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPerm('ymedia[]');
    }

    /**
     * Check permissions by filename
     */
    public static function checkPermByFilename(string $filename): bool
    {
        $ymedia = self::getByFilename($filename);
        return $ymedia ? $ymedia->checkPerm() : false;
    }
}
