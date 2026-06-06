<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        
        <!-- PWA Meta Tags & Icons -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#1e293b">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="192x192" href="/icon-192x192.png">
        <link rel="apple-touch-icon" sizes="192x192" href="/icon-192x192.png">
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&family=Figtree:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        <script>
            window.addEventListener('error', function(e) {
                var errorDiv = document.createElement('div');
                errorDiv.style.cssText = 'color:red; background:black; border:2px solid red; position:fixed; z-index:99999; top:10px; left:10px; padding:20px; font-family:monospace; font-size:16px;';
                errorDiv.innerHTML = 'JS ERROR: ' + e.message + '<br>File: ' + e.filename + '<br>Line: ' + e.lineno;
                document.body.appendChild(errorDiv);
            });
        </script>
        @inertia
        
        <!-- PWA Service Worker Registration -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('/sw.js');
                });
            }
        </script>
    </body>
</html>
