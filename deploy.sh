#!/bin/bash
# Deployment script with cache busting

echo "🚀 Starting deployment with cache busting..."

# Generate new version timestamp
NEW_VERSION=$(date +"%Y%m%d_%H%M")

# Update version config
cat > config/version.php << EOF
<?php
return [
    'css_version' => '${NEW_VERSION}',
    'js_version' => '${NEW_VERSION}',
    'cache_timestamp' => $(date +%s),
];
?>
EOF

echo "✅ Cache versions updated to: ${NEW_VERSION}"

# Optional: Update static HTML files
sed -i "s/v=[0-9]\{8\}_[0-9]\{4\}/v=${NEW_VERSION}/g" index.html
sed -i "s/v=[0-9]\{8\}_[0-9]\{4\}/v=${NEW_VERSION}/g" admin.html

echo "✅ HTML files updated with new cache version"
echo "🎯 Deployment complete - cache busting active!"
