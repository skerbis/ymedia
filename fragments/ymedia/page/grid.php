<?php

/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */

/** @var rex_yform_manager_query $query */
$query = $this->getVar('query');
/** @var rex_yform_manager_table $table */
$table = $this->getVar('table');
/** @var array $actionButtons */
$actionButtons = $this->getVar('actionButtons');
$rex_link_vars = $this->getVar('rex_link_vars');
$rex_yform_manager_opener = $this->getVar('rex_yform_manager_opener');
$rex_yform_manager_popup = $this->getVar('rex_yform_manager_popup');
$popup = $this->getVar('popup');
/** @var callable $hasDataPageFunctions */
$hasDataPageFunctions = $this->getVar('hasDataPageFunctions');

// Get user preferences for view mode
$user = rex::getUser();
$viewMode = 'grid'; // Default to grid view

// Prepare action buttons
$actionButtonsViews = [];
$editLink = '';
foreach ($actionButtons as $buttonKey => $buttonParams) {
    $a = [];
    $a['href'] = rex_url::backendController(array_merge($rex_link_vars, $buttonParams['params']), false);
    if ('view' == $buttonKey || 'edit' == $buttonKey) {
        $editLink = $a['href'];
    }
    $a = array_merge($a, $buttonParams['attributes'] ?? []);
    $actionButtonsViews[$buttonKey] = '<a '.rex_string::buildAttributes($a).'>'.$buttonParams['content'].'</a>';
}

// Prepare items
$items = [];
foreach ($query->find() as $ymedia) {
    $fragment = new rex_fragment();
    $fragment->setVar('ymedia', $ymedia, false);
    $fragment->setVar('editLink', $editLink);
    $fragment->setVar('actionButtonViews', $actionButtonsViews, false);
    $fragment->setVar('viewMode', $viewMode);
    $items[] = $fragment->parse('ymedia/page/media.php');
}

// Add new item button
$addButton = '';
if ($hasDataPageFunctions('add') && $table->isGranted('EDIT', rex::getUser())) {
    $addButton = '<a class="btn btn-primary" href="index.php?' . http_build_query(array_merge(['func' => 'add'], $rex_link_vars)) . '"' . rex::getAccesskey(rex_i18n::msg('add'), 'add') . '>
        <i class="rex-icon rex-icon-add"></i> ' . rex_i18n::msg('add') . '
    </a>';
}

// Build the toolbar
$toolbar = '
<div class="rex-ymedia-toolbar">
    <div class="row">
        <div class="col-md-12">
            ' . ($addButton ?? '') . '
        </div>
    </div>
</div>';

// Generate the view container
$container = '
<div class="rex-ymedia-container rex-ymedia-grid">
    ' . implode('', $items) . '
</div>';

// Add CSS for grid view
$css = '
<style>
.rex-ymedia-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    grid-gap: 20px;
    padding: 20px;
}

.rex-ymedia-toolbar {
    margin-bottom: 20px;
    padding: 10px;
    background: #f5f5f5;
    border-radius: 4px;
}
</style>';

// Build the complete output
$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('yform_tabledata_overview'));
$fragment->setVar('options', implode('', $this->getVar('panelOptions')), false);
$fragment->setVar('body', $toolbar . $container . $css, false);
echo $fragment->parse('core/page/section.php');
