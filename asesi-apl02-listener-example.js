// Example: Asesi Frontend APL-02 Real-time Listener
// This file shows how asesi can listen to real-time updates from asesor

/**
 * Vue 3 Composition API Example for Asesi
 */
import { ref, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/auth';

export function useApl02AsesiListener() {
    const authStore = useAuthStore();
    const isListening = ref(false);
    const asesorUpdates = ref([]);
    const lastSignature = ref(null);

    let echoChannel = null;

    const startListening = (idIzin, callback) => {
        if (echoChannel) {
            console.warn('Already listening to APL-02 asesor updates');
            return;
        }

        console.log('Starting APL-02 listener for asesi:', idIzin);

        try {
            // Listen to asesor updates on this APL-02
            echoChannel = window.Echo.private(`apl02.${idIzin}`)
                .listen('.apl02.asesor.updated', (event) => {
                    console.log('Asesor Update Received:', event);
                    handleAsesorUpdate(event);

                    // Call the callback with the event data
                    if (callback && typeof callback === 'function') {
                        callback(event);
                    }
                });

            isListening.value = true;
            console.log('APL-02 asesi listener started successfully');
        } catch (error) {
            console.error('Failed to start APL-02 asesi listener:', error);
            throw error;
        }
    };

    const handleAsesorUpdate = (event) => {
        const { id_izin, update_type, asesor_id, data, timestamp } = event;

        // Add to the updates list
        asesorUpdates.value.unshift({
            id_izin,
            update_type,
            asesor_id,
            data,
            timestamp
        });

        // Handle specific update types
        switch (update_type) {
            case 'signature':
                handleSignatureUpdate(data, timestamp);
                break;
            case 'approval':
                handleApprovalUpdate(data, timestamp);
                break;
            case 'feedback':
                handleFeedbackUpdate(data, timestamp);
                break;
            default:
                console.log('Unknown update type:', update_type);
        }
    };

    const handleSignatureUpdate = (data, timestamp) => {
        console.log('Asesor signed APL-02:', data);
        lastSignature.value = {
            pdf_url: data.pdf_url,
            filename: data.filename,
            signed_by: data.signed_by,
            signed_at: data.signed_at,
            barcode_data: data.barcode_data
        };

        // Show notification to asesi
        showNotification('Asesor telah menandatangani APL-02 Anda!', 'signature');
    };

    const handleApprovalUpdate = (data, timestamp) => {
        console.log('Asesor approved APL-02:', data);
        showNotification('Asesor telah menyetujui APL-02 Anda!', 'approval');
    };

    const handleFeedbackUpdate = (data, timestamp) => {
        console.log('Asesor provided feedback:', data);
        showNotification('Asesor memberikan feedback pada APL-02 Anda!', 'feedback');
    };

    const stopListening = () => {
        if (echoChannel) {
            console.log('Stopping APL-02 asesi listener');
            window.Echo.leaveChannel(`apl02.${authStore.user.id_izin}`);
            echoChannel = null;
            isListening.value = false;
        }
    };

    // Auto-cleanup on component unmount
    onUnmounted(() => {
        stopListening();
    });

    return {
        isListening,
        asesorUpdates,
        lastSignature,
        startListening,
        stopListening
    };
}

/**
 * Usage in Vue Component for Asesi
 */
export default {
    name: 'AsesiApl02View',
    setup() {
        const { isListening, asesorUpdates, lastSignature, startListening, stopListening } = useApl02AsesiListener();
        const authStore = useAuthStore();

        const apl02Data = ref({
            metode: null,
            is_dilanjutkan: null,
            answers: [],
            pdf_url: null,
            signatures: []
        });

        // Start listening when component mounts
        onMounted(() => {
            if (authStore.user.id_izin) {
                startListening(authStore.user.id_izin, (event) => {
                    updateApl02Data(event);
                });
            }
        });

        function updateApl02Data(event) {
            const { update_type, data } = event;

            if (update_type === 'signature') {
                // Update the PDF URL when asesor signs
                apl02Data.value.pdf_url = data.pdf_url;
                apl02Data.value.signatures.push({
                    type: 'asesor',
                    pdf_url: data.pdf_url,
                    signed_at: data.signed_at,
                    barcode_data: data.barcode_data
                });

                // Show success message
                showSuccessMessage('APL-02 telah ditandatangani oleh asesor!');
            }
        }

        function showSuccessMessage(message) {
            // Replace with your preferred notification system
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Update APL-02', {
                    body: message,
                    icon: '/icon.png',
                    badge: '/badge.png'
                });
            } else {
                // Fallback to in-app notification
                alert(message);
            }
        }

        // Request notification permission on mount
        onMounted(() => {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        });

        return {
            isListening,
            asesorUpdates,
            lastSignature,
            apl02Data,
            stopListening
        };
    }
};

/**
 * React Hooks Example for Asesi
 */
import { useEffect, useState, useCallback } from 'react';
import { useAuth } from '@/hooks/useAuth';

export function useApl02AsesiListenerReact() {
    const { user } = useAuth();
    const [isListening, setIsListening] = useState(false);
    const [asesorUpdates, setAsesorUpdates] = useState([]);
    const [lastSignature, setLastSignature] = useState(null);

    const startListening = useCallback((idIzin, callback) => {
        if (!window.Echo) {
            console.error('Echo is not initialized');
            return;
        }

        console.log('Starting APL-02 listener for asesi:', idIzin);

        const channel = window.Echo.private(`apl02.${idIzin}`)
            .listen('.apl02.asesor.updated', (event) => {
                console.log('Asesor Update Received:', event);
                setAsesorUpdates(prev => [event, ...prev]);

                if (event.update_type === 'signature') {
                    setLastSignature({
                        pdf_url: event.data.pdf_url,
                        filename: event.data.filename,
                        signed_by: event.data.signed_by,
                        signed_at: event.data.signed_at,
                        barcode_data: event.data.barcode_data
                    });
                }

                if (callback && typeof callback === 'function') {
                    callback(event);
                }
            });

        setIsListening(true);

        // Return cleanup function
        return () => {
            window.Echo.leaveChannel(`apl02.${idIzin}`);
            setIsListening(false);
        };
    }, []);

    return { isListening, asesorUpdates, lastSignature, startListening };
}

/**
 * Usage in React Component for Asesi
 */
export default function AsesiApl02ViewReact() {
    const { user } = useAuth();
    const { isListening, asesorUpdates, lastSignature, startListening } = useApl02AsesiListenerReact();
    const [apl02Data, setApl02Data] = useState({
        pdf_url: null,
        signatures: []
    });

    useEffect(() => {
        if (user.id_izin) {
            const cleanup = startListening(user.id_izin, (event) => {
                if (event.update_type === 'signature') {
                    setApl02Data(prev => ({
                        ...prev,
                        pdf_url: event.data.pdf_url,
                        signatures: [
                            ...prev.signatures,
                            {
                                type: 'asesor',
                                pdf_url: event.data.pdf_url,
                                signed_at: event.data.signed_at,
                                barcode_data: event.data.barcode_data
                            }
                        ]
                    }));
                }
            });

            return cleanup;
        }
    }, [user.id_izin, startListening]);

    // Request notification permission
    useEffect(() => {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }, []);

    const showNotification = (message) => {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Update APL-02', {
                body: message,
                icon: '/icon.png'
            });
        }
    };

    return (
        <div>
            <h1>APL-02 Asesi View</h1>
            <div className={`connection-status ${isListening ? 'connected' : 'disconnected'}`}>
                Status: {isListening ? '🟢 Terhubung' : '🔴 Terputus'}
            </div>

            {lastSignature && (
                <div className="signature-update alert alert-success">
                    <h4>✅ Tanda Tangan Asesor Baru</h4>
                    <p>Ditandatangani pada: {new Date(lastSignature.signed_at).toLocaleString()}</p>
                    <a href={lastSignature.pdf_url} target="_blank" rel="noopener noreferrer">
                        Lihat PDF APL-02
                    </a>
                </div>
            )}

            <div className="updates-history">
                <h3>Riwayat Update Asesor</h3>
                {asesorUpdates.map((update, idx) => (
                    <div key={idx} className="update-item">
                        <span className={`badge ${update.update_type}`}>
                            {update.update_type}
                        </span>
                        <span className="timestamp">
                            {new Date(update.timestamp).toLocaleString()}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

/**
 * Plain JavaScript Example for Asesi
 */
class AsesiApl02Listener {
    constructor(idIzin) {
        this.idIzin = idIzin;
        this.channel = null;
        this.isListening = false;
        this.updates = [];
    }

    start(callback) {
        if (this.isListening) {
            console.warn('Already listening to APL-02 asesor updates');
            return;
        }

        if (!window.Echo) {
            console.error('Echo is not initialized');
            throw new Error('Echo is not initialized');
        }

        console.log('Starting APL-02 listener for asesi:', this.idIzin);

        try {
            this.channel = window.Echo.private(`apl02.${this.idIzin}`)
                .listen('.apl02.asesor.updated', (event) => {
                    console.log('Asesor Update Received:', event);
                    this.updates.unshift(event);

                    // Handle specific update types
                    if (event.update_type === 'signature') {
                        this.handleSignatureUpdate(event);
                    }

                    if (callback && typeof callback === 'function') {
                        callback(event);
                    }
                });

            this.isListening = true;
            console.log('APL-02 asesi listener started successfully');
        } catch (error) {
            console.error('Failed to start APL-02 asesi listener:', error);
            throw error;
        }
    }

    handleSignatureUpdate(event) {
        const { data } = event;

        // Update the UI to show new signature
        updateSignatureDisplay(data);
        showNotification('Asesor telah menandatangani APL-02 Anda!');

        // Update PDF link
        const pdfLink = document.getElementById('apl02-pdf-link');
        if (pdfLink) {
            pdfLink.href = data.pdf_url;
            pdfLink.textContent = 'Lihat APL-02 Terbaru';
            pdfLink.classList.add('highlight');
        }
    }

    stop() {
        if (this.channel) {
            console.log('Stopping APL-02 asesi listener');
            window.Echo.leaveChannel(`apl02.${this.idIzin}`);
            this.channel = null;
            this.isListening = false;
        }
    }
}

/**
 * Usage in Plain JavaScript for Asesi
 */
document.addEventListener('DOMContentLoaded', function() {
    // Get asesi ID from your authentication system
    const idIzin = getCurrentAsesiIdIzin(); // Implement this function

    const listener = new AsesiApl02Listener(idIzin);

    listener.start(function(event) {
        console.log('New asesor update:', event);
        updateDashboardUI(event);
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        listener.stop();
    });
});

/**
 * Utility Functions
 */
function updateSignatureDisplay(signatureData) {
    const signatureContainer = document.getElementById('signature-container');
    if (!signatureContainer) return;

    const signatureElement = document.createElement('div');
    signatureElement.className = 'signature-item new-signature';
    signatureElement.innerHTML = `
        <div class="signature-info">
            <span class="signature-type">Asesor</span>
            <span class="signature-date">${new Date(signatureData.signed_at).toLocaleString()}</span>
        </div>
        <a href="${signatureData.pdf_url}" target="_blank" class="btn-view-pdf">
            Lihat PDF
        </a>
    `;

    signatureContainer.insertBefore(signatureElement, signatureContainer.firstChild);

    // Remove highlight after animation
    setTimeout(() => {
        signatureElement.classList.remove('new-signature');
    }, 3000);
}

function showNotification(message) {
    // Browser notification
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('Update APL-02', {
            body: message,
            icon: '/icon.png',
            badge: '/badge.png',
            vibrate: [200, 100, 200]
        });
    } else {
        // In-app notification fallback
        const notification = document.createElement('div');
        notification.className = 'in-app-notification';
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
}

function updateDashboardUI(event) {
    const { update_type, data, timestamp } = event;

    // Update connection indicator
    const connectionStatus = document.getElementById('connection-status');
    if (connectionStatus) {
        connectionStatus.className = 'connection-status connected';
        connectionStatus.textContent = '🟢 Terhubung';
    }

    // Update last update time
    const lastUpdate = document.getElementById('last-update-time');
    if (lastUpdate) {
        lastUpdate.textContent = new Date(timestamp).toLocaleString();
    }

    // Add to updates log
    const updatesLog = document.getElementById('updates-log');
    if (updatesLog) {
        const updateItem = document.createElement('div');
        updateItem.className = `update-item ${update_type}`;
        updateItem.innerHTML = `
            <span class="update-type">${update_type}</span>
            <span class="update-time">${new Date(timestamp).toLocaleString()}</span>
        `;
        updatesLog.insertBefore(updateItem, updatesLog.firstChild);
    }
}

// Helper function to get current asesi id_izin
function getCurrentAsesiIdIzin() {
    // Implement based on your authentication system
    // This might come from:
    // - localStorage
    // - sessionStorage
    // - API call
    // - Global user object

    const user = JSON.parse(localStorage.getItem('user') || '{}');
    return user.id_izin || null;
}

// Export for use in modules
export { useApl02AsesiListener, useApl02AsesiListenerReact, AsesiApl02Listener };
