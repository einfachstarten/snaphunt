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
        
        this.init();
    }
    
    init() {
        this.initializeMap();
        this.setupDrawingTools();
        this.setupEventListeners();
        this.startDeviceHeartbeat();
        
        console.log('🎯 Interactive Map Test initialized');
        console.log('📱 Device ID:', this.deviceId);
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
        
        // Try to get user's location for "My Location" feature
        this.initializeUserLocation();
        
        console.log('🗺️ Map initialized');
    }
    
    setupDrawingTools() {
        // Drawing toolbar with mobile-optimized options
        this.drawControl = new L.Control.Draw({
            position: 'topleft',
            draw: {
                polygon: {
                    allowIntersection: false,
                    shapeOptions: {
                        color: '#6366f1',
                        fillColor: '#6366f1',
                        fillOpacity: 0.2,
                        weight: 3
                    }
                },
                polyline: {
                    shapeOptions: {
                        color: '#ef4444',
                        weight: 4,
                        opacity: 0.8
                    }
                },
                rectangle: {
                    shapeOptions: {
                        color: '#10b981',
                        fillColor: '#10b981',
                        fillOpacity: 0.2,
                        weight: 3
                    }
                },
                circle: {
                    shapeOptions: {
                        color: '#f59e0b',
                        fillColor: '#f59e0b',
                        fillOpacity: 0.2,
                        weight: 3
                    }
                },
                marker: {
                    icon: L.divIcon({
                        className: 'custom-marker',
                        html: '📍',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15]
                    })
                },
                circlemarker: false // Disable to simplify
            },
            edit: {
                featureGroup: this.drawnItems,
                remove: true
            }
        });
        
        // Initially hidden - activated by button
        // this.map.addControl(this.drawControl);
    }
    
    setupEventListeners() {
        // Drawing event handlers
        this.map.on(L.Draw.Event.CREATED, (e) => {
            const layer = e.layer;
            const type = e.layerType;
            
            // Add metadata to layer
            layer.options.createdAt = Date.now();
            layer.options.createdBy = this.deviceId;
            layer.options.shapeType = type;
            
            this.drawnItems.addLayer(layer);
            
            console.log(`✏️ ${type} created:`, layer);
            this.saveShapesToStorage();
        });
        
        this.map.on(L.Draw.Event.EDITED, (e) => {
            console.log('📝 Shapes edited:', e.layers);
            this.saveShapesToStorage();
        });
        
        this.map.on(L.Draw.Event.DELETED, (e) => {
            console.log('🗑️ Shapes deleted:', e.layers);
            this.saveShapesToStorage();
        });
        
        // Button event listeners
        document.getElementById('clear-shapes').onclick = () => this.clearAllShapes();
        document.getElementById('toggle-drawing').onclick = () => this.toggleDrawingMode();
        document.getElementById('save-shapes').onclick = () => this.exportShapes();
        document.getElementById('load-shapes').onclick = () => this.importShapes();
        document.getElementById('ping-btn').onclick = () => this.triggerPing();
        
        // New button listeners
        document.getElementById('center-my-location').onclick = () => this.centerOnMyLocation();
        document.getElementById('clear-device-markers').onclick = () => this.clearDeviceMarkers();
        
        // Touch/mobile optimizations
        this.setupMobileGestures();
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

        // Add updated "my location" marker
        const myMarker = L.marker([this.myLocation.lat, this.myLocation.lng], {
            icon: L.divIcon({
                className: 'device-marker my-location',
                html: '📍',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            }),
            isMyLocation: true
        });

        myMarker.bindPopup('<strong>📍 My Location</strong><br>You are here');
        this.deviceMarkers.addLayer(myMarker);
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
    
    toggleDrawingMode() {
        const btn = document.getElementById('toggle-drawing');
        
        if (!this.isDrawingMode) {
            this.map.addControl(this.drawControl);
            this.isDrawingMode = true;
            btn.textContent = '🛑 Exit Drawing';
            btn.classList.add('active');
            console.log('✏️ Drawing mode enabled');
        } else {
            this.map.removeControl(this.drawControl);
            this.isDrawingMode = false;
            btn.textContent = '✏️ Drawing Mode';
            btn.classList.remove('active');
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
        } catch (error) {
            console.warn('Device registration to shared storage failed:', error);
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
        }
    }
    
    showDeviceMarkersOnMap(devices) {
        // Clear existing device markers (except my location)
        this.clearDeviceMarkers();

        devices.forEach(device => {
            if (!device.location || !device.location.lat || !device.location.lng) return;

            const deviceType = device.deviceType || 'desktop';
            const deviceIcon = this.getDeviceIcon(deviceType);
            const distance = this.calculateMapDistance(device.location);

            const marker = L.marker([device.location.lat, device.location.lng], {
                icon: L.divIcon({
                    className: `device-marker ${deviceType}`,
                    html: deviceIcon,
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                }),
                isDeviceMarker: true,
                deviceData: device
            });

            const popupContent = `
                <div style="text-align: center;">
                    <strong>${deviceIcon} ${this.getDeviceTypeName(deviceType)}</strong><br>
                    <small>Distance: ~${distance}m</small><br>
                    <small>Last seen: ${this.formatTimestamp(device.timestamp)}</small>
                </div>
            `;

            marker.bindPopup(popupContent);
            this.deviceMarkers.addLayer(marker);
        });

        console.log(`🗺️ Added ${devices.length} device markers to map`);
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
            if (data.success && data.notifications.length > 0) {
                data.notifications.forEach(notification => {
                    this.showDiscoveryNotification(notification);
                });
            }
        })
        .catch(error => console.warn('Failed to check notifications:', error));
    }

    showDiscoveryNotification(notification) {
        const notificationEl = document.getElementById('discovery-notification');
        const discoveryBy = document.getElementById('discovery-by');
        const directionArrow = document.getElementById('direction-arrow');
        
        // Calculate direction arrow
        const direction = this.calculateDirection(notification.discoverer_location);
        
        discoveryBy.textContent = this.getDeviceTypeName(notification.discoverer_type);
        directionArrow.textContent = direction.arrow;
        directionArrow.title = `${direction.degrees}° - ${direction.cardinal}`;
        
        // Show notification
        notificationEl.classList.remove('hidden');
        
        // Auto-hide after 8 seconds
        setTimeout(() => {
            notificationEl.classList.add('hidden');
        }, 8000);
        
        console.log('👁️ Discovery notification shown:', notification);
    }

    calculateDirection(fromLocation) {
        const myPos = this.getCurrentMapCenter();
        
        if (!fromLocation || !fromLocation.lat || !fromLocation.lng) {
            return { arrow: '❓', degrees: 0, cardinal: 'Unknown' };
        }
        
        // Calculate bearing from discoverer to me
        const dLng = (myPos.lng - fromLocation.lng) * Math.PI / 180;
        const lat1 = fromLocation.lat * Math.PI / 180;
        const lat2 = myPos.lat * Math.PI / 180;
        
        const y = Math.sin(dLng) * Math.cos(lat2);
        const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
        
        let bearing = Math.atan2(y, x) * 180 / Math.PI;
        bearing = (bearing + 360) % 360; // Normalize to 0-360
        
        // Convert to arrow and cardinal direction
        const arrows = ['⬆️', '↗️', '➡️', '↘️', '⬇️', '↙️', '⬅️', '↖️'];
        const cardinals = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        
        const index = Math.round(bearing / 45) % 8;
        
        return {
            arrow: arrows[index],
            degrees: Math.round(bearing),
            cardinal: cardinals[index]
        };
    }

    displayDiscoveredDevices(devices) {
        const deviceList = document.getElementById('device-list');
        
        if (devices.length === 0) {
            deviceList.innerHTML = `
                <p style="color: #6b7280; font-size: 0.875rem; text-align: center;">
                    No other devices discovered
                </p>
            `;
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

