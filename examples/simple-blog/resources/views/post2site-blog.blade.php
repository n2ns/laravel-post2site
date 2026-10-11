<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($preview)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <title>{{ is_string($content['title'] ?? null) ? $content['title'] : 'Untitled draft' }}</title>
</head>
<body>
    <main>
        @if ($preview)
            <p>Draft preview — this page does not publish the article.</p>
        @endif
        <h1>{{ is_string($content['title'] ?? null) ? $content['title'] : 'Untitled draft' }}</h1>
        <div style="white-space: pre-wrap">{{ is_string($content['body'] ?? null) ? $content['body'] : '' }}</div>
    </main>
</body>
</html>
