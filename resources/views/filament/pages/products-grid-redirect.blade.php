<x-filament-panels::page>
    <script>
        // Redirect to the actual products grid
        window.location.href = '{{ route('admin.products.grid') }}';
    </script>
    
    <div class="text-center p-6">
        <p class="text-lg">Redirecting to Products Grid...</p>
        <p class="text-sm text-gray-500 mt-2">If you are not redirected automatically, <a href="{{ route('admin.products.grid') }}" class="text-primary-600 hover:underline">click here</a>.</p>
    </div>
</x-filament-panels::page>
