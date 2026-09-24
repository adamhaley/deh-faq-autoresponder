<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A "something changed, go re-check" ping -- carries no model state, so the
 * Livewire listener it triggers just re-renders and reruns the existing
 * hasActiveFaqRetrieval()/hasActiveAnswerDraftGeneration()/
 * freshMessageAwaitingThreadDraft() status methods. Single source of truth
 * for status stays exactly where it already is.
 *
 * ShouldBroadcastNow, not ShouldBroadcast: this is always fired from inside
 * an already-queued job, so there's no reason to pay for a second queue hop.
 */
class RecordPipelineStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $channel) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return 'status.changed';
    }
}
