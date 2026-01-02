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
                    <h2><?php p($l->t('Absences')); ?></h2>
                    <p><?php p($l->t('Manage vacation, sick leave, and other absences')); ?></p>
                </div>
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
            </div>
        </div>

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
                                                <?php p($l->t(ucfirst($absence->getType()))); ?>
                                            </span>
                                        </td>
                                        <td><?php p($absence->getStartDate()->format('Y-m-d')); ?></td>
                                        <td><?php p($absence->getEndDate()->format('Y-m-d')); ?></td>
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
                                                <?php p($l->t(ucfirst($absence->getStatus()))); ?>
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
    </div>
</div>

<!-- Initialize JavaScript -->
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
    window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
    window.ArbeitszeitCheck.page = 'absences';
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
        delete: <?php echo json_encode($urlGenerator->linkToRoute('arbeitszeitcheck.absence.delete', ['id' => '__ID__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>.replace('__ID__', '')
    };
</script>
