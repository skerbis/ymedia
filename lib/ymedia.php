<?php

class rex_ymedia extends rex_yform_manager_dataset
{
    protected static $fileCache = [];
    
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
     * Get full filesystem path
     */
    public function getFullPath(): string
    {
        return self::getPath($this->getFilename());
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
        return strtolower(pathinfo($this->getFilename(), PATHINFO_EXTENSION));
    }

    /**
     * Get image dimensions if file is an image
     */
    public function getDimensions(): ?array
    {
        if ($this->isImage()) {
            $file = $this->getFullPath();
            if (file_exists($file) && $info = getimagesize($file)) {
                return [
                    'width' => $info[0],
                    'height' => $info[1],
                    'ratio' => $info[0] / $info[1],
                ];
            }
        }
        return null;
    }

    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        return in_array($this->getExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
    }

    /**
     * Get file type (image, document, video, etc.)
     */
    public function getType(): string
    {
        $extension = $this->getExtension();
        
        $types = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'txt', 'csv'],
            'video' => ['mp4', 'avi', 'mov', 'wmv', 'webm'],
            'audio' => ['mp3', 'wav', 'ogg', 'wma', 'm4a'],
            'archive' => ['zip', 'rar', 'gz', '7z', 'tar'],
        ];
        
        foreach ($types as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                return $type;
            }
        }
        
        return 'file';
    }

    /**
     * Get media manager URL for image
     */
    public function getMediaManagerImageUrl(string $type = 'rex_ymedia_preview'): string
    {
        if ($this->isImage()) {
            return rex_url::backendPage('media_manager', [
                'rex_media_type' => $type,
                'rex_media_file' => $this->getFilename()
            ]);
        }
        return '';
    }

    /**
     * Get media configuration
     */
    public static function getMediaConfiguration(): array
    {
        return [
            'path' => self::getPath(),
            'allowed_extensions' => [
                'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp',  // Images
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv',  // Documents
                'mp4', 'mp3', 'wav', 'ogg',                  // Media
                'zip', 'rar'                                 // Archives
            ],
            'denied_extensions' => [
                'php', 'php3', 'php4', 'php5', 'php7', 'phar', 'phtml',
                'exe', 'sh', 'cgi', 'pl', 'jsp', 'asp', 'aspx',
                'htaccess', 'htpasswd'
            ],
            'max_size' => 50 * 1024 * 1024, // 50 MB
        ];
    }

    /**
     * Check if media is in use
     */
    public static function mediaIsInUse(string $filename): bool
    {
        $sql = rex_sql::factory();
        $where = [];
        
        // Check media1-10 fields
        for ($i = 1; $i <= 10; $i++) {
            $where[] = 'media' . $i . ' = :filename';
            $where[] = 'medialist' . $i . ' LIKE :like_filename';
        }

        // Check value1-20 fields for embedded media
        for ($i = 1; $i <= 20; $i++) {
            $where[] = 'value' . $i . ' LIKE :like_filename';
        }

        try {
            // Check article slices
            $query = 'SELECT id FROM ' . rex::getTable('article_slice') . ' 
                     WHERE ' . implode(' OR ', $where);
            
            $sql->setQuery($query, [
                'filename' => $filename,
                'like_filename' => '%' . $filename . '%'
            ]);

            if ($sql->getRows() > 0) {
                return true;
            }

            // Check metadata and language tables
            $metaTables = [
                rex::getTable('article'),
                rex::getTable('media'),
                rex::getTable('clang')
            ];

            foreach ($metaTables as $table) {
                if (rex_sql_table::get($table)->exists()) {
                    $sql->setQuery(
                        'SELECT id FROM ' . $table . ' WHERE media LIKE :filename',
                        ['filename' => '%' . $filename . '%']
                    );
                    if ($sql->getRows() > 0) {
                        return true;
                    }
                }
            }

            return false;
        } catch (rex_sql_exception $e) {
            return false;
        }
    }

    /**
     * Get categories
     */
    public function getCategories(): array
    {
        $categories = [];
        if ($this->getValue('categories')) {
            $categoryIds = explode(',', $this->getValue('categories'));
            foreach ($categoryIds as $id) {
                if ($category = rex_ymedia_category::get($id)) {
                    $categories[] = $category;
                }
            }
        }
        return $categories;
    }

    /**
     * Get tags
     */
    public function getTags(): array
    {
        $tags = [];
        if ($this->getValue('tags')) {
            $tagIds = explode(',', $this->getValue('tags'));
            foreach ($tagIds as $id) {
                if ($tag = rex_ymedia_tag::get($id)) {
                    $tags[] = $tag;
                }
            }
        }
        return $tags;
    }

    /**
     * Check permissions
     */
    public function checkPerm(): bool
    {
        $user = rex::getUser();
        if (!$user) {
            return false;
        }

        // Admins always have permission
        if ($user->isAdmin()) {
            return true;
        }

        // Check category permissions
        $categories = $this->getCategories();
        foreach ($categories as $category) {
            if ($user->hasPerm('ymedia[' . $category->getId() . ']')) {
                return true;
            }
        }

        // Check general ymedia permission
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

    /**
     * Delete media file
     */
    public function delete(): bool
    {
        // Delete physical file
        $file = $this->getFullPath();
        if (file_exists($file)) {
            unlink($file);
        }

        // Delete media manager cache
        rex_media_manager::deleteCache($this->getFilename());

        // Delete dataset
        return parent::delete();
    }

    /**
     * Override save to update metadata
     */
    public function save(): bool
    {
        // Update timestamps
        $now = date('Y-m-d H:i:s');
        if (!$this->getValue('create_datetime')) {
            $this->setValue('create_datetime', $now);
            if ($user = rex::getUser()) {
                $this->setValue('create_user', $user->getLogin());
            }
        }
        $this->setValue('update_datetime', $now);
        if ($user = rex::getUser()) {
            $this->setValue('update_user', $user->getLogin());
        }

        // Update file metadata if it exists
        $file = $this->getFullPath();
        if (file_exists($file)) {
            $this->setValue('file_size', filesize($file));
            $this->setValue('mime_type', mime_content_type($file));
            
            if ($this->isImage()) {
                if ($dimensions = $this->getDimensions()) {
                    $this->setValue('dimensions', $dimensions['width'] . 'x' . $dimensions['height']);
                }
            }
        }

        return parent::save();
    }
}
