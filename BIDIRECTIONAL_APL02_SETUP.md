# Bidirectional APL-02 Real-Time Updates Setup

Complete setup for real-time WebSocket communication between asesi (candidates) and asesor (assessors) for APL-02 forms.

## 🔄 Bidirectional Flow

### Asesi → Asesor Updates
When asesi submits APL-02 answers, asesor receives real-time updates:
- **Event**: `Apl02Updated`
- **Channel**: `private:asesor.{asesor_id}` and `private:apl02.{id_izin}`
- **Trigger**: `POST /api/pra-asesmen/{id}/apl02`

### Asesor → Asesi Updates
When asesor signs/approves APL-02, asesi receives real-time updates:
- **Event**: `Apl02AsesorUpdated`
- **Channel**: `private:apl02.{id_izin}`
- **Triggers**:
  - `POST /api/qr/{id}/apl02` (asesor signature)

## 🎯 Complete Setup

### 1. Backend Events (Created)

#### Apl02Updated Event (Asesi → Asesor)
```php
// app/Events/Apl02Updated.php
class Apl02Updated implements ShouldBroadcast
{
    public $idIzin;
    public $submissionData;
    public $answersData;
    public $asesorIds;

    public function broadcastOn(): array
    {
        $channels = [];
        foreach ($this->asesorIds as $asesorId) {
            $channels[] = new PrivateChannel('asesor.' . $asesorId);
        }
        $channels[] = new PrivateChannel('apl02.' . $this->idIzin);
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'apl02.updated';
    }
}
```

#### Apl02AsesorUpdated Event (Asesor → Asesi)
```php
// app/Events/Apl02AsesorUpdated.php
class Apl02AsesorUpdated implements ShouldBroadcast
{
    public $idIzin;
    public $updateType; // 'signature', 'approval', 'feedback'
    public $updateData;
    public $asesorId;

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('apl02.' . $this->idIzin)
        ];
    }

    public function broadcastAs(): string
    {
        return 'apl02.asesor.updated';
    }
}
```

### 2. Channel Authorization (Updated)

#### routes/channels.php
```php
// APL-02 channel - asesor, asesi, and admin can access
Broadcast::channel('apl02.{idIzin}', function ($user, $idIzin) {
    // Check if the user is the asesi (candidate) themselves
    $personal = \App\Models\Personal::where('user_id', $user->id)->first();
    if ($personal && $personal->id_izin === $idIzin) {
        return ['role' => 'asesi', 'id' => $user->id, 'id_izin' => $idIzin];
    }

    // Check if user is an asesor assigned to this asesi
    $asesiJadwal = \App\Models\AsesiJadwal::where('id_izin', $idIzin)->first();
    if ($asesiJadwal && $asesiJadwal->jadwal) {
        $jadwal = $asesiJadwal->jadwal;
        if ((int) $user->id === (int) $jadwal->asesor_id ||
            (int) $user->id === (int) $jadwal->asesor2_id) {
            return ['role' => 'asesor', 'id' => $user->id, 'id_izin' => $idIzin];
        }
    }

    // Check if user is admin
    if ($user->role_id === 1) {
        return ['role' => 'admin', 'id' => $user->id, 'id_izin' => $idIzin];
    }

    return false;
});
```

### 3. Controller Broadcasting (Added)

#### PraAsesmenController@submitApl02 (Asesi → Asesor)
```php
public function submitApl02(Request $request, $id)
{
    // ... validation and database updates ...

    // Broadcast to asesor(s)
    broadcast(new Apl02Updated($id, $submission, $answers, $asesorIds));

    return response()->json(['message' => 'APL-02 successfully submitted']);
}
```

#### PraAsesmenController@ttdApl02 (Asesor → Asesi)
```php
public function ttdApl02(Request $request, $id)
{
    // ... PDF generation and database updates ...

    // Get the authenticated asesor ID
    $asesorId = auth()->id();

    // Broadcast the signature update to the asesi
    broadcast(new Apl02AsesorUpdated(
        $id,
        'signature',
        [
            'document_type' => 'APL02',
            'pdf_url' => $fullUrl,
            'filename' => $filename,
            'barcode_data' => $data,
            'signed_by' => $asesorId,
            'signed_at' => now()->toISOString(),
        ],
        $asesorId
    ));

    return response()->json([
        'message' => 'Success',
        'data' => $data,
        'broadcasted' => true,
    ]);
}
```

## 📱 Frontend Implementation

### Asesor Dashboard (Listening to Asesi Updates)
```javascript
// resources/js/asesor-apl02.js
import { ref, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/auth';

export default {
    setup() {
        const authStore = useAuthStore();
        const apl02Updates = ref([]);

        onMounted(() => {
            // Listen to asesi APL-02 submissions
            window.Echo.private(`asesor.${authStore.user.id}`)
                .listen('.apl02.updated', (e) => {
                    console.log('Asesi submitted APL-02:', e);
                    handleApl02Update(e);
                });
        });

        function handleApl02Update(event) {
            const { id_izin, submission, answers, timestamp } = event;

            // Update local state
            const asesiIndex = apl02Updates.value.findIndex(a => a.id_izin === id_izin);
            if (asesiIndex !== -1) {
                apl02Updates.value[asesiIndex] = {
                    ...apl02Updates.value[asesiIndex],
                    submission,
                    answers,
                    last_updated: timestamp
                };
            } else {
                apl02Updates.value.push({
                    id_izin,
                    submission,
                    answers,
                    last_updated: timestamp
                });
            }

            showNotification(`APL-02 diperbarui oleh asesi ${id_izin}`);
        }

        return { apl02Updates };
    }
};
```

### Asesi Dashboard (Listening to Asesor Updates)
```javascript
// resources/js/asesi-apl02.js
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';

export default {
    setup() {
        const authStore = useAuthStore();
        const asesorSignatures = ref([]);

        onMounted(() => {
            if (authStore.user.id_izin) {
                // Listen to asesor updates
                window.Echo.private(`apl02.${authStore.user.id_izin}`)
                    .listen('.apl02.asesor.updated', (e) => {
                        console.log('Asesor updated APL-02:', e);
                        handleAsesorUpdate(e);
                    });
            }
        });

        function handleAsesorUpdate(event) {
            const { update_type, data, timestamp } = event;

            if (update_type === 'signature') {
                asesorSignatures.value.push({
                    pdf_url: data.pdf_url,
                    signed_by: data.signed_by,
                    signed_at: data.signed_at,
                    barcode_data: data.barcode_data
                });

                showNotification('Asesor telah menandatangani APL-02 Anda!');
            }
        }

        return { asesorSignatures };
    }
};
```

## 🚀 Testing

### 1. Start Reverb Server
```bash
php artisan reverb:start
```

### 2. Test Asesi → Asesor
```bash
# Asesi submits APL-02
POST /api/pra-asesmen/{id}/apl02
{
  "metode": "observasi",
  "is_dilanjutkan": true,
  "answers": [
    {
      "subunit_id": 1,
      "kompeten": true,
      "file_ids": []
    }
  ]
}

# Asesor should receive real-time update
```

### 3. Test Asesor → Asesi
```bash
# Asesor signs APL-02
POST /api/qr/{id}/apl02
{
  "ttd_apl02": "signature_data"
}

# Asesi should receive real-time update
```

### 4. Test Endpoint
```bash
# Test broadcasting
POST /api/test-broadcast
{
  "id_izin": "test_id_here"
}
```

## 🔍 Event Data Structure

### Asesi → Asesor Event
```json
{
  "id_izin": "12345",
  "submission": {
    "metode": "observasi",
    "is_dilanjutkan": true,
    "submitted_at": "2026-03-15T10:30:00Z"
  },
  "answers": [
    {
      "subunit_id": 1,
      "kompeten": true,
      "file_ids": [10, 11, 12]
    }
  ],
  "timestamp": "2026-03-15T10:30:05Z"
}
```

### Asesor → Asesi Event
```json
{
  "id_izin": "12345",
  "update_type": "signature",
  "asesor_id": 5,
  "data": {
    "document_type": "APL02",
    "pdf_url": "https://moon.lspgatensi.id/files/new_gatensi/dok_asesmen/apl02/FR-APL-02-12345-uuid.pdf",
    "filename": "FR-APL-02-12345-uuid.pdf",
    "barcode_data": {...},
    "signed_by": 5,
    "signed_at": "2026-03-15T10:35:00Z"
  },
  "timestamp": "2026-03-15T10:35:05Z"
}
```

## 📋 Files Created/Modified

### Created:
- `app/Events/Apl02Updated.php` - Asesi → Asesor event
- `app/Events/Apl02AsesorUpdated.php` - Asesor → Asesi event
- `frontend-apl02-listener-example.js` - Asesor frontend examples
- `asesi-apl02-listener-example.js` - Asesi frontend examples

### Modified:
- `routes/channels.php` - Added bidirectional channel authorization
- `app/Http/Controllers/PraAsesmenController.php` - Added broadcasting to submitApl02 and ttdApl02
- `routes/api.php` - Added test broadcast endpoint

## 🎯 Benefits

### For Asesor:
- Real-time notifications when asesi submits APL-02
- Live dashboard updates without page refresh
- Immediate visibility into asesi progress

### For Asesi:
- Instant notification when asesor signs APL-02
- Real-time signature status updates
- Improved user experience with live feedback

### For Admin:
- Optional monitoring of all APL-02 activity
- Real-time audit trail of document changes

## 🔒 Security

- **Private Channels**: All channels are private and require authentication
- **Role-Based Access**: Users can only access channels relevant to their role
- **Jadwal Verification**: Asesor access is verified against jadwal assignments
- **Personal Verification**: Asesi access is verified against personal records

## 🎉 Next Steps

1. ✅ Backend bidirectional setup complete
2. ⬜ Implement asesor frontend listeners
3. ⬜ Implement asesi frontend listeners
4. ⬜ Add UI indicators for real-time status
5. ⬜ Test with multiple asesor and asesi simultaneously
6. ⬜ Deploy to production with proper scaling

The bidirectional real-time APL-02 system is now ready for implementation! 🚀
