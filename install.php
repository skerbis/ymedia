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

// Create upload directory if it doesn't exist
$mediaPath = rex_path::frontend('ymedia/');
if (!is_dir($mediaPath)) {
    rex_dir::create($mediaPath);
}

// Add media manager types and effects
$sql = rex_sql::factory();

// Add media manager type for rex_ymedia_preview
$sql->setQuery('SELECT id FROM ' . rex::getTable('media_manager_type') . ' WHERE name = "rex_ymedia_preview"');
if ($sql->getRows() == 0) {
    $sql->setQuery('INSERT INTO ' . rex::getTable('media_manager_type') . ' SET name = ?, description = ?, status = 1', 
        ['rex_ymedia_preview', 'YMedia Preview']);
    $typeId = $sql->getLastId();

    // Add resize effect
    $sql->setQuery('
        INSERT INTO ' . rex::getTable('media_manager_type_effect') . '
        SET type_id = ?, effect = ?, parameters = ?, priority = ?',
        [
            $typeId,
            'resize',
            json_encode(['rex_effect_resize' => [
                'rex_effect_resize_width' => '800',
                'rex_effect_resize_height' => '600',
                'rex_effect_resize_style' => 'maximum',
                'rex_effect_resize_allow_enlarge' => 'enlarge'
            ]]),
            1
        ]
    );
}
