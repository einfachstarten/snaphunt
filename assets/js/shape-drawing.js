class InteractiveMapTest {
    constructor() {
        this.map = null;
        this.drawnItems = new L.FeatureGroup();
        this.deviceMarkers = new L.FeatureGroup(); // New layer for device markers
        this.drawControl = null;
        this.deviceId = this.generateDeviceId();
        this.isDrawingMode = false;
        this.discoveredDevices = new Map();
        this.pingInterval = null;
        this.myLocation = null; // Store current user location
        this.watchPositionId = null;
        this.state = { initialized: false, debugId: Math.random().toString(36).substr(2, 9) };

        this.init();
    }

    init() {
        console.log(`🚀 Initializing Snaphunt Interactive Map Test (ID: ${this.state.debugId})`);

        if (this.state.initialized) {
            console.warn('🚨 Already initialized, skipping');
            return;
        }

        this.setupEventListeners();
        this.initializeMap();
        this.setupDrawingTools();
        this.initializeUserLocation();
        this.startDeviceHeartbeat();

        // Initialize status
        this.lastApiSuccess = false;
        this.updateConnectionStatus();

        this.state.initialized = true;
        console.log('✅ Interactive map test initialized with simplified controls');
    }
    
    initializeMap() {
        // Initialize map centered on Vienna
        this.map = L.map('test-map', {
            zoomControl: true,
            attributionControl: true,
            touchZoom: true,
            doubleClickZoom: false, // Prevent interference with drawing
            boxZoom: true,
            keyboard: true,
            scrollWheelZoom: true,
            tap: true,
            tapTolerance: 15, // Mobile optimization
            worldCopyJump: false
        }).setView([48.2082, 16.3738], 13);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19,
            minZoom: 3
        }).addTo(this.map);
        
        // Add drawn items layer
        this.map.addLayer(this.drawnItems);
        
        // Add device markers layer
        this.map.addLayer(this.deviceMarkers);
        
        console.log('🗺️ Map initialized');
    }

    setupDrawingTools() {
        // Simplified drawing toolbar - ONLY rectangles and circles
        this.drawControl = new L.Control.Draw({
            position: 'topleft',
            draw: {
                // ONLY these two shapes
                rectangle: {
                    shapeOptions: {
                        color: '#6366f1',
                        fillColor: '#6366f1',
                        fillOpacity: 0.25,
                        weight: 3
                    }
                },
                circle: {
                    shapeOptions: {
                        color: '#10b981',
                        fillColor: '#10b981',
                        fillOpacity: 0.25,
                        weight: 3
                    }
                },
                // Disable everything else
                polygon: false,
                polyline: false,
                marker: false,
                circlemarker: false
            },
            edit: {
                featureGroup: this.drawnItems,
                remove: true
            }
        });
    }
    
    setupEventListeners() {
        // Drawing event handlers with feedback
        this.map.on(L.Draw.Event.CREATED, (e) => {
            const layer = e.layer;
            const type = e.layerType;

            // Add metadata to layer
            layer.options.createdAt = Date.now();
            layer.options.createdBy = this.deviceId;
            layer.options.shapeType = type;

            this.drawnItems.addLayer(layer);

            // Show feedback
            this.showFeedback(`${type} created`);

            console.log(`✏️ ${type} created:`, layer);
            this.saveShapesToStorage();
        });

        this.map.on(L.Draw.Event.EDITED, (e) => {
            const count = Object.keys(e.layers._layers).length;
            this.showFeedback(`${count} shape(s) edited`);
            console.log('📝 Shapes edited:', e.layers);
            this.saveShapesToStorage();
        });

        this.map.on(L.Draw.Event.DELETED, (e) => {
            const count = Object.keys(e.layers._layers).length;
            this.showFeedback(`${count} shape(s) deleted`);
            console.log('🗑️ Shapes deleted:', e.layers);
            this.saveShapesToStorage();
        });

        // Simplified button event listeners
        document.getElementById('clear-shapes').onclick = () => this.clearAllShapes();
        document.getElementById('toggle-drawing').onclick = () => this.toggleDrawingMode();
        document.getElementById('save-shapes').onclick = () => this.exportShapes();
        document.getElementById('load-shapes').onclick = () => this.importShapes();
        document.getElementById('ping-btn').onclick = () => this.triggerPing();
        document.getElementById('center-my-location').onclick = () => this.centerOnMyLocation();
        document.getElementById('clear-device-markers').onclick = () => this.clearDeviceMarkers();

        // Update connection status periodically
        setInterval(() => {
            this.updateConnectionStatus();
        }, 2000);
    }
    
    initializeUserLocation() {
        if (!navigator.geolocation) {
            console.warn('Geolocation not supported');
            return;
        }

        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000
        };

        // Get initial position
        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.myLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                
                // Add "my location" marker to map
                this.updateMyLocationMarker();
                
                console.log('📍 User location obtained:', this.myLocation);
            },
            (error) => {
                console.warn('Failed to get user location:', error);
                // Fallback to Vienna center
                this.myLocation = { lat: 48.2082, lng: 16.3738 };
            },
            options
        );

        // Watch position for continuous updates
        this.watchPositionId = navigator.geolocation.watchPosition(
            (position) => {
                this.myLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                this.updateMyLocationMarker();
            },
            (error) => console.warn('Location watch error:', error),
            options
        );
    }

    updateMyLocationMarker() {
        if (!this.myLocation) return;

        // Remove existing "my location" marker
        this.deviceMarkers.eachLayer((layer) => {
            if (layer.options.isMyLocation) {
                this.deviceMarkers.removeLayer(layer);
            }
        });

        // Add updated "my location" marker with FIXED positioning
        const myMarker = L.marker([this.myLocation.lat, this.myLocation.lng], {
            icon: L.divIcon({
                className: '', // Remove default leaflet classes
                html: '<div class="device-marker my-location">📍</div>',
                iconSize: [36, 36],
                iconAnchor: [18, 18], // CRITICAL: Center the larger icon
                popupAnchor: [0, -18]
            }),
            isMyLocation: true
        });

        myMarker.bindPopup(`
            <div style="text-align: center; font-family: system-ui;">
                <strong>📍 My Location</strong><br>
                <small style="color: #6b7280;">You are here</small><br>
                <small style="font-size: 0.8em; color: #9ca3af;">
                    ${this.myLocation.lat.toFixed(4)}, ${this.myLocation.lng.toFixed(4)}
                </small>
            </div>
        `);
        
        this.deviceMarkers.addLayer(myMarker);
        console.log(`📍 My location marker updated at [${this.myLocation.lat}, ${this.myLocation.lng}]`);
    }

    centerOnMyLocation() {
        if (!this.myLocation) {
            // Try to get location again
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.myLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    this.map.setView([this.myLocation.lat, this.myLocation.lng], 16);
                    this.updateMyLocationMarker();
                    console.log('🎯 Centered on user location');
                },
                (error) => {
                    console.warn('Could not get location:', error);
                    alert('Unable to get your location. Please enable location access.');
                }
            );
        } else {
            this.map.setView([this.myLocation.lat, this.myLocation.lng], 16);
            console.log('🎯 Centered on stored location');
        }
    }

    clearDeviceMarkers() {
        // Clear all device markers except "my location"
        const layersToRemove = [];
        
        this.deviceMarkers.eachLayer((layer) => {
            if (!layer.options.isMyLocation) {
                layersToRemove.push(layer);
            }
        });

        layersToRemove.forEach(layer => this.deviceMarkers.removeLayer(layer));
        
        console.log('🧹 Device markers cleared');
    }

    updateConnectionStatus() {
        const statusDot = document.getElementById('connection-status');
        const deviceCount = document.getElementById('device-count');

        // Update connection status based on last successful API call
        if (statusDot) {
            statusDot.className = this.lastApiSuccess ? 'status-dot online' : 'status-dot offline';
        }

        // Update device count
        if (deviceCount) {
            const count = this.discoveredDevices.size;
            deviceCount.textContent = count === 1 ? '1 device' : `${count} devices`;
        }
    }

    showFeedback(message, duration = 3000) {
        // Remove existing feedback
        document.querySelectorAll('.feedback-message').forEach(el => el.remove());

        // Create new feedback message
        const feedback = document.createElement('div');
        feedback.className = 'feedback-message';
        feedback.textContent = message;
        document.body.appendChild(feedback);

        // Auto-remove
        setTimeout(() => {
            if (feedback.parentNode) {
                feedback.remove();
            }
        }, duration);
    }

    toggleDrawingMode() {
        const btn = document.getElementById('toggle-drawing');

        if (!this.isDrawingMode) {
            this.map.addControl(this.drawControl);
            this.isDrawingMode = true;
            btn.classList.add('active');
            btn.title = 'Exit Drawing Mode (rectangles & circles)';
            this.showFeedback('Drawing mode: Rectangles & Circles only');
            console.log('✏️ Simplified drawing mode enabled (rectangles & circles only)');
        } else {
            this.map.removeControl(this.drawControl);
            this.isDrawingMode = false;
            btn.classList.remove('active');
            btn.title = 'Enable Drawing Mode';
            this.showFeedback('Drawing mode disabled');
            console.log('🛑 Drawing mode disabled');
        }
    }
    
    clearAllShapes() {
        if (confirm('Clear all shapes from the map?')) {
            this.drawnItems.clearLayers();
            this.saveShapesToStorage();
            console.log('🗑️ All shapes cleared');
        }
    }
    
    saveShapesToStorage() {
        const shapesData = this.drawnItems.toGeoJSON();
        localStorage.setItem('map-test-shapes', JSON.stringify(shapesData));
    }
    
    loadShapesFromStorage() {
        const saved = localStorage.getItem('map-test-shapes');
        if (saved) {
            try {
                const shapesData = JSON.parse(saved);
                this.importGeoJSON(shapesData);
                console.log('📂 Shapes loaded from storage');
            } catch (error) {
                console.error('Error loading shapes:', error);
            }
        }
    }
    
    exportShapes() {
        const shapesData = this.drawnItems.toGeoJSON();
        const dataStr = JSON.stringify(shapesData, null, 2);
        const dataBlob = new Blob([dataStr], { type: 'application/json' });
        
        const url = URL.createObjectURL(dataBlob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `map-shapes-${Date.now()}.json`;
        link.click();
        
        URL.revokeObjectURL(url);
        console.log('💾 Shapes exported');
    }
    
    importShapes() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        const shapesData = JSON.parse(e.target.result);
                        this.importGeoJSON(shapesData);
                        console.log('📂 Shapes imported from file');
                    } catch (error) {
                        console.error('Error importing shapes:', error);
                        alert('Error importing file. Please check the format.');
                    }
                };
                reader.readAsText(file);
            }
        };
        
        input.click();
    }
    
    importGeoJSON(geojson) {
        L.geoJSON(geojson, {
            style: (feature) => ({
                color: feature.properties.color || '#6366f1',
                fillColor: feature.properties.fillColor || '#6366f1',
                fillOpacity: feature.properties.fillOpacity || 0.2,
                weight: feature.properties.weight || 3
            }),
            pointToLayer: (feature, latlng) => {
                return L.marker(latlng, {
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: feature.properties.icon || '📍',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15]
                    })
                });
            },
            onEachFeature: (feature, layer) => {
                this.drawnItems.addLayer(layer);
            }
        });
        
        this.saveShapesToStorage();
    }
    
    // Device Discovery System - SHARED STORAGE APPROACH
    generateDeviceId() {
        let deviceId = localStorage.getItem('test-device-id');
        if (!deviceId) {
            deviceId = 'device_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
            localStorage.setItem('test-device-id', deviceId);
        }
        return deviceId;
    }
    
    startDeviceHeartbeat() {
        // Register this device in SHARED storage and maintain heartbeat
        this.registerDevice();
        
        // Send heartbeat every 3 seconds to shared storage
        setInterval(() => {
            this.registerDevice();
        }, 3000);

        // Check for discovery notifications every 2 seconds
        setInterval(() => {
            this.checkForDiscoveryNotifications();
        }, 2000);
    }
    
    async registerDevice() {
        try {
            const deviceInfo = {
                id: this.deviceId,
                timestamp: Date.now(),
                userAgent: navigator.userAgent.substring(0, 100), // Truncate for storage
                deviceType: this.detectDeviceType(),
                screen: {
                    width: screen.width,
                    height: screen.height
                },
                location: this.getCurrentMapCenter()
            };
            
            // Send to SHARED storage (database or file)
            await fetch('api/device-discovery.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'register',
                    device: deviceInfo
                })
            });
            this.lastApiSuccess = true;
        } catch (error) {
            console.warn('Device registration to shared storage failed:', error);
            this.lastApiSuccess = false;
        }
    }
    
    detectDeviceType() {
        const ua = navigator.userAgent;
        if (/Mobile|Android|iPhone|iPad|iPod|BlackBerry|Windows Phone/i.test(ua)) {
            if (/iPad|Tablet/i.test(ua)) return 'tablet';
            return 'mobile';
        }
        return 'desktop';
    }
    
    getCurrentMapCenter() {
        const center = this.map.getCenter();
        return {
            lat: center.lat,
            lng: center.lng,
            zoom: this.map.getZoom()
        };
    }
    
    async triggerPing() {
        const pingBtn = document.getElementById('ping-btn');
        const devicePanel = document.getElementById('device-panel');
        
        // Visual feedback
        pingBtn.classList.add('pinging');
        setTimeout(() => pingBtn.classList.remove('pinging'), 600);
        
        try {
            const response = await fetch('api/device-discovery.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'ping_discover', // New action that includes ping notifications
                    requester: this.deviceId,
                    requester_location: this.getCurrentMapCenter()
                })
            });
            
            const data = await response.json();

            if (data.success) {
                this.lastApiSuccess = true;
                this.displayDiscoveredDevices(data.devices);
                this.showDeviceMarkersOnMap(data.devices); // NEW: Show devices on map
                devicePanel.classList.add('visible');
                
                console.log(`📡 PING Results (${data.method} storage):`, {
                    discovered: data.count,
                    devices: data.devices.map(d => ({
                        id: d.id.substring(0, 12) + '...',
                        type: d.deviceType || 'unknown',
                        location: d.location
                    }))
                });
                
                // Auto-hide after 15 seconds (longer to see map markers)
                setTimeout(() => {
                    devicePanel.classList.remove('visible');
                }, 15000);
            }
            
        } catch (error) {
            console.error('Ping failed:', error);
            this.showOfflineFallback();
            this.lastApiSuccess = false;
        }
    }

    showDeviceMarkersOnMap(devices) {
        // Clear existing device markers (except my location)
        this.clearDeviceMarkers();

        devices.forEach(device => {
            if (!device.location || !device.location.lat || !device.location.lng) {
                console.warn('Device missing location data:', device);
                return;
            }

            const deviceType = device.deviceType || 'desktop';
            const deviceIcon = this.getDeviceIcon(deviceType);
            const distance = this.calculateMapDistance(device.location);

            // FIXED: Proper marker creation with correct positioning
            const marker = L.marker([device.location.lat, device.location.lng], {
                icon: L.divIcon({
                    className: '', // Remove default leaflet-div-icon class
                    html: `<div class="device-marker ${deviceType}">${deviceIcon}</div>`,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16], // CRITICAL: Center the icon properly
                    popupAnchor: [0, -16] // Position popup above marker
                }),
                isDeviceMarker: true,
                deviceData: device
            });

            const popupContent = `
                <div style="text-align: center; font-family: system-ui; min-width: 120px;">
                    <div style="font-size: 1.1em; margin-bottom: 8px;">
                        <strong>${deviceIcon} ${this.getDeviceTypeName(deviceType)}</strong>
                    </div>
                    <div style="color: #6b7280; font-size: 0.9em;">
                        <div>Distance: ~${distance}m</div>
                        <div>Last seen: ${this.formatTimestamp(device.timestamp)}</div>
                        <div style="font-size: 0.8em; margin-top: 4px; color: #9ca3af;">
                            ID: ${device.id.substring(0, 8)}...
                        </div>
                    </div>
                </div>
            `;

            marker.bindPopup(popupContent);
            this.deviceMarkers.addLayer(marker);

            console.log(`📍 Added device marker at [${device.location.lat}, ${device.location.lng}] for ${deviceType}`);
        });

        console.log(`🗺️ Added ${devices.length} device markers to map with fixed positioning`);
    }

    setupMobileGestures() {
        // Enhanced touch handling for drawing on mobile
        this.map.getContainer().addEventListener('touchstart', (e) => {
            if (this.isDrawingMode && e.touches.length === 1) {
                e.preventDefault(); // Prevent scrolling while drawing
            }
        }, { passive: false });
        
        // Prevent context menu on long press while drawing
        this.map.getContainer().addEventListener('contextmenu', (e) => {
            if (this.isDrawingMode) {
                e.preventDefault();
            }
        });
    }

    getDeviceIcon(deviceType) {
        const icons = {
            mobile: '📱',
            tablet: '📟', 
            desktop: '💻'
        };
        return icons[deviceType] || '📱';
    }

    getDeviceTypeName(deviceType) {
        const names = {
            mobile: 'Mobile Device',
            tablet: 'Tablet',
            desktop: 'Desktop'
        };
        return names[deviceType] || 'Device';
    }

    calculateMapDistance(deviceLocation) {
        const myPos = this.getCurrentMapCenter();
        
        if (!deviceLocation || !deviceLocation.lat || !deviceLocation.lng) return '?';
        
        // Haversine distance calculation
        const R = 6371000; // Earth radius in meters
        const dLat = (deviceLocation.lat - myPos.lat) * Math.PI / 180;
        const dLng = (deviceLocation.lng - myPos.lng) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(myPos.lat * Math.PI / 180) * Math.cos(deviceLocation.lat * Math.PI / 180) *
                Math.sin(dLng/2) * Math.sin(dLng/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return Math.round(R * c);
    }

    // Handle incoming discovery notifications (when this device is discovered)
    checkForDiscoveryNotifications() {
        // Poll for notifications that this device was discovered
        fetch('api/device-discovery.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'check_notifications',
                device_id: this.deviceId
            })
        })
        .then(response => response.json())
        .then(data => {
            this.lastApiSuccess = true;
            if (data.success && data.notifications.length > 0) {
                data.notifications.forEach(notification => {
                    this.showDiscoveryNotification(notification);
                });
            }
        })
        .catch(error => {
            console.warn('Failed to check notifications:', error);
            this.lastApiSuccess = false;
        });
    }

    showDiscoveryNotification(notification) {
        const notificationEl = document.getElementById('discovery-notification');
        const discoveryBy = document.getElementById('discovery-by');
        const compassNeedle = document.getElementById('compass-needle');
        const directionDegrees = document.getElementById('direction-degrees');
        const directionCardinal = document.getElementById('direction-cardinal');
        
        // Calculate direction with compass bearing
        const direction = this.calculateDirection(notification.discoverer_location);
        
        discoveryBy.textContent = this.getDeviceTypeName(notification.discoverer_type);
        directionDegrees.textContent = `${direction.degrees}°`;
        directionCardinal.textContent = direction.cardinal;
        
        // Set compass needle rotation - pointing FROM discoverer TO me
        const needleRotation = (direction.degrees + 180) % 360;
        compassNeedle.style.transform = `rotate(${needleRotation}deg)`;
        
        // Show notification with entrance animation
        notificationEl.classList.remove('hidden');
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            notificationEl.classList.add('hidden');
        }, 10000);
        
        console.log('🧭 Discovery notification shown with compass:', {
            discoverer: notification.discoverer_type,
            bearing: direction.degrees,
            cardinal: direction.cardinal,
            needleRotation: needleRotation
        });
    }

    calculateDirection(fromLocation) {
        const myPos = this.getCurrentMapCenter();
        
        if (!fromLocation || !fromLocation.lat || !fromLocation.lng) {
            return { degrees: 0, cardinal: 'Unknown' };
        }
        
        // Calculate bearing from me to discoverer (where they are relative to me)
        const dLng = (fromLocation.lng - myPos.lng) * Math.PI / 180;
        const lat1 = myPos.lat * Math.PI / 180;
        const lat2 = fromLocation.lat * Math.PI / 180;
        
        const y = Math.sin(dLng) * Math.cos(lat2);
        const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
        
        let bearing = Math.atan2(y, x) * 180 / Math.PI;
        bearing = (bearing + 360) % 360; // Normalize to 0-360
        
        // Convert to cardinal direction
        const cardinals = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 
                          'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        
        const index = Math.round(bearing / 22.5) % 16;
        
        return {
            degrees: Math.round(bearing),
            cardinal: cardinals[index]
        };
    }

    displayDiscoveredDevices(devices) {
        const deviceList = document.getElementById('device-list');

        this.discoveredDevices.clear();
        devices.forEach(device => {
            if (device.id !== this.deviceId) {
                this.discoveredDevices.set(device.id, device);
            }
        });

        if (devices.length === 0) {
            deviceList.innerHTML = `
                <p style="color: #6b7280; font-size: 0.875rem; text-align: center;">
                    No other devices discovered
                </p>
            `;
            this.updateConnectionStatus();
            return;
        }

        deviceList.innerHTML = devices
            .filter(device => device.id !== this.deviceId) // Exclude self
            .map(device => `
                <div class="device-item">
                    <div class="device-info">
                        <h4>📱 ${this.getDeviceType(device)}</h4>
                        <p>Last seen: ${this.formatTimestamp(device.timestamp)}</p>
                        <p>Distance: ${this.calculateDistance(device.location)}m</p>
                    </div>
                    <div class="device-status" title="Online"></div>
                </div>
            `).join('');

        this.updateConnectionStatus();
    }
    
    showOfflineFallback() {
        // Show debugging info about storage methods
        const devicePanel = document.getElementById('device-panel');
        const deviceList = document.getElementById('device-list');
        
        deviceList.innerHTML = `
            <div style="color: #f59e0b; font-size: 0.875rem; text-align: center;">
                ⚠️ Discovery server unavailable<br>
                <small>Shared storage system is offline</small>
            </div>
            <div style="margin-top: 1rem; padding: 0.75rem; background: #f3f4f6; border-radius: 4px; font-size: 0.75rem;">
                <strong>How it works:</strong><br>
                • Each device registers in shared database/file<br>
                • PING queries for other active devices<br>
                • Shows devices active in last 3 minutes<br>
                • No localStorage cross-device access needed!
            </div>
        `;
        
        devicePanel.classList.add('visible');
        setTimeout(() => devicePanel.classList.remove('visible'), 8000);
    }
    
    getDeviceType(device) {
        const ua = device.userAgent || '';
        if (ua.includes('Mobile')) return 'Mobile Device';
        if (ua.includes('Tablet')) return 'Tablet';
        return 'Desktop';
    }
    
    formatTimestamp(timestamp) {
        const diff = Date.now() - timestamp;
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
        return `${Math.floor(diff / 3600000)}h ago`;
    }
    
    calculateDistance(location) {
        const center = this.getCurrentMapCenter();
        
        if (!location || !location.lat || !location.lng) return '?';
        
        // Haversine distance (simplified for demo)
        const R = 6371000; // Earth radius in meters
        const dLat = (location.lat - center.lat) * Math.PI / 180;
        const dLng = (location.lng - center.lng) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(center.lat * Math.PI / 180) * Math.cos(location.lat * Math.PI / 180) *
                Math.sin(dLng/2) * Math.sin(dLng/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return Math.round(R * c);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.mapTest = new InteractiveMapTest();
});
