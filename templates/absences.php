<?php
declare(strict_types=1);

/**
 * Absences template for arbeitszeitcheck app
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
Util::addStyle('arbeitszeitcheck', 'absences');
Util::addScript('arbeitszeitcheck', 'common/utils');
Util::addScript('arbeitszeitcheck', 'arbeitszeitcheck-main');

$absences = $_['absences'] ?? [];
$urlGenerator = $_['urlGenerator'] ?? \OC::$server->getURLGenerator();
$stats = $_['stats'] ?? [];
$mode = $_['mode'] ?? 'list'; // 'list', 'create', 'edit'
$absence = $_['absence'] ?? null;
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
                    <li aria-current="page"><?php p($l->t('Absences')); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Page Header -->
        <div class="section page-header-section">
            <div class="header-content">
                <div class="header-text">
                    <h2><?php 
                        if ($mode === 'create') {
                            p($l->t('Request Time Off'));
                        } elseif ($mode === 'edit') {
                            p($l->t('Edit Absence Request'));
                        } else {
                            p($l->t('Absences'));
                        }
                    ?></h2>
                    <p><?php 
                        if ($mode === 'create') {
                            p($l->t('Request a new absence. Your manager will review and approve or reject your request.'));
                        } elseif ($mode === 'edit') {
                            p($l->t('Edit your absence request. You can only edit pending requests.'));
                        } else {
                            p($l->t('Manage vacation, sick leave, and other absences'));
                        }
                    ?></p>
                </div>
                <?php if ($mode === 'list'): ?>
                <div class="header-actions">
                    <button id="btn-request-absence" 
                            class="btn btn--primary" 
                            type="button"
                            aria-label="<?php p($l->t('Request time off for vacation or sick leave')); ?>"
                            title="<?php p($l->t('Click to request time off. You can request vacation days, sick leave, or other types of absences.')); ?>">
                        <?php p($l->t('Request Time Off')); ?>
                    </button>
                    <button id="btn-filter" 
                            class="btn btn--secondary" 
                            type="button"
                            aria-label="<?php p($l->t('Filter absence requests by date or status')); ?>"
                            title="<?php p($l->t('Click to show options for filtering your absence requests. You can filter by date range or approval status.')); ?>">
                        <?php p($l->t('Filter')); ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($mode === 'create' || $mode === 'edit'): ?>
            <!-- Create/Edit Form -->
            <div class="section">
                <?php if ($error): ?>
                    <div class="alert alert--error">
                        <p><?php p($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <form id="absence-form" class="form" method="POST" action="<?php 
                    if ($mode === 'create') {
                        p($urlGenerator->linkToRoute('arbeitszeitcheck.absence.store'));
                    } else {
                        p($urlGenerator->linkToRoute('arbeitszeitcheck.absence.updatePost', ['id' => $absence->getId()]));
                    }
                ?>">
                    <div class="form-group">
                        <label for="absence-type" class="form-label">
                            <?php p($l->t('Type')); ?> <span class="form-required">*</span>
                        </label>
                        <select id="absence-type" name="type" class="form-select" required>
                            <option value=""><?php p($l->t('Select the type of absence you want to request')); ?></option>
                            <option value="vacation" <?php echo ($absence && $absence->getType() === 'vacation') ? 'selected' : ''; ?>>
                                <?php p($l->t('Vacation')); ?>
                            </option>
                            <option value="sick_leave" <?php echo ($absence && $absence->getType() === 'sick_leave') ? 'selected' : ''; ?>>
                                <?php p($l->t('Sick Leave')); ?>
                            </option>
                            <option value="personal_leave" <?php echo ($absence && $absence->getType() === 'personal_leave') ? 'selected' : ''; ?>>
                                <?php p($l->t('Personal Leave')); ?>
                            </option>
                            <option value="parental_leave" <?php echo ($absence && $absence->getType() === 'parental_leave') ? 'selected' : ''; ?>>
                                <?php p($l->t('Parental Leave')); ?>
                            </option>
                            <option value="special_leave" <?php echo ($absence && $absence->getType() === 'special_leave') ? 'selected' : ''; ?>>
                                <?php p($l->t('Special Leave')); ?>
                            </option>
                            <option value="unpaid_leave" <?php echo ($absence && $absence->getType() === 'unpaid_leave') ? 'selected' : ''; ?>>
                                <?php p($l->t('Unpaid Leave')); ?>
                            </option>
                            <option value="home_office" <?php echo ($absence && $absence->getType() === 'home_office') ? 'selected' : ''; ?>>
                                <?php p($l->t('Home Office')); ?>
                            </option>
                            <option value="business_trip" <?php echo ($absence && $absence->getType() === 'business_trip') ? 'selected' : ''; ?>>
                                <?php p($l->t('Business Trip')); ?>
                            </option>
                        </select>
                        <p class="form-help"><?php p($l->t('Select the type of absence you want to request')); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="absence-start-date" class="form-label">
                            <?php p($l->t('Start Date')); ?> <span class="form-required">*</span>
                        </label>
                        <input type="date" 
                               id="absence-start-date" 
                               name="start_date" 
                               class="form-input" 
                               value="<?php echo $absence ? $absence->getStartDate()->format('Y-m-d') : ''; ?>"
                               min="<?php echo date('Y-m-d'); ?>"
                               required>
                        <p class="form-help"><?php p($l->t('The first day of your absence')); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="absence-end-date" class="form-label">
                            <?php p($l->t('End Date')); ?> <span class="form-required">*</span>
                        </label>
                        <input type="date" 
                               id="absence-end-date" 
                               name="end_date" 
                               class="form-input" 
                               value="<?php echo $absence ? $absence->getEndDate()->format('Y-m-d') : ''; ?>"
                               min="<?php echo date('Y-m-d'); ?>"
                               required>
                        <p class="form-help"><?php p($l->t('The last day of your absence')); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="absence-reason" class="form-label">
                            <?php p($l->t('Reason')); ?>
                        </label>
                        <textarea id="absence-reason" 
                                  name="reason" 
                                  class="form-textarea" 
                                  rows="4"
                                  placeholder="<?php p($l->t('Optional reason or notes for your absence request')); ?>"><?php echo $absence ? htmlspecialchars($absence->getReason() ?? '') : ''; ?></textarea>
                        <p class="form-help"><?php p($l->t('You can provide additional information about your absence request')); ?></p>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary">
                            <?php echo $mode === 'create' ? $l->t('Submit Request') : $l->t('Update Request'); ?>
                        </button>
                        <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.absences')); ?>" class="btn btn--secondary">
                            <?php p($l->t('Cancel')); ?>
                        </a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Stats Cards -->
            <div class="section">
                <?php if (!empty($stats)): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <span class="stat-label"><?php p($l->t('Vacation Days Remaining')); ?></span>
                            <span class="stat-value"><?php p($stats['vacation_days_remaining'] ?? 0); ?></span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label"><?php p($l->t('Pending Requests')); ?></span>
                            <span class="stat-value"><?php p($stats['pending_requests'] ?? 0); ?></span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label"><?php p($l->t('Days Taken This Year')); ?></span>
                            <span class="stat-value"><?php p($stats['days_taken_this_year'] ?? 0); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Absences Table -->
        <div class="section">
            <div class="table-container">
                    <table class="table table--hover" id="absences-table">
                        <thead>
                            <tr>
                                <th><?php p($l->t('Type')); ?></th>
                                <th><?php p($l->t('Start Date')); ?></th>
                                <th><?php p($l->t('End Date')); ?></th>
                                <th><?php p($l->t('Days')); ?></th>
                                <th><?php p($l->t('Reason')); ?></th>
                                <th><?php p($l->t('Status')); ?></th>
                                <th><?php p($l->t('Actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($absences)): ?>
                                <?php foreach ($absences as $absence): ?>
                                    <tr data-absence-id="<?php p($absence->getId()); ?>">
                                        <td>
                                            <span class="absence-type-badge type-<?php p($absence->getType()); ?>">
                                                <?php 
                                                $typeKey = $absence->getType();
                                                $typeLabel = match($typeKey) {
                                                    'vacation' => $l->t('Vacation'),
                                                    'sick' => $l->t('Sick Leave'),
                                                    'sick_leave' => $l->t('Sick Leave'),
                                                    default => $l->t(ucfirst($typeKey))
                                                };
                                                p($typeLabel);
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php p($absence->getStartDate()->format('d.m.Y')); ?></td>
                                        <td><?php p($absence->getEndDate()->format('d.m.Y')); ?></td>
                                        <td><?php p($absence->getDays()); ?></td>
                                        <td class="reason-cell">
                                            <?php 
                                            $reason = $absence->getReason();
                                            p($reason ? substr($reason, 0, 50) : '-'); 
                                            ?>
                                            <?php if ($reason && strlen($reason) > 50): ?>
                                                <span class="reason-more">...</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge--<?php 
                                                echo match($absence->getStatus()) {
                                                    'approved' => 'success',
                                                    'pending' => 'warning',
                                                    'rejected' => 'error',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php 
                                                $statusKey = $absence->getStatus();
                                                $statusLabel = match($statusKey) {
                                                    'approved' => $l->t('Approved'),
                                                    'pending' => $l->t('Pending'),
                                                    'rejected' => $l->t('Rejected'),
                                                    default => $l->t(ucfirst($statusKey))
                                                };
                                                p($statusLabel);
                                                ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <?php if ($absence->getStatus() === 'pending'): ?>
                                                <button class="btn-icon btn-edit" 
                                                        data-absence-id="<?php p($absence->getId()); ?>"
                                                        title="<?php p($l->t('Edit')); ?>">
                                                    ✏️
                                                </button>
                                                <button class="btn-icon btn-cancel" 
                                                        data-absence-id="<?php p($absence->getId()); ?>"
                                                        title="<?php p($l->t('Cancel')); ?>">
                                                    ❌
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-icon btn-view" 
                                                        data-absence-id="<?php p($absence->getId()); ?>"
                                                        title="<?php p($l->t('View Details')); ?>">
                                                    👁️
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <div class="empty-state">
                                            <h3 class="empty-state__title"><?php p($l->t('No absences yet')); ?></h3>
                                            <p class="empty-state__description">
                                                <?php p($l->t('You have not requested any absences yet. Use the button below to request vacation, sick leave, or other time off.')); ?>
                                            </p>
                                            <button id="btn-request-first-absence"
                                                class="btn btn--primary"
                                                type="button"
                                                aria-label="<?php p($l->t('Request your first absence')); ?>">
                                                <?php p($l->t('Request Time Off')); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Initialize JavaScript -->
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
    window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
    window.ArbeitszeitCheck.page = 'absences';
    window.ArbeitszeitCheck.mode = <?php echo json_encode($mode, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.absences = <?php echo json_encode(array_map(function($absence) {
        return [
            'id' => $absence->getId(),
            'type' => $absence->getType(),
            'startDate' => $absence->getStartDate()->format('Y-m-d'),
            'endDate' => $absence->getEndDate()->format('Y-m-d'),
            'status' => $absence->getStatus()
        ];
    }, $absences), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    
    window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
    window.ArbeitszeitCheck.l10n.confirmCancel = <?php echo json_encode($l->t('Are you sure you want to cancel this absence request?'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    
    window.ArbeitszeitCheck.apiUrl = {
        absences: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.absence.index'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        create: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.absence.store'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        update: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.absence.updatePost', ['id' => '__ID__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>.replace('__ID__', ''),
        delete: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.absence.delete', ['id' => '__ID__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>.replace('__ID__', '')
    };
    
    // Handle form submission for create/edit
    <?php if ($mode === 'create' || $mode === 'edit'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('absence-form');
        const startDateInput = document.getElementById('absence-start-date');
        const endDateInput = document.getElementById('absence-end-date');
        
        // Validate end date is not before start date
        function validateDates() {
            if (startDateInput.value && endDateInput.value) {
                if (new Date(endDateInput.value) < new Date(startDateInput.value)) {
                    endDateInput.setCustomValidity('<?php echo addslashes($l->t('End date cannot be before start date')); ?>');
                    return false;
                } else {
                    endDateInput.setCustomValidity('');
                }
            }
            return true;
        }
        
        if (startDateInput) {
            startDateInput.addEventListener('change', function() {
                if (endDateInput.value) {
                    validateDates();
                }
                // Update end date min to be at least start date
                if (this.value) {
                    endDateInput.min = this.value;
                }
            });
        }
        
        if (endDateInput) {
            endDateInput.addEventListener('change', validateDates);
        }
        
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!validateDates()) {
                    return;
                }
                
                const formData = new FormData(form);
                const data = {
                    type: formData.get('type'),
                    start_date: formData.get('start_date'),
                    end_date: formData.get('end_date'),
                    reason: formData.get('reason') || null
                };
                
                const url = <?php echo $mode === 'create' 
                    ? json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.absence.store'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
                    : json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.absence.updatePost', ['id' => $absence->getId()]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>;
                
                const method = 'POST';
                
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
                            console.error('Error submitting absence request:', error);
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
