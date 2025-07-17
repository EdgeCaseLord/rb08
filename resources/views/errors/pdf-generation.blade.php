<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>PDF-Fehler</title>
    <style>
        body { font-family: Arial, sans-serif; background: #fff3e0; color: #8B0000; padding: 40px; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #eee; padding: 32px; }
        h1 { color: #FF6100; }
        .message { margin: 24px 0; font-size: 1.2em; }
        a { color: #FF6100; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PDF-Generierung fehlgeschlagen</h1>
        <div class="message">
            {{ $message ?? 'Beim Erstellen des PDFs ist ein Fehler aufgetreten.' }}
        </div>
        @if(isset($book))
            <a href="{{ url('/filament/resources/books/' . $book->id . '/edit') }}">Zurück zum Buch</a>
        @else
            <a href="{{ url('/dashboard') }}">Zurück zum Dashboard</a>
        @endif
    </div>
</body>
</html>
