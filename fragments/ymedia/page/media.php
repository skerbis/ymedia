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

// Check usage status
$isInUse = $ymedia->mediaIsInUse($ymedia->getFilename());
$statusClass = $isInUse ? 'is-in-use' : '';
$statusIcon = $isInUse ? '<i class="fa fa-link" title="' . rex_i18n::msg('ymedia_in_use') . '"></i>' : '';

echo '
<div class="ymedia-item '.$statusClass.'" data-id="' . $ymedia->getId() . '">
    <div class="card h-100">
        <div class="card-preview">
            <a href="' . $editLink . '">
                <img src="' . $ymedia->getMediaManagerImageUrl() . '" alt="' . rex_escape($ymedia->getTitle()) . '" class="img-fluid" loading="lazy" />
            </a>
        </div>
        <div class="card-body p-2">
            <h6 class="card-title mb-1">
                <a href="' . $editLink . '">' . rex_escape($ymedia->getTitle()) . '</a>
            </h6>
            <div class="ymedia-meta">
                <small>' . $filesize . ' ' . $statusIcon . '</small>
            </div>
        </div>
        <div class="card-footer p-2">
            ' . $buttons . '
        </div>
    </div>
</div>';
?>

<style>
.ymedia-item {
    transition: all 0.3s ease;
}

.ymedia-item .card {
    border: 1px solid #dee2e6;
}

.ymedia-item.is-in-use .card {
    border-color: #28a745;
}

.card-preview {
    height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #f8f9fa;
}

.card-preview img {
    max-height: 100%;
    width: auto;
    object-fit: contain;
}

.ymedia-meta {
    color: #6c757d;
}

.ymedia-meta i {
    color: #28a745;
    margin-left: 0.5rem;
}
</style>
