<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Mengarah ke app.ts karena kita pakai TypeScript -->
    @vite('resources/js/app.ts')
    <x-inertia::head />
</head>
<body class="antialiased">
    <x-inertia::app />
</body>
</html>
