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

use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Interface for sending automated system emails.
 * 
 * Handles sending system notifications such as:
 * - Registration confirmation
 * - Password reset
 * - Account notifications
 * - System alerts
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Application\Service\Mailer
 */
interface SystemMailerInterface 
{
    /**
     * Sends a system email to a user.
     *
     * @param string|array $recipientAddress The recipient's email address (or array of addresses)
     * @param string $subject The subject of the email
     * @param string $templatePath The path to the Twig template (e.g., 'emails/confirmation.html.twig')
     * @param array<string, mixed> $context Variables to pass to the template
     * @param int $priority Priority level (1=max, 5=min). Use PRIORITY_* constants
     * 
     * @return void
     * 
     * @throws TransportExceptionInterface If sending fails
     * @throws InvalidArgumentException If recipient email is invalid or priority is invalid
     */
    public function send(
        string|array $recipientAddress,
        string $subject,
        string $templatePath,
        array $context,
        int $priority = PriorityInterface::PRIORITY_NORMAL
    ): void;
}