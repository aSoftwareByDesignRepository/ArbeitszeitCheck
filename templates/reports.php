<?php
declare(strict_types=1);

/**
 * Reports template for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

/** @var array $_ */
/** @var \OCP\IL10N $l */

// Add common + page-specific styles and scripts
Util::addTranslations('arbeitszeitcheck');
Util::addStyle('arbeitszeitcheck', 'common/colors');
Util::addStyle('arbeitszeitcheck', 'common/typography');
Util::addStyle('arbeitszeitcheck', 'common/base');
Util::addStyle('arbeitszeitcheck', 'common/components');
Util::addStyle('arbeitszeitcheck', 'common/layout');
Util::addStyle('arbeitszeitcheck', 'common/utilities');
Util::addStyle('arbeitszeitcheck', 'common/responsive');
Util::addStyle('arbeitszeitcheck', 'common/accessibility');
Util::addStyle('arbeitszeitcheck', 'navigation');
Util::addStyle('arbeitszeitcheck', 'reports');
Util::addScript('arbeitszeitcheck', 'common/utils');
Util::addScript('arbeitszeitcheck', 'arbeitszeitcheck-main');

$urlGenerator = $_['urlGenerator'] ?? \OC::$server->getURLGenerator();
$isAdmin = $_['isAdmin'] ?? false;
$isManager = $_['isManager'] ?? false;
$canAccessReports = $isAdmin || $isManager;
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
    <div id="app-content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.index')); ?>"><?php p($l->t('Dashboard')); ?></a></li>
                    <li aria-current="page"><?php p($l->t('Reports')); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Page Header -->
        <div class="section page-header-section">
            <div class="header-content">
                <div class="header-text">
                    <h2><?php p($l->t('Reports')); ?></h2>
                    <p><?php p($l->t('Generate and export working time reports')); ?></p>
                </div>
            </div>
        </div>

        <!-- Report Type Selection -->
        <div class="section">
            <?php if (!$canAccessReports): ?>
                <div class="empty-state">
                    <h3 class="empty-state__title"><?php p($l->t('Reports are only available for administrators and managers')); ?></h3>
                    <p class="empty-state__description">
                        <?php p($l->t('If you need to generate reports, please contact your administrator or manager.')); ?>
                    </p>
                </div>
            <?php else: ?>
            <div class="report-selection-section">
                <h3><?php p($l->t('Select Report Type')); ?></h3>
                <div class="report-types-grid">
                    <div class="report-type-card" data-report-type="daily">
                        <div class="report-type-icon">📊</div>
                        <h4><?php p($l->t('Daily Report')); ?></h4>
                        <p><?php p($l->t('View working hours for a specific day')); ?></p>
                        <button class="btn-select-report" data-report="daily"><?php p($l->t('Generate')); ?></button>
                    </div>

                    <div class="report-type-card" data-report-type="weekly">
                        <div class="report-type-icon">📅</div>
                        <h4><?php p($l->t('Weekly Report')); ?></h4>
                        <p><?php p($l->t('Weekly summary of working time')); ?></p>
                        <button class="btn-select-report" data-report="weekly"><?php p($l->t('Generate')); ?></button>
                    </div>

                    <div class="report-type-card" data-report-type="monthly">
                        <div class="report-type-icon">📈</div>
                        <h4><?php p($l->t('Monthly Report')); ?></h4>
                        <p><?php p($l->t('Monthly working time overview')); ?></p>
                        <button class="btn-select-report" data-report="monthly"><?php p($l->t('Generate')); ?></button>
                    </div>

                    <div class="report-type-card" data-report-type="overtime">
                        <div class="report-type-icon">⏰</div>
                        <h4><?php p($l->t('Overtime Report')); ?></h4>
                        <p><?php p($l->t('Overtime balance and history')); ?></p>
                        <button class="btn-select-report" data-report="overtime"><?php p($l->t('Generate')); ?></button>
                    </div>

                    <div class="report-type-card" data-report-type="absence">
                        <div class="report-type-icon">🏖️</div>
                        <h4><?php p($l->t('Absence Report')); ?></h4>
                        <p><?php p($l->t('Vacation and absence overview')); ?></p>
                        <button class="btn-select-report" data-report="absence"><?php p($l->t('Generate')); ?></button>
                    </div>

                    <div class="report-type-card" data-report-type="compliance">
                        <div class="report-type-icon">✅</div>
                        <h4><?php p($l->t('Compliance Report')); ?></h4>
                        <p><?php p($l->t('German labor law compliance')); ?></p>
                        <button class="btn-select-report" data-report="compliance"><?php p($l->t('Generate')); ?></button>
                    </div>
                </div>
            </div>

            <!-- Report Parameters -->
            <div id="report-parameters" class="report-parameters-section" style="display: none;">
                <h3><?php p($l->t('What information do you want in the report?')); ?></h3>
                <form id="report-form" class="report-form">
                    <input type="hidden" id="report-type" name="report_type" value="">
                    
                    <div class="form-group">
                        <label for="start-date" class="form-label">
                            <?php p($l->t('Start Date')); ?>
                            <span class="form-required" aria-label="required">*</span>
                        </label>
                        <input type="date" 
                               id="start-date" 
                               name="start_date" 
                               class="form-input" 
                               required
                               aria-describedby="start-date-help">
                        <p id="start-date-help" class="form-help">
                            <?php p($l->t('The first day to include in the report. Click the calendar icon to pick a date. Example: If you want a report for January, select January 1st.')); ?>
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label for="end-date" class="form-label">
                            <?php p($l->t('End Date')); ?>
                            <span class="form-required" aria-label="required">*</span>
                        </label>
                        <input type="date" 
                               id="end-date" 
                               name="end_date" 
                               class="form-input" 
                               required
                               aria-describedby="end-date-help">
                        <p id="end-date-help" class="form-help">
                            <?php p($l->t('The last day to include in the report. Click the calendar icon to pick a date. Example: If you want a report for January, select January 31st.')); ?>
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label for="format" class="form-label">
                            <?php p($l->t('File Format')); ?>
                        </label>
                        <select id="format" 
                                name="format" 
                                class="form-select"
                                aria-describedby="format-help">
                            <option value="pdf"><?php p($l->t('PDF (for printing or viewing)')); ?></option>
                            <option value="csv"><?php p($l->t('CSV (for Excel or other programs)')); ?></option>
                            <option value="xlsx"><?php p($l->t('Excel File (XLSX)')); ?></option>
                            <option value="json"><?php p($l->t('JSON (for computer programs)')); ?></option>
                        </select>
                        <p id="format-help" class="form-help">
                            <?php p($l->t('Choose how you want to save the report. PDF is best for printing or viewing. Excel or CSV is best if you want to edit the data in a spreadsheet program.')); ?>
                        </p>
                    </div>
                    
                    <div class="card-actions">
                        <button type="button" 
                                id="btn-preview-report" 
                                class="btn btn--secondary"
                                aria-label="<?php p($l->t('Preview the report before downloading')); ?>"
                                title="<?php p($l->t('Click to see what the report will look like before downloading it')); ?>">
                            <?php p($l->t('Preview')); ?>
                        </button>
                        <button type="submit" 
                                id="btn-generate-report" 
                                class="btn btn--primary"
                                aria-label="<?php p($l->t('Generate and download the report')); ?>"
                                title="<?php p($l->t('Click to create the report and download it to your computer')); ?>">
                            <?php p($l->t('Generate & Download')); ?>
                        </button>
                        <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.index')); ?>"
                           class="btn btn--secondary"
                           aria-label="<?php p($l->t('Cancel and go back')); ?>"
                           title="<?php p($l->t('Click to cancel and go back without generating a report')); ?>">
                            <?php p($l->t('Cancel')); ?>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Report Preview -->
            <div id="report-preview" class="report-preview-section" style="display: none;">
                <h3><?php p($l->t('Report Preview')); ?></h3>
                <div id="report-preview-content" class="report-preview-content">
                    <!-- Preview will be loaded here dynamically -->
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Initialize JavaScript -->
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
    window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
    window.ArbeitszeitCheck.page = 'reports';
    window.ArbeitszeitCheck.canAccessReports = <?php echo json_encode($canAccessReports, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    
    window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
    window.ArbeitszeitCheck.l10n.error = <?php echo json_encode($l->t('An error occurred'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.generating = <?php echo json_encode($l->t('Generating report...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    
    window.ArbeitszeitCheck.apiUrl = {
        daily: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.report.daily'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        weekly: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.report.weekly'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        monthly: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.report.monthly'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        overtime: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.report.overtime'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        absence: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.report.absence'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    };
    
    <?php if ($canAccessReports): ?>
    // Initialize reports functionality
    document.addEventListener('DOMContentLoaded', function() {
        const reportCards = document.querySelectorAll('.report-type-card');
        const reportButtons = document.querySelectorAll('.btn-select-report');
        const reportParameters = document.getElementById('report-parameters');
        const reportForm = document.getElementById('report-form');
        const reportTypeInput = document.getElementById('report-type');
        const startDateInput = document.getElementById('start-date');
        const endDateInput = document.getElementById('end-date');
        const formatSelect = document.getElementById('format');
        const previewBtn = document.getElementById('btn-preview-report');
        const generateBtn = document.getElementById('btn-generate-report');
        
        // Handle report card clicks
        reportCards.forEach(card => {
            card.addEventListener('click', function(e) {
                // Don't trigger if clicking the button
                if (e.target.classList.contains('btn-select-report')) {
                    return;
                }
                const button = card.querySelector('.btn-select-report');
                if (button) {
                    button.click();
                }
            });
        });
        
        // Handle report button clicks
        reportButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const reportType = this.dataset.report;
                if (reportType && reportTypeInput) {
                    reportTypeInput.value = reportType;
                    
                    // Show parameters section
                    if (reportParameters) {
                        reportParameters.style.display = 'block';
                        reportParameters.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    
                    // Set default dates (last 30 days)
                    const today = new Date();
                    const thirtyDaysAgo = new Date();
                    thirtyDaysAgo.setDate(today.getDate() - 30);
                    
                    if (startDateInput) {
                        startDateInput.value = thirtyDaysAgo.toISOString().split('T')[0];
                    }
                    if (endDateInput) {
                        endDateInput.value = today.toISOString().split('T')[0];
                    }
                }
            });
        });
        
        // Handle form submission
        if (reportForm) {
            reportForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const reportType = reportTypeInput ? reportTypeInput.value : '';
                const startDate = startDateInput ? startDateInput.value : '';
                const endDate = endDateInput ? endDateInput.value : '';
                const format = formatSelect ? formatSelect.value : 'pdf';
                
                if (!reportType || !startDate || !endDate) {
                    alert(window.ArbeitszeitCheck.l10n.error || 'Please fill in all required fields');
                    return;
                }
                
                // Get API URL for report type
                const apiUrl = window.ArbeitszeitCheck.apiUrl[reportType];
                if (!apiUrl) {
                    alert(window.ArbeitszeitCheck.l10n.error || 'Invalid report type');
                    return;
                }
                
                // Build URL with parameters
                const url = new URL(apiUrl, window.location.origin);
                url.searchParams.set('start_date', startDate);
                url.searchParams.set('end_date', endDate);
                url.searchParams.set('format', format);
                
                // Show loading state
                if (generateBtn) {
                    const originalText = generateBtn.textContent;
                    generateBtn.disabled = true;
                    generateBtn.textContent = window.ArbeitszeitCheck.l10n.generating || 'Generating...';
                    
                    // Download the report
                    window.location.href = url.toString();
                    
                    // Reset button after a delay
                    setTimeout(() => {
                        generateBtn.disabled = false;
                        generateBtn.textContent = originalText;
                    }, 2000);
                } else {
                    window.location.href = url.toString();
                }
            });
        }
        
        // Handle preview button
        if (previewBtn) {
            previewBtn.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Preview functionality will be implemented in a future update.');
            });
        }
    });
    <?php endif; ?>
</script>

