<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Service;

use OCA\ArbeitszeitCheck\Constants;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use Psr\Log\LoggerInterface;

/**
 * Employee email when overtime above the bank cap is recorded as paid out.
 */
class OvertimePayoutMailService
{
	public function __construct(
		private readonly IMailer $mailer,
		private readonly IConfig $config,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @param array<string, mixed> $payout Audit fields from {@see OvertimePayoutService::entityToArray}
	 */
	public function sendEmployeePayoutConfirmation(IUser $user, array $payout): bool
	{
		if ($this->config->getAppValue('arbeitszeitcheck', Constants::CONFIG_OVERTIME_PAYOUT_NOTIFY_EMAIL, '1') !== '1') {
			return false;
		}

		$email = $user->getEMailAddress();
		if ($email === null || $email === '' || !$this->mailer->validateMailAddress($email)) {
			$this->logger->info('arbeitszeitcheck: overtime payout email skipped (no valid address)', [
				'app' => 'arbeitszeitcheck',
				'user_id' => $user->getUID(),
			]);

			return false;
		}

		$year = (int)($payout['calendar_year'] ?? 0);
		$month = (int)($payout['calendar_month'] ?? 0);
		$hours = (float)($payout['hours_paid'] ?? 0);
		$period = sprintf('%04d-%02d', $year, $month);

		$subject = $this->l10n->t('Overtime payout recorded for %s', [$period]);
		$body = $this->l10n->t('Hello %s,', [$user->getDisplayName()]) . "\n\n"
			. $this->l10n->t('Payroll has recorded a payout of %1$s hours of overtime above your bank cap for %2$s.', [
				number_format($hours, 2),
				$period,
			]) . "\n\n"
			. $this->l10n->t('Balance before payout: %1$s h', [number_format((float)($payout['effective_balance_before'] ?? 0), 2)]) . "\n"
			. $this->l10n->t('Balance after payout (bank cap): %1$s h', [number_format((float)($payout['effective_balance_after'] ?? 0), 2)]) . "\n"
			. $this->l10n->t('Bank cap at time of payout: %1$s h', [number_format((float)($payout['bank_max_hours'] ?? 0), 2)]) . "\n"
			. $this->l10n->t('Record ID: %1$s', [(string)($payout['id'] ?? '')]) . "\n\n"
			. $this->l10n->t('This message is for your records. If anything looks wrong, contact HR or payroll.');

		try {
			$message = $this->mailer->createMessage();
			$message->setSubject($subject);
			$message->setPlainBody($body);
			$message->setTo([$email => $user->getDisplayName()]);
			$this->setFrom($message);
			$this->mailer->send($message);

			return true;
		} catch (\Throwable $e) {
			$this->logger->warning('arbeitszeitcheck: Failed to send overtime payout email', [
				'app' => 'arbeitszeitcheck',
				'user_id' => $user->getUID(),
				'exception' => $e,
			]);

			return false;
		}
	}

	private function setFrom(IMessage $message): void
	{
		$fromAddress = (string)$this->config->getSystemValue('mail_from_address', '');
		$fromDomain = (string)$this->config->getSystemValue('mail_domain', 'localhost');
		if ($fromAddress === '') {
			return;
		}
		$from = $fromAddress . '@' . $fromDomain;
		$fromName = (string)$this->config->getSystemValue('mail_from_name', 'ArbeitszeitCheck');
		$message->setFrom([$from => $fromName]);
	}
}
