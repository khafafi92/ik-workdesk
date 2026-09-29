<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LTRO Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>{!! file_get_contents(resource_path('css/ltro-legacy.css')) !!}</style>
    @livewireStyles
</head>
<body class="bg-gray-100 min-h-screen p-0 m-0">
    {{ $slot }}
    @livewireScripts
</body>
</html>
