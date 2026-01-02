<?php
declare(strict_types=1);

/**
 * Time Entries template for arbeitszeitcheck app
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
Util::addStyle('arbeitszeitcheck', 'time-entries');
Util::addScript('arbeitszeitcheck', 'common/utils');
Util::addScript('arbeitszeitcheck', 'arbeitszeitcheck-main');

$entries = $_['entries'] ?? [];
$urlGenerator = $_['urlGenerator'] ?? \OC::$server->getURLGenerator();
$stats = $_['stats'] ?? [];
$mode = $_['mode'] ?? 'list'; // 'list', 'create', 'edit'
$entry = $_['entry'] ?? null;
$error = $_['error'] ?? null;
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
    <div id="app-content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.index')); ?>"><?php p($l->t('Dashboard')); ?></a></li>
                    <li aria-current="page"><?php p($l->t('Time Entries')); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Page Header -->
        <div class="section page-header-section">
            <div class="header-content">
                <div class="header-text">
                    <h2><?php 
                        if ($mode === 'create') {
                            p($l->t('Add Time Entry'));
                        } elseif ($mode === 'edit') {
                            p($l->t('Edit Time Entry'));
                        } else {
                            p($l->t('Time Entries'));
                        }
                    ?></h2>
                    <p><?php 
                        if ($mode === 'create') {
                            p($l->t('Record when you worked by entering the start and end times, and any breaks you took.'));
                        } elseif ($mode === 'edit') {
                            p($l->t('Edit your time entry. You can only edit manual entries or entries with pending approval.'));
                        } else {
                            p($l->t('Manage your working time records'));
                        }
                    ?></p>
                </div>
                <?php if ($mode === 'list'): ?>
                <div class="header-actions">
                    <button id="btn-add-entry" 
                            class="btn btn--primary" 
                            type="button"
                            aria-label="<?php p($l->t('Add a new time entry to record when you worked')); ?>"
                            title="<?php p($l->t('Click to add a new time entry. You can record when you started and finished work, and any breaks you took.')); ?>">
                        <?php p($l->t('Add Time Entry')); ?>
                    </button>
                    <button id="btn-filter" 
                            class="btn btn--secondary" 
                            type="button"
                            aria-label="<?php p($l->t('Filter time entries by date or status')); ?>"
                            title="<?php p($l->t('Click to show options for filtering your time entries. You can filter by date range or status.')); ?>">
                        <?php p($l->t('Filter')); ?>
                    </button>
                    <button id="btn-export" 
                            class="btn btn--secondary" 
                            type="button"
                            aria-label="<?php p($l->t('Download your time entries as a file')); ?>"
                            title="<?php p($l->t('Click to download all your time entries as a file. You can choose PDF, Excel, or CSV format.')); ?>">
                        <?php p($l->t('Download')); ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($mode === 'list' && !empty($stats)): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-label"><?php p($l->t('Total Entries')); ?></span>
                        <span class="stat-value"><?php p($stats['total_time_entries'] ?? 0); ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label"><?php p($l->t('This Month')); ?></span>
                        <span class="stat-value"><?php p($stats['entries_this_month'] ?? 0); ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label"><?php p($l->t('Total Hours')); ?></span>
                        <span class="stat-value"><?php p(round($stats['total_hours'] ?? 0, 2)); ?> h</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($mode === 'create' || $mode === 'edit'): ?>
            <!-- Create/Edit Form -->
            <div class="section">
                <?php if ($error): ?>
                    <div class="alert alert--error">
                        <p><?php p($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <form id="time-entry-form" class="form" method="POST" action="<?php 
                    if ($mode === 'create') {
                        p($urlGenerator->linkToRoute('arbeitszeitcheck.time_entry.apiStore'));
                    } else {
                        p($urlGenerator->linkToRoute('arbeitszeitcheck.time_entry.apiUpdate', ['id' => $entry->getId()]));
                    }
                ?>">
                    <div class="form-group">
                        <label for="entry-date" class="form-label">
                            <?php p($l->t('Date')); ?> <span class="form-required">*</span>
                        </label>
                        <input type="text" 
                               id="entry-date" 
                               name="date" 
                               class="form-input" 
                               pattern="\d{2}\.\d{2}\.\d{4}"
                               placeholder="dd.mm.yyyy"
                               value="<?php echo $entry ? $entry->getStartTime()->format('d.m.Y') : date('d.m.Y'); ?>"
                               required>
                        <p class="form-help"><?php p($l->t('Select the day you worked (format: dd.mm.yyyy)')); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="entry-start-time" class="form-label">
                            <?php p($l->t('Start Time')); ?> <span class="form-required">*</span>
                        </label>
                        <input type="time" 
                               id="entry-start-time" 
                               name="startTime" 
                               class="form-input" 
                               value="<?php echo $entry ? $entry->getStartTime()->format('H:i') : '09:00'; ?>"
                               required>
                        <p class="form-help"><?php p($l->t('What time did you start working?')); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="entry-end-time" class="form-label">
                            <?php p($l->t('End Time')); ?> <span class="form-required">*</span>
                        </label>
                        <input type="time" 
                               id="entry-end-time" 
                               name="endTime" 
                               class="form-input" 
                               value="<?php echo $entry && $entry->getEndTime() ? $entry->getEndTime()->format('H:i') : '17:00'; ?>"
                               required>
                        <p class="form-help"><?php p($l->t('What time did you finish working?')); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="entry-break-start" class="form-label">
                            <?php p($l->t('Break Start Time')); ?>
                        </label>
                        <input type="time" 
                               id="entry-break-start" 
                               name="breakStartTime" 
                               class="form-input" 
                               value="<?php echo $entry && $entry->getBreakStartTime() ? $entry->getBreakStartTime()->format('H:i') : ''; ?>">
                        <p class="form-help"><?php p($l->t('Optional: When did your break start?')); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="entry-break-end" class="form-label">
                            <?php p($l->t('Break End Time')); ?>
                        </label>
                        <input type="time" 
                               id="entry-break-end" 
                               name="breakEndTime" 
                               class="form-input" 
                               value="<?php echo $entry && $entry->getBreakEndTime() ? $entry->getBreakEndTime()->format('H:i') : ''; ?>">
                        <p class="form-help"><?php p($l->t('Optional: When did your break end?')); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="entry-description" class="form-label">
                            <?php p($l->t('Description')); ?>
                        </label>
                        <textarea id="entry-description" 
                                  name="description" 
                                  class="form-textarea" 
                                  rows="3"
                                  placeholder="<?php p($l->t('Optional description or notes about this work period')); ?>"><?php echo $entry ? htmlspecialchars($entry->getDescription() ?? '') : ''; ?></textarea>
                        <p class="form-help"><?php p($l->t('Optional description or notes about this work period')); ?></p>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary">
                            <?php echo $mode === 'create' ? $l->t('Create Entry') : $l->t('Update Entry'); ?>
                        </button>
                        <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.timeEntries')); ?>" class="btn btn--secondary">
                            <?php p($l->t('Cancel')); ?>
                        </a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Filter Section (initially hidden) -->
        <div id="filter-section" class="section filter-section" style="display: none;">
            <div class="form">
                <div class="form-group">
                    <label for="filter-start-date" class="form-label"><?php p($l->t('Start Date')); ?></label>
                    <input type="date" id="filter-start-date" name="start_date" class="form-input">
                </div>
                <div class="form-group">
                    <label for="filter-end-date" class="form-label"><?php p($l->t('End Date')); ?></label>
                    <input type="date" id="filter-end-date" name="end_date" class="form-input">
                </div>
                <div class="form-group">
                    <label for="filter-status" class="form-label"><?php p($l->t('Status')); ?></label>
                    <select id="filter-status" name="status" class="form-select">
                        <option value=""><?php p($l->t('All')); ?></option>
                        <option value="active"><?php p($l->t('Active')); ?></option>
                        <option value="completed"><?php p($l->t('Completed')); ?></option>
                        <option value="pending_approval"><?php p($l->t('Pending Approval')); ?></option>
                    </select>
                </div>
                <div class="card-actions">
                    <button id="btn-apply-filter" class="btn btn--primary" type="button"><?php p($l->t('Apply')); ?></button>
                    <button id="btn-clear-filter" class="btn btn--secondary" type="button"><?php p($l->t('Clear')); ?></button>
                </div>
            </div>
        </div>

        <!-- Time Entries Table -->
        <div class="section">
                <div class="table-container">
                    <table class="table table--hover" id="time-entries-table">
                        <thead>
                            <tr>
                                <th><?php p($l->t('Date')); ?></th>
                                <th><?php p($l->t('Start Time')); ?></th>
                                <th><?php p($l->t('End Time')); ?></th>
                                <th><?php p($l->t('Duration')); ?></th>
                                <th><?php p($l->t('Break')); ?></th>
                                <th><?php p($l->t('Working Hours')); ?></th>
                                <th><?php p($l->t('Description')); ?></th>
                                <th><?php p($l->t('Status')); ?></th>
                                <th><?php p($l->t('Actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($entries)): ?>
                                <?php foreach ($entries as $entry): ?>
                                    <tr data-entry-id="<?php p($entry->getId()); ?>">
                                        <td><?php p($entry->getStartTime()->format('d.m.Y')); ?></td>
                                        <td><?php p($entry->getStartTime()->format('H:i')); ?></td>
                                        <td><?php 
                                            if ($entry->getEndTime()) {
                                                $endTime = $entry->getEndTime();
                                                $startDate = $entry->getStartTime()->format('Y-m-d');
                                                $endDate = $endTime->format('Y-m-d');
                                                // Show date if end time is on a different day
                                                if ($startDate !== $endDate) {
                                                    p($endTime->format('d.m.Y H:i'));
                                                } else {
                                                    p($endTime->format('H:i'));
                                                }
                                            } else {
                                                p('-');
                                            }
                                        ?></td>
                                        <td><?php p(round($entry->getDurationHours() ?? 0, 2)); ?> h</td>
                                        <td><?php p(round($entry->getBreakDurationHours() ?? 0, 2)); ?> h</td>
                                        <td><?php p(round($entry->getWorkingDurationHours() ?? 0, 2)); ?> h</td>
                                        <td class="description-cell">
                                            <?php p($entry->getDescription() ? substr($entry->getDescription(), 0, 50) : '-'); ?>
                                            <?php if ($entry->getDescription() && strlen($entry->getDescription()) > 50): ?>
                                                <span class="description-more">...</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge--<?php 
                                                echo match($entry->getStatus()) {
                                                    'completed' => 'success',
                                                    'active' => 'primary',
                                                    'pending_approval' => 'warning',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php 
                                                $statusKey = $entry->getStatus();
                                                $statusLabel = match($statusKey) {
                                                    'completed' => $l->t('Completed'),
                                                    'active' => $l->t('Active'),
                                                    'pending_approval' => $l->t('Pending Approval'),
                                                    default => $statusKey
                                                };
                                                p($statusLabel);
                                                ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <button class="btn btn--sm btn--secondary" 
                                                    data-entry-id="<?php p($entry->getId()); ?>"
                                                    title="<?php p($l->t('Edit')); ?>"
                                                    type="button"
                                                    aria-label="<?php p($l->t('Edit time entry')); ?>">
                                                <?php p($l->t('Edit')); ?>
                                            </button>
                                            <button class="btn btn--sm btn--danger btn-delete" 
                                                    data-entry-id="<?php p($entry->getId()); ?>"
                                                    title="<?php p($l->t('Delete this time entry permanently. This cannot be undone.')); ?>"
                                                    type="button"
                                                    aria-label="<?php p($l->t('Delete this time entry permanently')); ?>">
                                                <?php p($l->t('Delete')); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="empty-state">
                                        <div class="empty-state">
                                            <h3 class="empty-state__title"><?php p($l->t('No time entries yet')); ?></h3>
                                            <p class="empty-state__description">
                                                <?php p($l->t('You haven\'t recorded any working time yet. Click the button below to add your first time entry, or use the clock in button on the dashboard to start tracking automatically.')); ?>
                                            </p>
                                            <button id="btn-add-first-entry" 
                                                    class="btn btn--primary" 
                                                    type="button"
                                                    aria-label="<?php p($l->t('Add your first time entry')); ?>">
                                                <?php p($l->t('Add Your First Entry')); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if (!empty($entries) && count($entries) > 0): ?>
                    <div class="pagination">
                        <button id="btn-prev-page" class="btn btn--secondary" type="button" disabled>
                            <?php p($l->t('Previous')); ?>
                        </button>
                        <span class="pagination-info">
                            <span id="current-page">1</span> / <span id="total-pages">1</span>
                        </span>
                        <button id="btn-next-page" class="btn btn--secondary" type="button" disabled>
                            <?php p($l->t('Next')); ?>
                        </button>
                    </div>
                <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Initialize JavaScript -->
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
    // Pass essential data to JS
    window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
    window.ArbeitszeitCheck.page = 'time-entries';
    window.ArbeitszeitCheck.mode = <?php echo json_encode($mode, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.entries = <?php echo json_encode(array_map(function($entry) {
        return [
            'id' => $entry->getId(),
            'startTime' => $entry->getStartTime()->format('c'),
            'endTime' => $entry->getEndTime() ? $entry->getEndTime()->format('c') : null,
            'status' => $entry->getStatus(),
            'description' => $entry->getDescription()
        ];
    }, $entries), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    
    // L10n strings
    window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
    window.ArbeitszeitCheck.l10n.confirmDelete = <?php echo json_encode($l->t('Are you sure you want to delete this time entry?'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.confirmDeleteTimeEntry = <?php echo json_encode($l->t('Are you sure you want to delete this time entry?\n\nThis will permanently remove this record of your working time. This action cannot be undone.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.error = <?php echo json_encode($l->t('An error occurred'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.deleted = <?php echo json_encode($l->t('Time entry deleted successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    
    // API URLs
    window.ArbeitszeitCheck.apiUrl = {
        timeEntries: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.time_entry.apiIndex'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        create: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.time_entry.apiStore'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        update: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.time_entry.apiUpdate', ['id' => '__ID__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>.replace('__ID__', ''),
        delete: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.time_entry.apiDelete', ['id' => '__ID__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>.replace('__ID__', ''),
        export: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.export.timeEntries'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    };
    
    // Handle form submission for create/edit
    <?php if ($mode === 'create' || $mode === 'edit'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('time-entry-form');
        const startTimeInput = document.getElementById('entry-start-time');
        const endTimeInput = document.getElementById('entry-end-time');
        const breakStartInput = document.getElementById('entry-break-start');
        const breakEndInput = document.getElementById('entry-break-end');
        const dateInput = document.getElementById('entry-date');
        
        // Convert dd.mm.yyyy to yyyy-mm-dd
        function convertDateFormat(dateStr) {
            if (!dateStr) return null;
            // Check if already in yyyy-mm-dd format
            if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
                return dateStr;
            }
            // Convert dd.mm.yyyy to yyyy-mm-dd
            const parts = dateStr.split('.');
            if (parts.length === 3) {
                const day = parts[0].padStart(2, '0');
                const month = parts[1].padStart(2, '0');
                const year = parts[2];
                return `${year}-${month}-${day}`;
            }
            return dateStr;
        }
        
        // Validate end time is not before start time
        function validateTimes() {
            if (dateInput.value && startTimeInput.value && endTimeInput.value) {
                const dateStr = convertDateFormat(dateInput.value);
                const startDateTime = new Date(dateStr + 'T' + startTimeInput.value);
                const endDateTime = new Date(dateStr + 'T' + endTimeInput.value);
                
                // If end time is before start time, assume it's the next day
                if (endDateTime < startDateTime) {
                    endDateTime.setDate(endDateTime.getDate() + 1);
                }
                
                // Validate break times if provided
                if (breakStartInput.value && breakEndInput.value) {
                    const dateStr = convertDateFormat(dateInput.value);
                    const breakStart = new Date(dateStr + 'T' + breakStartInput.value);
                    const breakEnd = new Date(dateStr + 'T' + breakEndInput.value);
                    
                    if (breakEnd < breakStart) {
                        breakEnd.setDate(breakEnd.getDate() + 1);
                    }
                    
                    // Break must be within work period
                    if (breakStart < startDateTime || breakEnd > endDateTime) {
                        breakStartInput.setCustomValidity('<?php echo addslashes($l->t('Break must be within work period')); ?>');
                        breakEndInput.setCustomValidity('<?php echo addslashes($l->t('Break must be within work period')); ?>');
                        return false;
                    } else {
                        breakStartInput.setCustomValidity('');
                        breakEndInput.setCustomValidity('');
                    }
                }
            }
            return true;
        }
        
        if (startTimeInput) {
            startTimeInput.addEventListener('change', validateTimes);
        }
        if (endTimeInput) {
            endTimeInput.addEventListener('change', validateTimes);
        }
        if (breakStartInput) {
            breakStartInput.addEventListener('change', validateTimes);
        }
        if (breakEndInput) {
            breakEndInput.addEventListener('change', validateTimes);
        }
        if (dateInput) {
            dateInput.addEventListener('change', validateTimes);
            
            // Format date input as user types (dd.mm.yyyy)
            dateInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                
                // Format as dd.mm.yyyy
                if (value.length > 0) {
                    if (value.length <= 2) {
                        value = value;
                    } else if (value.length <= 4) {
                        value = value.slice(0, 2) + '.' + value.slice(2);
                    } else {
                        value = value.slice(0, 2) + '.' + value.slice(2, 4) + '.' + value.slice(4, 8);
                    }
                }
                
                e.target.value = value;
            });
            
            // Validate date format on blur
            dateInput.addEventListener('blur', function(e) {
                const value = e.target.value;
                if (value && !/^\d{2}\.\d{2}\.\d{4}$/.test(value)) {
                    // Try to fix common mistakes
                    const parts = value.split('.');
                    if (parts.length === 3) {
                        const day = parts[0].padStart(2, '0');
                        const month = parts[1].padStart(2, '0');
                        const year = parts[2];
                        if (day && month && year && year.length === 4) {
                            e.target.value = `${day}.${month}.${year}`;
                        }
                    }
                }
            });
        }
        
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!validateTimes()) {
                    return;
                }
                
                const formData = new FormData(form);
                const dateInputValue = formData.get('date');
                const date = convertDateFormat(dateInputValue); // Convert dd.mm.yyyy to yyyy-mm-dd
                const startTime = formData.get('startTime');
                const endTime = formData.get('endTime');
                const breakStartTime = formData.get('breakStartTime');
                const breakEndTime = formData.get('breakEndTime');
                
                // Build datetime strings
                const startDateTime = date && startTime ? `${date}T${startTime}:00` : null;
                const endDateTime = date && endTime ? `${date}T${endTime}:00` : null;
                
                // Check if end time is before start time (next day)
                if (startDateTime && endDateTime) {
                    const start = new Date(startDateTime);
                    const end = new Date(endDateTime);
                    if (end < start) {
                        // End time is next day
                        const nextDay = new Date(end);
                        nextDay.setDate(nextDay.getDate() + 1);
                        endDateTime = nextDay.toISOString().slice(0, 16) + ':00';
                    }
                }
                
                const data = {
                    startTime: startDateTime,
                    endTime: endDateTime,
                    description: formData.get('description') || null
                };
                
                // Add break times if provided
                if (breakStartTime && breakEndTime) {
                    const breakStartDateTime = date && breakStartTime ? `${date}T${breakStartTime}:00` : null;
                    let breakEndDateTime = date && breakEndTime ? `${date}T${breakEndTime}:00` : null;
                    
                    if (breakStartDateTime && breakEndDateTime) {
                        const breakStart = new Date(breakStartDateTime);
                        const breakEnd = new Date(breakEndDateTime);
                        if (breakEnd < breakStart) {
                            const nextDay = new Date(breakEnd);
                            nextDay.setDate(nextDay.getDate() + 1);
                            breakEndDateTime = nextDay.toISOString().slice(0, 16) + ':00';
                        }
                    }
                    
                    data.breakStartTime = breakStartDateTime;
                    data.breakEndTime = breakEndDateTime;
                }
                
                const url = <?php echo $mode === 'create' 
                    ? json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.time_entry.apiStore'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
                    : json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.time_entry.apiUpdate', ['id' => $entry->getId()]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>;
                
                const method = '<?php echo $mode === 'create' ? 'POST' : 'PUT'; ?>';
                
                if (window.ArbeitszeitCheck && window.ArbeitszeitCheck.callApi) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn ? submitBtn.textContent : '';
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = (window.t && window.t('arbeitszeitcheck', 'Submitting...')) || 'Submitting...';
                    }
                    
                    window.ArbeitszeitCheck.callApi(url, method, data, true)
                        .then(() => {
                            // Redirect handled by callApi with reloadOnSuccess
                        })
                        .catch(error => {
                            console.error('Error submitting time entry:', error);
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.textContent = originalText;
                            }
                        });
                } else {
                    // Fallback: submit form normally
                    form.submit();
                }
            });
        }
    });
    <?php endif; ?>
</script>
