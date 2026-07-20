<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page en maintenance - Support DC-KNOWING</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-color: #f8fafc;
            --text-secondary: #94a3b8;
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.15);
            --danger: #ef4444;
            --border: #334155;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }

        /* Effets de lumière en arrière-plan */
        body::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.2) 0%, transparent 70%);
            top: -100px;
            right: -100px;
            z-index: 1;
        }
        body::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(239, 68, 68, 0.08) 0%, transparent 70%);
            bottom: -150px;
            left: -150px;
            z-index: 1;
        }

        .container {
            max-width: 600px;
            width: 100%;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            position: relative;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .error-illustration {
            width: 120px;
            height: 120px;
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid rgba(239, 68, 68, 0.2);
            color: var(--danger);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin-bottom: 24px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #f8fafc 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .support-info {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }

        .support-title {
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .support-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: var(--text-color);
            margin-bottom: 10px;
        }
        .support-item:last-child {
            margin-bottom: 0;
        }

        .support-item i {
            color: var(--text-secondary);
            width: 16px;
            text-align: center;
        }

        .support-item a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: opacity 0.2s;
        }
        .support-item a:hover {
            text-decoration: underline;
        }

        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary);
            color: #fff;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
            box-shadow: 0 4px 14px var(--primary-glow);
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
        }

        .footer-logo {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="error-illustration">
        <i class="fas fa-toolbox"></i>
    </div>
    <h1>Page en maintenance technique</h1>
    <p>Cette page rencontre actuellement une anomalie ou est momentanément indisponible. Notre équipe technique a été automatiquement alertée pour résoudre cet incident dans les plus brefs délais.</p>
    
    <div class="support-info">
        <div class="support-title">
            <i class="fas fa-headset"></i> Support DC-KNOWING
        </div>
        <div class="support-item">
            <i class="fas fa-envelope"></i>
            <span>Email : <a href="mailto:support@dc-knowing.com">support@dc-knowing.com</a></span>
        </div>
        <div class="support-item">
            <i class="fab fa-whatsapp"></i>
            <span>WhatsApp : <a href="https://wa.me/22507000000" target="_blank">+225 07 00 00 00</a></span>
        </div>
        <div class="support-item">
            <i class="fas fa-phone-alt"></i>
            <span>Support téléphonique : +225 27 00 00 00</span>
        </div>
    </div>

    <a href="/" class="btn-home">
        <i class="fas fa-arrow-left"></i> Retour à l'accueil
    </a>

    <div class="footer-logo">
        <i class="fas fa-shield-halved"></i> Propulsé par DC-KNOWING
    </div>
</div>

</body>
</html>
