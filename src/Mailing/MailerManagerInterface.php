<?php

declare(strict_types=1);

namespace Wlindabla\MailingBundle\Mailing;

use Wlindabla\MailingBundle\Mailing\PriorityInterface;

/**
 * High-level interface to orchestrate and centralize all email delivery flows
 * within the company.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
interface MailerManagerInterface
{
    /**
     * Sends an automated system notification to a user.
     *
     * @param string|array $recipientAddress The recipient's email address or list of addresses (users).
     * @param string $subject The subject of the email.
     * @param string $templatePath The path to the Twig template (e.g., 'emails/confirmation.html.twig').
     * @param array $context Variables to pass to the Twig template.
     * @param int $priority Priority level (constants from PriorityInterface).
     */
    public function notifyBySystem(
        string|array $recipientAddress,
        string $subject,
        string $templatePath,
        array $context,
        int $priority = PriorityInterface::PRIORITY_NORMAL
    ): void;

    /**
     * Internally notifies management or the secretariat of a system event (e.g., form submission).
     *
     * @param string|array $directionEmail The email address of management or secretariat (recipient).
     * @param string $subject The subject of the notification.
     * @param string $htmlTemplate The path to the Twig template for the notification.
     * @param array|null $context Data to display in the template (e.g., submitted data).
     * @param string|null $clientEmail The client's email address to set in Reply-To to allow a direct response.
     * @param array|null $attachments Any attachments (e.g., manuscript file).
     */
    public function notifyToDirection(
        string|array $directionEmail,
        string $subject,
        string $htmlTemplate,
        ?array $context = null,
        ?string $clientEmail = null,
        ?array $attachments = []
    ): void;

    /**
     * Sends an official email from the management address to a third party (client, partner).
     *
     * @param string|array $recipientEmail The email address of the third party or client (recipient).
     * @param string $subject The subject of the email.
     * @param string $htmlTemplate The path to the Twig template for the message body.
     * @param array|null $context Variables to customize the template.
     * @param string|null $replyToEmail Optional: A specific reply-to address if different from the support sender.
     * @param array|null $attachments Optional: Attachments to include with the message (e.g., invoices, contracts).
     */
    public function sendMailByDirection(
        string|array $recipientEmail,
        string $subject,
        string $htmlTemplate,
        ?array $context = null,
        ?string $replyToEmail = null,
        ?array $attachments = []
    ): void;
}