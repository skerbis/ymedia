<?php

class rex_ymedia extends rex_yform_manager_dataset
{
    protected static $fileUsageCache = [];
    protected static $permissionCache = [];

    /**
     * Get media by filename
     * @return null|rex_ymedia
     */
    public static function getByFilename(string $filename)
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
     * Get media manager URL for preview
     */
    public function getMediaManagerImageUrl(string $type = 'rex_ymedia_preview'): string
    {
        return rex_url::media($type, $this->getFilename());
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrl(int $width = 100, int $height = 100): string
    {
        return rex_url::media("rex_ymedia_thumbnail_" . $width . "x" . $height, $this->getFilename());
    }

    /**
     * Get base media configuration
     */
    public static function getMediaConfiguration(): array
    {
        return [
            'path' => self::getPath(),
            'sizes' => [
                'min' => 0,
                'max' => 50 * 1024 * 1024 // 50MB
            ],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'],
            'disallowed_extensions' => ['php', 'php3', 'php4', 'php5', 'php6', 'php7', 'phar', 'pht', 'phtml', 'exe'],
            'check' => ['multiple_extensions', 'zip_archive'],
        ];
    }

    /**
     * Check if media is in use
     */
    public static function mediaIsInUse(string $filename): string|bool
    {
        if (isset(self::$fileUsageCache[$filename])) {
            return self::$fileUsageCache[$filename];
        }

        $sql = rex_sql::factory();
        $warning = [];

        // Check article slices
        $values = [];
        for ($i = 1; $i < 21; ++$i) {
            $values[] = 'value' . $i . ' REGEXP ' . $sql->escape('(^|[^[:alnum:]+_-])' . $filename);
        }

        $files = [];
        $filelists = [];
        $escapedFilename = $sql->escape($filename);
        for ($i = 1; $i < 11; ++$i) {
            $files[] = 'media' . $i . ' = ' . $escapedFilename;
            $filelists[] = 'FIND_IN_SET(' . $escapedFilename . ', medialist' . $i . ')';
        }

        $where = '';
        $where .= implode(' OR ', $files) . ' OR ';
        $where .= implode(' OR ', $filelists) . ' OR ';
        $where .= implode(' OR ', $values);
        
        $query = 'SELECT DISTINCT article_id, clang_id FROM ' . rex::getTablePrefix() . 'article_slice WHERE ' . $where;
        $res = $sql->getArray($query);
        
        if ($sql->getRows() > 0) {
            $warning[] = rex_i18n::msg('pool_file_in_use_articles') . '<ul>';
            foreach ($res as $artArr) {
                $aid = (int) $artArr['article_id'];
                $clang = (int) $artArr['clang_id'];
                $article = rex_article::get($aid, $clang);
                $name = $article ? $article->getName() : '';
                $warning[] = '<li><a href="' . rex_url::backendPage('content', [
                    'article_id' => $aid,
                    'mode' => 'edit',
                    'clang' => $clang
                ]) . '">' . rex_escape($name) . '</a></li>';
            }
            $warning[] = '</ul>';
        }

        // Check YForm tables
        $yformTables = rex_yform_manager_table::getAll();
        foreach ($yformTables as $table) {
            $mediaFields = array_filter($table->getFields(), function($field) {
                return $field->getType() === 'value' && $field->getTypeName() === 'media';
            });
            
            if (!empty($mediaFields)) {
                $query = 'SELECT id FROM ' . $table->getTableName() . ' WHERE ';
                $conditions = [];
                foreach ($mediaFields as $field) {
                    $conditions[] = $field->getName() . ' = ' . $sql->escape($filename);
                }
                $query .= implode(' OR ', $conditions);
                
                $res = $sql->getArray($query);
                if ($sql->getRows() > 0) {
                    $warning[] = rex_i18n::msg('pool_file_in_use_yform') . ' ' . $table->getName() . '<ul>';
                    foreach ($res as $row) {
                        $warning[] = '<li><a href="' . rex_url::backendPage('yform/manager/data_edit', [
                            'table_name' => $table->getTableName(),
                            'data_id' => $row['id'],
                            'func' => 'edit'
                        ]) . '">' . rex_i18n::msg('pool_file_in_use_yform_dataset') . ' #' . $row['id'] . '</a></li>';
                    }
                    $warning[] = '</ul>';
                }
            }
        }

        // Extension Point
        $warning = rex_extension::registerPoint(new rex_extension_point('MEDIA_IS_IN_USE', $warning, [
            'filename' => $filename
        ]));

        $result = !empty($warning) ? implode('', $warning) : false;
        self::$fileUsageCache[$filename] = $result;
        
        return $result;
    }

    /**
     * Check permissions for current user
     */
    public function checkPerm(): bool
    {
        $user = rex::getUser();
        if (!$user) {
            return false;
        }

        // Admin has all permissions
        if ($user->isAdmin()) {
            return true;
        }

        $cacheKey = $this->getId() . '_' . $user->getId();
        if (isset(self::$permissionCache[$cacheKey])) {
            return self::$permissionCache[$cacheKey];
        }

        // Check complex permissions
        $hasPermission = false;

        // Check categories
        $categories = $this->getCategories();
        if ($categories) {
            foreach ($categories as $category) {
                if ($user->hasPerm('ymedia[' . $category->getId() . ']')) {
                    $hasPermission = true;
                    break;
                }
            }
        }

        // Check media type permissions
        $mediaType = $this->getMediaType();
        if ($mediaType && $user->hasPerm('ymedia[type=' . $mediaType . ']')) {
            $hasPermission = true;
        }

        // Extension Point for custom permission checks
        $hasPermission = rex_extension::registerPoint(new rex_extension_point('YMEDIA_CHECK_PERM', $hasPermission, [
            'media' => $this,
            'user' => $user
        ]));

        self::$permissionCache[$cacheKey] = $hasPermission;
        return $hasPermission;
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
     * Get media categories
     * @return rex_ymedia_category[]
     */
    public function getCategories()
    {
        return $this->getRelatedCollection('categories');
    }

    /**
     * Get media tags
     * @return rex_ymedia_tag[]
     */
    public function getTags()
    {
        return $this->getRelatedCollection('tags');
    }

    /**
     * Get media type based on file extension
     */
    public function getMediaType(): string
    {
        $extension = strtolower(pathinfo($this->getFilename(), PATHINFO_EXTENSION));
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        $documentTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        
        if (in_array($extension, $imageTypes)) {
            return 'image';
        }
        if (in_array($extension, $documentTypes)) {
            return 'document';
        }
        return 'file';
    }

    /**
     * Save search/filter preferences for user
     */
    public function saveUserPreferences(int $userId, array $preferences): bool
    {
        $sql = rex_sql::factory();
        try {
            $sql->setTable(rex::getTable('ymedia_user_prefs'));
            $sql->setValue('user_id', $userId);
            $sql->setValue('preferences', json_encode($preferences));
            $sql->insertOrUpdate();
            return true;
        } catch (rex_sql_exception $e) {
            return false;
        }
    }

    /**
     * Get user preferences
     */
    public static function getUserPreferences(int $userId): array
    {
        $sql = rex_sql::factory();
        $prefs = $sql->getArray(
            'SELECT preferences FROM ' . rex::getTable('ymedia_user_prefs') . ' WHERE user_id = :id LIMIT 1',
            ['id' => $userId]
        );
        
        return $prefs ? json_decode($prefs[0]['preferences'], true) : [];
    }
}
