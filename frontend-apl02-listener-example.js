// Example: Frontend APL-02 Real-time Listener
// This file shows how to implement WebSocket listeners for APL-02 updates

/**
 * Vue 3 Composition API Example
 */
import { ref, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/auth';

export function useApl02Listener() {
    const authStore = useAuthStore();
    const isListening = ref(false);
    const lastUpdate = ref(null);

    let echoChannel = null;

    const startListening = (callback) => {
        if (echoChannel) {
            console.warn('Already listening to APL-02 updates');
            return;
        }

        console.log('Starting APL-02 listener for asesor:', authStore.user.id);

        try {
            // Listen to asesor's personal channel
            echoChannel = window.Echo.private(`asesor.${authStore.user.id}`)
                .listen('.apl02.updated', (event) => {
                    console.log('APL-02 Update Received:', event);
                    lastUpdate.value = event;

                    // Call the callback with the event data
                    if (callback && typeof callback === 'function') {
                        callback(event);
                    }
                });

            isListening.value = true;
            console.log('APL-02 listener started successfully');
        } catch (error) {
            console.error('Failed to start APL-02 listener:', error);
            throw error;
        }
    };

    const stopListening = () => {
        if (echoChannel) {
            console.log('Stopping APL-02 listener');
            window.Echo.leaveChannel(`asesor.${authStore.user.id}`);
            echoChannel = null;
            isListening.value = false;
            lastUpdate.value = null;
        }
    };

    // Auto-cleanup on component unmount
    onUnmounted(() => {
        stopListening();
    });

    return {
        isListening,
        lastUpdate,
        startListening,
        stopListening
    };
}

/**
 * Usage in Vue Component
 */
export default {
    name: 'AsesorDashboard',
    setup() {
        const { isListening, lastUpdate, startListening, stopListening } = useApl02Listener();
        const apl02Data = ref([]);

        // Start listening when component mounts
        onMounted(() => {
            startListening((event) => {
                handleApl02Update(event);
            });
        });

        function handleApl02Update(event) {
            const { id_izin, submission, answers, timestamp } = event;

            // Find existing asesi or add new one
            const existingIndex = apl02Data.value.findIndex(item => item.id_izin === id_izin);

            if (existingIndex !== -1) {
                // Update existing asesi
                apl02Data.value[existingIndex] = {
                    ...apl02Data.value[existingIndex],
                    submission,
                    answers,
                    last_updated: timestamp,
                    has_new_update: true
                };
            } else {
                // Add new asesi
                apl02Data.value.push({
                    id_izin,
                    submission,
                    answers,
                    last_updated: timestamp,
                    has_new_update: true
                });
            }

            // Show notification
            showNotification(`APL-02 diperbarui untuk asesi: ${id_izin}`);
        }

        function showNotification(message) {
            // Replace with your preferred notification system
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('APL-02 Update', {
                    body: message,
                    icon: '/icon.png'
                });
            } else {
                alert(message); // Fallback
            }
        }

        // Request notification permission
        onMounted(() => {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        });

        return {
            isListening,
            lastUpdate,
            apl02Data,
            stopListening
        };
    }
};

/**
 * React Hooks Example
 */
import { useEffect, useState, useCallback } from 'react';
import { useAuth } from '@/hooks/useAuth';

export function useApl02ListenerReact() {
    const { user } = useAuth();
    const [isListening, setIsListening] = useState(false);
    const [lastUpdate, setLastUpdate] = useState(null);

    const startListening = useCallback((callback) => {
        if (!window.Echo) {
            console.error('Echo is not initialized');
            return;
        }

        console.log('Starting APL-02 listener for asesor:', user.id);

        const channel = window.Echo.private(`asesor.${user.id}`)
            .listen('.apl02.updated', (event) => {
                console.log('APL-02 Update Received:', event);
                setLastUpdate(event);

                if (callback && typeof callback === 'function') {
                    callback(event);
                }
            });

        setIsListening(true);

        // Return cleanup function
        return () => {
            window.Echo.leaveChannel(`asesor.${user.id}`);
            setIsListening(false);
        };
    }, [user.id]);

    return { isListening, lastUpdate, startListening };
}

/**
 * Usage in React Component
 */
export default function AsesorDashboardReact() {
    const { isListening, lastUpdate, startListening } = useApl02ListenerReact();
    const [apl02Data, setApl02Data] = useState([]);

    useEffect(() => {
        const cleanup = startListening((event) => {
            const { id_izin, submission, answers, timestamp } = event;

            setApl02Data(prev => {
                const existingIndex = prev.findIndex(item => item.id_izin === id_izin);

                if (existingIndex !== -1) {
                    const updated = [...prev];
                    updated[existingIndex] = {
                        ...updated[existingIndex],
                        submission,
                        answers,
                        last_updated: timestamp,
                        has_new_update: true
                    };
                    return updated;
                }

                return [...prev, {
                    id_izin,
                    submission,
                    answers,
                    last_updated: timestamp,
                    has_new_update: true
                }];
            });
        });

        return cleanup;
    }, [startListening]);

    return (
        <div>
            <h1>Asesor Dashboard</h1>
            <p>Status: {isListening ? '🟢 Connected' : '🔴 Disconnected'}</p>

            {lastUpdate && (
                <div className="alert alert-info">
                    <strong>Last Update:</strong> {new Date(lastUpdate.timestamp).toLocaleString()}
                </div>
            )}

            <div className="asesi-list">
                {apl02Data.map(asesi => (
                    <div key={asesi.id_izin} className={`asesi-card ${asesi.has_new_update ? 'new-update' : ''}`}>
                        <h3>Asesi: {asesi.id_izin}</h3>
                        <div className="submission-info">
                            <p><strong>Metode:</strong> {asesi.submission.metode}</p>
                            <p><strong>Dilanjutkan:</strong> {asesi.submission.is_dilanjutkan ? 'Ya' : 'Tidak'}</p>
                            <p><strong>Submitted:</strong> {new Date(asesi.submission.submitted_at).toLocaleString()}</p>
                        </div>

                        <div className="answers-summary">
                            <strong>Jawaban:</strong>
                            <ul>
                                {asesi.answers.map((answer, idx) => (
                                    <li key={idx}>
                                        Subunit {answer.subunit_id}:
                                        {answer.kompeten ? '✅ Kompeten' : '❌ Belum Kompeten'}
                                        ({answer.file_ids?.length || 0} file)
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="timestamp">
                            Last Updated: {new Date(asesi.last_updated).toLocaleString()}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

/**
 * Plain JavaScript Example
 */
class Apl02Listener {
    constructor(asesorId) {
        this.asesorId = asesorId;
        this.channel = null;
        this.isListening = false;
    }

    start(callback) {
        if (this.isListening) {
            console.warn('Already listening to APL-02 updates');
            return;
        }

        if (!window.Echo) {
            console.error('Echo is not initialized');
            throw new Error('Echo is not initialized');
        }

        console.log('Starting APL-02 listener for asesor:', this.asesorId);

        try {
            this.channel = window.Echo.private(`asesor.${this.asesorId}`)
                .listen('.apl02.updated', (event) => {
                    console.log('APL-02 Update Received:', event);

                    if (callback && typeof callback === 'function') {
                        callback(event);
                    }
                });

            this.isListening = true;
            console.log('APL-02 listener started successfully');
        } catch (error) {
            console.error('Failed to start APL-02 listener:', error);
            throw error;
        }
    }

    stop() {
        if (this.channel) {
            console.log('Stopping APL-02 listener');
            window.Echo.leaveChannel(`asesor.${this.asesorId}`);
            this.channel = null;
            this.isListening = false;
        }
    }
}

/**
 * Usage in Plain JavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    // Get asesor ID from your authentication system
    const asesorId = getCurrentAsesorId(); // Implement this function

    const listener = new Apl02Listener(asesorId);
    const apl02Data = [];

    listener.start(function(event) {
        console.log('New APL-02 update:', event);

        // Handle the update
        const { id_izin, submission, answers, timestamp } = event;

        // Update your UI
        updateAsesiCard(id_izin, submission, answers, timestamp);
        showNotification(`APL-02 diperbarui untuk asesi: ${id_izin}`);
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        listener.stop();
    });
});

/**
 * Utility Functions
 */
function updateAsesiCard(idIzin, submission, answers, timestamp) {
    // Find or create the asesi card element
    let card = document.getElementById(`asesi-${idIzin}`);

    if (!card) {
        card = createAsesiCard(idIzin);
        document.getElementById('asesi-container').appendChild(card);
    }

    // Update the card content
    card.querySelector('.metode').textContent = submission.metode;
    card.querySelector('.is_dilanjutkan').textContent = submission.is_dilanjutkan ? 'Ya' : 'Tidak';
    card.querySelector('.submitted_at').textContent = new Date(submission.submitted_at).toLocaleString();
    card.querySelector('.last_updated').textContent = new Date(timestamp).toLocaleString();

    // Add highlight effect
    card.classList.add('just-updated');
    setTimeout(() => card.classList.remove('just-updated'), 3000);
}

function createAsesiCard(idIzin) {
    const card = document.createElement('div');
    card.id = `asesi-${idIzin}`;
    card.className = 'asesi-card';
    card.innerHTML = `
        <h3>Asesi: ${idIzin}</h3>
        <p><strong>Metode:</strong> <span class="metode">-</span></p>
        <p><strong>Dilanjutkan:</strong> <span class="is_dilanjutkan">-</span></p>
        <p><strong>Submitted:</strong> <span class="submitted_at">-</span></p>
        <p><strong>Last Updated:</strong> <span class="last_updated">-</span></p>
    `;
    return card;
}

function showNotification(message) {
    // Browser notification
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('APL-02 Update', {
            body: message,
            icon: '/icon.png'
        });
    } else {
        // Fallback to console or custom notification
        console.log('Notification:', message);
    }
}

// Export for use in modules
export { useApl02Listener, useApl02ListenerReact, Apl02Listener };
