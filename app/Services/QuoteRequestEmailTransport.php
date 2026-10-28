<?php

namespace App\Services;

use Illuminate\Mail\MailManager;

class QuoteRequestEmailTransport
{
    public function send(array $payload, array $configuration, array $files): string
    {
        $mailer = app(MailManager::class)->build($configuration);
        $sent = $mailer->raw($payload['body'], function ($message) use ($payload, $files) {
            $message->from($payload['from']['address'], $payload['from']['name'])
                ->to($payload['recipient']['address'], $payload['recipient']['name'])->subject($payload['subject']);
            foreach ($files as $file) $message->attachData($file['bytes'], $file['name'], ['mime'=>$file['mime'] ?? 'application/pdf']);
        });
        if (!$sent) throw new \RuntimeException('Invio non confermato.');
        return $sent->getMessageId();
    }
}
