<?php

class rex_ymedia_category extends rex_yform_manager_dataset
{
    protected static $categoryPathCache = [];
    protected static $childrenCache = [];

    /**
     * Get category by name
     */
    public static function getByName(string $name): ?self
    {
        return self::query()->where('name', $name)->findOne();
    }

    /**
     * Get category path string
     */
    public function getPath(): string
    {
        if (isset(self::$categoryPathCache[$this->getId()])) {
            return self::$categoryPathCache[$this->getId()];
        }

        $path = [];
        $category = $this;
        while ($category) {
            $path[] = $category->getName();
            $category = $category->getParent();
        }

        $pathString = implode(' / ', array_reverse($path));
        self::$categoryPathCache[$this->getId()] = $pathString;
        
        return $pathString;
    }

    /**
     * Get parent category
     */
    public function getParent(): ?self
    {
        return $this->getValue('parent_id') ? self::get($this->getValue('parent_id')) : null;
    }

    /**
     * Get child categories
     * @return rex_ymedia_category[]
     */
    public function getChildren(): array
    {
        if (isset(self::$childrenCache[$this->getId()])) {
            return self::$childrenCache[$this->getId()];
        }

        $children = self::query()
            ->where('parent_id', $this->getId())
            ->orderBy('name')
            ->find();

        self::$childrenCache[$this->getId()] = $children;
        return $children;
    }

    /**
     * Get all descendants (children, grandchildren, etc.)
     * @return rex_ymedia_category[]
     */
    public function getDescendants(): array
    {
        $descendants = [];
        foreach ($this->getChildren() as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getDescendants());
        }
        return $descendants;
    }

    /**
     * Get root categories
     * @return rex_ymedia_category[]
     */
    public static function getRootCategories(): array
    {
        return self::query()
            ->where('parent_id', 0)
            ->orWhere('parent_id', null)
            ->orderBy('name')
            ->find();
    }

    /**
     * Get category tree as array
     */
    public static function getTree(): array
    {
        $tree = [];
        foreach (self::getRootCategories() as $rootCategory) {
            $tree[] = self::buildTreeArray($rootCategory);
        }
        return $tree;
    }

    /**
     * Build tree array for a category
     */
    protected static function buildTreeArray(self $category): array
    {
        $node = [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'path' => $category->getPath(),
            'children' => [],
        ];

        foreach ($category->getChildren() as $child) {
            $node['children'][] = self::buildTreeArray($child);
        }

        return $node;
    }

    /**
     * Get category tree as HTML list
     */
    public static function getTreeList(): string
    {
        $html = '<ul class="rex-ymedia-category-tree">';
        foreach (self::getRootCategories() as $rootCategory) {
            $html .= self::buildTreeHtml($rootCategory);
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Build tree HTML for a category
     */
    protected static function buildTreeHtml(self $category): string
    {
        $children = $category->getChildren();
        
        $html = '<li class="rex-ymedia-category" data-id="' . $category->getId() . '">';
        $html .= '<div class="rex-ymedia-category-name">';
        
        if ($children) {
            $html .= '<span class="rex-ymedia-category-toggle"></span>';
        }
        
        $html .= '<a href="' . rex_url::backendPage('ymedia/pool', [
            'func' => 'filter',
            'category_id' => $category->getId()
        ]) . '">' . rex_escape($category->getName()) . '</a>';
        $html .= '</div>';

        if ($children) {
            $html .= '<ul class="rex-ymedia-category-children">';
            foreach ($children as $child) {
                $html .= self::buildTreeHtml($child);
            }
            $html .= '</ul>';
        }
        
        $html .= '</li>';
        return $html;
    }

    /**
     * Check if category is ancestor of another category
     */
    public function isAncestorOf(self $category): bool
    {
        while ($category = $category->getParent()) {
            if ($category->getId() == $this->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get media files in this category
     * @return rex_ymedia[]
     */
    public function getMedia(): array
    {
        return rex_ymedia::query()
            ->where('FIND_IN_SET(:id, categories)', ['id' => $this->getId()])
            ->orderBy('title')
            ->find();
    }

    /**
     * Count media files in this category
     */
    public function countMedia(): int
    {
        return rex_ymedia::query()
            ->where('FIND_IN_SET(:id, categories)', ['id' => $this->getId()])
            ->count();
    }

    /**
     * Get media count including subcategories
     */
    public function countMediaRecursive(): int
    {
        $count = $this->countMedia();
        foreach ($this->getChildren() as $child) {
            $count += $child->countMediaRecursive();
        }
        return $count;
    }

    /**
     * Check if user has permission for this category
     */
    public function checkPerm(?rex_user $user = null): bool
    {
        if (!$user) {
            $user = rex::getUser();
        }
        
        if (!$user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPerm('ymedia[' . $this->getId() . ']');
    }

    /**
     * Override delete to handle children
     */
    public function delete(): bool
    {
        // First delete all children
        foreach ($this->getChildren() as $child) {
            if (!$child->delete()) {
                return false;
            }
        }

        // Remove category from all media files
        $sql = rex_sql::factory();
        $sql->setQuery('
            UPDATE ' . rex::getTable('ymedia') . ' 
            SET categories = TRIM(BOTH "," FROM REPLACE(CONCAT(",", categories, ","), ",' . $this->getId() . ',", ","))
            WHERE FIND_IN_SET(:id, categories)
        ', ['id' => $this->getId()]);

        return parent::delete();
    }
}
