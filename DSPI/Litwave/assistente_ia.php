<?php
session_start();
if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php");
    exit();
}

$servidor = "localhost";
$usuario = "root";
$senha = "";
$database = "appLivroTeste";
$conexao = mysqli_connect($servidor, $usuario, $senha, $database);

if(!$conexao) {
    die("Erro na conexão: " . mysqli_connect_error());
}

// Verificar se a tabela consultas_ia permite NULL no livro_id
$result = mysqli_query($conexao, "DESCRIBE consultas_ia livro_id");
$column_info = mysqli_fetch_assoc($result);
$allows_null = $column_info['Null'] === 'YES';

// Sistema de múltiplas conversas
if(!isset($_SESSION['chats'])) {
    $_SESSION['chats'] = [
        'default' => [
            'nome' => 'Conversa Principal',
            'historico' => []
        ]
    ];
    $_SESSION['chat_atual'] = 'default';
}

// Trocar de chat
if(isset($_GET['chat'])) {
    $_SESSION['chat_atual'] = $_GET['chat'];
}

// Criar novo chat
if(isset($_GET['novo_chat'])) {
    $chat_id = uniqid();
    $_SESSION['chats'][$chat_id] = [
        'nome' => 'Nova Conversa',
        'historico' => []
    ];
    $_SESSION['chat_atual'] = $chat_id;
    header("Location: assistente_ia.php");
    exit();
}

// Pegar informações do livro se vier por GET
$livro_id = $_GET['livro_id'] ?? null;
$pagina = $_GET['pagina'] ?? 1;
$livro = null;

if($livro_id) {
    $sql = "SELECT * FROM livros WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "i", $livro_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $livro = mysqli_fetch_assoc($resultado);
}

// Buscar chats salvos para a sidebar
$chats_salvos = [];
$sql_chats = "SELECT id, livro_id, termo_consultado, created_at 
             FROM consultas_ia 
             WHERE usuario_id = ? AND tipo = 'CONVERSA' 
             ORDER BY created_at DESC 
             LIMIT 10";
$stmt_chats = mysqli_prepare($conexao, $sql_chats);
if($stmt_chats) {
    $usuario_id = $_SESSION['usuario_id'];
    mysqli_stmt_bind_param($stmt_chats, "i", $usuario_id);
    mysqli_stmt_execute($stmt_chats);
    $resultado_chats = mysqli_stmt_get_result($stmt_chats);
    $chats_salvos = mysqli_fetch_all($resultado_chats, MYSQLI_ASSOC);
}

// Funções
function salvarChatNoBanco($conexao, $usuario_id, $livro_id, $historico, $allows_null) {
    if(empty($historico)) return;
    
    $conversa_completa = "";
    
    foreach($historico as $mensagem) {
        $role = $mensagem['tipo'] === 'usuario' ? 'USUÁRIO' : 'AURORAI';
        $conversa_completa .= "$role: {$mensagem['conteudo']}\n\n";
    }
    
    if($allows_null) {
        if($livro_id) {
            $sql = "INSERT INTO consultas_ia (usuario_id, livro_id, termo_consultado, explicacao_ia, tipo) 
                   VALUES (?, ?, 'Conversa Completa', ?, 'CONVERSA')";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "iis", $usuario_id, $livro_id, $conversa_completa);
        } else {
            $sql = "INSERT INTO consultas_ia (usuario_id, livro_id, termo_consultado, explicacao_ia, tipo) 
                   VALUES (?, NULL, 'Conversa Completa', ?, 'CONVERSA')";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "is", $usuario_id, $conversa_completa);
        }
    } else {
        $livro_id_valor = $livro_id ? $livro_id : 0;
        $sql = "INSERT INTO consultas_ia (usuario_id, livro_id, termo_consultado, explicacao_ia, tipo) 
               VALUES (?, ?, 'Conversa Completa', ?, 'CONVERSA')";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "iis", $usuario_id, $livro_id_valor, $conversa_completa);
    }
    
    mysqli_stmt_execute($stmt);
}

function salvarConsultaNoBanco($conexao, $usuario_id, $livro_id, $pergunta, $resposta, $allows_null) {
    $pergunta_escape = mysqli_real_escape_string($conexao, $pergunta);
    $resposta_escape = mysqli_real_escape_string($conexao, $resposta);
    
    if($allows_null) {
        if($livro_id) {
            $sql = "INSERT INTO consultas_ia (usuario_id, livro_id, termo_consultado, explicacao_ia, tipo) 
                   VALUES (?, ?, ?, ?, 'SIMPLIFICADA')";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "iiss", $usuario_id, $livro_id, $pergunta_escape, $resposta_escape);
        } else {
            $sql = "INSERT INTO consultas_ia (usuario_id, livro_id, termo_consultado, explicacao_ia, tipo) 
                   VALUES (?, NULL, ?, ?, 'SIMPLIFICADA')";
            $stmt = mysqli_prepare($conexao, $sql);
            mysqli_stmt_bind_param($stmt, "iss", $usuario_id, $pergunta_escape, $resposta_escape);
        }
    } else {
        $livro_id_valor = $livro_id ? $livro_id : 0;
        $sql = "INSERT INTO consultas_ia (usuario_id, livro_id, termo_consultado, explicacao_ia, tipo) 
               VALUES (?, ?, ?, ?, 'SIMPLIFICADA')";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "iiss", $usuario_id, $livro_id_valor, $pergunta_escape, $resposta_escape);
    }
    
    mysqli_stmt_execute($stmt);
}

function construirContexto($livro, $pagina, $historico) {
    $contexto = "Você é o AurorAI, um assistente de leitura inteligente. Sua missão é 'orientar o saber como o nascer do sol que orienta o amanhecer'.\n\n";
    
    if($livro) {
        $contexto .= "CONTEXTO ATUAL:\n";
        $contexto .= "- Livro: {$livro['titulo']}\n";
        $contexto .= "- Autor: {$livro['autor']}\n";
        $contexto .= "- Página: $pagina\n";
        if($livro['genero']) {
            $contexto .= "- Gênero: {$livro['genero']}\n";
        }
        $contexto .= "\n";
    }
    
    $historico_recente = array_slice($historico, -6);
    if(count($historico_recente) > 0) {
        $contexto .= "HISTÓRICO RECENTE DA CONVERSA:\n";
        foreach($historico_recente as $mensagem) {
            $role = $mensagem['tipo'] === 'usuario' ? 'USUÁRIO' : 'AURORAI';
            $contexto .= "$role: {$mensagem['conteudo']}\n";
        }
        $contexto .= "\n";
    }
    
    $contexto .= "INSTRUÇÕES:\n";
    $contexto .= "1. Responda de forma clara e educada\n";
    $contexto .= "2. Use **markdown** para formatação (negrito, itálico, listas)\n";
    $contexto .= "3. Seja preciso e objetivo\n";
    $contexto .= "4. Mantenha o contexto da conversa\n";
    
    return $contexto;
}

function chamarGeminiAPI($contexto, $pergunta) {
    $api_key = 'AIzaSyDbtwg810NmZxjnqKwMEflO4iHad8WQ0e4';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    
    $prompt = $contexto . "PERGUNTA ATUAL DO USUÁRIO:\n\"$pergunta\"\n\nRESPOSTA DO AURORAI:";
    
    $dados = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 1024,
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-goog-api-key: ' . $api_key
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $resposta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if($http_code === 200) {
        $dados_resposta = json_decode($resposta, true);
        if(isset($dados_resposta['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($dados_resposta['candidates'][0]['content']['parts'][0]['text']);
        }
    }
    
    return "**Desculpe, houve um erro ao processar sua solicitação.**\n\nTente novamente em alguns instantes.";
}

function markdownParaHtml($texto) {
    $texto = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $texto);
    $texto = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $texto);
    $texto = preg_replace('/^- (.*)$/m', '<li>$1</li>', $texto);
    $texto = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $texto);
    $texto = nl2br($texto);
    $texto = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank">$1</a>', $texto);
    
    return $texto;
}

// Processar ações
$acao = $_GET['acao'] ?? '';

if($acao === 'limpar') {
    $_SESSION['chats'][$_SESSION['chat_atual']]['historico'] = [];
    $redirect_url = "assistente_ia.php";
    if($livro_id) {
        $redirect_url .= "?livro_id=$livro_id&pagina=$pagina";
    }
    header("Location: " . $redirect_url);
    exit();
}

if($acao === 'sair') {
    salvarChatNoBanco($conexao, $_SESSION['usuario_id'], $livro_id, $_SESSION['chats'][$_SESSION['chat_atual']]['historico'], $allows_null);
    $_SESSION['chats'][$_SESSION['chat_atual']]['historico'] = [];
    $redirect_url = "assistente_ia.php";
    if($livro_id) {
        $redirect_url .= "?livro_id=$livro_id&pagina=$pagina";
    }
    header("Location: " . $redirect_url);
    exit();
}

// Processar pergunta do usuário
if(isset($_POST['pergunta']) && !empty(trim($_POST['pergunta']))) {
    $pergunta_usuario = trim($_POST['pergunta']);
    
    $_SESSION['chats'][$_SESSION['chat_atual']]['historico'][] = [
        'tipo' => 'usuario',
        'conteudo' => $pergunta_usuario,
        'timestamp' => time()
    ];
    
    $contexto = construirContexto($livro, $pagina, $_SESSION['chats'][$_SESSION['chat_atual']]['historico']);
    $resposta_ia = chamarGeminiAPI($contexto, $pergunta_usuario);
    
    $_SESSION['chats'][$_SESSION['chat_atual']]['historico'][] = [
        'tipo' => 'ia',
        'conteudo' => $resposta_ia,
        'timestamp' => time()
    ];
    
    salvarConsultaNoBanco($conexao, $_SESSION['usuario_id'], $livro_id, $pergunta_usuario, $resposta_ia, $allows_null);
}

$chat_atual = $_SESSION['chats'][$_SESSION['chat_atual']];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AurorAI - Chat Inteligente</title>
    <style>
        :root {
            --bg: #0a0a0f;
            --panel: rgba(17, 17, 24, 0.7);
            --card: rgba(26, 26, 36, 0.8);
            --accent: #8b5cf6;
            --accent-2: #a78bfa;
            --muted: #6b7280;
            --text: #f8fafc;
            --aurora-1: #8b5cf6;
            --aurora-2: #7c3aed;
            --aurora-3: #6d28d9;
            --aurora-4: #c4b5fd;
            --success: #10b981;
            --danger: #ef4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        body {
            background: 
                linear-gradient(135deg, var(--bg) 0%, #050508 100%),
                radial-gradient(circle at 10% 20%, var(--aurora-1) 0%, transparent 25%),
                radial-gradient(circle at 90% 80%, var(--aurora-2) 0%, transparent 25%),
                radial-gradient(circle at 50% 50%, var(--aurora-3) 0%, transparent 40%),
                radial-gradient(circle at 30% 70%, var(--aurora-4) 0%, transparent 30%);
            background-blend-mode: overlay, screen, screen, screen, screen;
            color: var(--text);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(45deg, transparent 45%, var(--aurora-1) 50%, transparent 55%),
                linear-gradient(-45deg, transparent 45%, var(--aurora-2) 50%, transparent 55%),
                linear-gradient(135deg, transparent 45%, var(--aurora-3) 50%, transparent 55%),
                linear-gradient(-135deg, transparent 45%, var(--aurora-4) 50%, transparent 55%);
            background-size: 400% 400%;
            animation: aurora 15s ease-in-out infinite;
            opacity: 0.15;
            pointer-events: none;
            z-index: -1;
            filter: blur(1px);
        }
        
        @keyframes aurora {
            0%, 100% { 
                background-position: 0% 50%, 100% 50%, 50% 0%, 50% 100%;
            }
            25% { 
                background-position: 100% 50%, 0% 50%, 50% 100%, 50% 0%;
            }
            50% { 
                background-position: 50% 0%, 50% 100%, 0% 50%, 100% 50%;
            }
            75% { 
                background-position: 50% 100%, 50% 0%, 100% 50%, 0% 50%;
            }
        }
        
        .app-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            height: 100vh;
            gap: 0;
            transition: grid-template-columns 0.3s ease;
        }
        
        .app-container.sidebar-hidden {
            grid-template-columns: 0 1fr;
        }
        
        /* Sidebar */
        .sidebar {
            background: rgba(17, 17, 24, 0.6);
            backdrop-filter: blur(25px);
            border-right: 1px solid rgba(139, 92, 246, 0.3);
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: sticky;
            top: 0;
            transition: all 0.3s ease;
        }
        
        .app-container.sidebar-hidden .sidebar {
            transform: translateX(-100%);
            opacity: 0;
        }
        
        .sidebar-header {
            margin-bottom: 30px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--aurora-1), var(--aurora-3));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .new-chat-btn {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            width: 100%;
            justify-content: center;
        }
        
        .new-chat-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4);
        }
        
        .chats-section h3 {
            color: var(--accent);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            opacity: 0.8;
        }
        
        .chat-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .chat-item {
            padding: 12px 16px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }
        
        .chat-item:hover {
            background: rgba(139, 92, 246, 0.15);
            border-color: rgba(139, 92, 246, 0.4);
            transform: translateX(4px);
        }
        
        .chat-item.active {
            background: rgba(139, 92, 246, 0.2);
            border-color: var(--accent);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
        }
        
        .chat-preview {
            color: var(--text);
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .chat-meta {
            font-size: 0.8rem;
            color: var(--muted);
        }
        
        /* Main Content */
        .main-content {
            display: flex;
            flex-direction: column;
            height: 100vh;
            background: rgba(10, 10, 15, 0.5);
            backdrop-filter: blur(10px);
        }
        
        .chat-header {
            padding: 20px 30px;
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
            background: rgba(17, 17, 24, 0.6);
            backdrop-filter: blur(20px);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .menu-toggle {
            background: none;
            border: none;
            color: var(--text);
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .menu-toggle:hover {
            background: rgba(139, 92, 246, 0.1);
        }
        
        .menu-toggle svg {
            width: 20px;
            height: 20px;
        }
        
        .header-info {
            flex: 1;
        }
        
        .chat-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 5px;
        }
        
        .chat-subtitle {
            color: var(--muted);
            font-size: 0.9rem;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-voltar {
            background: rgba(139, 92, 246, 0.1);
            color: var(--text);
            border: 1px solid rgba(139, 92, 246, 0.3);
            padding: 10px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-voltar:hover {
            background: rgba(139, 92, 246, 0.2);
            border-color: var(--accent);
            transform: translateY(-1px);
        }
        
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .mensagem {
            max-width: 70%;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .mensagem.usuario {
            align-self: flex-end;
            margin-left: auto;
        }
        
        .mensagem.ia {
            align-self: flex-start;
            margin-right: auto;
        }
        
        .mensagem-conteudo {
            padding: 16px 20px;
            border-radius: 18px;
            line-height: 1.5;
            position: relative;
        }
        
        .mensagem.usuario .mensagem-conteudo {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border-bottom-right-radius: 6px;
        }
        
        .mensagem.ia .mensagem-conteudo {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-bottom-left-radius: 6px;
            backdrop-filter: blur(10px);
        }
        
        .input-area {
            padding: 20px 30px;
            background: rgba(17, 17, 24, 0.6);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(139, 92, 246, 0.2);
        }
        
        .input-container {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        
        .input-pergunta {
            flex: 1;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 16px;
            color: var(--text);
            font-size: 1rem;
            resize: none;
            min-height: 56px;
            max-height: 120px;
            font-family: inherit;
            transition: all 0.2s ease;
            backdrop-filter: blur(10px);
        }
        
        .input-pergunta:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .input-pergunta::placeholder {
            color: var(--muted);
        }
        
        .btn-enviar {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 14px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 100px;
            justify-content: center;
        }
        
        .btn-enviar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4);
        }
        
        .btn-enviar:active {
            transform: translateY(0);
        }
        
        .empty-state {
            text-align: center;
            color: var(--muted);
            padding: 60px 20px;
            font-style: italic;
        }
        
        .consulta-resposta {
            line-height: 1.6;
        }
        
        .consulta-resposta strong {
            color: var(--accent);
        }
        
        .consulta-resposta em {
            color: var(--muted);
            font-style: italic;
        }
        
        .consulta-resposta ul, 
        .consulta-resposta ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .consulta-resposta li {
            margin-bottom: 5px;
        }
        
        .consulta-resposta code {
            background: rgba(139, 92, 246, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.9em;
        }
        
        /* Scrollbar personalizado */
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 3px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: var(--accent-2);
        }
    </style>
</head>
<body>
    <div class="app-container" id="appContainer">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">AI</div>
                    <div class="logo-text">AurorAI</div>
                </div>
                <a href="?novo_chat=1" class="new-chat-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14m-7-7h14"/>
                    </svg>
                    Nova Conversa
                </a>
            </div>
            
            <div class="chats-section">
                <h3>Suas Conversas</h3>
                <div class="chat-list">
                    <?php foreach($_SESSION['chats'] as $chat_id => $chat): ?>
                    <div class="chat-item <?php echo $chat_id === $_SESSION['chat_atual'] ? 'active' : ''; ?>" 
                         onclick="location.href='?chat=<?php echo $chat_id; ?>'">
                        <div class="chat-preview"><?php echo htmlspecialchars($chat['nome']); ?></div>
                        <div class="chat-meta">
                            <?php echo count($chat['historico']); ?> mensagens
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="chat-header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 12h18M3 6h18M3 18h18"/>
                        </svg>
                    </button>
                    <div class="header-info">
                        <div class="chat-title"><?php echo htmlspecialchars($chat_atual['nome']); ?></div>
                        <div class="chat-subtitle">
                            <?php if($livro): ?>
                            Contexto: <?php echo htmlspecialchars($livro['titulo']); ?> • Página <?php echo $pagina; ?>
                            <?php else: ?>
                            Conversa geral
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="header-actions">
                    <?php if($livro): ?>
                    <a href="ler_livro.php?id=<?php echo $livro_id; ?>&pagina=<?php echo $pagina; ?>" class="btn-voltar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Voltar para Leitura
                    </a>
                    <?php else: ?>
                    <a href="ler_livro.php" class="btn-voltar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Voltar para a Leitura
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="chat-container">
                <div class="chat-messages" id="chatMessages">
                    <?php if(empty($chat_atual['historico'])): ?>
                    <div class="empty-state">
                        <p>Esta conversa está vazia.</p>
                        <p>Comece digitando uma mensagem abaixo.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach($chat_atual['historico'] as $mensagem): ?>
                    <div class="mensagem <?php echo $mensagem['tipo']; ?>">
                        <div class="mensagem-conteudo">
                            <div class="consulta-resposta">
                                <?php echo markdownParaHtml($mensagem['conteudo']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="input-area">
                    <form method="POST" class="input-container" id="chatForm">
                        <textarea 
                            class="input-pergunta" 
                            name="pergunta" 
                            placeholder="Digite sua mensagem... (Markdown suportado)"
                            required
                            id="inputPergunta"
                        ></textarea>
                        
                        <button type="submit" class="btn-enviar">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                            </svg>
                            Enviar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Toggle da sidebar
    const menuToggle = document.getElementById('menuToggle');
    const appContainer = document.getElementById('appContainer');
    
    menuToggle.addEventListener('click', function() {
        appContainer.classList.toggle('sidebar-hidden');
    });
    
    // Rolagem automática para baixo
    function scrollToBottom() {
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Rolagem quando a página carrega
    window.addEventListener('load', scrollToBottom);
    
    // Auto-expand textarea
    const textarea = document.getElementById('inputPergunta');
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
    // Foco no textarea
    textarea.focus();

    // Enviar com Enter (e Shift+Enter para nova linha)
    textarea.addEventListener('keydown', function(e) {
        if(e.key === 'Enter') {
            if(e.shiftKey) {
                // Shift+Enter - nova linha
                return;
            } else {
                // Apenas Enter - enviar mensagem
                e.preventDefault();
                document.getElementById('chatForm').submit();
            }
        }
    });

    // Enviar com Ctrl+Enter
    textarea.addEventListener('keydown', function(e) {
        if(e.key === 'Enter' && e.ctrlKey) {
            e.preventDefault();
            document.getElementById('chatForm').submit();
        }
    });

    // ENVIAR COM ENTER (nova funcionalidade)
    textarea.addEventListener('keydown', function(e) {
        if(e.key === 'Enter') {
            if(e.shiftKey) {
                // Shift+Enter - nova linha
                return;
            } else {
                // Apenas Enter - enviar mensagem
                e.preventDefault();
                document.getElementById('chatForm').submit();
            }
        }
    });
    </script>
</body>
</html>