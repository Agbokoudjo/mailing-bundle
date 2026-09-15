<?php

declare(strict_types=1);

/*
 * This file is part of the project by AGBOKOUDJO Franck.
 *
 * (c) AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * Phone: +229 01 67 25 18 86
 * LinkedIn: https://www.linkedin.com/in/internationales-web-apps-services-120520193/
 * Github: https://github.com/Agbokoudjo/
 * Company: INTERNATIONALES WEB APPS & SERVICES
 *
 * For more information, please feel free to contact the author.
 */

namespace Wlindabla\MailingBundle\Mailing;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Wlindabla\MailingBundle\Mailing\MailerFactoryInterface;
use Wlindabla\MailingBundle\Mailing\SystemMailerInterface;

/**
 * Service for sending automated system emails.
 * 
 * Uses the 'system' configuration to send system notification
 * emails (confirmation, reset, etc.).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Service\Mailing
 */
#[AutoconfigureTag(name:"app.system.mailer")]
final class SystemMailer implements SystemMailerInterface
{
    private const CONFIG_TYPE = 'system';

    public function __construct(
        private readonly MailerFactoryInterface $mailerFactory,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function send(
        string|array $recipientAddress,
        string $subject,
        string $templatePath,
        array $context,
        int $priority = PriorityInterface::PRIORITY_HIGH
    ): void {

        $this->mailerFactory->validatePriority($priority);
        try {
            $fromConfig = $this->mailerFactory->fromConfig(self::CONFIG_TYPE);

            if (!isset($fromConfig['address'], $fromConfig['name'])) {
                throw new RuntimeException(
                    'Invalid system configuration: "address" and "name" keys are required'
                );
            }

            $systemEmail = $this->mailerFactory
                ->createTemplateEmail(
                $fromConfig['address'],
                $fromConfig['name'],
                $recipientAddress,
                $subject,
                $templatePath,
                $context
            )
            ->priority($priority);

            $systemEmail->getHeaders()->addTextHeader('X-Transport', self::CONFIG_TYPE);
            
            $this->mailerFactory->sendAsync($systemEmail);

            $this->logger?->info('System email sent successfully', [
                'recipient' => $recipientAddress,
                'subject' => $subject,
                'template' => $templatePath,
            ]);
        } catch (RuntimeException $e) {
            $this->logger?->error('Failed to retrieve system configuration', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger?->error('Failed to send system email', [
                'recipient' => $recipientAddress,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                sprintf('Could not send system email: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}