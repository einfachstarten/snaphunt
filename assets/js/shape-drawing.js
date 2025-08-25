class InteractiveMapTest {
    constructor() {
        this.map = null;
        this.drawnItems = new L.FeatureGroup();
        this.drawControl = null;
        this.deviceId = this.generateDeviceId();
        this.isDrawingMode = false;
        this.discoveredDevices = new Map();
        this.pingInterval = null;
        
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
        
        // Touch/mobile optimizations
        this.setupMobileGestures();
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
    
    // Device Discovery System
    generateDeviceId() {
        let deviceId = localStorage.getItem('test-device-id');
        if (!deviceId) {
            deviceId = 'device_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
            localStorage.setItem('test-device-id', deviceId);
        }
        return deviceId;
    }
    
    startDeviceHeartbeat() {
        // Register this device and maintain heartbeat
        this.registerDevice();
        
        // Send heartbeat every 5 seconds
        setInterval(() => {
            this.registerDevice();
        }, 5000);
    }
    
    async registerDevice() {
        try {
            const deviceInfo = {
                id: this.deviceId,
                timestamp: Date.now(),
                userAgent: navigator.userAgent,
                screen: {
                    width: screen.width,
                    height: screen.height,
                    pixelRatio: window.devicePixelRatio
                },
                location: this.getCurrentMapCenter()
            };
            
            await fetch('api/device-discovery.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'register',
                    device: deviceInfo
                })
            });
        } catch (error) {
            console.warn('Device registration failed:', error);
        }
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
                    action: 'discover',
                    requester: this.deviceId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.displayDiscoveredDevices(data.devices);
                devicePanel.classList.add('visible');
                
                // Auto-hide after 10 seconds
                setTimeout(() => {
                    devicePanel.classList.remove('visible');
                }, 10000);
            }
            
        } catch (error) {
            console.error('Ping failed:', error);
            this.showOfflineFallback();
        }
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
        // Show devices from localStorage as fallback
        const devicePanel = document.getElementById('device-panel');
        const deviceList = document.getElementById('device-list');
        
        deviceList.innerHTML = `
            <p style="color: #f59e0b; font-size: 0.875rem; text-align: center;">
                ⚠️ Discovery server unavailable<br>
                Showing cached devices only
            </p>
        `;
        
        devicePanel.classList.add('visible');
        setTimeout(() => devicePanel.classList.remove('visible'), 5000);
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
