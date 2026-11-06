<?php

namespace App\Services\Zulip;

use App\Models\DivisionPlacement;
use App\Models\DivisionResult;
use App\Models\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tournament results in a Zulip channel.
 *
 * Off until ZULIP_RESULTS_CHANNEL is set. It uses its own bot rather than the
 * sync's: that one holds organization-owner rights, and this only has to post.
 *
 * Corrections edit the original post where Zulip allows it. An incoming webhook
 * bot can only send, and Zulip limits how long a message stays editable, so a
 * refused edit becomes a new post marked as a correction.
 */
class ResultsChannel
{
    /** Zulip's default topic limit. */
    private const TOPIC_LIMIT = 60;

    private ZulipClient $client;

    // Built here, not injected: the container's ZulipClient is the sync bot.
    public function __construct()
    {
        $this->client = new ZulipClient(
            config('services.zulip.results.site'),
            config('services.zulip.results.bot_email'),
            config('services.zulip.results.bot_api_key'),
        );
    }

    public function enabled(): bool
    {
        return filled(config('services.zulip.results.channel')) && $this->client->isConfigured();
    }

    public function completed(DivisionResult $result): void
    {
        $body = $this->resultsBody($result);

        if ($result->zulip_message_id === null) {
            $this->remember($result, $this->send($result->event, $result->discipline, $body));

            return;
        }

        if (! $this->edit($result->zulip_message_id, $body)) {
            $this->remember($result, $this->send($result->event, $result->discipline, "**Correction**\n\n{$body}"));
        }
    }

    /** Best effort: the saved correction posts whether or not this edit lands. */
    public function reopened(DivisionResult $result): void
    {
        if ($result->zulip_message_id !== null) {
            $this->edit($result->zulip_message_id, $this->heading($result->division_name)."\n\n_Being corrected. Updated results to follow._");
        }
    }

    public function cleared(Event $event, string $discipline, string $divisionName, int $messageId): void
    {
        $body = $this->heading($divisionName)."\n\n_This division changed, so its results were cleared and it will be scored again._";

        if (! $this->edit($messageId, $body)) {
            $this->send($event, $discipline, $body);
        }
    }

    private function resultsBody(DivisionResult $result): string
    {
        // Not a Markdown list: Zulip renumbers those, which would turn a tied
        // 3, 3 into 3, 4.
        $lines = $result->placements->map(function (DivisionPlacement $placement) {
            $school = $placement->school ? ' ('.$this->plain($placement->school).')' : '';

            return "**{$placement->place}** ".$this->plain($placement->displayName()).$school;
        });

        return $this->heading($result->division_name)."\n\n".$lines->implode("\n")
            ."\n\n[All results](".route('event.results', $result->event->slug).')';
    }

    private function heading(string $divisionName): string
    {
        return '**'.$this->plain($divisionName).'**';
    }

    /**
     * Names and labels come from people's profiles and the board, and must not
     * act as Zulip markup. Above all an @ must not become a mention: this
     * channel must never notify anyone beyond those following it.
     */
    private function plain(string $text): string
    {
        return trim(preg_replace('/[@*_`~#\[\]$|\\\\<>]/u', '', $text));
    }

    private function send(Event $event, string $discipline, string $body): int
    {
        return $this->client->sendChannelMessage(
            config('services.zulip.results.channel'),
            $this->topic($event, $discipline),
            $body,
        );
    }

    /** One topic per event and discipline; the event name gives way if it is long. */
    private function topic(Event $event, string $discipline): string
    {
        $suffix = ' - '.ucfirst($discipline);

        return Str::limit($event->name, self::TOPIC_LIMIT - mb_strlen($suffix) - 1, '…').$suffix;
    }

    private function edit(int $messageId, string $body): bool
    {
        try {
            $this->client->updateMessage($messageId, $body);

            return true;
        } catch (RuntimeException $e) {
            Log::info('Zulip refused a results edit.', ['message_id' => $messageId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function remember(DivisionResult $result, int $messageId): void
    {
        $result->update(['zulip_message_id' => $messageId]);
    }
}
