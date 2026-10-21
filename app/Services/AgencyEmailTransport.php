<?php

namespace App\Services;

use Illuminate\Mail\MailManager;

class AgencyEmailTransport
{
    public function send(array $payload, array $configuration): string
    {
        $mailer = app(MailManager::class)->build($configuration);
        $sent = $mailer->raw($payload['body'], function ($message) use ($payload) {
            $message->from($payload['from']['address'], $payload['from']['name'])
                ->to($payload['recipient']['address'], $payload['recipient']['name'])->subject($payload['subject']);
            foreach ($payload['_attachments'] ?? [] as $file) $message->attachData($file['bytes'], $file['name'], ['mime'=>$file['mime']]);
        });
        if (!$sent) throw new \RuntimeException('Invio non confermato.');
        return $sent->getMessageId();
    }
}
