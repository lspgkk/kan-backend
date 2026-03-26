<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Apl02AsesorUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $idIzin;
    public $updateType;
    public $updateData;
    public $asesorId;

    /**
     * Create a new event instance.
     *
     * @param string $idIzin
     * @param string $updateType - 'signature', 'approval', 'feedback', etc.
     * @param array $updateData - The data that was updated
     * @param int|null $asesorId - The asesor who made the update
     */
    public function __construct($idIzin, $updateType, $updateData, $asesorId = null)
    {
        $this->idIzin = $idIzin;
        $this->updateType = $updateType;
        $this->updateData = $updateData;
        $this->asesorId = $asesorId;

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

        // Broadcast to the specific asesi/jadwal channel
        $channels[] = new PrivateChannel('apl02.' . $this->idIzin);

        // Also broadcast to admin channel if needed
        // $channels[] = new PrivateChannel('admin.apl02.updates');

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'apl02.asesor.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id_izin' => $this->idIzin,
            'update_type' => $this->updateType,
            'asesor_id' => $this->asesorId,
            'data' => $this->updateData,
            'timestamp' => now()->toISOString(),
        ];
    }
}
