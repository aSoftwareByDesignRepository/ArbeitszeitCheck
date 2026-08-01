<?php

declare(strict_types=1);

/**
 * Admin users template for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */


use OCA\ArbeitszeitCheck\Util\TemplateL10n;

/** @var array $_ */
/** @var \OCP\IL10N $l */
$l = $_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck');

$users = $_['users'] ?? [];
$total = $_['total'] ?? 0;
/** @var \OCP\IURLGenerator|null $urlGenerator */
$urlGenerator = $_['urlGenerator'] ?? null;
?>

<?php include __DIR__ . '/common/page-start.php'; ?>


        <div class="azc-page-stack">

        <section class="azc-card admin-users-card" aria-labelledby="admin-users-list-heading">
            <header class="azc-card__header">
                <div class="azc-card__header-text">
                    <h2 id="admin-users-list-heading" class="azc-card__title"><?php p($l->t('Employee list')); ?></h2>
                    <p class="azc-card__lead"><?php p($l->t('Review work schedules, vacation days, and overtime settings. Open an employee to change their setup.')); ?></p>
                </div>
                <div class="azc-card__header-actions">
                    <button type="button" id="refresh-users" class="azc-btn azc-btn--secondary">
                        <?php p($l->t('Refresh list')); ?>
                    </button>
                </div>
            </header>
            <div class="azc-card__body">
                <div class="admin-users-toolbar">
                    <div class="admin-users-toolbar__search">
                        <label for="user-search" class="form-label"><?php p($l->t('Find an employee')); ?></label>
                        <input type="search"
                            id="user-search"
                            class="form-input"
                            autocomplete="off"
                            autocapitalize="none"
                            spellcheck="false"
                            placeholder="<?php p($l->t('Search by name or login…')); ?>"
                            aria-describedby="user-search-help users-pagination">
                        <p id="user-search-help" class="form-help">
                            <?php p($l->t('Type at least 2 characters to search. Leave empty to browse the list page by page.')); ?>
                        </p>
                    </div>
                </div>

                <div class="table-container" role="region" aria-label="<?php p($l->t('Employee list')); ?>">
                    <table class="table table--hover azc-table--responsive" id="users-table" role="table" aria-label="<?php p($l->t('Employee list')); ?>">
                        <thead>
                            <tr>
                                <th scope="col"><?php p($l->t('Name')); ?></th>
                                <th scope="col"><?php p($l->t('Email')); ?></th>
                                <th scope="col"><?php p($l->t('Working Time Model')); ?></th>
                                <th scope="col"><?php p($l->t('Vacation days')); ?></th>
                                <th scope="col"><?php p($l->t('Valid from / to')); ?></th>
                                <th scope="col"><?php p($l->t('Overtime Stichtag')); ?></th>
                                <th scope="col"><?php p($l->t('Status')); ?></th>
                                <th scope="col" class="azc-table-actions-col"><?php p($l->t('Actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <?php p($l->t('No users found')); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $formatDate = function ($iso) use ($l) {
                                    if (empty($iso)) {
                                        return '-';
                                    }
                                    $d = \DateTime::createFromFormat('Y-m-d', $iso);
                                    return $d ? $d->format('d.m.Y') : $iso;
                                };
                                ?>
                                <?php foreach (($users ?? []) as $user): ?>
                                    <?php
                                    $vacation = $user['vacationDaysPerYear'] ?? null;
                                    $start = $user['workingTimeModelStartDate'] ?? null;
                                    $end = $user['workingTimeModelEndDate'] ?? null;
                                    $validity = $start ? ($formatDate($start) . ($end ? ' – ' . $formatDate($end) : ' – ' . $l->t('ongoing'))) : '-';
                                    $stichtag = $user['overtimeTrackingFrom'] ?? null;
                                    ?>
                                    <tr data-user-id="<?php p($user['userId']); ?>">
                                        <td data-label="<?php p($l->t('Name')); ?>"><?php p($user['displayName']); ?></td>
                                        <td data-label="<?php p($l->t('Email')); ?>"><?php p($user['email'] ?? '-'); ?></td>
                                        <td data-label="<?php p($l->t('Working Time Model')); ?>">
                                            <?php if ($user['workingTimeModel']): ?>
                                                <?php p($user['workingTimeModel']['name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted"><?php p($l->t('Not assigned')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="<?php p($l->t('Vacation days')); ?>"><?php p($vacation !== null ? (string)$vacation : '-'); ?></td>
                                        <td data-label="<?php p($l->t('Valid from / to')); ?>"><?php p($validity); ?></td>
                                        <td data-label="<?php p($l->t('Overtime Stichtag')); ?>">
                                            <?php if ($stichtag): ?>
                                                <span class="badge badge--info"><?php p($formatDate($stichtag)); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge--warning" title="<?php p($l->t('Year-to-date balance uses 1 January until configured')); ?>"><?php p($l->t('Not set')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="<?php p($l->t('Status')); ?>">
                                            <?php if ($user['enabled']): ?>
                                                <span class="badge badge--success"><?php p($l->t('Enabled')); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge--error"><?php p($l->t('Disabled')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions-cell" data-label="<?php p($l->t('Actions')); ?>">
                                            <div class="user-actions azc-table-actions" role="group" aria-label="<?php p($l->t('Actions for %s', [$user['displayName']])); ?>">
                                                <a class="azc-btn azc-btn--sm azc-btn--ghost"
                                                        href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.userDetail', ['userId' => $user['userId']])); ?>#assignment-history"
                                                        data-action="history-user"
                                                        data-user-id="<?php p($user['userId']); ?>"
                                                        aria-label="<?php p($l->t('View assignment history for %s', [$user['displayName']])); ?>"
                                                        title="<?php p($l->t('View model assignment history')); ?>">
                                                    <?php p($l->t('History')); ?>
                                                </a>
                                                <a class="azc-btn azc-btn--sm azc-btn--secondary"
                                                        href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.userDetail', ['userId' => $user['userId']])); ?>"
                                                        data-action="edit-user"
                                                        data-user-id="<?php p($user['userId']); ?>"
                                                        aria-label="<?php p($l->t('Edit this employee\'s work schedule')); ?>"
                                                        title="<?php p($l->t('Click to change this employee\'s work schedule or other settings')); ?>">
                                                    <?php p($l->t('Edit')); ?>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <nav class="admin-users-pager" id="users-pager" aria-label="<?php p($l->t('Employee list pages')); ?>" hidden>
                    <button type="button" id="users-page-prev" class="azc-btn azc-btn--secondary" disabled>
                        <?php p($l->t('Previous page')); ?>
                    </button>
                    <button type="button" id="users-page-next" class="azc-btn azc-btn--secondary" disabled>
                        <?php p($l->t('Next page')); ?>
                    </button>
                </nav>
                <div class="azc-table-meta pagination-info admin-users-meta" id="users-pagination" aria-live="polite" aria-atomic="true">
                    <p id="users-pagination-text"><?php
                        $shown = count($users);
                        $from = $shown > 0 ? 1 : 0;
                        $to = $shown;
                        p(str_replace(
                            ['{from}', '{to}', '{total}'],
                            [(string) $from, (string) $to, (string) $total],
                            $l->t('Showing employees {from}–{to} of {total}')
                        ));
                    ?></p>
                </div>
            </div><!-- /.azc-card__body -->
        </section><!-- /.azc-card -->
<?php $urlGenerator = $_['urlGenerator'] ?? $urlGenerator ?? null; ?>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
<?php include __DIR__ . '/partials/admin-user-edit-l10n.php'; ?>
    window.ArbeitszeitCheck.adminUsersConfig = <?php echo json_encode([
        'pageSize' => 50,
        'minSearchLength' => 2,
        'initialOffset' => 0,
        'initialTotal' => (int) $total,
        'initialShown' => count($users),
        'organizationTimeCapture' => $_['organizationTimeCapture'] ?? ['clockStampingEnabled' => true, 'manualTimeEntryEnabled' => true],
        'userDetailUrlTemplate' => ($urlGenerator
            ? $urlGenerator->linkToRoute('arbeitszeitcheck.admin.userDetail', ['userId' => '__USER_ID__'])
            : '/apps/arbeitszeitcheck/admin/users/__USER_ID__'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

</div><!-- /.azc-page-stack -->
<?php include __DIR__ . '/common/page-end.php'; ?>
