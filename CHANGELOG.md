# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-12-29

### Added
- Initial release of ArbeitszeitCheck / TimeGuard
- German labor law (ArbZG) compliant time tracking
- GDPR/DSGVO compliant data handling
- Employee self-service portal with dashboard
- Clock in/out functionality with break tracking
- Absence management (vacation, sick leave, etc.)
- Manager dashboard and approval workflows
- Compliance violation detection and reporting
- Integration with ProjectCheck app (optional)
- Working time models configuration
- Audit logging for all data operations
- Data export for GDPR compliance
- Admin and personal settings pages
- RESTful API endpoints
- Vue.js 3 frontend with Nextcloud Vue components
- WCAG 2.1 AAA accessibility compliance
- Comprehensive test suite
- German and English translations
- Privacy policy templates (English and German)
- GitHub Actions CI/CD workflow for automated testing
- Accessibility testing with jest-axe
- Composer scripts for linting and code analysis

### Fixed
- Added default values to boolean properties in entity classes for data integrity
- Updated boolean column definitions in migration to use `notnull => false` with `default => false` for better data flexibility and consistency
- Implemented missing `requestAbsence()` method in Absences.vue with full modal form functionality
- Implemented missing `exportAbsences()` method in Absences.vue with CSV export download
- Added missing `/api/absences/stats` endpoint to AbsenceController for vacation and sick leave statistics
- Implemented missing `viewEmployee()` method in ManagerDashboard.vue with employee detail modal showing comprehensive statistics
- Added missing `/api/compliance/run-check` endpoint to ComplianceController for manual compliance check triggering (admin only)
- Removed duplicate table `arbeitszeitcheck_working_time_models` with too long name - using `at_models` instead to comply with Nextcloud table name length restrictions
- Added Docker setup guide (DOCKER_SETUP.md) with instructions for building frontend assets in Docker environments
- Updated README.md with Docker installation instructions
- Fixed syntax errors in Timeline.vue, Reports.vue, and ComplianceReports.vue (escaped quotes in translation strings)
- Updated build.sh to use build:dev as fallback for Docker environments with limited memory