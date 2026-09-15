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

use Wlindabla\MailingBundle\Mailing\MailerFactoryInterface;
use Wlindabla\MailingBundle\Mailing\PriorityInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;

/**
 * Factory implementation for creating and sending emails.
 * 
 * Supports async sending via queue, DKIM signing,
 * and handling multiple sender configurations.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Infrastructure\Service\Mailing
 */
final class MailerFactory implements MailerFactoryInterface, PriorityInterface
{
    /**
     * @param string|null $dkimKey Path to the DKIM private key
     * @param array<string, array{address: string, name: string}> $fromAddresses Sender configurations
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly array $fromAddresses = []
    ) {}

    /**
     * {@inheritdoc}
     */
    public function sendAsync(Email $email): void 
    {
        $this->sendNow($email);
    }

    /**
     * {@inheritdoc}
     */
    public function sendNow(Email $email): void
    {
        try {
            // Send via selected transport
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(
                sprintf('Failed to send email: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTemplateEmail(
        string $senderAddress,
        string $senderName,
        string|array $recipientAddress,
        string $subject,
        string $templatePath,
        ?array $context
    ): Email {
        $this->validateEmailAddress($senderAddress, 'senderAddress');
        $this->validateEmailAddress($recipientAddress, 'recipientAddress');

        $this->validateSubject($subject);

        $templateEmail = (new TemplatedEmail())
            ->from(new Address($senderAddress, $senderName))
            ->to(...(is_array($recipientAddress) ? $recipientAddress : [$recipientAddress]))
            ->subject($subject)
            ->htmlTemplate($templatePath);

        if ($context !== null && !empty($context)) {
            $templateEmail->context($context);
        }

        return $templateEmail;
    }

    /**
     * @return Email
     */
    public function addTextHeader(Email $email, string $type, string $name, $body): Email
    {
        $email->getHeaders()->addTextHeader($type, $name, $body);

        return $email;
    }

    /**
     * Applies DKIM signature if configured.
     *
     * @param Email $email The email to sign
     * @param string $dkimKey: same as openssl_pkey_get_private(), either a string with the contents of the private key or the absolute path to it (prefixed with 'file://')
     * @param string $domainName: the domain name and "selector" used to perform a DNS lookup
     * @param string $selector: the selector is a string used to point to a specific DKIM public key record in your DNS
     * 
     * @return Email|Message The signed email or the original email
     */
    public function applyDkimSignature(
        Email $email,
        string $domainName,
        string $selector='default',
        array $defaultOptions = [],
        string $passphrase = '',
        ?string $dkimKey = null,
        ?LoggerInterface $logger = null): Email|Message
    {
        if ($dkimKey === null || empty($domainName)) {
            return $email;
        }

        try {
            $keyPath = str_starts_with($dkimKey, 'file://')
                ? $dkimKey
                : "file://{$dkimKey}";

            $dkimSigner = new DkimSigner($keyPath, $domainName,$selector,$defaultOptions,$passphrase);
            $message = new Message($email->getPreparedHeaders(), $email->getBody());

            return $dkimSigner->sign($message);
        } catch (\Exception $e) {
            // Log the error but continue without DKIM rather than blocking sending
            $logger->error(sprintf('DKIM signing failed: %s', $e->getMessage()));
            
            return $email;
        }
    }

    /**
     * @param string|array $email The email address or list of addresses to validate
     */
    private function validateEmailAddress(string|array $email, string $paramName): void
    {
        // If array, validate each element recursively
        if (is_array($email)) {
            foreach ($email as $address) {
                $this->validateEmailAddress($address, $paramName);
            }
            return;
        }

        // Standard string validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                sprintf('The parameter "%s" must be a valid email address. Received: "%s"', $paramName, $email)
            );
        }
    }

    /**
     * Retrieves a sender configuration by type.
     *
     * @param string $type The sender type (e.g., 'system', 'noreply', 'support')
     * 
     * @return array{address: string, name: string} Sender configuration
     * 
     * @throws RuntimeException If the type does not exist
     */
    public function fromConfig(string $type = 'system'): array
    {
        if (!isset($this->fromAddresses[$type])) {
            throw new RuntimeException(
                sprintf(
                    'Unknown sender type "%s". Available types: %s',
                    $type,
                    implode(', ', array_keys($this->fromAddresses))
                )
            );
        }

        return $this->fromAddresses[$type];
    }

    /**
     * Validates that the subject is not empty.
     *
     * @throws InvalidArgumentException If the subject is empty
     */
    private function validateSubject(string $subject): void
    {
        if (trim($subject) === '' && empty($subject)) {
            throw new InvalidArgumentException('The email subject cannot be empty');
        }
    }

    public function validatePriority(int $priority): void
    {
        if ($priority < self::PRIORITY_HIGHEST || $priority > self::PRIORITY_LOWEST) {
            throw new InvalidArgumentException(
                sprintf(
                    'Priority must be between %d and %d. Received: %d',
                    self::PRIORITY_HIGHEST,
                    self::PRIORITY_LOWEST,
                    $priority
                )
            );
        }
    }
}