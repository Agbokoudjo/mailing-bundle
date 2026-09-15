<?php

declare(strict_strict=1);

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

use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Interface for creating and sending emails.
 * 
 * Provides methods for creating emails from Twig templates
 * and sending them synchronously or asynchronously via Symfony Mailer.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Infrastructure\Service\Mailing
 */
interface MailerFactoryInterface
{
    /**
     * Sends an email asynchronously (via Messenger if configured).
     *
     * @param Email $email The email object to send
     * 
     * @return void
     * 
     * @throws TransportExceptionInterface If sending fails
     */
    public function sendAsync(Email $email): void;

    /**
     * Sends an email synchronously (immediately).
     * 
     * Use this method for critical emails that must
     * be sent immediately without going through a message queue.
     *
     * @param Email $email The email object to send
     * 
     * @return void
     * 
     * @throws TransportExceptionInterface If sending fails
     */
    public function sendNow(Email $email): void;

    /**
     * Creates an email based on a Twig template.
     *
     * @param string $senderAddress The sender's email address (must match MAILER_DSN)
     * @param string $senderName The sender's display name (e.g., "MyApp System")
     * @param string|array $recipientAddress The recipient's email address (or array of addresses)
     * @param string $subject The subject of the email
     * @param string $templatePath The path to the Twig template (e.g., 'emails/welcome.html.twig')
     * @param array<string, mixed>|null $context Variables to pass to the Twig template
     * 
     * @return Email The configured email object (not yet sent)
     * 
     * @throws \Twig\Error\Error If the template is invalid
     */
    public function createTemplateEmail(
        string $senderAddress,
        string $senderName,
        string|array $recipientAddress,
        string $subject,
        string $templatePath,
        ?array $context
    ): Email;

    public function fromConfig(string $type="system"): array;

    public function validatePriority(int $priority): void;
}