<?php

declare(strict_types=1);

/**
 * Import per-user vacation carryover (Resturlaub opening balance) from CSV for initial setup / migration.
 *
 * CSV format (header required): user_id,year,carryover_days
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Command;

use OCA\ArbeitszeitCheck\Constants;
use OCA\ArbeitszeitCheck\Db\AuditLogMapper;
use OCA\ArbeitszeitCheck\Db\VacationYearBalanceMapper;
use OCA\ArbeitszeitCheck\Service\VacationAllocationService;
use OCA\ArbeitszeitCheck\Service\VacationUnitMigrationService;
use OCA\ArbeitszeitCheck\Service\VacationUnitService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportVacationBalanceCommand extends Command
{
	public function __construct(
		private IUserManager $userManager,
		private VacationYearBalanceMapper $vacationYearBalanceMapper,
		private AuditLogMapper $auditLogMapper,
		private VacationAllocationService $vacationAllocationService,
		private VacationUnitService $vacationUnitService,
		private VacationUnitMigrationService $vacationUnitMigrationService,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->setName('arbeitszeitcheck:import-vacation-balance')
			->setDescription('Import vacation carryover (Resturlaub) opening balances from a CSV file.')
			->addArgument('file', InputArgument::REQUIRED, 'Path to CSV file (columns: user_id,year,carryover_days — amount is in the active vacation unit)')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse and validate only; do not write to the database.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$path = (string)$input->getArgument('file');
		$dryRun = (bool)$input->getOption('dry-run');

		if (!is_readable($path)) {
			$io->error('File not readable: ' . $path);
			return Command::FAILURE;
		}

		$fh = fopen($path, 'rb');
		if ($fh === false) {
			$io->error('Could not open file: ' . $path);
			return Command::FAILURE;
		}

		$header = fgetcsv($fh);
		if ($header === false || count($header) < 3) {
			fclose($fh);
			$io->error('CSV must have a header row with at least: user_id,year,carryover_days');
			return Command::FAILURE;
		}

		$norm = array_map(static fn ($h) => strtolower(trim((string)$h)), $header);
		$idxUser = array_search('user_id', $norm, true);
		$idxYear = array_search('year', $norm, true);
		$idxDays = array_search('carryover_days', $norm, true);
		if ($idxUser === false || $idxYear === false || $idxDays === false) {
			fclose($fh);
			$io->error('Header must contain columns: user_id, year, carryover_days');
			return Command::FAILURE;
		}

		$ok = 0;
		$skipped = 0;
		$lineNum = 1;
		$hoursMode = $this->vacationUnitService->isHoursMode();
		$amountCeiling = $hoursMode ? 4000.0 : 366.0;

		$rows = [];
		while (($row = fgetcsv($fh)) !== false) {
			$lineNum++;
			if (count($row) < 3) {
				$skipped++;
				continue;
			}
			$userId = trim((string)($row[$idxUser] ?? ''));
			$year = (int)($row[$idxYear] ?? 0);
			$days = (float)str_replace(',', '.', trim((string)($row[$idxDays] ?? '')));

			if ($userId === '' || $year < 2000 || $year > 2100) {
				$io->warning("Line $lineNum: invalid user or year, skipped.");
				$skipped++;
				continue;
			}
			if ($days < 0 || $days > $amountCeiling) {
				$io->warning("Line $lineNum: carryover amount out of range (0–{$amountCeiling}), skipped.");
				$skipped++;
				continue;
			}

			$user = $this->userManager->get($userId);
			if ($user === null || !$user->isEnabled()) {
				$io->warning("Line $lineNum: user not found or disabled: $userId");
				$skipped++;
				continue;
			}

			$rows[] = ['user_id' => $userId, 'year' => $year, 'days' => $days, 'line' => $lineNum];
		}
		fclose($fh);

		if ($dryRun) {
			$io->success('Dry run: ' . count($rows) . " rows would be imported ($skipped skipped).");
			return Command::SUCCESS;
		}

		try {
			$this->vacationUnitMigrationService->withIdleShared(function () use ($rows, $hoursMode, &$ok): void {
				foreach ($rows as $row) {
					$stored = $this->vacationAllocationService->applyCapToOpeningBalance($row['days']);
					$this->vacationYearBalanceMapper->upsert(
						$row['user_id'],
						$row['year'],
						$stored,
						$hoursMode ? $stored : null,
						!$hoursMode
					);
					try {
						$this->auditLogMapper->logAction(
							$row['user_id'],
							'vacation_balance_import',
							'vacation_year_balance',
							null,
							null,
							[
								'year' => $row['year'],
								'carryover_days' => $stored,
								'carryover_days_csv' => $row['days'],
								'vacation_unit' => $hoursMode ? 'hours' : 'days',
							],
							'cli'
						);
					} catch (\Throwable $e) {
						// Audit is best-effort for CLI
					}
					$ok++;
				}
			});
		} catch (\RuntimeException $e) {
			if ($e->getMessage() === Constants::VAC_UNIT_MIGRATE_IN_PROGRESS) {
				$io->error('Vacation unit migration is in progress. Retry the import after it finishes.');
				return Command::FAILURE;
			}
			throw $e;
		}

		$io->success("Imported $ok carryover rows ($skipped skipped).");
		return Command::SUCCESS;
	}
}
