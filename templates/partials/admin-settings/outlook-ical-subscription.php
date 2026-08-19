<?php
declare(strict_types=1);

/**
 * Admin settings section for calendar (iCal) subscriptions.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */

use OCA\ArbeitszeitCheck\Service\OutlookIcalSubscriptionLanguageCatalog;

/** @var \OCP\IL10N $l */
$useAppTeams = !empty($useAppTeams) || !empty($_['useAppTeams']);
$azcSettingsShowCardChrome = !empty($azcSettingsShowCardChrome) || !empty($renderAll);
$outlookIntroDescId = !empty($azcSettingsShowCardChrome) ? 'outlook-ical-subscription-intro' : 'azc-page-help';
$outlookTeamsUrl = ($urlGenerator ?? $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class))
	->linkToRoute('arbeitszeitcheck.outlook_ical_subscription.adminTeams');
$outlookRotateUrl = ($urlGenerator ?? $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class))
	->linkToRoute('arbeitszeitcheck.outlook_ical_subscription.adminRotateToken');
$outlookWebcalLocalAccessUrl = ($urlGenerator ?? $_['urlGenerator'] ?? \OCP\Server::get(\OCP\IURLGenerator::class))
	->linkToRoute('arbeitszeitcheck.outlook_ical_subscription.adminWebcalLocalAccess');
$outlookFeedLanguageOptions = OutlookIcalSubscriptionLanguageCatalog::optionsForUi();
$outlookDefaultFeedLanguage = OutlookIcalSubscriptionLanguageCatalog::resolveDefault($l->getLanguageCode());
?>
<section id="outlook-ical-subscription"
	class="azc-card admin-settings-section admin-settings-section--outlook-subscription"
	aria-labelledby="section-outlook-subscription-heading"
	data-outlook-teams-url="<?php p($outlookTeamsUrl); ?>"
	data-outlook-rotate-url="<?php p($outlookRotateUrl); ?>"
	data-outlook-webcal-local-access-url="<?php p($outlookWebcalLocalAccessUrl); ?>"
	data-use-app-teams="<?php echo $useAppTeams ? '1' : '0'; ?>"
	data-org-wide-available="1"
	data-default-feed-language="<?php p($outlookDefaultFeedLanguage); ?>">
	<header class="azc-card__header<?php echo empty($azcSettingsShowCardChrome) ? ' azc-card__header--page-title-only' : ''; ?>">
		<div class="azc-card__header-text">
			<?php if (!empty($azcSettingsShowCardChrome)): ?>
			<h2 id="section-outlook-subscription-heading" class="azc-card__title"><?php p($l->t('Calendar subscription (Per team, privacy-safe)')); ?></h2>
			<p class="azc-card__lead" id="outlook-ical-subscription-intro">
				<?php p($l->t('Generate one calendar subscription link per scope. The feed contains approved absences only and never includes free-text reasons.')); ?>
			</p>
			<?php else: ?>
			<h2 id="section-outlook-subscription-heading" class="azc-card__title visually-hidden"><?php p($l->t('Calendar subscription (Per team, privacy-safe)')); ?></h2>
			<?php endif; ?>
		</div>
	</header>
	<div class="azc-card__body">
		<?php if (!$useAppTeams): ?>
		<div class="form-help form-help--note" role="status" aria-live="polite" aria-atomic="true">
			<?php p($l->t('No app teams configured? Choose “All employees” below — ideal for small teams without departments.')); ?>
		</div>
		<?php endif; ?>
		<fieldset class="form-fieldset" aria-describedby="<?php p($outlookIntroDescId); ?> outlook-ical-subscription-help outlookIcalWindowHelp">
			<legend class="form-legend"><?php p($l->t('Subscription setup')); ?></legend>
			<div class="form-row form-row--2">
				<div class="form-group">
					<label for="outlookIcalTeamSearch" class="form-label"><?php p($l->t('Who is included?')); ?></label>
					<input type="hidden" id="outlookIcalTeamId" value="">
					<div class="user-picker" id="outlook-ical-team-picker">
						<div class="user-picker__control">
							<input type="search"
								id="outlookIcalTeamSearch"
								class="form-input user-picker__search"
								autocomplete="off"
								spellcheck="false"
								placeholder="<?php p($l->t('Search scopes…')); ?>"
								role="combobox"
								aria-autocomplete="list"
								aria-expanded="false"
								aria-controls="outlookIcalTeamListbox"
								aria-describedby="outlookIcalTeamHelp outlookIcalTeamStatus">
							<button type="button"
								class="user-picker__clear"
								id="outlookIcalTeamClear"
								hidden
								aria-label="<?php p($l->t('Clear selected scope')); ?>">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>
						<div id="outlookIcalTeamListbox"
							class="user-picker__list"
							role="listbox"
							hidden
							aria-label="<?php p($l->t('Matching scopes')); ?>"></div>
						<p id="outlookIcalTeamStatus" class="azc-sr-only" role="status" aria-live="polite" aria-atomic="true"></p>
					</div>
					<p id="outlookIcalTeamHelp" class="form-help">
						<?php p($l->t('Choose “All employees” for the whole company, or a specific team when app-owned teams are enabled. Child teams are included automatically.')); ?>
					</p>
				</div>

				<div class="form-group">
					<label for="outlookIcalFeedLanguage" class="form-label"><?php p($l->t('Calendar language')); ?></label>
					<select id="outlookIcalFeedLanguage"
						class="form-select"
						aria-describedby="outlookIcalFeedLanguageHelp">
						<?php foreach ($outlookFeedLanguageOptions as $option): ?>
							<option value="<?php p($option['code']); ?>"<?php if ($option['code'] === $outlookDefaultFeedLanguage) { echo ' selected'; } ?>>
								<?php p($option['label']); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p id="outlookIcalFeedLanguageHelp" class="form-help">
						<?php p($l->t('Labels in the calendar feed (for example “Vacation” or “Urlaub”). Employee names stay as in Nextcloud.')); ?>
					</p>
					<p class="form-help form-help--note">
						<?php p($l->t('Each event title shows the employee name and absence type (for example “Alex (Vacation)”). After changing language or scope, generate a new link and update your calendar subscription.')); ?>
					</p>
					<p class="form-help form-help--note">
						<?php p($l->t('In Thunderbird: delete the old subscribed calendar completely, then add the new link. Cached events can otherwise keep showing generic English titles.')); ?>
					</p>
					<p class="form-help form-help--note">
						<?php p($l->t('Subscribe from URL (iCal/webcal) — not CalDAV: in Nextcloud Calendar use “+ New subscription” in the left sidebar and paste the link. Do not use “Add calendar account” or pick calendars from a list — that is CalDAV, not this feed.')); ?>
					</p>
					<div id="outlookIcalWebcalLocalAccess" class="form-help form-help--note" hidden role="status" aria-live="polite">
						<p id="outlookIcalWebcalLocalAccessText"></p>
						<button type="button"
							id="outlookIcalEnableWebcalLocalBtn"
							class="azc-btn azc-btn--secondary azc-btn--touch"
							hidden>
							<?php p($l->t('Allow Nextcloud Calendar subscriptions on this server')); ?>
						</button>
					</div>
				</div>
			</div>

			<p id="outlookIcalWindowHelp" class="form-help">
				<?php p($l->t('Each refresh includes approved absences from the last 3 months through the next 12 months. The window moves forward automatically — subscribe once.')); ?>
			</p>

			<div class="card-actions card-actions--inline">
				<button type="button" id="outlookIcalGenerateBtn" class="azc-btn azc-btn--primary azc-btn--touch">
					<?php p($l->t('Generate feed URL')); ?>
				</button>
				<button type="button" id="outlookIcalRotateBtn" class="azc-btn azc-btn--secondary azc-btn--touch" disabled>
					<?php p($l->t('Revoke & rotate')); ?>
				</button>
			</div>

			<div class="form-group" id="outlookIcalResultCard" hidden>
				<p class="form-help form-help--note" id="outlookIcalEventCount">
					<?php p($l->t('Approved absences in the current window: 0')); ?>
				</p>
				<p class="form-help" id="outlookIcalWindowRange" hidden></p>
				<label for="outlookIcalFeedUrl" class="form-label"><?php p($l->t('Tokenized calendar subscription URL')); ?></label>
				<textarea id="outlookIcalFeedUrl" class="form-input" rows="3" readonly aria-describedby="outlookIcalFeedHelp outlookIcalLive"></textarea>
				<p id="outlookIcalFeedHelp" class="form-help">
					<?php p($l->t('Copy this link into your calendar app and subscribe from URL (Thunderbird, Nextcloud Calendar, Outlook, and others). The URL ends with .ics so clients recognize it as a calendar feed. Keep it secret like a password.')); ?>
				</p>
				<div class="card-actions card-actions--inline">
					<button type="button" id="outlookIcalCopyBtn" class="azc-btn azc-btn--secondary azc-btn--touch">
						<?php p($l->t('Copy')); ?>
					</button>
				</div>
			</div>

			<div id="outlookIcalLive" class="form-help" role="status" aria-live="polite" aria-atomic="true"></div>
			<p id="outlook-ical-subscription-help" class="form-help form-help--note">
				<?php p($l->t('Security note: the URL is scoped to your selection, stores only a token hash on the server, and is invalidated immediately when you rotate it.')); ?>
			</p>
		</fieldset>
	</div>
</section>
