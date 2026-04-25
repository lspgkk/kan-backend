# Laravel Reverb Setup for APL-02 Real-Time Updates

This setup enables real-time WebSocket communication when asesi submits APL-02 answers, immediately updating the asesor's view.

## Backend Setup (Completed)

### 1. Event Broadcasting
- Created `Apl02Updated` event that broadcasts APL-02 changes
- Updated `PraAsesmenController@submitApl02` to broadcast events
- Added channel authorization in `routes/channels.php`

### 2. Channel Structure
- **Asesor Channel**: `private:asesor.{asesor_id}` - Individual asesor updates
- **APL-02 Channel**: `private:apl02.{id_izin}` - Specific asesi/jadwal updates

### 3. Authentication
Channels are private and require authentication. Only assigned asesor(s) and admins can subscribe.

## Frontend Implementation

### Setup Echo Connection

```javascript
// resources/js/echo.js (already configured)
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel, options) => {
        return {
            authorize: (socketId, callback) => {
                axios.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name
                })
                .then(response => {
                    callback(false, response.data);
                })
                .catch(error => {
                    callback(true, error);
                });
            }
        };
    },
});
```

### Asesor Dashboard - Listen for APL-02 Updates

```javascript
// In your Asesor Dashboard component
<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const apl02Updates = ref([]);
let echoChannel = null;

onMounted(() => {
    // Listen to asesor's personal channel
    echoChannel = window.Echo.private(`asesor.${authStore.user.id}`)
        .listen('.apl02.updated', (e) => {
            console.log('APL-02 Updated:', e);
            handleApl02Update(e);
        });
});

onUnmounted(() => {
    if (echoChannel) {
        window.Echo.leaveChannel(`asesor.${authStore.user.id}`);
    }
});

function handleApl02Update(event) {
    // Update your local state
    const { id_izin, submission, answers, timestamp } = event;

    // Update the specific asesi's APL-02 data
    const asesiIndex = apl02Updates.value.findIndex(a => a.id_izin === id_izin);

    if (asesiIndex !== -1) {
        // Update existing asesi data
        apl02Updates.value[asesiIndex] = {
            ...apl02Updates.value[asesiIndex],
            submission,
            answers,
            last_updated: timestamp
        };
    } else {
        // Add new asesi data
        apl02Updates.value.push({
            id_izin,
            submission,
            answers,
            last_updated: timestamp
        });
    }

    // Show notification to asesor
    showNotification(`APL-02 diperbarui untuk asesi ${id_izin}`);
}

function showNotification(message) {
    // Your notification logic
    alert(message); // Replace with proper notification
}
</script>
```

### React Example

```jsx
import { useEffect, useState } from 'react';
import { useAuth } from '@/hooks/useAuth';

export default function AsesorDashboard() {
    const { user } = useAuth();
    const [apl02Updates, setApl02Updates] = useState([]);

    useEffect(() => {
        const channel = window.Echo.private(`asesor.${user.id}`)
            .listen('.apl02.updated', (e) => {
                console.log('APL-02 Updated:', e);
                setApl02Updates(prev => {
                    const index = prev.findIndex(a => a.id_izin === e.id_izin);
                    if (index !== -1) {
                        const updated = [...prev];
                        updated[index] = {
                            ...updated[index],
                            submission: e.submission,
                            answers: e.answers,
                            last_updated: e.timestamp
                        };
                        return updated;
                    }
                    return [...prev, {
                        id_izin: e.id_izin,
                        submission: e.submission,
                        answers: e.answers,
                        last_updated: e.timestamp
                    }];
                });
            });

        return () => {
            window.Echo.leaveChannel(`asesor.${user.id}`);
        };
    }, [user.id]);

    return (
        <div>
            <h1>Asesor Dashboard</h1>
            {apl02Updates.map(update => (
                <div key={update.id_izin}>
                    <h3>Asesi: {update.id_izin}</h3>
                    <p>Metode: {update.submission.metode}</p>
                    <p>Dilanjutkan: {update.submission.is_dilanjutkan ? 'Ya' : 'Tidak'}</p>
                    <p>Last Updated: {new Date(update.last_updated).toLocaleString()}</p>
                </div>
            ))}
        </div>
    );
}
```

## Running the Reverb Server

### Start Reverb Server
```bash
php artisan reverb:start
```

### Development Mode
Run both Laravel and Reverb together:
```bash
# Terminal 1 - Laravel Server
php artisan serve

# Terminal 2 - Reverb Server
php artisan reverb:start

# Terminal 3 - Frontend Dev Server
npm run dev
```

## Event Data Structure

When an APL-02 is updated, the event will broadcast:

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
    },
    {
      "subunit_id": 2,
      "kompeten": false,
      "file_ids": [13]
    }
  ],
  "timestamp": "2026-03-15T10:30:05Z"
}
```

## Troubleshooting

### Connection Issues
1. Ensure Reverb server is running: `php artisan reverb:start`
2. Check firewall settings for port 8080
3. Verify environment variables in `.env`

### Authorization Failures
1. Ensure user is authenticated
2. Check channel authorization logic in `routes/channels.php`
3. Verify asesor is assigned to the jadwal

### No Events Received
1. Check browser console for WebSocket connection status
2. Verify the event is being broadcast in backend logs
3. Ensure channel names match exactly

## Testing

### Manual Testing
```bash
# Start Reverb server
php artisan reverb:start

# In your browser console
window.Echo.private('asesor.YOUR_ASESOR_ID')
    .listen('.apl02.updated', (e) => console.log('Received:', e));

# Trigger an APL-02 submission from your frontend
# You should see the event in console
```

### API Testing
```bash
# Submit APL-02 via API
curl -X POST http://localhost:8000/api/pra-asesmen/ID_IZIN/apl02 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "metode": "observasi",
    "is_dilanjutkan": true,
    "answers": [
      {
        "subunit_id": 1,
        "kompeten": true,
        "file_ids": []
      }
    ]
  }'
```

## Next Steps

1. ✅ Backend setup complete
2. ⬜ Implement frontend listener in asesor dashboard
3. ⬜ Add UI indicators for real-time updates
4. ⬜ Add error handling for connection failures
5. ⬜ Test with multiple asesor and asesi
6. ⬜ Deploy Reverb server in production
