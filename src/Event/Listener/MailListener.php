<?php

namespace App\Event\Listener;

use App\Event\MailEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

readonly class MailListener
{
    public function __construct(
        private MailerInterface $mailer
    ) {}

    /**
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    #[AsEventListener(event: 'app.mail')]
    public function sendMail(MailEvent $event): void
    {
        $email = (new Email())
            ->from('Dentest <noreply@dentest.tech>')
            ->to($event->to)
            ->subject($event->mail->getSubject())
            ->text($event->mail->getPlain());

        $this->mailer->send($email);
    }
}
