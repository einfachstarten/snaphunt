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
        this.radarAutoHideTimeout = null;

        this.init();
    }

    init() {
        console.log(`🚀 Initializing Snaphunt Interactive Map Test (ID: ${this.state.debugId})`);

        if (this.state.initialized) {
            console.warn('🚨 Already initialized, skipping');
            return;
        }

        // Initialize map with delay to ensure DOM is ready
        setTimeout(() => {
            this.initializeMap();
            this.setupDrawingTools();
            this.setupEventListeners();
        }, 100);

        this.startDeviceHeartbeat();

        // Initialize status
        this.lastApiSuccess = false;
        this.updateConnectionStatus();

        this.state.initialized = true;

        // Add emergency fix button for debugging (remove in production)
        if (window.location.hostname === 'localhost' || window.location.hostname.includes('debug')) {
            setTimeout(() => {
                const emergencyBtn = document.createElement('button');
                emergencyBtn.textContent = '🚨 Emergency Map Fix';
                emergencyBtn.style.cssText = `
                    position: fixed; top: 70px; right: 10px; z-index: 9999;
                    padding: 0.5rem; background: #dc2626; color: white;
                    border: none; border-radius: 6px; cursor: pointer;
                    font-size: 0.8rem;
                `;
                emergencyBtn.onclick = () => this.emergencyMapFix();
                document.body.appendChild(emergencyBtn);
            }, 1000);
        }

        console.log('✅ Interactive map test initialized with debugging');
    }

    debugMapContainer() {
        const mapContainer = document.getElementById('test-map');
        const wrapper = document.querySelector('.map-wrapper');

        console.group('🗺️ Map Container Debug');
        console.log('Map Container Element:', mapContainer);
        console.log('Map Wrapper Element:', wrapper);

        if (mapContainer) {
            const rect = mapContainer.getBoundingClientRect();
            const styles = getComputedStyle(mapContainer);

            console.log('Container Dimensions:', {
                width: rect.width,
                height: rect.height,
                offsetWidth: mapContainer.offsetWidth,
                offsetHeight: mapContainer.offsetHeight
            });

            console.log('Container Styles:', {
                display: styles.display,
                position: styles.position,
                width: styles.width,
                height: styles.height,
                minHeight: styles.minHeight
            });

            console.log('Container Classes:', mapContainer.className);
            console.log('Container HTML:', mapContainer.innerHTML.length + ' characters');
        }

        if (wrapper) {
            const wrapperRect = wrapper.getBoundingClientRect();
            console.log('Wrapper Dimensions:', {
                width: wrapperRect.width,
                height: wrapperRect.height
            });
        }

        console.groupEnd();
    }
    initializeMap() {
        console.log(`🗺️ Initializing map (Instance ID: ${this.state.debugId})`);

        const mapContainer = document.getElementById('test-map');
        if (!mapContainer) {
            console.error('❌ Map container not found');
            return;
        }

        // Debug container before initialization
        this.debugMapContainer();

        // Complete cleanup of any existing map
        if (this.map) {
            console.log('🧹 Cleaning up existing map...');
            try {
                this.map.remove();
                this.map = null;
            } catch (e) {
                console.warn('⚠️ Error removing existing map:', e);
            }
            this.deviceMarkers.clearLayers();
        }

        // Nuclear cleanup of map container
        if (mapContainer._leaflet_id) {
            console.log('☢️ Nuclear cleanup of map container...');
            delete mapContainer._leaflet_id;
            mapContainer.innerHTML = '';
        }

        // CRITICAL: Force explicit dimensions on map element BEFORE initialization
        console.log('🔧 Setting explicit map dimensions...');
        mapContainer.style.width = '100%';
        mapContainer.style.height = '100%';
        mapContainer.style.minHeight = '400px';
        mapContainer.style.display = 'block';
        mapContainer.style.position = 'relative';
        mapContainer.style.backgroundColor = '#e5e7eb'; // Visible background for debugging

        // Wait for DOM to update dimensions
        setTimeout(() => {
            // Double-check dimensions after CSS application
            const containerRect = mapContainer.getBoundingClientRect();
            console.log(`📐 Map container dimensions after CSS: ${containerRect.width}x${containerRect.height}`);

            if (containerRect.width === 0 || containerRect.height === 0) {
                console.error('❌ Map container still has no dimensions after CSS fix');
                console.error('🔧 Applying emergency dimension fix...');

                // Emergency fallback dimensions
                mapContainer.style.width = '100vw';
                mapContainer.style.height = 'calc(100vh - 60px)';
                mapContainer.style.minHeight = '400px';
                mapContainer.style.position = 'absolute';
                mapContainer.style.top = '60px';
                mapContainer.style.left = '0';
            }

            try {
                console.log('🏗️ Creating Leaflet map...');

                this.map = L.map('test-map', {
                    // Enhanced Leaflet options for better initialization
                    preferCanvas: false,
                    zoomControl: true,
                    attributionControl: true,
                    closePopupOnClick: true,
                    trackResize: true,
                    worldCopyJump: false
                }).setView([48.2082, 16.3738], 13); // Vienna center

                console.log('🗺️ Leaflet map created successfully');

                // Add tile layer with error handling
                const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19,
                    errorTileUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' // 1x1 transparent PNG
                });

                tileLayer.on('tileerror', (e) => {
                    console.warn('🗺️ Tile load error:', e);
                });

                tileLayer.addTo(this.map);

                console.log('🗺️ Tile layer added');

                // Add feature groups
                this.map.addLayer(this.drawnItems);
                this.map.addLayer(this.deviceMarkers);

                // Critical: Force map to recognize its size immediately
                setTimeout(() => {
                    this.map.invalidateSize(true);
                    console.log('🔄 Map size invalidated');

                    // Final dimension check
                    const mapSize = this.map.getSize();
                    console.log(`📏 Final map size: ${mapSize.x}x${mapSize.y}`);

                    // Try to get user location for initialization
                    this.initializeUserLocation();

                    console.log('✅ Map initialized successfully');
                }, 100);

            } catch (error) {
                console.error('❌ Map initialization failed:', error);
                console.error('📊 Debug info:', {
                    containerWidth: mapContainer.offsetWidth,
                    containerHeight: mapContainer.offsetHeight,
                    containerDisplay: getComputedStyle(mapContainer).display,
                    containerPosition: getComputedStyle(mapContainer).position,
                    hasLeafletId: !!mapContainer._leaflet_id
                });

                // Last resort: Show error message
                mapContainer.innerHTML = `
                    <div style="
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        height: 100%;
                        background: #fef2f2;
                        color: #dc2626;
                        font-family: system-ui;
                        flex-direction: column;
                        padding: 2rem;
                        text-align: center;
                    ">
                        <h3>🗺️ Map Initialization Failed</h3>
                        <p>Container: ${mapContainer.offsetWidth}x${mapContainer.offsetHeight}</p>
                        <p>Error: ${error.message}</p>
                        <button onclick="window.location.reload()" style="
                            padding: 0.5rem 1rem;
                            background: #dc2626;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            margin-top: 1rem;
                        ">Reload Page</button>
                    </div>
                `;
            }
        }, 200); // Wait 200ms for CSS to be applied
    }

    emergencyMapFix() {
        console.log('🚨 Applying emergency map fix...');

        const mapContainer = document.getElementById('test-map');
        if (!mapContainer) {
            console.error('No map container found for emergency fix');
            return;
        }

        // Apply emergency CSS class
        mapContainer.className = 'map-emergency-fix';

        // Force dimensions with inline styles
        mapContainer.style.cssText = `
            position: fixed !important;
            top: 60px !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: calc(100vh - 60px) !important;
            z-index: 1 !important;
            background: #e5e7eb !important;
            display: block !important;
        `;

        // Clear and reinitialize map
        if (this.map) {
            this.map.remove();
            this.map = null;
        }

        setTimeout(() => {
            try {
                this.map = L.map('test-map').setView([48.2082, 16.3738], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(this.map);

                this.map.addLayer(this.drawnItems);
                this.map.addLayer(this.deviceMarkers);

                this.map.invalidateSize(true);
                console.log('✅ Emergency map fix successful');

            } catch (error) {
                console.error('❌ Emergency map fix failed:', error);
            }
        }, 300);
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

        // Add radar close button listener
        const closeRadarBtn = document.getElementById('close-radar');
        if (closeRadarBtn) {
            closeRadarBtn.onclick = () => this.hideDiscoveryRadar();
        }

        // Close radar on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideDiscoveryRadar();
            }
        });

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
                // Show regular device panel
                this.displayDiscoveredDevices(data.devices);
                this.showDeviceMarkersOnMap(data.devices);
                devicePanel.classList.add('visible');

                // NEW: Show radar compass display
                this.showDiscoveryRadar(data.devices);

                console.log(`📡 PING Results (${data.method} storage):`, {
                    discovered: data.count,
                    devices: data.devices.map(d => ({
                        id: d.id.substring(0, 12) + '...',
                        type: d.deviceType || 'unknown',
                        location: d.location
                    }))
                });

                // Auto-hide device panel after 10 seconds (radar stays longer)
                setTimeout(() => {
                    devicePanel.classList.remove('visible');
                }, 10000);
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

    // Show radar compass for discovered devices
    showDiscoveryRadar(devices) {
        const radarEl = document.getElementById('discovery-radar');
        const devicesContainer = document.getElementById('radar-devices');
        const deviceItemsContainer = document.getElementById('radar-device-items');
        
        // Clear existing indicators
        devicesContainer.innerHTML = '';
        deviceItemsContainer.innerHTML = '';
        
        if (!devices || devices.length === 0) {
            this.hideDiscoveryRadar();
            return;
        }
        
        console.log('📡 Showing discovery radar for', devices.length, 'devices');
        
        // Calculate positions for each device
        devices.forEach((device, index) => {
            const direction = this.calculateDirection(device.location);
            const distance = this.calculateMapDistance(device.location);
            
            // Add radar indicator
            this.addRadarDeviceIndicator(device, direction, distance, index);
            
            // Add device to list
            this.addRadarDeviceListItem(device, direction, distance, index);
        });
        
        // Show radar with animation
        radarEl.classList.remove('hidden');
        
        // Auto-hide after 15 seconds (longer than regular discovery panel)
        this.radarAutoHideTimeout = setTimeout(() => {
            this.hideDiscoveryRadar();
        }, 15000);
        
        console.log('📡 Discovery radar displayed with', devices.length, 'device indicators');
    }

    addRadarDeviceIndicator(device, direction, distance, index) {
        const devicesContainer = document.getElementById('radar-devices');
        const deviceType = device.deviceType || 'desktop';
        
        // Calculate position on radar (distance affects radius from center)
        const maxRadius = 85; // Maximum distance from center in pixels
        const minRadius = 25; // Minimum distance from center in pixels
        
        // Normalize distance to radar radius (assume max 1000m for radar scale)
        const normalizedDistance = Math.min(distance / 1000, 1);
        const radarRadius = minRadius + (normalizedDistance * (maxRadius - minRadius));
        
        // Convert bearing to radians for positioning
        const angleRad = (direction.degrees - 90) * (Math.PI / 180); // -90 to make 0° point up
        
        const x = 100 + (Math.cos(angleRad) * radarRadius); // 100 = center of 200px radar
        const y = 100 + (Math.sin(angleRad) * radarRadius);
        
        // Create radar indicator
        const indicator = document.createElement('div');
        indicator.className = `radar-device-indicator ${deviceType}`;
        indicator.style.left = x + 'px';
        indicator.style.top = y + 'px';
        indicator.style.animationDelay = (index * 0.1) + 's'; // Staggered animation
        
        // Add tooltip and click handler
        indicator.title = `${this.getDeviceTypeName(deviceType)} - ${direction.degrees}° ${direction.cardinal} (~${distance}m)`;
        indicator.onclick = () => this.focusOnRadarDevice(device);
        
        devicesContainer.appendChild(indicator);
    }

    addRadarDeviceListItem(device, direction, distance, index) {
        const itemsContainer = document.getElementById('radar-device-items');
        const deviceType = device.deviceType || 'desktop';
        const deviceIcon = this.getDeviceIcon(deviceType);
        
        const item = document.createElement('div');
        item.className = 'radar-device-item';
        item.style.animationDelay = (index * 0.05) + 's';
        
        item.innerHTML = `
            <div class="radar-device-icon ${deviceType}">
                ${deviceIcon}
            </div>
            <div class="radar-device-info">
                <div class="radar-device-name">
                    ${this.getDeviceTypeName(deviceType)}
                </div>
                <div class="radar-device-details">
                    ${direction.degrees}° ${direction.cardinal} • ~${distance}m
                </div>
            </div>
        `;
        
        item.onclick = () => this.focusOnRadarDevice(device);
        itemsContainer.appendChild(item);
    }

    focusOnRadarDevice(device) {
        if (!device.location || !device.location.lat || !device.location.lng) return;
        
        // Center map on selected device
        if (this.map) {
            this.map.setView([device.location.lat, device.location.lng], 16);
            
            // Show feedback
            this.showFeedback(`Focused on ${this.getDeviceTypeName(device.deviceType || 'desktop')}`);
            
            // Find and pulse the device marker
            this.deviceMarkers.eachLayer((marker) => {
                if (marker.options.deviceData && marker.options.deviceData.id === device.id) {
                    marker.openPopup();
                    setTimeout(() => marker.closePopup(), 3000);
                }
            });
        }
        
        console.log('🎯 Focused on radar device:', device);
    }

    hideDiscoveryRadar() {
        const radarEl = document.getElementById('discovery-radar');
        radarEl.classList.add('hidden');
        
        // Clear auto-hide timeout
        if (this.radarAutoHideTimeout) {
            clearTimeout(this.radarAutoHideTimeout);
            this.radarAutoHideTimeout = null;
        }
        
        console.log('📡 Discovery radar hidden');
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

    // Enhanced direction calculation with better precision
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
