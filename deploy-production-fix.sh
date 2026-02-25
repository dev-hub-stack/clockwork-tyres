#!/bin/bash

echo "🚀 Deploying Production Errors Fix..."
echo "================================"

# Step 1: Pull latest changes (AddOn search fix)
echo "📥 Pulling latest changes..."
sudo git pull origin main

# Step 2: Verify AddOn search fix is applied
echo "🔍 Verifying AddOn search fix..."
if grep -q "part_number.*like.*search" app/Filament/Resources/QuoteResource.php; then
    echo "✅ AddOn search fix verified"
else
    echo "❌ AddOn search fix not found"
    exit 1
fi

# Step 3: Clear application caches (fix Livewire issues)
echo "🧹 Clearing application caches..."
sudo php artisan cache:clear
sudo php artisan config:clear
sudo php artisan view:clear
sudo php artisan route:clear

# Step 4: Optimize for production
echo "⚡ Optimizing for production..."
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache

# Step 5: Restart services if needed
echo "🔄 Restarting services..."
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm

echo "✅ Production errors fix deployed successfully!"
echo "================================"
echo "📋 Changes applied:"
echo "  - AddOn search now uses 'part_number' instead of 'sku'"
echo "  - Application caches cleared"
echo "  - Services restarted"
echo ""
echo "🧪 Test the following:"
echo "  - Search AddOns in Quote creation"
echo "  - Login functionality"
echo "  - Component rendering"
