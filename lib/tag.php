<?php

class rex_ymedia_tag extends rex_yform_manager_dataset
{
    protected static $tagUsageCache = [];
    protected static $popularTagsCache = null;

    /**
     * Get tag by name
     */
    public static function getByName(string $name): ?self
    {
        return self::query()->where('name', $name)->findOne();
    }

    /**
     * Find or create a tag
     */
    public static function findOrCreate(string $name): self
    {
        $tag = self::getByName($name);
        if (!$tag) {
            $tag = self::create();
            $tag->setValue('name', $name);
            $tag->save();
        }
        return $tag;
    }

    /**
     * Get media files using this tag
     * @return rex_ymedia[]
     */
    public function getMedia(): array
    {
        return rex_ymedia::query()
            ->where('FIND_IN_SET(:id, tags)', ['id' => $this->getId()])
            ->orderBy('title')
            ->find();
    }

    /**
     * Count media files using this tag
     */
    public function countMedia(): int
    {
        if (isset(self::$tagUsageCache[$this->getId()])) {
            return self::$tagUsageCache[$this->getId()];
        }

        $count = rex_ymedia::query()
            ->where('FIND_IN_SET(:id, tags)', ['id' => $this->getId()])
            ->count();

        self::$tagUsageCache[$this->getId()] = $count;
        return $count;
    }

    /**
     * Get tag cloud data
     * @return array Array of tags with usage counts
     */
    public static function getTagCloud(): array
    {
        if (self::$popularTagsCache !== null) {
            return self::$popularTagsCache;
        }

        $tags = [];
        foreach (self::query()->orderBy('name')->find() as $tag) {
            $count = $tag->countMedia();
            if ($count > 0) {
                $tags[] = [
                    'id' => $tag->getId(),
                    'name' => $tag->getName(),
                    'count' => $count
                ];
            }
        }

        // Calculate font sizes based on usage
        if (!empty($tags)) {
            $minCount = min(array_column($tags, 'count'));
            $maxCount = max(array_column($tags, 'count'));
            $minSize = 80;  // minimum font size %
            $maxSize = 180; // maximum font size %

            foreach ($tags as &$tag) {
                if ($maxCount > $minCount) {
                    $size = $minSize + ($tag['count'] - $minCount) * ($maxSize - $minSize) / ($maxCount - $minCount);
                } else {
                    $size = ($minSize + $maxSize) / 2;
                }
                $tag['size'] = round($size);
            }
        }

        self::$popularTagsCache = $tags;
        return $tags;
    }

    /**
     * Get popular tags
     * @return array Array of most used tags
     */
    public static function getPopularTags(int $limit = 10): array
    {
        $tags = self::getTagCloud();
        usort($tags, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        return array_slice($tags, 0, $limit);
    }

    /**
     * Get related tags
     * Returns tags that are often used together with this tag
     * @return array Array of related tags with correlation scores
     */
    public function getRelatedTags(): array
    {
        $relatedTags = [];
        $thisTagMedia = $this->getMedia();
        
        if (empty($thisTagMedia)) {
            return [];
        }

        $thisTagMediaIds = array_map(function($media) {
            return $media->getId();
        }, $thisTagMedia);

        // Get all tags used on the same media items
        $sql = rex_sql::factory();
        $query = '
            SELECT t.id, t.name, COUNT(*) as correlation
            FROM ' . rex::getTable('ymedia') . ' m
            JOIN ' . rex::getTable('ymedia_tag') . ' t
            WHERE FIND_IN_SET(t.id, m.tags)
            AND m.id IN (' . implode(',', $thisTagMediaIds) . ')
            AND t.id != :tagId
            GROUP BY t.id, t.name
            ORDER BY correlation DESC
        ';

        $relatedTags = $sql->getArray($query, ['tagId' => $this->getId()]);

        return array_map(function($tag) use ($thisTagMedia) {
            return [
                'id' => $tag['id'],
                'name' => $tag['name'],
                'correlation' => round(($tag['correlation'] / count($thisTagMedia)) * 100)
            ];
        }, $relatedTags);
    }

    /**
     * Suggest tags based on filename and existing content
     */
    public static function suggestTags(rex_ymedia $media): array
    {
        $suggestions = [];
        
        // Get filename-based suggestions
        $filename = $media->getFilename();
        $words = explode('_', preg_replace('/[^a-zA-Z0-9_]/', '_', $filename));
        foreach ($words as $word) {
            if (strlen($word) > 3) {
                $suggestions[] = strtolower($word);
            }
        }

        // Get suggestions from similar files
        $similarMedia = rex_ymedia::query()
            ->where('media LIKE :pattern', ['pattern' => '%' . pathinfo($filename, PATHINFO_EXTENSION)])
            ->find();

        foreach ($similarMedia as $similar) {
            $tags = $similar->getTags();
            foreach ($tags as $tag) {
                $suggestions[] = $tag->getName();
            }
        }

        // Count occurrences and sort by frequency
        $suggestions = array_count_values($suggestions);
        arsort($suggestions);

        // Return top 10 suggestions
        return array_slice(array_keys($suggestions), 0, 10);
    }

    /**
     * Override delete to clean up unused tags
     */
    public function delete(): bool
    {
        // Remove tag from all media files
        $sql = rex_sql::factory();
        $sql->setQuery('
            UPDATE ' . rex::getTable('ymedia') . ' 
            SET tags = TRIM(BOTH "," FROM REPLACE(CONCAT(",", tags, ","), ",' . $this->getId() . ',", ","))
            WHERE FIND_IN_SET(:id, tags)
        ', ['id' => $this->getId()]);

        return parent::delete();
    }

    /**
     * Clean up unused tags
     */
    public static function cleanup(): int
    {
        $count = 0;
        $tags = self::query()->find();
        
        foreach ($tags as $tag) {
            if ($tag->countMedia() === 0) {
                $tag->delete();
                $count++;
            }
        }

        // Clear caches
        self::$tagUsageCache = [];
        self::$popularTagsCache = null;

        return $count;
    }

    /**
     * Merge tags
     */
    public static function mergeTags(self $sourceTag, self $targetTag): bool
    {
        if ($sourceTag->getId() === $targetTag->getId()) {
            return false;
        }

        // Update all media files using the source tag
        $sql = rex_sql::factory();
        $sql->setQuery('
            UPDATE ' . rex::getTable('ymedia') . ' 
            SET tags = REPLACE(tags, :sourceId, :targetId)
            WHERE FIND_IN_SET(:sourceId, tags)
        ', [
            'sourceId' => $sourceTag->getId(),
            'targetId' => $targetTag->getId()
        ]);

        // Delete the source tag
        return $sourceTag->delete();
    }
}
