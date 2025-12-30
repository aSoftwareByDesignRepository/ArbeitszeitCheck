<?php

/**
 * Common navigation template for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

// Get current page to highlight active navigation item
$currentPage = $_SERVER['REQUEST_URI'] ?? '';
$isTimeEntries = strpos($currentPage, '/time-entries') !== false;
$isAbsences = strpos($currentPage, '/absences') !== false;
$isReports = strpos($currentPage, '/reports') !== false;
$isCalendar = strpos($currentPage, '/calendar') !== false;
$isTimeline = strpos($currentPage, '/timeline') !== false;
$isSettings = strpos($currentPage, '/settings') !== false;
// Dashboard is active if URL contains /dashboard OR if it's the base app URL without any specific section
$isDashboard = strpos($currentPage, '/dashboard') !== false || 
               (!$isTimeEntries && !$isAbsences && !$isReports && !$isCalendar && !$isTimeline && !$isSettings && 
                strpos($currentPage, '/apps/arbeitszeitcheck') !== false);

// Get stats for the footer (if available)
$timeEntryCount = $_['stats']['total_time_entries'] ?? $_['stats']['totalTimeEntries'] ?? 0;
$absenceCount = $_['stats']['total_absences'] ?? $_['stats']['totalAbsences'] ?? 0;

// Ensure we have valid numbers and provide fallbacks
$timeEntryCount = is_numeric($timeEntryCount) ? (int)$timeEntryCount : 0;
$absenceCount = is_numeric($absenceCount) ? (int)$absenceCount : 0;

// If stats are not available, show a loading indicator or default values
if (!isset($_['stats']) || empty($_['stats'])) {
    $timeEntryCount = '...';
    $absenceCount = '...';
}
?>

<div id="arbeitszeitcheck-navigation" role="navigation" aria-label="<?php p($l->t('Main navigation')); ?>">
    <!-- Sidebar Header -->
    <div class="arbeitszeitcheck-navigation__header">
        <div class="arbeitszeitcheck-navigation__brand">
            <div class="arbeitszeitcheck-navigation__icon">
                <i data-lucide="clock" class="lucide-icon"></i>
            </div>
            <div class="arbeitszeitcheck-navigation__info">
                <h3><?php p($l->t('ArbeitszeitCheck')); ?></h3>
                <p><?php p($l->t('Time tracking and compliance')); ?></p>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="arbeitszeitcheck-navigation__menu">
        <li class="<?php echo $isDashboard ? 'arbeitszeitcheck-navigation__item--active' : ''; ?>" <?php echo $isDashboard ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['dashboardUrl'] ?? '/index.php/apps/arbeitszeitcheck/dashboard'); ?>" class="arbeitszeitcheck-navigation__link">
                <i data-lucide="home" class="lucide-icon"></i>
                <span><?php p($l->t('Dashboard')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isTimeEntries ? 'arbeitszeitcheck-navigation__item--active' : ''; ?>" <?php echo $isTimeEntries ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['timeEntriesUrl'] ?? '/index.php/apps/arbeitszeitcheck/time-entries'); ?>" class="arbeitszeitcheck-navigation__link">
                <i data-lucide="clock" class="lucide-icon"></i>
                <span><?php p($l->t('Time Entries')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isAbsences ? 'arbeitszeitcheck-navigation__item--active' : ''; ?>" <?php echo $isAbsences ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['absencesUrl'] ?? '/index.php/apps/arbeitszeitcheck/absences'); ?>" class="arbeitszeitcheck-navigation__link">
                <i data-lucide="calendar-off" class="lucide-icon"></i>
                <span><?php p($l->t('Absences')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isReports ? 'arbeitszeitcheck-navigation__item--active' : ''; ?>" <?php echo $isReports ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['reportsUrl'] ?? '/index.php/apps/arbeitszeitcheck/reports'); ?>" class="arbeitszeitcheck-navigation__link">
                <i data-lucide="file-text" class="lucide-icon"></i>
                <span><?php p($l->t('Reports')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isCalendar ? 'arbeitszeitcheck-navigation__item--active' : ''; ?>" <?php echo $isCalendar ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['calendarUrl'] ?? '/index.php/apps/arbeitszeitcheck/calendar'); ?>" class="arbeitszeitcheck-navigation__link">
                <i data-lucide="calendar" class="lucide-icon"></i>
                <span><?php p($l->t('Calendar')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isTimeline ? 'arbeitszeitcheck-navigation__item--active' : ''; ?>" <?php echo $isTimeline ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['timelineUrl'] ?? '/index.php/apps/arbeitszeitcheck/timeline'); ?>" class="arbeitszeitcheck-navigation__link">
                <i data-lucide="activity" class="lucide-icon"></i>
                <span><?php p($l->t('Timeline')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isSettings ? 'arbeitszeitcheck-navigation__item--active' : ''; ?>" <?php echo $isSettings ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['settingsUrl'] ?? '/index.php/apps/arbeitszeitcheck/settings'); ?>" class="arbeitszeitcheck-navigation__link">
                <i data-lucide="settings" class="lucide-icon"></i>
                <span><?php p($l->t('Settings')); ?></span>
            </a>
        </li>
    </ul>

    <!-- Sidebar Footer -->
    <div class="arbeitszeitcheck-navigation__footer">
        <div class="arbeitszeitcheck-navigation__stats">
            <div class="arbeitszeitcheck-navigation__stat-item">
                <span class="arbeitszeitcheck-navigation__stat-number"><?php p($timeEntryCount); ?></span>
                <span class="arbeitszeitcheck-navigation__stat-label"><?php p($l->t('Entries')); ?></span>
            </div>
            <div class="arbeitszeitcheck-navigation__stat-item">
                <span class="arbeitszeitcheck-navigation__stat-number"><?php p($absenceCount); ?></span>
                <span class="arbeitszeitcheck-navigation__stat-label"><?php p($l->t('Absences')); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Initialize Lucide Icons for Navigation -->
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
    // Local SVG icon library for navigation
    const arbeitszeitcheckNavSvgIcons = {
        clock: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
        home: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>',
        'calendar-off': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M4.18 4.18A2 2 0 0 0 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 1.82-1.18"/><path d="M21 15.5V6a2 2 0 0 0-2-2H9.5"/><path d="M16 2v4"/><path d="M3 10h7"/><path d="M21 10h-5.5"/><line x1="2" y1="2" x2="22" y2="22"/></svg>',
        calendar: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        activity: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>',
        'file-text': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>',
        settings: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.39a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>'
    };

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#arbeitszeitcheck-navigation [data-lucide]').forEach(function(el) {
            const iconName = el.getAttribute('data-lucide');
            if (arbeitszeitcheckNavSvgIcons[iconName]) {
                el.innerHTML = arbeitszeitcheckNavSvgIcons[iconName];
            }
        });
    });
</script>
