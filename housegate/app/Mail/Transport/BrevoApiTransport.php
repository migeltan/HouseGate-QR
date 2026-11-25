<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Brevo's HTTPS API (port 443). Needed because hosts such as
 * Render's free plan block outbound SMTP ports 25, 465 and 587.
 */
class BrevoApiTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $payload = [
            'sender' => $this->address($email->getFrom()[0]),
            'to' => array_map(fn (Address $a) => $this->address($a), $email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = (string) $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['textContent'] = (string) $email->getTextBody();
        }
        if (! isset($payload['htmlContent']) && ! isset($payload['textContent'])) {
            throw new TransportException('Brevo: the message has no body.');
        }
        if ($email->getReplyTo()) {
            $payload['replyTo'] = $this->address($email->getReplyTo()[0]);
        }

        $response = Http::withHeaders(['api-key' => $this->apiKey, 'accept' => 'application/json'])
            ->timeout(20)
            ->post('https://api.brevo.com/v3/smtp/email', $payload);

        if ($response->failed()) {
            throw new TransportException('Brevo API error '.$response->status().': '.$response->body());
        }
    }

    /** @return array{email: string, name?: string} */
    private function address(Address $address): array
    {
        return array_filter(['email' => $address->getAddress(), 'name' => $address->getName()]);
    }

    public function __toString(): string
    {
        return 'brevo+api';
    }
}