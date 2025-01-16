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

// Get user preferences for view
$user = rex::getUser();
$userPrefs = rex_ymedia::getUserPreferences($user->getId());
$viewMode = $userPrefs['view_mode'] ?? 'grid';
$itemsPerPage = $userPrefs['items_per_page'] ?? 20;
$sortBy = $userPrefs['sort_by'] ?? 'createdate';
$sortOrder = $userPrefs['sort_order'] ?? 'DESC';

// Apply filters from user preferences
if (isset($userPrefs['filters']) && is_array($userPrefs['filters'])) {
    foreach ($userPrefs['filters'] as $field => $value) {
        if ($value !== '') {
            $query->where($field, $value);
        }
    }
}

// Apply sorting
$query->orderBy($sortBy, $sortOrder);

// Handle pagination
$page = rex_request('page', 'int', 1);
$totalItems = $query->count();
$lastPage = ceil($totalItems / $itemsPerPage);
$page = min($page, $lastPage);
$offset = ($page - 1) * $itemsPerPage;

$query->limit($itemsPerPage);
$query->offset($offset);

// Prepare action buttons
$actionButtonsViews = [];
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

// Build view mode switcher
$viewModes = [
    'grid' => '<i class="fa fa-th"></i>',
    'list' => '<i class="fa fa-list"></i>',
    'detail' => '<i class="fa fa-th-list"></i>'
];

$viewSwitcher = '<div class="btn-group view-switcher">';
foreach ($viewModes as $mode => $icon) {
    $active = $mode === $viewMode ? 'active' : '';
    $viewSwitcher .= '
        <button class="btn btn-default ' . $active . '" data-view="' . $mode . '" 
                onclick="switchView(\'' . $mode . '\')">
            ' . $icon . '
        </button>';
}
$viewSwitcher .= '</div>';

// Build pagination
$pagination = '<ul class="pagination">';
for ($i = 1; $i <= $lastPage; $i++) {
    $active = $i === $page ? 'active' : '';
    $pagination .= '<li class="page-item ' . $active . '">
        <a class="page-link" href="' . rex_url::currentBackendPage(['page' => $i]) . '">' . $i . '</a>
    </li>';
}
$pagination .= '</ul>';

// Add new item button
if ($hasDataPageFunctions('add') && $table->isGranted('EDIT', rex::getUser())) {
    $addButton = '<a class="btn btn-primary" href="index.php?' . http_build_query(array_merge(['func' => 'add'], $rex_link_vars)) . '"' . rex::getAccesskey(rex_i18n::msg('add'), 'add') . '>
        <i class="rex-icon rex-icon-add"></i> ' . rex_i18n::msg('add') . '
    </a>';
}

// Build the toolbar
$toolbar = '
<div class="rex-ymedia-toolbar">
    <div class="row">
        <div class="col-md-4">
            ' . ($addButton ?? '') . '
        </div>
        <div class="col-md-4 text-center">
            ' . $pagination . '
        </div>
        <div class="col-md-4 text-right">
            ' . $viewSwitcher . '
        </div>
    </div>
</div>';

// Generate the view container
$viewClass = 'rex-ymedia-' . $viewMode;
$container = '
<div class="rex-ymedia-container ' . $viewClass . '">
    ' . implode('', $items) . '
</div>';

// Add JavaScript for view switching and other functionality
$js = '
<script>
function switchView(mode) {
    // Save preference via AJAX
    $.post("index.php", {
        page: "ymedia/pool",
        func: "save_pref",
        key: "view_mode",
        value: mode
    });
    
    // Update view immediately
    $(".rex-ymedia-container")
        .removeClass("rex-ymedia-grid rex-ymedia-list rex-ymedia-detail")
        .addClass("rex-ymedia-" + mode);
        
    // Update active button
    $(".view-switcher .btn").removeClass("active");
    $(".view-switcher .btn[data-view=\'" + mode + "\']").addClass("active");
}

// Initialize drag and drop for file upload
new Dropzone(".rex-ymedia-container", {
    url: "index.php",
    params: {
        page: "ymedia/pool",
        func: "upload"
    },
    thumbnailWidth: 150,
    thumbnailHeight: 150,
    previewsContainer: false
});

// Initialize select2 for tags with image preview
$(".ymedia-tag-select").select2({
    templateResult: formatTagOption
});

function formatTagOption(tag) {
    if (!tag.element) return tag.text;
    
    var $preview = $(
        \'<div class="select2-result-tag">\' +
            \'<div class="select2-result-tag__meta">\' +
                \'<div class="select2-result-tag__title">\' + tag.text + \'</div>\' +
            \'</div>\' +
        \'</div>\'
    );
    
    return $preview;
}
</script>';

// Add CSS for different view modes
$css = '
<style>
.rex-ymedia-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    grid-gap: 20px;
    padding: 20px;
}

.rex-ymedia-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.rex-ymedia-detail {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    grid-gap: 30px;
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
$fragment->setVar('body', $toolbar . $container . $js . $css, false);
echo $fragment->parse('core/page/section.php');
