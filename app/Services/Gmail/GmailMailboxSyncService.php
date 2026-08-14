<?php

namespace App\Services\Gmail;

use App\Models\GmailMailbox;
use App\Models\GmailMessage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Throwable;

class GmailMailboxSyncService
{
    public function __construct(
        private readonly GmailClient $gmail,
    ) {}

    public function syncActiveMailboxes(): int
    {
        $importedMessages = 0;

        GmailMailbox::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (GmailMailbox $mailbox) use (&$importedMessages): void {
                $importedMessages += $this->syncMailbox($mailbox);
            });

        return $importedMessages;
    }

    public function syncMailbox(GmailMailbox $mailbox): int
    {
        try {
            return $this->syncMailboxOrFail($mailbox);
        } catch (RequestException $exception) {
            $this->markMailboxFailure($mailbox, $exception);

            if ($exception->response->status() !== 404) {
                report($exception);
            }

            return 0;
        } catch (Throwable $exception) {
            $this->markMailboxFailure($mailbox, $exception);
            report($exception);

            return 0;
        }
    }

    /**
     * How far back to backfill when recovering a mailbox stuck in
     * resync_required. Gmail's history API only retains state for about a
     * week, so recovery cannot rely on the (possibly stale) last_sync_at
     * checkpoint alone.
     */
    private const ResyncBackfillDays = 3;

    private function syncMailboxOrFail(GmailMailbox $mailbox): int
    {
        if ($mailbox->sync_status === GmailMailbox::SyncStatusResyncRequired) {
            return $this->recoverFromResyncRequired($mailbox);
        }

        if (! is_string($mailbox->last_history_id) || $mailbox->last_history_id === '') {
            $profile = $this->gmail->profile($mailbox);

            $mailbox->update([
                'last_history_id' => $profile['historyId'] ?? null,
                'last_sync_at' => now(),
                'sync_status' => GmailMailbox::SyncStatusConnected,
                'last_error' => null,
            ]);

            return 0;
        }

        $importedMessages = 0;
        $pageToken = null;
        $latestHistoryId = $mailbox->last_history_id;

        do {
            $history = $this->gmail->history($mailbox, $pageToken);

            foreach ($this->messageIdsFromHistory($history) as $messageId) {
                $message = $this->gmail->message($mailbox, $messageId);
                $this->storeMessage($mailbox, $message);
                $importedMessages++;
            }

            if (isset($history['historyId']) && is_string($history['historyId'])) {
                $latestHistoryId = $history['historyId'];
            }

            $pageToken = isset($history['nextPageToken']) && is_string($history['nextPageToken'])
                ? $history['nextPageToken']
                : null;
        } while ($pageToken !== null);

        $mailbox->update([
            'last_history_id' => $latestHistoryId,
            'last_sync_at' => now(),
            'sync_status' => GmailMailbox::SyncStatusConnected,
            'last_error' => null,
        ]);

        return $importedMessages;
    }

    /**
     * Recover a mailbox whose history checkpoint has expired or gone
     * invalid. Backfills any messages missed while the sync was stuck, then
     * re-establishes a fresh history checkpoint.
     */
    private function recoverFromResyncRequired(GmailMailbox $mailbox): int
    {
        $importedMessages = $this->backfillRecentMessages($mailbox);

        $profile = $this->gmail->profile($mailbox);

        $mailbox->update([
            'last_history_id' => $profile['historyId'] ?? null,
            'last_sync_at' => now(),
            'sync_status' => GmailMailbox::SyncStatusConnected,
            'last_error' => null,
        ]);

        return $importedMessages;
    }

    private function backfillRecentMessages(GmailMailbox $mailbox): int
    {
        $importedMessages = 0;
        $pageToken = null;
        $afterUnixTimestamp = now()->subDays(self::ResyncBackfillDays)->timestamp;

        do {
            $list = $this->gmail->listMessages($mailbox, $pageToken, $afterUnixTimestamp);

            foreach ($this->messageIdsFromList($list) as $messageId) {
                $message = $this->gmail->message($mailbox, $messageId);
                $this->storeMessage($mailbox, $message);
                $importedMessages++;
            }

            $pageToken = isset($list['nextPageToken']) && is_string($list['nextPageToken'])
                ? $list['nextPageToken']
                : null;
        } while ($pageToken !== null);

        return $importedMessages;
    }

    /**
     * @param  array<string, mixed>  $list
     * @return list<string>
     */
    private function messageIdsFromList(array $list): array
    {
        $messageIds = [];

        foreach ($list['messages'] ?? [] as $message) {
            if (! is_array($message)) {
                continue;
            }

            $messageId = $message['id'] ?? null;

            if (is_string($messageId) && $messageId !== '') {
                $messageIds[] = $messageId;
            }
        }

        return array_values(array_unique($messageIds));
    }

    /**
     * @param  array<string, mixed>  $history
     * @return list<string>
     */
    private function messageIdsFromHistory(array $history): array
    {
        $messageIds = [];

        foreach ($history['history'] ?? [] as $record) {
            if (! is_array($record)) {
                continue;
            }

            foreach ($record['messagesAdded'] ?? [] as $messageAdded) {
                if (! is_array($messageAdded)) {
                    continue;
                }

                $messageId = $messageAdded['message']['id'] ?? null;

                if (is_string($messageId) && $messageId !== '') {
                    $messageIds[] = $messageId;
                }
            }
        }

        return array_values(array_unique($messageIds));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function storeMessage(GmailMailbox $mailbox, array $message): GmailMessage
    {
        $payload = is_array($message['payload'] ?? null) ? $message['payload'] : [];
        $headers = $this->headers($payload);
        $body = $this->body($payload);
        $from = $this->parseAddress($headers['from'] ?? null);

        return GmailMessage::query()->updateOrCreate(
            [
                'gmail_mailbox_id' => $mailbox->id,
                'gmail_message_id' => $this->requiredString($message, 'id'),
            ],
            [
                'thread_id' => $message['threadId'] ?? null,
                'history_id' => $message['historyId'] ?? null,
                'subject' => $headers['subject'] ?? null,
                'from_email' => $from['email'],
                'from_name' => $from['name'],
                'to_recipients' => $this->splitRecipients($headers['to'] ?? null),
                'cc_recipients' => $this->splitRecipients($headers['cc'] ?? null),
                'snippet' => $message['snippet'] ?? null,
                'text_body' => $body['text'],
                'html_body' => $body['html'],
                'label_ids' => $message['labelIds'] ?? null,
                'internal_date' => $this->internalDate($message['internalDate'] ?? null),
                'payload' => $message,
                'imported_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function headers(array $payload): array
    {
        $headers = [];

        foreach ($payload['headers'] ?? [] as $header) {
            if (! is_array($header)) {
                continue;
            }

            $name = $header['name'] ?? null;
            $value = $header['value'] ?? null;

            if (is_string($name) && is_string($value)) {
                $headers[strtolower($name)] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $part
     * @return array{text: ?string, html: ?string}
     */
    private function body(array $part): array
    {
        $textParts = [];
        $htmlParts = [];

        $this->collectBodyParts($part, $textParts, $htmlParts);

        return [
            'text' => $textParts === [] ? null : implode("\n", $textParts),
            'html' => $htmlParts === [] ? null : implode("\n", $htmlParts),
        ];
    }

    /**
     * @param  array<string, mixed>  $part
     * @param  list<string>  $textParts
     * @param  list<string>  $htmlParts
     */
    private function collectBodyParts(array $part, array &$textParts, array &$htmlParts): void
    {
        $mimeType = is_string($part['mimeType'] ?? null) ? $part['mimeType'] : '';
        $data = $part['body']['data'] ?? null;

        if (is_string($data) && $data !== '') {
            $decoded = $this->decodeBase64Url($data);

            if ($decoded !== '' && str_starts_with($mimeType, 'text/plain')) {
                $textParts[] = $decoded;
            }

            if ($decoded !== '' && str_starts_with($mimeType, 'text/html')) {
                $htmlParts[] = $decoded;
            }
        }

        foreach ($part['parts'] ?? [] as $childPart) {
            if (is_array($childPart)) {
                $this->collectBodyParts($childPart, $textParts, $htmlParts);
            }
        }
    }

    private function decodeBase64Url(string $value): string
    {
        $normalized = strtr($value, '-_', '+/');
        $normalized .= str_repeat('=', (4 - strlen($normalized) % 4) % 4);

        $decoded = base64_decode($normalized, true);

        return $decoded === false ? '' : $decoded;
    }

    /**
     * @return array{name: ?string, email: ?string}
     */
    private function parseAddress(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return ['name' => null, 'email' => null];
        }

        if (preg_match('/^(?:"?([^"]*)"?\s)?<([^>]+)>$/', trim($value), $matches) === 1) {
            return [
                'name' => trim($matches[1]) === '' ? null : trim($matches[1]),
                'email' => strtolower(trim($matches[2])),
            ];
        }

        return ['name' => null, 'email' => strtolower(trim($value))];
    }

    /**
     * @return list<string>
     */
    private function splitRecipients(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $value))));
    }

    private function internalDate(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $value);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function requiredString(array $message, string $key): string
    {
        $value = $message[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new \RuntimeException("Gmail message did not include {$key}.");
        }

        return $value;
    }

    private function markMailboxFailure(GmailMailbox $mailbox, Throwable $exception): void
    {
        $mailbox->update([
            'sync_status' => $exception instanceof RequestException && $exception->response->status() === 404
                ? GmailMailbox::SyncStatusResyncRequired
                : GmailMailbox::SyncStatusFailed,
            'last_error' => $exception->getMessage(),
            'last_sync_at' => now(),
        ]);
    }
}
