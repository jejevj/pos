<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">

    {{-- Canonical so crawlers de-dup the URL --}}
    <link rel="canonical" href="{{ $canonicalUrl }}">

    {{-- Open Graph (WhatsApp / Facebook / most chat clients) --}}
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="{{ $siteName }}">
    <meta property="og:title"       content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url"         content="{{ $canonicalUrl }}">
    <meta property="og:image"       content="{{ $coverUrl }}">
    <meta property="og:image:secure_url" content="{{ $coverUrl }}">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt"   content="{{ $title }}">
    <meta property="og:locale"      content="id_ID">

    {{-- Twitter Card --}}
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image"       content="{{ $coverUrl }}">

    {{-- Send real browsers (not crawlers) to the SPA. --}}
    <meta http-equiv="refresh" content="0;url={{ $spaUrl }}">
    <script>
        // Use replace() so the preview shell never lands in the user's history.
        try { window.location.replace({!! json_encode($spaUrl) !!}); } catch (e) {}
    </script>

    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
               background:#f8fafc; color:#1f2937; margin:0; padding:2rem;
               display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .card { background:#fff; padding:2rem 1.5rem; border-radius:12px;
                box-shadow:0 6px 24px rgba(15,23,42,0.08); max-width:480px; text-align:center; }
        .card img { max-width:100%; border-radius:8px; margin-bottom:1rem; }
        .card h1 { font-size:1.25rem; margin:0 0 .5rem; }
        .card p  { color:#475569; margin:0 0 1rem; }
        .card a  { color:#2563eb; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <div class="card">
        <img src="{{ $coverUrl }}" alt="{{ $title }}">
        <h1>{{ $title }}</h1>
        <p>{{ $description }}</p>
        <p><a href="{{ $spaUrl }}">Buka halaman pelacakan</a></p>
    </div>
</body>
</html>
