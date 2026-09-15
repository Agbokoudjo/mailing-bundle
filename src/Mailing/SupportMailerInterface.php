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

/**
 * Interface for sending email notifications to support and management.
 *
 * This service centralizes the sending of contact forms or customer submissions 
 * to administrators. Thanks to the 'Reply-To' field configuration, administrators 
 * can reply directly to the received email to start or continue a discussion with the customer.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package Wlindabla\MailingBundle\Mailing
 */
interface SupportMailerInterface
{
    /**
     * Sends a notification email to administrators or management.
     *
     * This method generates an HTML email based on a Twig template. It includes 
     * the 'Reply-To' mechanism: when the administrator receives the notification 
     * in their inbox, clicking "Reply" allows them to send a message directly 
     * to the customer's personal email address without going back through the application.
     *
     * @param string|array $recipientEmail The address (or list of addresses) of the administrator or internal department receiving the notification.
     * @param string $subject The subject or title of the email.
     * @param string $htmlTemplate The path to the Twig template (e.g., 'contact/contact_notification.html.twig').
     * @param array<string, mixed>|null $context Variables and entity data to pass to the Twig template.
     * @param string|null $senderEmail The address used in the 'From' field (must match SMTP authentication credentials).
     * @param string|null $replyToEmail The reply-to address (usually the customer's email). Enables a direct communication channel.
     * @param array $attachments List of attachments as key-value pairs ['file/path' => 'file_name.ext'].
     *
     * @return void
     */
    public function send(
        string|array $recipientEmail,
        string $subject,
        string $htmlTemplate,
        ?array $context,
        ?string $senderEmail,
        ?string $replyToEmail,
        array $attachments = []
    ): void;
}