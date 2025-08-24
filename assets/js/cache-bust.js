// Cache-busting utility for dynamic loading
class CacheBuster {
    constructor() {
        this.version = Date.now();
        this.loadedVersions = new Map();
    }

    // Force reload CSS with cache busting
    reloadCSS(cssPath) {
        const existingLink = document.querySelector(`link[href*="${cssPath}"]`);
        if (existingLink) {
            existingLink.remove();
        }

        const newLink = document.createElement('link');
        newLink.rel = 'stylesheet';
        newLink.href = `${cssPath}?v=${this.version}&t=${Date.now()}`;
        document.head.appendChild(newLink);

        console.log(`🔄 CSS reloaded: ${newLink.href}`);
        return newLink;
    }

    // Force reload JavaScript with cache busting
    reloadJS(jsPath, callback) {
        const existingScript = document.querySelector(`script[src*="${jsPath}"]`);
        if (existingScript) {
            existingScript.remove();
        }

        const newScript = document.createElement('script');
        newScript.src = `${jsPath}?v=${this.version}&t=${Date.now()}`;
        newScript.onload = callback;
        document.head.appendChild(newScript);

        console.log(`🔄 JS reloaded: ${newScript.src}`);
        return newScript;
    }

    // Check if resources need updating
    async checkVersions() {
        try {
            const response = await fetch(`cache_version.php?t=${Date.now()}`);
            const data = await response.json();
            console.log('📋 Current cache versions:', data);
            return data;
        } catch (error) {
            console.warn('Could not check cache versions:', error);
            return null;
        }
    }

    // Force refresh all assets
    forceRefreshAll() {
        console.log('🔄 Force refreshing all cached assets...');
        this.reloadCSS('assets/css/main.css');
        
        setTimeout(() => {
            location.reload(true);
        }, 500);
    }
}

// Global cache buster instance
window.cacheBuster = new CacheBuster();

// Debug commands
window.debugCache = {
    checkVersions: () => window.cacheBuster.checkVersions(),
    forceRefresh: () => window.cacheBuster.forceRefreshAll(),
    reloadCSS: () => window.cacheBuster.reloadCSS('assets/css/main.css')
};

console.log('🔧 Cache busting tools loaded. Use window.debugCache for testing.');
