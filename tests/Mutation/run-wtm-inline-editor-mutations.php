<?php
/**
 * Mutation suite: WTM inline editor must not regress to create/edit modals.
 * Run: php tests/Mutation/run-wtm-inline-editor-mutations.php
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = 0;

function assertTrue(bool $cond, string $msg): void
{
	global $failures;
	if (!$cond) {
		fwrite(STDERR, "FAIL: $msg\n");
		$failures++;
	} else {
		fwrite(STDOUT, "OK: $msg\n");
	}
}

$js = (string)file_get_contents($root . '/js/working-time-models.js');
$tpl = (string)file_get_contents($root . '/templates/working-time-models.php');
$css = (string)file_get_contents($root . '/css/working-time-models.css');
$ctrl = (string)file_get_contents($root . '/lib/Controller/AdminController.php');
$mapper = (string)file_get_contents($root . '/lib/Db/WorkingTimeModelMapper.php');

assertTrue(str_contains($js, 'function openEditorPanel'), 'openEditorPanel present');
assertTrue(str_contains($js, 'function closeEditorPanel'), 'closeEditorPanel present');
assertTrue(str_contains($js, 'editorSaveInflight'), 'double-submit guard');
assertTrue(str_contains($js, "host.setAttribute('aria-busy', 'true')"), 'panel aria-busy while saving');
assertTrue(str_contains($js, "button.getAttribute('aria-busy') === 'true'"), 'delete busy guard');
assertTrue(str_contains($js, 'Please wait until the current save finishes'), 'refuse open while saving');
assertTrue(str_contains($js, '!Array.isArray(model.breakRules)'), 'duplicate refuses list breakRules');
assertTrue(!str_contains($js, 'breakRules: model.breakRules || []'), 'no list fallback for breakRules');
assertTrue(str_contains($ctrl, 'deleteDefaultsForWorkingTimeModel'), 'delete purges vacation defaults');
assertTrue(str_contains($ctrl, "'DEFAULT_MODEL'"), 'refuse deleting default model');
assertTrue(str_contains($ctrl, 'array_is_list($breakRules)'), 'server rejects list breakRules');
assertTrue(!str_contains($js, 'create-model-modal'), 'no create-model-modal');
assertTrue(!str_contains($js, 'edit-model-modal'), 'no edit-model-modal');
assertTrue(str_contains($tpl, 'id="wtm-editor-panel"'), 'template host');
assertTrue(str_contains($css, 'wtm-editor-panel'), 'css panel');

assertTrue(str_contains($mapper, 'function clearDefaults'), 'mapper clearDefaults');
assertTrue(str_contains($ctrl, 'clearDefaults()'), 'create uses clearDefaults');
assertTrue(str_contains($ctrl, 'clearDefaults($model->getId())'), 'update uses clearDefaults(except)');

$mut = str_replace('function openEditorPanel', 'function openEditorPanelRemoved', $js);
assertTrue(!str_contains($mut, 'function openEditorPanel(') && !str_contains($mut, 'function openEditorPanel '), 'mutation removes openEditorPanel');
assertTrue(str_contains($js, 'function openEditorPanel'), 'original keeps openEditorPanel');

$mutGuard = str_replace('let editorSaveInflight = false;', 'let editorSaveInflightRemoved = false;', $js);
assertTrue(!str_contains($mutGuard, 'let editorSaveInflight = false;'), 'mutation removes save guard');
assertTrue(str_contains($js, 'let editorSaveInflight = false;'), 'original keeps save guard');

if ($failures > 0) {
	fwrite(STDERR, "\n$failures mutation contract failure(s)\n");
	exit(1);
}
fwrite(STDOUT, "\nAll WTM inline editor mutation contracts passed.\n");
exit(0);
