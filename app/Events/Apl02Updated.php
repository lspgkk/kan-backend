<?php

namespace App\Events;

use App\Models\Apl2Answer;
use App\Models\Apl2Submission;
use App\Models\AsesiJadwal;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Apl02Updated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $idIzin;
    public $submissionData;
    public $answersData;
    public $asesorIds;

    /**
     * Create a new event instance.
     */
    public function __construct($idIzin, $submission, $answers, $asesorIds = [])
    {
        $this->idIzin = $idIzin;
        $this->submissionData = [
            'metode' => $submission->metode,
            'is_dilanjutkan' => $submission->is_dilanjutkan,
            'submitted_at' => $submission->submitted_at?->toISOString(),
        ];
        $this->answersData = $answers->map(function ($answer) {
            return [
                'subunit_id' => $answer->subunit_id,
                'kompeten' => $answer->kompeten,
                'file_ids' => $answer->files->pluck('id')->toArray(),
            ];
        })->toArray();
        $this->asesorIds = $asesorIds;

        // Set higher priority for real-time updates
        $this->afterCommit = true;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Broadcast to asesor's personal channels
        foreach ($this->asesorIds as $asesorId) {
            $channels[] = new PrivateChannel('asesor.' . $asesorId);
        }

        // Broadcast to the specific jadwal/asesi channel
        $channels[] = new PrivateChannel('apl02.' . $this->idIzin);

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'apl02.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id_izin' => $this->idIzin,
            'submission' => $this->submissionData,
            'answers' => $this->answersData,
            'timestamp' => now()->toISOString(),
        ];
    }
}
