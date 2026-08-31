<?php
// -------------------------------------------------------------------------
// BLOQUEIO TOTAL DE ROBÔS E CACHE (NÍVEL MÁXIMO)
// -------------------------------------------------------------------------

// 1. X-Robots-Tag Agressiva:
// noindex: Não indexar a página
// nofollow: Não seguir links
// noarchive: Não mostrar link "Em cache" nos resultados
// nosnippet: Não mostrar descrição/texto nos resultados
// noimageindex: Não indexar imagens desta página
// notranslate: Não oferecer tradução nos resultados
header("X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate", true);

// 2. Bloqueio de Cache de Navegador/Proxy (Para evitar que a página fique salva):
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 3. Configuração de Conteúdo
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- REFORÇO NO HTML (Caso algum robô ignore o cabeçalho HTTP) -->
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    
    <title>Processando Áudio</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #333;
        }
        .card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
            border-top: 5px solid #0056b3;
        }
        .spinner-container {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
        }
        .spinner {
            width: 44px;
            height: 44px;
            border: 4px solid #e9ecef;
            border-top: 4px solid #0056b3;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        h1 {
            font-size: 22px;
            margin-bottom: 15px;
            color: #1a1a1a;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
            color: #555;
            margin-bottom: 25px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: #0056b3;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            font-size: 15px;
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.3s;
        }
        .btn:hover {
            background-color: #004494;
        }
        .btn svg {
            width: 16px;
            height: 16px;
        }
        .footer-note {
            margin-top: 25px;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>

    <div class="card">
        <div class="spinner-container">
            <div class="spinner"></div>
        </div>
        <h1>Áudio em Processamento</h1>
        <p>
            <?= htmlspecialchars($message ?? 'O áudio ainda está sendo processado. Tente novamente em alguns instantes.', ENT_QUOTES, 'UTF-8'); ?>
        </p>
        
        <button type="button" onclick="window.location.reload();" class="btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
            </svg>
            Recarregar
        </button>

        <div class="footer-note">
            &copy; <?= date("Y"); ?> USODIGITAL - Todos os direitos reservados.
        </div>
    </div>

</body>
</html>
