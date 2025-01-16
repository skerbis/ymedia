<?php

/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */

/** @var rex_ymedia $ymedia */
$ymedia = $this->getVar('ymedia');
$editLink = $this->getVar('editLink');
$actionButtonViews = $this->getVar('actionButtonViews');
$viewMode = $this->getVar('viewMode', 'grid');

$editLink = str_replace('___id___', $ymedia->getId(), $editLink);
$actionButtonViews = str_replace('___id___', $ymedia->getId(), $actionButtonViews);

$fragment = new rex_fragment();
$fragment->setVar('buttons', $actionButtonViews, false);
$buttons = $fragment->parse('yform/manager/action_buttons.php');

// Get additional media information
$filesize = rex_formatter::bytes($ymedia->getSize());
$createDate = rex_formatter::strftime($ymedia->getValue('create_datetime'), 'datetime');
$updateDate = rex_formatter::strftime($ymedia->getValue('update_datetime'), 'datetime');
$createUser = $ymedia->getValue('create_user');
$updateUser = $ymedia->getValue('update_user');

// Get media type and appropriate icon/preview
$mediaType = $ymedia->getMediaType();
$preview = '';
$icon = '';

switch ($mediaType) {
    case 'image':
        $preview = '<img src="' . $ymedia->getMediaManagerImageUrl('rex_media_small') . '" 
                        class="img-fluid" 
                        alt="' . rex_escape($ymedia->getTitle()) . '"
                        loading="lazy" />';
        $icon = '<i class="fa fa-image"></i>';
        break;
    case 'video':
        $icon = '<i class="fa fa-film"></i>';
        break;
    case 'audio':
        $icon = '<i class="fa fa-music"></i>';
        break;
    case 'document':
        $icon = '<i class="fa fa-file-text"></i>';
        break;
    case 'archive':
        $icon = '<i class="fa fa-file-archive"></i>';
        break;
    default:
        $icon = '<i class="fa fa-file"></i>';
}

// Get usage status
$isInUse = $ymedia->mediaIsInUse($ymedia->getFilename());
$usageClass = $isInUse ? 'in-use' : '';
$usageIcon = $isInUse ? '<i class="fa fa-link" title="' . rex_i18n::msg('ymedia_file_in_use') . '"></i>' : '';

// Build tag list
$tags = [];
foreach ($ymedia->getTags() as $tag) {
    $tags[] = '<span class="badge badge-secondary">' . rex_escape($tag->getName()) . '</span>';
}
$tagList = !empty($tags) ? implode(' ', $tags) : '';

// Build category list
$categories = [];
foreach ($ymedia->getCategories() as $category) {
    $categories[] = '<span class="badge badge-info">' . rex_escape($category->getName()) . '</span>';
}
$categoryList = !empty($categories) ? implode(' ', $categories) : '';

switch ($viewMode) {
    case 'list':
        // List view with compact information
        echo '<div class="ymedia-item list-view ' . $usageClass . '" data-id="' . $ymedia->getId() . '">
            <div class="row align-items-center">
                <div class="col-auto">
                    ' . ($preview ?: $icon) . '
                </div>
                <div class="col">
                    <h5><a href="' . $editLink . '">' . rex_escape($ymedia->getTitle()) . '</a></h5>
                    <div class="ymedia-meta">
                        <small>' . $filesize . ' | ' . $createDate . ' ' . $usageIcon . '</small>
                    </div>
                    <div class="ymedia-tags">' . $tagList . '</div>
                </div>
                <div class="col-auto">
                    ' . $buttons . '
                </div>
            </div>
        </div>';
        break;

    case 'detail':
        // Detailed view with all information
        echo '<div class="ymedia-item detail-view ' . $usageClass . '" data-id="' . $ymedia->getId() . '">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">' . rex_escape($ymedia->getTitle()) . '</h5>
                    ' . $buttons . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="ymedia-preview">
                                ' . ($preview ?: $icon) . '
                            </div>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th>' . rex_i18n::msg('ymedia_filename') . '</th>
                                    <td>' . rex_escape($ymedia->getFilename()) . '</td>
                                </tr>
                                <tr>
                                    <th>' . rex_i18n::msg('ymedia_filesize') . '</th>
                                    <td>' . $filesize . '</td>
                                </tr>
                                <tr>
                                    <th>' . rex_i18n::msg('ymedia_created') . '</th>
                                    <td>' . $createDate . ' ' . rex_escape($createUser) . '</td>
                                </tr>
                                <tr>
                                    <th>' . rex_i18n::msg('ymedia_updated') . '</th>
                                    <td>' . $updateDate . ' ' . rex_escape($updateUser) . '</td>
                                </tr>
                            </table>
                            ' . $categoryList . '
                            <div class="mt-2">' . $tagList . '</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        break;

    default:
        // Grid view (default)
        echo '<div class="ymedia-item grid-view ' . $usageClass . '" data-id="' . $ymedia->getId() . '">
            <div class="card h-100">
                <div class="card-preview">
                    <a href="' . $editLink . '">
                        ' . ($preview ?: $icon) . '
                    </a>
                </div>
                <div class="card-body p-2">
                    <h6 class="card-title mb-1">
                        <a href="' . $editLink . '">' . rex_escape($ymedia->getTitle()) . '</a>
                    </h6>
                    <div class="ymedia-meta">
                        <small>' . $filesize . ' ' . $usageIcon . '</small>
                    </div>
                    <div class="ymedia-tags">' . $tagList . '</div>
                </div>
                <div class="card-footer p-2">
                    ' . $buttons . '
                </div>
            </div>
        </div>';
}

?>

<style>
/* Base styles */
.ymedia-item {
    position: relative;
}

.ymedia-item.in-use {
    position: relative;
}

.ymedia-item.in-use::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border: 2px solid #28a745;
    pointer-events: none;
    border-radius: 4px;
}

/* Grid view specific */
.grid-view .card-preview {
    height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #f8f9fa;
}

.grid-view .card-preview img {
    max-height: 100%;
    width: auto;
    object-fit: contain;
}

.grid-view .card-preview i {
    font-size: 3em;
    color: #dee2e6;
}

/* List view specific */
.list-view {
    padding: 10px;
    border: 1px solid #dee2e6;
    margin-bottom: 10px;
    border-radius: 4px;
}

.list-view img {
    max-height: 50px;
    width: auto;
}

/* Detail view specific */
.detail-view .ymedia-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    background: #f8f9fa;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.detail-view .ymedia-preview img {
    max-height: 300px;
    width: auto;
    object-fit: contain;
}

/* Common elements */
.ymedia-tags {
    margin-top: 0.5rem;
}

.ymedia-tags .badge {
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
}

.ymedia-meta {
    color: #6c757d;
}

</style>
