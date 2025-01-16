<?php

/**
 * YMedia Addon - Enhanced Media Management for REDAXO 5
 *
 * @author jan[dot]kristinus[at]redaxo[dot]de Jan Kristinus
 * @author yakamara.de
 */

$addon = rex_addon::get('ymedia');

// Register model classes
rex_yform_manager_dataset::setModelClass('rex_ymedia', rex_ymedia::class);
rex_yform_manager_dataset::setModelClass('rex_ymedia_tag', rex_ymedia_tag::class);
rex_yform_manager_dataset::setModelClass('rex_ymedia_category', rex_ymedia_category::class);

// Set custom table layout
rex_yform_manager_table::setTableLayout('rex_ymedia', 'ymedia/page/layout.php');

// Add template path for custom YForm templates
rex_extension::register('PACKAGES_INCLUDED', function (rex_extension_point $ep) {
    rex_yform::addTemplatePath($this->getPath('ytemplates'));
});

// Handle media permissions
rex_extension::register(['MEDIA_IS_PERMITTED'], static function (rex_extension_point $ep) {
    $ycom_ignore = $ep->getParam('ycom_ignore');
    $subject = $ep->getSubject();
    
    if ($ycom_ignore) {
        return $subject;
    }
    
    if (!$subject) {
        return false;
    }
    
    $rex_media = $ep->getParam('element');
    return rex_ymedia::checkPermByFilename($rex_media->getFileName());
});

// Handle media manager permissions and caching
rex_extension::register(['MEDIA_MANAGER_BEFORE_SEND'], static function (rex_extension_point $ep) {
    /** @var rex_media_manager $mm */
    $mm = $ep->getSubject();
    $originalMediaPath = dirname($mm->getMedia()->getSourcePath());
    
    if (trim(rex_ymedia::getPath(), '/') == trim($originalMediaPath, '/')) {
        /** @var rex_ymedia|null $YMedia */
        $YMedia = rex_ymedia::getByFilename($mm->getMedia()->getMediaFilename());
        if ($YMedia && $YMedia->checkPerm()) {
            $ep->setParam('ycom_ignore', true);
        }
    }
}, rex_extension::EARLY);

// Handle media cache management
rex_extension::register('MEDIA_UPDATED', static function(rex_extension_point $ep) {
    $filename = $ep->getParam('filename');
    if ($filename) {
        rex_media_manager::deleteCache($filename);
    }
});

rex_extension::register('MEDIA_DELETED', static function(rex_extension_point $ep) {
    $filename = $ep->getParam('filename');
    if ($filename) {
        rex_media_manager::deleteCache($filename);
    }
});

// Track media usage
rex_extension::register('MEDIA_IS_IN_USE', static function(rex_extension_point $ep) {
    $filename = $ep->getParam('filename');
    if ($filename) {
        return rex_ymedia::mediaIsInUse($filename);
    }
    return false;
});
