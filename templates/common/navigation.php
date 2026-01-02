<?php

/**
 * Common navigation template for the arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

// Get URL generator from OC server
$urlGenerator = \OC::$server->getURLGenerator();

// Get translation object if not available
if (!isset($l)) {
    $l = \OC::$server->getL10N('arbeitszeitcheck');
}

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

// Stats removed - they don't make sense in the sidebar
?>

<!-- Mobile hamburger menu button -->
<button class="nav-mobile-toggle" 
        id="nav-mobile-toggle" 
        aria-label="<?php p($l->t('Open navigation menu')); ?>" 
        aria-expanded="false"
        aria-controls="app-navigation"
        title="<?php p($l->t('Click to open or close the navigation menu')); ?>">
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
</button>

<!-- Mobile overlay background -->
<div class="nav-mobile-overlay" id="nav-mobile-overlay" aria-hidden="true"></div>

<div id="app-navigation" role="navigation" aria-label="<?php p($l->t('Main navigation')); ?>">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="app-brand">
            <div class="app-icon">
                <i data-lucide="clock" class="lucide-icon"></i>
            </div>
            <div class="app-info">
                <h3><?php p($l->t('ArbeitszeitCheck')); ?></h3>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="nav-menu">
        <li class="<?php echo $isDashboard ? 'active' : ''; ?>" <?php echo $isDashboard ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.index')); ?>"
               title="<?php p($l->t('Dashboard: See your current work status, today\'s hours, and recent time entries')); ?>"
               aria-label="<?php p($l->t('Go to dashboard to see your work status and today\'s hours')); ?>">
                <i data-lucide="home" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Dashboard')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isTimeEntries ? 'active' : ''; ?>" <?php echo $isTimeEntries ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.timeEntries')); ?>"
               title="<?php p($l->t('Time Entries: View, add, edit, or delete all your working time records')); ?>"
               aria-label="<?php p($l->t('Go to time entries to see all your working time records')); ?>">
                <i data-lucide="clock" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Time Entries')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isAbsences ? 'active' : ''; ?>" <?php echo $isAbsences ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.absences')); ?>"
               title="<?php p($l->t('Absences: Request and manage vacation days, sick leave, and other time off')); ?>"
               aria-label="<?php p($l->t('Go to absences to request vacation or sick leave')); ?>">
                <i data-lucide="calendar-off" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Absences')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isReports ? 'active' : ''; ?>" <?php echo $isReports ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.reports')); ?>"
               title="<?php p($l->t('Reports: Create and download reports about your working time')); ?>"
               aria-label="<?php p($l->t('Go to reports to create and download working time reports')); ?>">
                <i data-lucide="file-text" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Reports')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isCalendar ? 'active' : ''; ?>" <?php echo $isCalendar ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.calendar')); ?>"
               title="<?php p($l->t('Calendar: View your working time and absences in a calendar view')); ?>"
               aria-label="<?php p($l->t('Go to calendar to see your working time in a calendar')); ?>">
                <i data-lucide="calendar" class="lucide-icon"></i>
                <span><?php p($l->t('Calendar')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isTimeline ? 'active' : ''; ?>" <?php echo $isTimeline ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.timeline')); ?>"
               title="<?php p($l->t('Timeline: See your working time history in chronological order')); ?>"
               aria-label="<?php p($l->t('Go to timeline to see your working time history')); ?>">
                <i data-lucide="activity" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Timeline')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isSettings ? 'active' : ''; ?>" <?php echo $isSettings ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.settings')); ?>"
               title="<?php p($l->t('Settings: Change your personal preferences and working time settings')); ?>"
               aria-label="<?php p($l->t('Go to settings to change your preferences')); ?>">
                <i data-lucide="settings" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Settings')); ?></span>
            </a>
        </li>
    </ul>
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
        document.querySelectorAll('[data-lucide]').forEach(function(el) {
            const iconName = el.getAttribute('data-lucide');
            if (arbeitszeitcheckNavSvgIcons[iconName]) {
                el.innerHTML = arbeitszeitcheckNavSvgIcons[iconName];
            }
        });
    });
</script>
