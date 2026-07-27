<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hors connexion</title>
    <meta name="theme-color" content="#391F0E">
    <style>
        *{ box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #F7F4EF; color: #391F0E; padding: 24px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .card {
            background: #fff; border: 1px solid rgba(204,171,135,.35); border-radius: 20px;
            padding: 40px 32px; max-width: 420px; width: 100%; text-align: center;
            box-shadow: 0 12px 40px rgba(57,31,14,.08);
        }
        .icon {
            width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 50%;
            background: #EED4A3; display: flex; align-items: center; justify-content: center;
        }
        h1 { font-size: 20px; margin: 0 0 10px; font-weight: 600; }
        p  { font-size: 14px; line-height: 1.6; color: rgba(57,31,14,.65); margin: 0 0 24px; }
        button {
            background: #391F0E; color: #fff; border: 0; border-radius: 10px;
            padding: 12px 24px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%;
        }
        button:hover { background: #2C1810; }
        .hint { margin-top: 16px; font-size: 12px; color: rgba(57,31,14,.45); }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#391F0E" stroke-width="2" stroke-linecap="round">
                <path d="M1 1l22 22"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
                <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.58 9"/>
                <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                <line x1="12" y1="20" x2="12.01" y2="20"/>
            </svg>
        </div>
        <h1>Vous êtes hors connexion</h1>
        <p>
            Cette page n'est pas disponible sans réseau. Vos données saisies avant la coupure
            ne sont pas perdues : reconnectez-vous pour poursuivre.
        </p>
        <button type="button" onclick="location.reload()">Réessayer</button>
        <p class="hint" id="status">Vérification de la connexion…</p>
    </div>

    <script>
        // Dès que le réseau revient, on recharge : l'utilisateur n'a rien à faire.
        function refreshStatus() {
            const el = document.getElementById('status');
            if (navigator.onLine) {
                el.textContent = 'Connexion rétablie — rechargement…';
                setTimeout(() => location.reload(), 600);
            } else {
                el.textContent = 'En attente du réseau…';
            }
        }
        window.addEventListener('online', refreshStatus);
        window.addEventListener('offline', refreshStatus);
        refreshStatus();
    </script>
</body>
</html>
