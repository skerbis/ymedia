<?php

rex_yform_manager_table::deleteCache();

// Install YForm tables from JSON definitions
$files = [
    'ymedia.json',
    'ymedia_tag.json',
    'ymedia_category.json'
];

foreach ($files as $file) {
    $content = rex_file::get(rex_path::addon('ymedia', 'install/tablesets/' . $file));
    if (is_string($content) && '' != $content) {
        rex_yform_manager_table_api::importTablesets($content);
    }
}

// Create additional tables for saved searches and user preferences
$sql = rex_sql::factory();

// Create saved searches table
$sql->setQuery('
    CREATE TABLE IF NOT EXISTS ' . rex::getTable('ymedia_saved_search') . ' (
        `id` int(10) unsigned NOT NULL auto_increment,
        `name` varchar(191) NOT NULL,
        `user_id` int(10) unsigned NOT NULL,
        `filter` text NOT NULL,
        `is_global` tinyint(1) NOT NULL DEFAULT 0,
        `createdate` datetime NOT NULL,
        `updatedate` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
');

// Create user preferences table
$sql->setQuery('
    CREATE TABLE IF NOT EXISTS ' . rex::getTable('ymedia_user_prefs') . ' (
        `id` int(10) unsigned NOT NULL auto_increment,
        `user_id` int(10) unsigned NOT NULL,
        `preferences` text NOT NULL,
        `createdate` datetime NOT NULL,
        `updatedate` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
');

// Add new columns to ymedia table if they don't exist
$sql->setQuery('
    ALTER TABLE ' . rex::getTable('ymedia') . '
    ADD COLUMN IF NOT EXISTS `preview_image` VARCHAR(191) NULL DEFAULT NULL AFTER `media`,
    ADD COLUMN IF NOT EXISTS `file_type` VARCHAR(50) NULL DEFAULT NULL AFTER `preview_image`,
    ADD COLUMN IF NOT EXISTS `file_size` INT UNSIGNED NULL DEFAULT NULL AFTER `file_type`,
    ADD COLUMN IF NOT EXISTS `mime_type` VARCHAR(100) NULL DEFAULT NULL AFTER `file_size`,
    ADD COLUMN IF NOT EXISTS `dimensions` VARCHAR(50) NULL DEFAULT NULL AFTER `mime_type`,
    ADD COLUMN IF NOT EXISTS `duration` INT UNSIGNED NULL DEFAULT NULL AFTER `dimensions`,
    ADD COLUMN IF NOT EXISTS `meta_data` TEXT NULL DEFAULT NULL AFTER `duration`,
    ADD COLUMN IF NOT EXISTS `usage_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `meta_data`;
');

// Create upload directory if it doesn't exist
$mediaPath = rex_path::frontend('ymedia/');
if (!is_dir($mediaPath)) {
    rex_dir::create($mediaPath);
}

// Add media manager type for previews if it doesn't exist
$sql->setQuery('SELECT * FROM ' . rex::getTable('media_manager_type') . ' WHERE name = "rex_ymedia_preview"');
if ($sql->getRows() == 0) {
    $sql->setTable(rex::getTable('media_manager_type'));
    $sql->setValue('name', 'rex_ymedia_preview');
    $sql->setValue('description', 'YMedia Preview Images');
    $sql->setValue('status', 1);
    $sql->insert();

    $typeId = $sql->getLastId();

    // Add effects
    $sql->setTable(rex::getTable('media_manager_type_effect'));

    // Resize effect
    $sql->setValue('type_id', $typeId);
    $sql->setValue('effect', 'resize');
    $sql->setValue('parameters', '{"rex_effect_resize":{"rex_effect_resize_width":"800","rex_effect_resize_height":"600","rex_effect_resize_style":"maximum","rex_effect_resize_allow_enlarge":"enlarge"}}');
    $sql->setValue('priority', 1);
    $sql->insert();

    // Quality effect
    $sql->setValue('type_id', $typeId);
    $sql->setValue('effect', 'quality');
    $sql->setValue('parameters', '{"rex_effect_quality":{"rex_effect_quality_quality":"85"}}');
    $sql->setValue('priority', 2);
    $sql->insert();
}

// Add image types for different views
$imageTypes = [
    'rex_ymedia_small' => ['width' => 150, 'height' => 150],
    'rex_ymedia_medium' => ['width' => 400, 'height' => 300],
    'rex_ymedia_large' => ['width' => 800, 'height' => 600]
];

foreach ($imageTypes as $name => $dimensions) {
    $sql->setQuery('SELECT * FROM ' . rex::getTable('media_manager_type') . ' WHERE name = ?', [$name]);
    if ($sql->getRows() == 0) {
        $sql->setTable(rex::getTable('media_manager_type'));
        $sql->setValue('name', $name);
        $sql->setValue('description', 'YMedia ' . ucfirst(str_replace('rex_ymedia_', '', $name)));
        $sql->setValue('status', 1);
        $sql->insert();

        $typeId = $sql->getLastId();

        // Add effects
        $sql->setTable(rex::getTable('media_manager_type_effect'));

        // Resize effect
        $sql->setValue('type_id', $typeId);
        $sql->setValue('effect', 'resize');
        $sql->setValue('parameters', json_encode([
            'rex_effect_resize' => [
                'rex_effect_resize_width' => (string)$dimensions['width'],
                'rex_effect_resize_height' => (string)$dimensions['height'],
                'rex_effect_resize_style' => 'maximum',
                'rex_effect_resize_allow_enlarge' => 'enlarge'
            ]
        ]));
        $sql->setValue('priority', 1);
        $sql->insert();

        // Quality effect
        $sql->setValue('type_id', $typeId);
        $sql->setValue('effect', 'quality');
        $sql->setValue('parameters', '{"rex_effect_quality":{"rex_effect_quality_quality":"85"}}');
        $sql->setValue('priority', 2);
        $sql->insert();
    }
}
