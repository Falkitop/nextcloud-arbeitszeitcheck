<?php

declare(strict_types=1);

/**
 * Common navigation template for the arbeitszeitcheck app
 *
 * Shows Manager link only to users who can actually use it (admin, manager group, or have team members).
 * Substitution requests shown to all (anyone can be selected as substitute).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCA\ArbeitszeitCheck\Constants;
use OCP\Util;

// Ensure navigation scripts load on all pages with sidebar (mobile menu, keyboard nav, SVG icons)
Util::addScript('arbeitszeitcheck', 'common/navigation');
Util::addScript('arbeitszeitcheck', 'common/navigation-icons');

// Single, authoritative emission of the JavaScript timezone bootstrap on every
// page that uses the shared navigation. This guarantees that `ArbeitszeitCheck.tz`
// and `ArbeitszeitCheck.serverNow` are always defined for the `common/time.js`
// module, no matter which page rendered the template.
require __DIR__ . '/time-bootstrap.php';

// URL generator and translation must be passed in from the controller
/** @var array<string, mixed> $_ */
/** @var \OCP\IURLGenerator $urlGenerator */
/** @var \OCP\IL10N $l */
$urlGenerator = $_['urlGenerator'];
$l = $_['l'];

// Get current page to highlight active navigation item
$currentPage = $_SERVER['REQUEST_URI'] ?? '';
$isTimeEntries = strpos($currentPage, '/time-entries') !== false;
$isAbsences = strpos($currentPage, '/absences') !== false;
$isReports = strpos($currentPage, '/reports') !== false;
$isCalendar = strpos($currentPage, '/calendar') !== false;
$isTimeline = strpos($currentPage, '/timeline') !== false;
$isSettings = strpos($currentPage, '/settings') !== false;
$isManagerPage = strpos($currentPage, '/manager') !== false;
$isManagerTimeEntries = strpos($currentPage, '/manager/time-entries') !== false;
$isManagerAbsences = strpos($currentPage, '/manager/absences') !== false;
$isManagerMonthClosures = strpos($currentPage, '/manager/month-closures') !== false;
$isManagerDashboard = $isManagerPage && !$isManagerTimeEntries && !$isManagerAbsences && !$isManagerMonthClosures;
$isSubstitutionRequests = strpos($currentPage, '/substitution-requests') !== false;
$isCompliance = strpos($currentPage, '/compliance') !== false;
$isAdmin = strpos($currentPage, '/admin') !== false;
// Finer-grained admin section flags for clear highlighting of sub-items
$isAdminDashboard = strpos($currentPage, '/admin/dashboard') !== false || ($isAdmin && strpos($currentPage, '/admin/') === false);
$isAdminUsers = strpos($currentPage, '/admin/users') !== false;
$isAdminWorkingTimeModels = strpos($currentPage, '/admin/working-time-models') !== false;
$isAdminTariffRules = strpos($currentPage, '/admin/tariff-rules') !== false;
$isAdminHolidays = strpos($currentPage, '/admin/holidays') !== false;
$isAdminTeams = strpos($currentPage, '/admin/teams') !== false;
$isAdminVacationLayers = strpos($currentPage, '/admin/vacation-layers') !== false;
$isAdminAuditLog = strpos($currentPage, '/admin/audit-log') !== false;
$isAdminSettingsPage = strpos($currentPage, '/admin/settings') !== false;
$isAdminLicensePage = strpos($currentPage, '/admin/license') !== false;
$isAdminKioskPage = strpos($currentPage, '/admin/kiosk') !== false;
$isAdminNotificationsPage = strpos($currentPage, '/admin/notifications') !== false;
$isAdminOvertimePayoutsPage = strpos($currentPage, '/admin/overtime-payouts') !== false;
$isAdminOvertimePayoutAuditPage = strpos($currentPage, '/admin/overtime-payout-audit') !== false;
// Dashboard is active if URL contains /dashboard OR if it's the base app URL without any specific section
$isDashboard = strpos($currentPage, '/dashboard') !== false ||
    (!$isTimeEntries && !$isAbsences && !$isReports && !$isCompliance && !$isCalendar && !$isTimeline && !$isSettings &&
        !$isSubstitutionRequests && !$isAdmin && strpos($currentPage, '/apps/arbeitszeitcheck') !== false) && !$isManagerPage;

// Show Substitution requests link only when user has pending requests (where they are the substitute)
$showSubstitutionLink = !empty($_['showSubstitutionLink']);

// Show Manager link only when user can actually access the manager dashboard (admin or has team members)
$showManagerLink = !empty($_['showManagerLink']);

// Show Reports link only when the user can access manager features (manager dashboard) or is an admin.
// This keeps the Reports area strictly limited to managers and administrators.
$showReportsLink = !empty($_['showReportsLink']);
// Admin section visibility (admin navigation)
$showAdminNav = !empty($_['showAdminNav']);

// Revision PDFs (month closure): prefer controller-provided flag; otherwise read app config so the item appears on every page (e.g. dashboard) when the feature is on.
$monthClosureEnabledNav = array_key_exists('monthClosureEnabled', $_)
	? !empty($_['monthClosureEnabled'])
	: (\OCP\Server::get(\OCP\IConfig::class)->getAppValue('arbeitszeitcheck', Constants::CONFIG_MONTH_CLOSURE_ENABLED, '0') === '1');
?>

<!-- App layout wrapper: flex container for sidebar + content (desktop), stacked (mobile) -->
<div id="arbeitszeitcheck-app" class="arbeitszeitcheck-app">
    <a href="#app-navigation" class="skip-link azc-skip-link--nav" aria-describedby="azc-skiplinks-help"><?php p($l->t('Skip to app navigation')); ?></a>
    <p id="azc-skiplinks-help" class="visually-hidden">
        <?php p($l->t('Help: These skip links let you jump directly to the main content or to the app navigation.')); ?>
    </p>
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
                    <i data-lucide="clock" class="lucide-icon" aria-hidden="true"></i>
                </div>
                <div class="app-info">
                    <h3><?php p($l->t('ArbeitszeitCheck')); ?></h3>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <ul class="nav-menu">
            <li class="<?php p($isDashboard ? 'active' : ''); ?>" <?php p($isDashboard ? 'aria-current="page"' : ''); ?>>
                <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.index')); ?>"
                    title="<?php p($l->t('Dashboard: View your current status, today\'s hours, and recent entries')); ?>"
                    aria-label="<?php p($l->t('Go to dashboard to see your status and today\'s hours')); ?>">
                    <i data-lucide="home" class="lucide-icon" aria-hidden="true"></i>
                    <span><?php p($l->t('Dashboard')); ?></span>
                </a>
            </li>
            <li class="<?php p($isTimeEntries ? 'active' : ''); ?>" <?php p($isTimeEntries ? 'aria-current="page"' : ''); ?>>
                <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.timeEntries')); ?>"
                    title="<?php p($l->t('Time entries: View, create, and edit working time records')); ?>"
                    aria-label="<?php p($l->t('Go to time entries to view all working times')); ?>">
                    <i data-lucide="clock" class="lucide-icon" aria-hidden="true"></i>
                    <span><?php p($l->t('Time entries')); ?></span>
                </a>
            </li>
            <li class="<?php p($isAbsences ? 'active' : ''); ?>" <?php p($isAbsences ? 'aria-current="page"' : ''); ?>>
                <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.absences')); ?>"
                    title="<?php p($l->t('Absences: Manage vacation, sickness, and other absences')); ?>"
                    aria-label="<?php p($l->t('Go to absences to manage vacation and sick leave')); ?>">
                    <i data-lucide="calendar-off" class="lucide-icon" aria-hidden="true"></i>
                    <span><?php p($l->t('Absences')); ?></span>
                </a>
            </li>
            <li class="<?php p($isCalendar ? 'active' : ''); ?>" <?php p($isCalendar ? 'aria-current="page"' : ''); ?>>
                <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.calendar')); ?>"
                    title="<?php p($l->t('Calendar: View working times and absences in calendar format')); ?>"
                    aria-label="<?php p($l->t('Go to calendar to view working times in a calendar')); ?>">
                    <i data-lucide="calendar" class="lucide-icon" aria-hidden="true"></i>
                    <span><?php p($l->t('Calendar')); ?></span>
                </a>
            </li>
            <li class="<?php p($isTimeline ? 'active' : ''); ?>" <?php p($isTimeline ? 'aria-current="page"' : ''); ?>>
                <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.timeline')); ?>"
                    title="<?php p($l->t('Timeline: View working times in chronological order')); ?>"
                    aria-label="<?php p($l->t('Go to timeline to view your working-time history')); ?>">
                    <i data-lucide="activity" class="lucide-icon" aria-hidden="true"></i>
                    <span><?php p($l->t('Timeline')); ?></span>
                </a>
            </li>
            <li class="<?php p($isCompliance ? 'active' : ''); ?>" <?php p($isCompliance ? 'aria-current="page"' : ''); ?>>
                <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.compliance.dashboard')); ?>"
                    title="<?php p($l->t('Working time compliance: Check whether your times comply with German labor law')); ?>"
                    aria-label="<?php p($l->t('Go to working time compliance to review compliance status')); ?>">
                    <i data-lucide="shield-check" class="lucide-icon" aria-hidden="true"></i>
                    <span><?php p($l->t('Working time compliance')); ?></span>
                </a>
            </li>
            <li class="nav-section-divider" role="separator" aria-hidden="true"></li>
            <li class="<?php p($isSettings ? 'active' : ''); ?>" <?php p($isSettings ? 'aria-current="page"' : ''); ?>>
                <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.settings')); ?>"
                    title="<?php p($l->t('My settings: Customize personal view and notification options')); ?>"
                    aria-label="<?php p($l->t('Go to my settings to change personal options')); ?>">
                    <i data-lucide="settings" class="lucide-icon" aria-hidden="true"></i>
                    <span><?php p($l->t('My settings')); ?></span>
                </a>
            </li>
            <?php if ($showAdminNav): ?>
                <li class="nav-section-divider" role="separator" aria-hidden="true"></li>
                <li class="nav-item-has-children <?php p($isAdmin ? 'is-open' : ''); ?>">
                    <button class="nav-parent-toggle"
                        type="button"
                        aria-expanded="<?php p($isAdmin ? 'true' : 'false'); ?>"
                        aria-controls="admin-subnav">
                        <i data-lucide="shield" class="lucide-icon" aria-hidden="true"></i>
                        <span><?php p($l->t('Administration')); ?></span>
                    </button>
                    <ul id="admin-subnav" class="nav-submenu" <?php p($isAdmin ? '' : 'hidden'); ?>>
                        <li class="<?php p($isAdminDashboard ? 'active' : ''); ?>" <?php p($isAdminDashboard ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.dashboard')); ?>"
                                title="<?php p($l->t('Status overview with key metrics and current alerts')); ?>"
                                aria-label="<?php p($l->t('Open administration status')); ?>">
                                <span><?php p($l->t('Status')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminNotificationsPage ? 'active' : ''); ?>" <?php p($isAdminNotificationsPage ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.notifications')); ?>"
                                title="<?php p($l->t('Configure notification rules for absences and HR mailbox')); ?>"
                                aria-label="<?php p($l->t('Open notification settings')); ?>">
                                <span><?php p($l->t('Notifications')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminOvertimePayoutsPage ? 'active' : ''); ?>" <?php p($isAdminOvertimePayoutsPage ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.index')); ?>"
                                title="<?php p($l->t('Month-end payout of overtime above the bank cap')); ?>"
                                aria-label="<?php p($l->t('Open overtime payouts')); ?>">
                                <span><?php p($l->t('Overtime payouts')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminOvertimePayoutAuditPage ? 'active' : ''); ?>" <?php p($isAdminOvertimePayoutAuditPage ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.overtime_payout.auditIndex')); ?>"
                                title="<?php p($l->t('Audit registry of recorded overtime payouts')); ?>"
                                aria-label="<?php p($l->t('Open overtime payout audit')); ?>">
                                <span><?php p($l->t('Payout audit')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminUsers ? 'active' : ''); ?>" <?php p($isAdminUsers ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.users')); ?>"
                                title="<?php p($l->t('Manage employees and working time models')); ?>"
                                aria-label="<?php p($l->t('Manage employees')); ?>">
                                <span><?php p($l->t('Employees')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminWorkingTimeModels ? 'active' : ''); ?>" <?php p($isAdminWorkingTimeModels ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.workingTimeModels')); ?>"
                                title="<?php p($l->t('Configure working time models')); ?>"
                                aria-label="<?php p($l->t('Manage working time models')); ?>">
                                <span><?php p($l->t('Working time models')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminTariffRules ? 'active' : ''); ?>" <?php p($isAdminTariffRules ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.tariffRuleSets')); ?>"
                                title="<?php p($l->t('Manage tariff rule sets used for vacation entitlement calculations')); ?>"
                                aria-label="<?php p($l->t('Manage tariff rule sets')); ?>">
                                <span><?php p($l->t('Tariff rule sets')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminHolidays ? 'active' : ''); ?>" <?php p($isAdminHolidays ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.holidays')); ?>"
                                title="<?php p($l->t('Manage state holiday calendars and default calendar')); ?>"
                                aria-label="<?php p($l->t('Manage holidays and calendars')); ?>">
                                <span><?php p($l->t('Holidays and calendars')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminTeams ? 'active' : ''); ?>" <?php p($isAdminTeams ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.teams')); ?>"
                                title="<?php p($l->t('Manage teams, locations, and responsibilities')); ?>"
                                aria-label="<?php p($l->t('Manage teams')); ?>">
                                <span><?php p($l->t('Teams and locations')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminVacationLayers ? 'active' : ''); ?>" <?php p($isAdminVacationLayers ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.vacationLayers')); ?>"
                                title="<?php p($l->t('Configure layered vacation entitlement defaults (organisation, working time models, teams)')); ?>"
                                aria-label="<?php p($l->t('Open vacation entitlement layers')); ?>">
                                <span><?php p($l->t('Vacation entitlement')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminAuditLog ? 'active' : ''); ?>" <?php p($isAdminAuditLog ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.auditLog')); ?>"
                                title="<?php p($l->t('View audit log')); ?>"
                                aria-label="<?php p($l->t('Open audit log')); ?>">
                                <span><?php p($l->t('Audit log')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminLicensePage ? 'active' : ''); ?>" <?php p($isAdminLicensePage ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.license_admin.index')); ?>"
                                title="<?php p($l->t('Organisation license for Mobile and Terminal apps')); ?>"
                                aria-label="<?php p($l->t('Open license settings')); ?>">
                                <span><?php p($l->t('License')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminKioskPage ? 'active' : ''); ?>" <?php p($isAdminKioskPage ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.kiosk_admin.index')); ?>"
                                title="<?php p($l->t('Foyer kiosk terminals and employee badges')); ?>"
                                aria-label="<?php p($l->t('Open kiosk administration')); ?>">
                                <span><?php p($l->t('Kiosk')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isAdminSettingsPage ? 'active' : ''); ?>" <?php p($isAdminSettingsPage ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.admin.settings')); ?>"
                                title="<?php p($l->t('Manage global rules, access control, and compliance settings')); ?>"
                                aria-label="<?php p($l->t('Open global administration settings')); ?>">
                                <span><?php p($l->t('Global settings')); ?></span>
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>
            <?php if ($showManagerLink): ?>
                <li class="nav-section-divider" role="separator" aria-hidden="true"></li>
                <li class="nav-item-has-children <?php p($isManagerPage ? 'is-open' : ''); ?>">
                    <button class="nav-parent-toggle"
                        type="button"
                        aria-expanded="<?php p($isManagerPage ? 'true' : 'false'); ?>"
                        aria-controls="manager-subnav">
                        <i data-lucide="users" class="lucide-icon" aria-hidden="true"></i>
                        <span><?php p($l->t('Manager')); ?></span>
                    </button>
                    <ul id="manager-subnav" class="nav-submenu" <?php p($isManagerPage ? '' : 'hidden'); ?>>
                        <li class="<?php p($isManagerDashboard ? 'active' : ''); ?>" <?php p($isManagerDashboard ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.manager.dashboard')); ?>"
                                title="<?php p($l->t('Manager: Approve absences and time-entry corrections, view team overview')); ?>"
                                aria-label="<?php p($l->t('Go to manager dashboard to approve requests and view your team')); ?>">
                                <span><?php p($l->t('Overview')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isManagerTimeEntries ? 'active' : ''); ?>" <?php p($isManagerTimeEntries ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.manager.employeeTimeEntriesPage')); ?>"
                                title="<?php p($l->t('View employee time entries directly in the app (managers/admins)')); ?>"
                                aria-label="<?php p($l->t('Open employee time entries')); ?>">
                                <span><?php p($l->t('Employee time entries')); ?></span>
                            </a>
                        </li>
                        <li class="<?php p($isManagerAbsences ? 'active' : ''); ?>" <?php p($isManagerAbsences ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.manager.employeeAbsencesPage')); ?>"
                                title="<?php p($l->t('View employee absences directly in the app (managers/admins)')); ?>"
                                aria-label="<?php p($l->t('Open employee absences')); ?>">
                                <span><?php p($l->t('Employee absences')); ?></span>
                            </a>
                        </li>
                        <?php if ($monthClosureEnabledNav): ?>
                        <li class="<?php p($isManagerMonthClosures ? 'active' : ''); ?>" <?php p($isManagerMonthClosures ? 'aria-current="page"' : ''); ?>>
                            <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.manager.monthClosuresPage')); ?>"
                                title="<?php p($l->t('Download revision-safe monthly PDFs for team members (same document as employees).')); ?>"
                                aria-label="<?php p($l->t('Open revision PDFs for employees')); ?>">
                                <span><?php p($l->t('Revision PDFs')); ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($showReportsLink): ?>
                            <li class="<?php p($isReports ? 'active' : ''); ?>" <?php p($isReports ? 'aria-current="page"' : ''); ?>>
                                <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.reports')); ?>"
                                    title="<?php p($l->t('Reports for team and organization overviews (visible to managers/admins only)')); ?>"
                                    aria-label="<?php p($l->t('Go to reports to create team or organization overviews (managers/admins only)')); ?>">
                                    <span><?php p($l->t('Reports')); ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($showSubstitutionLink): ?>
                            <li class="<?php p($isSubstitutionRequests ? 'active' : ''); ?>" <?php p($isSubstitutionRequests ? 'aria-current="page"' : ''); ?>>
                                <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.substitute.index')); ?>"
                                    title="<?php p($l->t('Substitution requests: Accept or decline colleague coverage requests')); ?>"
                                    aria-label="<?php p($l->t('Go to substitution requests')); ?>">
                                    <span><?php p($l->t('Substitution requests')); ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>
            <?php if (!$showManagerLink && $showSubstitutionLink): ?>
                <li class="nav-section-divider" role="separator" aria-hidden="true"></li>
            <?php endif; ?>
            <?php if (!$showManagerLink && $showReportsLink): ?>
                <li class="nav-section-divider" role="separator" aria-hidden="true"></li>
                <li class="<?php p($isReports ? 'active' : ''); ?>" <?php p($isReports ? 'aria-current="page"' : ''); ?>>
                    <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.page.reports')); ?>"
                        title="<?php p($l->t('Reports for team and organization overviews (visible to managers/admins only)')); ?>"
                        aria-label="<?php p($l->t('Go to reports to create team or organization overviews (managers/admins only)')); ?>">
                        <i data-lucide="file-text" class="lucide-icon" aria-hidden="true"></i>
                        <span><?php p($l->t('Reports')); ?></span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($showSubstitutionLink && !$showManagerLink): ?>
                <li class="<?php p($isSubstitutionRequests ? 'active' : ''); ?>" <?php p($isSubstitutionRequests ? 'aria-current="page"' : ''); ?>>
                    <a href="<?php p($urlGenerator->linkToRoute('arbeitszeitcheck.substitute.index')); ?>"
                        title="<?php p($l->t('Substitution requests: Accept or decline colleague coverage requests')); ?>"
                        aria-label="<?php p($l->t('Go to substitution requests')); ?>">
                        <i data-lucide="user-check" class="lucide-icon" aria-hidden="true"></i>
                        <span><?php p($l->t('Substitution requests')); ?></span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Icon rendering now handled by bundled JS: js/common/navigation-icons.js -->