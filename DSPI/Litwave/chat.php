<?php
session_start();

# Verificar se usuário está logado
if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php");
    exit();
}

# === Variáveis do Banco ===
$servidor = "localhost";
$usuario = "root";
$senha = "";
$database = "appLivroTeste";

# === Conectando ao banco ===
$conexao = mysqli_connect($servidor, $usuario, $senha, $database);

if(!$conexao){
    die("Erro na conexão: ".mysqli_connect_error());
}

# Buscar informações do usuário
$usuario_id = $_SESSION['usuario_id'];
$sql_usuario = "SELECT nome, email, ra, foto_perfil FROM usuarios WHERE id = '$usuario_id'";
$result_usuario = mysqli_query($conexao, $sql_usuario);
$usuario_info = mysqli_fetch_assoc($result_usuario);

# Buscar amigos do usuário (com fotos reais)
$sql_amigos = "SELECT u.id, u.nome, u.email, u.ra, u.foto_perfil 
               FROM amigos a 
               JOIN usuarios u ON (a.amigo_id = u.id OR a.usuario_id = u.id)
               WHERE (a.usuario_id = '$usuario_id' OR a.amigo_id = '$usuario_id') 
               AND a.status = 'aceito'
               AND u.id != '$usuario_id'
               LIMIT 10";
$result_amigos = mysqli_query($conexao, $sql_amigos);
$amigos = [];
if($result_amigos) {
    while($row = mysqli_fetch_assoc($result_amigos)){
        $amigos[] = $row;
    }
}

# Buscar livros da estante do usuário para os chats
$sql_livros_estante = "SELECT l.*, hl.status 
                       FROM livros l 
                       INNER JOIN historico_leitura hl ON l.id = hl.livro_id 
                       WHERE hl.usuario_id = '$usuario_id' 
                       ORDER BY hl.updated_at DESC 
                       LIMIT 10";
$result_livros = mysqli_query($conexao, $sql_livros_estante);
$livros_estante = [];
if($result_livros) {
    while($row = mysqli_fetch_assoc($result_livros)){
        $livros_estante[] = $row;
    }
}

# Buscar livro atual para o chat
$livro_atual_id = isset($_GET['livro_id']) ? $_GET['livro_id'] : ($livros_estante[0]['id'] ?? null);
$livro_atual = null;

if($livro_atual_id) {
    $sql_livro_atual = "SELECT * FROM livros WHERE id = '$livro_atual_id'";
    $result_livro_atual = mysqli_query($conexao, $sql_livro_atual);
    $livro_atual = mysqli_fetch_assoc($result_livro_atual);
}

# Processar envio de nova mensagem
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensagem']) && $livro_atual_id) {
    $mensagem = mysqli_real_escape_string($conexao, $_POST['mensagem']);
    $sql_inserir = "INSERT INTO mensagens_chat (livro_id, usuario_id, mensagem) 
                    VALUES ('$livro_atual_id', '$usuario_id', '$mensagem')";
    mysqli_query($conexao, $sql_inserir);
    
    # Redirecionar para evitar reenvio no refresh
    header("Location: chat.php?livro_id=" . $livro_atual_id);
    exit();
}

# Buscar mensagens do chat do banco de dados
$mensagens_chat = [];
if($livro_atual_id) {
    $sql_mensagens = "SELECT m.*, u.nome, u.foto_perfil 
                      FROM mensagens_chat m 
                      JOIN usuarios u ON m.usuario_id = u.id 
                      WHERE m.livro_id = '$livro_atual_id' 
                      ORDER BY m.data_envio ASC";
    $result_mensagens = mysqli_query($conexao, $sql_mensagens);
    if($result_mensagens) {
        while($row = mysqli_fetch_assoc($result_mensagens)){
            $mensagens_chat[] = [
                'usuario_id' => $row['usuario_id'],
                'nome' => $row['nome'],
                'foto_perfil' => $row['foto_perfil'],
                'texto' => $row['mensagem'],
                'hora' => date('H:i', strtotime($row['data_envio']))
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Leitura+ - Chat</title>
    <style>
        :root {
            --bg: #0b0b0d;
            --panel: #0f0f14;
            --card: #111217;
            --accent: #8a5cf6;
            --accent-2: #cfa3ff;
            --muted: #9aa0b4;
            --text: #e7e9ee;
            --glass: rgba(255,255,255,0.03);
            --success: #38d39f;
            --danger: #e74c3c;
            --purple-light: #a78bfa;
            --purple-dark: #7c3aed;
        }
        
        [data-theme="light"] {
            --bg: #f5f7fb;
            --panel: #ffffff;
            --card: #ffffff;
            --accent: #4f46e5;
            --accent-2: #8b5cf6;
            --muted: #64748b;
            --text: #081124;
            --glass: rgba(2,6,23,0.03);
            --danger: #dc2626;
            --purple-light: #8b5cf6;
            --purple-dark: #7c3aed;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial;
            background: linear-gradient(180deg, var(--bg), #050507);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
            min-height: 100vh;
        }
        
        /* HEADER */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: var(--card);
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        
        [data-theme="light"] header {
            background: var(--panel);
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-wrap {
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 6px 20px rgba(138,92,246,0.12);
            overflow: hidden;
        }
        
        .logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        h1 {
            margin: 0;
            font-size: 1rem;
        }
        
        .nav-menu {
            display: flex;
            gap: 2px;
            align-items: center;
            justify-content: center;
            flex: 1;
            max-width: 600px;
            margin: 0 20px;
        }
        
        .nav-menu a {
            padding: 12px 20px;
            text-decoration: none;
            color: var(--muted);
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(138,92,246,0.1);
            color: var(--text);
        }
        
        .controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .muted {
            color: var(--muted);
        }
        
        .small {
            font-size: 0.86rem;
        }

        /* LAYOUT DO CHAT */
        .chat-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            height: calc(100vh - 70px);
        }
        
        /* PAINEL LATERAL */
        .sidebar {
            background: var(--panel);
            border-right: 1px solid rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }
        
        .section-title {
            font-size: 0.9rem;
            color: var(--muted);
            margin-bottom: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* LISTA DE AMIGOS */
        .friends-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 25px;
        }
        
        .friend-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .friend-item:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .friend-item.active {
            background: rgba(138,92,246,0.15);
        }
        
        .friend-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 14px;
            background-size: cover;
            background-position: center;
        }
        
        .friend-info {
            flex: 1;
        }
        
        .friend-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .friend-status {
            font-size: 0.75rem;
            color: var(--muted);
        }
        
        .online-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            margin-left: auto;
        }
        
        /* LISTA DE LIVROS */
        .books-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .book-chat-item {
            background: var(--card);
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid rgba(255,255,255,0.03);
        }
        
        .book-chat-item:hover {
            border-color: var(--accent);
            transform: translateY(-1px);
        }
        
        .book-chat-item.active {
            background: rgba(138,92,246,0.1);
            border-color: var(--accent);
        }
        
        .book-chat-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }
        
        .book-chat-author {
            font-size: 0.8rem;
            color: var(--muted);
            margin-bottom: 6px;
        }
        
        .book-chat-status {
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-block;
        }
        
        .status-lendo { background: rgba(56,211,159,0.2); color: #38d39f; }
        .status-lido { background: rgba(138,92,246,0.2); color: var(--purple-light); }
        .status-quero-ler { background: rgba(253,150,68,0.2); color: #fd9644; }
        
        /* ÁREA PRINCIPAL DO CHAT */
        .chat-main {
            display: flex;
            flex-direction: column;
            background: var(--bg);
        }
        
        .chat-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            background: var(--card);
        }
        
        .chat-book-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .chat-book-cover {
            width: 60px;
            height: 80px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            background-size: cover;
            background-position: center;
        }
        
        .chat-book-details h2 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .chat-book-author {
            color: var(--muted);
            font-size: 0.9rem;
            margin-top: 4px;
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message {
            display: flex;
            gap: 12px;
            max-width: 70%;
        }
        
        .message.own {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 12px;
            background-size: cover;
            background-position: center;
            flex-shrink: 0;
        }
        
        .message-content {
            background: var(--card);
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.03);
        }
        
        .message.own .message-content {
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            color: white;
        }
        
        .message-sender {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 4px;
        }
        
        .message.own .message-sender {
            display: none;
        }
        
        .message-text {
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .message-time {
            font-size: 0.7rem;
            color: var(--muted);
            margin-top: 4px;
            text-align: right;
        }
        
        .message.own .message-time {
            color: rgba(255,255,255,0.7);
        }
        
        .chat-input {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
            background: var(--card);
        }
        
        .input-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .message-input {
            flex: 1;
            background: var(--glass);
            border: 1px solid rgba(255,255,255,0.03);
            padding: 12px 16px;
            border-radius: 12px;
            color: var(--text);
            font-family: inherit;
            resize: none;
            min-height: 44px;
            max-height: 120px;
        }
        
        .message-input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .btn {
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            padding: 12px 20px;
            border-radius: 10px;
            border: 0;
            color: #ffffff !important;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .ghost {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.04);
            padding: 8px 12px;
            border-radius: 8px;
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        [data-theme="light"] .ghost {
            border: 1px solid rgba(0,0,0,0.1);
            color: var(--text);
        }
        
        .ghost:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
        }
        
        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        .chat-status {
            font-size: 0.8rem;
            color: var(--muted);
            text-align: center;
            padding: 10px;
            background: rgba(138,92,246,0.1);
            border-radius: 8px;
            margin: 10px 0;
        }
        
        footer {
            padding: 15px 18px;
            text-align: center;
            color: var(--muted);
            font-size: 0.85rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            background: var(--panel);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .footer-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .footer-links {
            display: flex;
            gap: 15px;
        }
        
        .footer-links a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.2s ease;
        }
        
        .footer-links a:hover {
            color: var(--accent);
        }
        
        @media (max-width: 768px) {
            .chat-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            header {
                flex-direction: column;
                gap: 10px;
            }
            
            .nav-menu {
                order: 2;
                margin: 10px 0;
            }
            
            .brand {
                order: 1;
            }
            
            .controls {
                order: 3;
            }
            
            .message {
                max-width: 85%;
            }
            
            footer {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            
            .footer-links {
                order: -1;
            }
        }
    </style>
</head>
<body data-theme="dark">
    <!-- HEADER -->
    <header>
        <div class="brand">
            <div class="logo-wrap">
                <img src="icon.png" alt="Litwave Icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden width="30" height="30">
                    <defs>
                        <linearGradient id="g1" x1="0" x2="1">
                            <stop offset="0" stop-color="#8a5cf6"/>
                            <stop offset="1" stop-color="#cfa3ff"/>
                        </linearGradient>
                    </defs>
                    <path d="M8 12c0 0 10-6 24-6s24 6 24 6v32c0 0-10-6-24-6S8 44 8 44V12z" fill="url(#g1)"/>
                </svg>
            </div>
            <div>
                <h1>Litwave</h1>
                <div class="small muted">Rede SENAI pela literária & leitura</div>
            </div>
        </div>

        <nav class="nav-menu">
            <a href="index.php">Biblioteca</a>
            <a href="index.php?view=shelf">Minha Estante</a>
            <a href="chat.php" class="active">Chats</a>
            <a href="amigos.php">Amigos</a>
            <a href="perfil.php">Perfil</a>
        </nav>

        <div class="controls">
            <button class="ghost" id="btnTheme">Modo Claro</button>
            <a href="login.php" class="ghost" style="text-decoration:none;color:inherit">Sair</a>
        </div>
    </header>

    <!-- CONTAINER PRINCIPAL -->
    <div class="chat-container">
        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2 style="margin: 0 0 8px 0;">💬 Chats</h2>
                <p class="muted" style="font-size: 0.9rem;">Converse sobre seus livros favoritos</p>
            </div>
            
            <div class="sidebar-content">
                <!-- AMIGOS ONLINE -->
                <div class="section-title">👥 Amigos Online</div>
                <div class="friends-list">
                    <?php if(!empty($amigos)): ?>
                        <?php foreach($amigos as $amigo): ?>
                            <div class="friend-item">
                                <div class="friend-avatar" style="<?php echo $amigo['foto_perfil'] ? 'background-image: url(' . htmlspecialchars($amigo['foto_perfil']) . ')' : ''; ?>">
                                    <?php if(!$amigo['foto_perfil']): ?>
                                        <?php echo substr($amigo['nome'], 0, 2); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="friend-info">
                                    <div class="friend-name"><?php echo htmlspecialchars($amigo['nome']); ?></div>
                                    <div class="friend-status">Lendo um livro...</div>
                                </div>
                                <div class="online-dot"></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 20px 0;">
                            <div class="icon">👥</div>
                            <p style="font-size: 0.9rem;">Nenhum amigo online</p>
                            <a href="amigos.php" class="ghost" style="margin-top: 10px; font-size: 0.8rem;">Encontrar amigos</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- LIVROS PARA CHAT -->
                <div class="section-title">📚 Seus Livros</div>
                <div class="books-list">
                    <?php if(!empty($livros_estante)): ?>
                        <?php foreach($livros_estante as $livro): ?>
                            <div class="book-chat-item <?php echo $livro_atual && $livro['id'] == $livro_atual['id'] ? 'active' : ''; ?>" 
                                 onclick="window.location.href='chat.php?livro_id=<?php echo $livro['id']; ?>'">
                                <div class="book-chat-title"><?php echo htmlspecialchars($livro['titulo']); ?></div>
                                <div class="book-chat-author"><?php echo htmlspecialchars($livro['autor']); ?></div>
                                <div class="book-chat-status status-<?php echo strtolower($livro['status']); ?>">
                                    <?php 
                                    $status_labels = [
                                        'LENDO' => '📖 Lendo',
                                        'LIDO' => '✅ Lido', 
                                        'QUERO_LER' => '📚 Quero Ler'
                                    ];
                                    echo $status_labels[$livro['status']] ?? $livro['status'];
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 20px 0;">
                            <div class="icon">📚</div>
                            <p style="font-size: 0.9rem;">Nenhum livro na estante</p>
                            <a href="index.php" class="ghost" style="margin-top: 10px; font-size: 0.8rem;">Adicionar livros</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ÁREA PRINCIPAL DO CHAT -->
        <div class="chat-main">
            <?php if($livro_atual): ?>
                <!-- HEADER DO CHAT -->
                <div class="chat-header">
                    <div class="chat-book-info">
                        <div class="chat-book-cover" style="<?php echo $livro_atual['capa_url'] ? 'background-image: url(' . htmlspecialchars($livro_atual['capa_url']) . ')' : ''; ?>">
                            <?php if(!$livro_atual['capa_url']): ?>
                                <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; color: white; font-size: 0.8rem; text-align: center;">
                                    <?php echo substr($livro_atual['titulo'], 0, 2); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="chat-book-details">
                            <h2><?php echo htmlspecialchars($livro_atual['titulo']); ?></h2>
                            <div class="chat-book-author">por <?php echo htmlspecialchars($livro_atual['autor']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- MENSAGENS -->
                <div class="chat-messages" id="chatMessages">
                    <?php if(empty($mensagens_chat)): ?>
                        <div class="chat-status">
                            💬 Seja o primeiro a comentar sobre este livro!
                        </div>
                    <?php else: ?>
                        <?php foreach($mensagens_chat as $mensagem): ?>
                            <div class="message <?php echo $mensagem['usuario_id'] == $usuario_id ? 'own' : ''; ?>">
                                <div class="message-avatar" style="<?php echo $mensagem['foto_perfil'] ? 'background-image: url(' . htmlspecialchars($mensagem['foto_perfil']) . ')' : ''; ?>">
                                    <?php if(!$mensagem['foto_perfil']): ?>
                                        <?php echo substr($mensagem['nome'], 0, 2); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="message-content">
                                    <div class="message-sender"><?php echo htmlspecialchars($mensagem['nome']); ?></div>
                                    <div class="message-text"><?php echo htmlspecialchars($mensagem['texto']); ?></div>
                                    <div class="message-time"><?php echo $mensagem['hora']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- INPUT DO CHAT -->
                <div class="chat-input">
                    <form method="POST" id="chatForm">
                        <div class="input-group">
                            <textarea class="message-input" id="messageInput" name="mensagem" placeholder="Digite sua mensagem sobre o livro..." rows="1" required></textarea>
                            <button type="submit" class="btn" id="sendButton">
                                <span>Enviar</span>
                                <span style="font-size: 1.1rem;">↑</span>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- ESTADO VAZIO -->
                <div class="empty-state" style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                    <div class="icon">💬</div>
                    <h3>Nenhum livro selecionado</h3>
                    <p class="muted">Escolha um livro da sua estante para começar a conversar sobre ele!</p>
                    <a href="index.php" class="btn" style="margin-top: 15px; text-decoration: none;">Ir para Minha Estante</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Tema
        document.getElementById('btnTheme').addEventListener('click', () => {
            const body = document.body;
            const cur = body.getAttribute('data-theme');
            if (cur === 'dark') {
                body.setAttribute('data-theme', 'light');
                document.getElementById('btnTheme').textContent = 'Modo Escuro';
            } else {
                body.setAttribute('data-theme', 'dark');
                document.getElementById('btnTheme').textContent = 'Modo Claro';
            }
        });

        // Auto-resize do textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            // Enter para enviar (Shift+Enter para nova linha)
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    document.getElementById('chatForm').submit();
                }
            });
        }

        // Scroll automático para as mensagens mais recentes
        window.addEventListener('load', function() {
            const messagesContainer = document.getElementById('chatMessages');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
            
            // Focar no input
            if (messageInput) {
                messageInput.focus();
            }
        });

        // Auto-refresh do chat a cada 5 segundos
        setInterval(function() {
            if (window.location.href.includes('livro_id')) {
                window.location.reload();
            }
        }, 5000);
    </script>
</body>
</html>