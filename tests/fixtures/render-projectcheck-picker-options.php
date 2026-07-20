<?php

declare(strict_types=1);

/**
 * Render picker options in the global namespace (matches Nextcloud template runtime).
 *
 * @param list<array<string, mixed>> $projects
 */
function azc_test_render_projectcheck_picker_options(array $projects, string $selectedId, \OCP\IL10N $l): string
{
	// Use the real Nextcloud template helpers (p(), …). Never declare our own p():
	// the server declares it unconditionally, so a local copy fatals with
	// "Cannot redeclare function p()" as soon as any other test renders a template.
	if (!\function_exists('p')) {
		require_once \OC::$SERVERROOT . '/lib/private/Template/functions.php';
	}

	$azcPickerProjects = $projects;
	$azcPickerSelectedId = $selectedId;

	\ob_start();
	include __DIR__ . '/../../templates/common/projectcheck-picker-options.php';

	return (string)\ob_get_clean();
}
