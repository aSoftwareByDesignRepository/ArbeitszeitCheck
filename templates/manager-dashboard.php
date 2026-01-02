<?php

declare(strict_types=1);

/**
 * Manager dashboard template for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */


/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = \OC::$server->getL10N('arbeitszeitcheck');

$teamStats = $_['teamStats'] ?? [];
$teamMembers = $_['teamMembers'] ?? [];
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
    <div id="app-content-wrapper">
        <div class="section">
            <div class="section-header">
                <h2><?php p($l->t('Manager Dashboard')); ?></h2>
                <p><?php p($l->t('See how your team is doing with time tracking and check for any problems')); ?></p>
            </div>

            <!-- Team Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php p($teamStats['total_members'] ?? 0); ?></div>
                    <div class="stat-label"><?php p($l->t('Team Members')); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php p($teamStats['active_today'] ?? 0); ?></div>
                    <div class="stat-label"><?php p($l->t('Active Today')); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php p(round($teamStats['total_hours_today'] ?? 0, 1)); ?>h</div>
                    <div class="stat-label"><?php p($l->t('Hours Today')); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php p($teamStats['pending_absences'] ?? 0); ?></div>
                    <div class="stat-label"><?php p($l->t('Pending Absences')); ?></div>
                </div>
            </div>

            <!-- Team Members -->
            <div class="section">
                <div class="section-header">
                    <h3><?php p($l->t('Team Members')); ?></h3>
                </div>

                <?php if (empty($teamMembers)): ?>
                    <div class="empty-state">
                        <p><?php p($l->t('No team members found')); ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?php p($l->t('Name')); ?></th>
                                    <th><?php p($l->t('Hours Today')); ?></th>
                                    <th><?php p($l->t('Status')); ?></th>
                                    <th><?php p($l->t('Pending Absences')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teamMembers as $member): ?>
                                    <tr>
                                        <td><?php p($member['displayName']); ?></td>
                                        <td><?php p(round($member['todayHours'], 2)); ?>h</td>
                                        <td>
                                            <?php
                                            $statusLabels = [
                                                'active' => $l->t('Clocked In'),
                                                'break' => $l->t('On Break'),
                                                'clocked_out' => $l->t('Clocked Out')
                                            ];
                                            $statusLabel = $statusLabels[$member['status']] ?? $member['status'];
                                            ?>
                                            <span class="badge badge--primary"><?php p($statusLabel); ?></span>
                                        </td>
                                        <td><?php p($member['pendingAbsences']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
