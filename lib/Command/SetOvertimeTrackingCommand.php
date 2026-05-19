<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Command;

use OCA\ArbeitszeitCheck\Service\UserOvertimeSettingsService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bulk-set per-user overtime tracking-from date (Stichtag).
 *
 * Usage:
 *   occ arbeitszeitcheck:set-overtime-tracking <user_id> <YYYY-MM-DD>
 *   occ arbeitszeitcheck:set-overtime-tracking --file=users.csv
 */
class SetOvertimeTrackingCommand extends Command
{
	public function __construct(
		private IUserManager $userManager,
		private UserOvertimeSettingsService $overtimeSettingsService,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->setName('arbeitszeitcheck:set-overtime-tracking')
			->setDescription('Set overtime tracking-from date (Stichtag) for one or more users.')
			->addArgument('user_id', InputArgument::OPTIONAL, 'Nextcloud user id')
			->addArgument('tracking_from', InputArgument::OPTIONAL, 'ISO date YYYY-MM-DD, or "clear" to reset to legacy Jan 1')
			->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'CSV file: user_id,tracking_from (header required)')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate only; do not persist.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$dryRun = (bool)$input->getOption('dry-run');
		$actor = 'occ:set-overtime-tracking';

		$file = $input->getOption('file');
		if ($file !== null && $file !== '') {
			return $this->executeFromFile($io, (string)$file, $dryRun, $actor);
		}

		$userId = (string)($input->getArgument('user_id') ?? '');
		$trackingRaw = (string)($input->getArgument('tracking_from') ?? '');
		if ($userId === '' || $trackingRaw === '') {
			$io->error('Provide user_id and tracking_from, or use --file=path.csv');
			return Command::FAILURE;
		}

		return $this->applyOne($io, $userId, $trackingRaw, $dryRun, $actor) ? Command::SUCCESS : Command::FAILURE;
	}

	private function executeFromFile(SymfonyStyle $io, string $path, bool $dryRun, string $actor): int
	{
		if (!is_readable($path)) {
			$io->error('File not readable: ' . $path);
			return Command::FAILURE;
		}
		$fh = fopen($path, 'rb');
		if ($fh === false) {
			$io->error('Could not open: ' . $path);
			return Command::FAILURE;
		}
		$header = fgetcsv($fh);
		if ($header === false) {
			fclose($fh);
			$io->error('Empty CSV');
			return Command::FAILURE;
		}
		$norm = array_map(static fn ($h) => strtolower(trim((string)$h)), $header);
		$idxUser = array_search('user_id', $norm, true);
		$idxDate = array_search('tracking_from', $norm, true);
		if ($idxUser === false || $idxDate === false) {
			fclose($fh);
			$io->error('Header must contain: user_id, tracking_from');
			return Command::FAILURE;
		}
		$ok = 0;
		$fail = 0;
		while (($row = fgetcsv($fh)) !== false) {
			$uid = trim((string)($row[$idxUser] ?? ''));
			$date = trim((string)($row[$idxDate] ?? ''));
			if ($uid === '') {
				continue;
			}
			if ($this->applyOne($io, $uid, $date, $dryRun, $actor, false)) {
				$ok++;
			} else {
				$fail++;
			}
		}
		fclose($fh);
		$io->success(sprintf('Done: %d updated, %d failed%s', $ok, $fail, $dryRun ? ' (dry-run)' : ''));
		return $fail > 0 ? Command::FAILURE : Command::SUCCESS;
	}

	private function applyOne(SymfonyStyle $io, string $userId, string $trackingRaw, bool $dryRun, string $actor, bool $verbose = true): bool
	{
		$user = $this->userManager->get($userId);
		if ($user === null) {
			if ($verbose) {
				$io->error('Unknown user: ' . $userId);
			}
			return false;
		}

		$date = null;
		if ($trackingRaw !== '' && strtolower($trackingRaw) !== 'clear' && strtolower($trackingRaw) !== 'null') {
			try {
				$date = new \DateTimeImmutable($trackingRaw);
				$date = $date->setTime(0, 0, 0);
			} catch (\Throwable $e) {
				if ($verbose) {
					$io->error(sprintf('Invalid date for %s: %s', $userId, $trackingRaw));
				}
				return false;
			}
		}

		if ($dryRun) {
			if ($verbose) {
				$io->writeln(sprintf('[dry-run] %s → %s', $userId, $date?->format('Y-m-d') ?? '(legacy Jan 1)'));
			}
			return true;
		}

		$this->overtimeSettingsService->setTrackingFrom($userId, $date, $actor);
		if ($verbose) {
			$io->writeln(sprintf('Set %s tracking_from=%s', $userId, $date?->format('Y-m-d') ?? 'cleared'));
		}
		return true;
	}
}
