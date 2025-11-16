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
$sql_usuario = "SELECT nome, email, ra FROM usuarios WHERE id = '$usuario_id'";
$result_usuario = mysqli_query($conexao, $sql_usuario);
$usuario_info = mysqli_fetch_assoc($result_usuario);

# Buscar amigos/participantes do chat
$sql_amigos = "SELECT u.id, u.nome, u.email, u.ra 
               FROM usuarios u 
               WHERE u.id != '$usuario_id' 
               LIMIT 6";
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

# Buscar livro atual para o chat (primeiro livro da estante ou default)
$livro_atual_id = isset($_GET['livro_id']) ? $_GET['livro_id'] : ($livros_estante[0]['id'] ?? null);
$livro_atual = null;

if($livro_atual_id) {
    $sql_livro_atual = "SELECT * FROM livros WHERE id = '$livro_atual_id'";
    $result_livro_atual = mysqli_query($conexao, $sql_livro_atual);
    $livro_atual = mysqli_fetch_assoc($result_livro_atual);
}

# URLs das imagens de perfil
$fotos_perfil = [
    'https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=200&q=80',
    'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=200&q=80',
    'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=200&q=80',
    'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=200&q=80',
    'https://images.unsplash.com/photo-1534528741775-53994a69daeb?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=200&q=80',
    'https://images.unsplash.com/photo-1517841905240-472988babdf9?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=200&q=80'
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Litwave — Chat do Livro</title>

<style>
    :root {
        --bg:#0b0b0d;
        --card:#111217;
        --panel:#0f0f14;
        --accent:#8a5cf6;
        --accent-2:#cfa3ff;
        --muted:#9aa0b4;
        --text:#e7e9ee;
    }

    body {
        margin: 0;
        font-family: Inter, sans-serif;
        background: linear-gradient(180deg, var(--bg), #050507);
        color: var(--text);
    }

    header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 20px;
        background: var(--card);
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .logo-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.2rem;
    }

    /* LAYOUT */
    .container {
        display: grid;
        grid-template-columns: 220px 1fr 220px;
        height: calc(100vh - 70px);
    }

    /* PAINEL ESQUERDO — USUÁRIOS */
    .users-panel {
        background: var(--panel);
        border-right: 1px solid rgba(255,255,255,0.05);
        padding: 20px;
        overflow-y: auto;
    }

    .panel-title {
        margin-bottom: 10px;
        font-size: 1rem;
        color: var(--accent-2);
        font-weight: 600;
    }

    .user {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255,255,255,0.04);
        border-radius: 8px;
        padding: 8px;
        margin-bottom: 8px;
        border: 1px solid rgba(255,255,255,0.03);
        cursor: default;
    }

    .avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
    }

    .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* PAINEL DIREITO — OUTROS CHATS */
    .chats-panel {
        background: var(--panel);
        border-left: 1px solid rgba(255,255,255,0.05);
        padding: 20px;
        overflow-y: auto;
    }

    .chat-item {
        background: rgba(255,255,255,0.04);
        border-radius: 10px;
        padding: 10px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: 0.2s;
        border: 1px solid rgba(255,255,255,0.03);
    }

    .chat-item:hover {
        background: rgba(255,255,255,0.08);
        border-color: var(--accent);
    }

    .chat-item.active {
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
    }

    .livro-status {
        font-size: 0.7rem;
        color: var(--muted);
        margin-top: 4px;
    }

    .chat-item.active .livro-status {
        color: rgba(255,255,255,0.8);
    }

    /* CHAT CENTRAL */
    .chat-area {
        padding: 20px;
        display: flex;
        justify-content: center;
    }

    .chat-card {
        width: 100%;
        max-width: 680px;
        background: var(--card);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 14px;
        padding: 18px;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .livro-header {
        display: flex;
        align-items: center;
        gap: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    .livro-capa {
        width: 60px;
        height: 80px;
        border-radius: 8px;
        object-fit: cover;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }

    .livro-info h2 {
        margin: 0;
        font-size: 1.3rem;
    }

    .livro-autor {
        color: var(--muted);
        font-size: 0.9rem;
        margin-top: 5px;
    }

    #chatMessages {
        height: 400px;
        overflow-y: auto;
        padding-right: 10px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .msg {
        background: rgba(255,255,255,0.04);
        padding: 10px 14px;
        border-radius: 10px;
        max-width: 80%;
        border: 1px solid rgba(255,255,255,0.03);
    }

    .msg.self {
        align-self: flex-end;
        background: linear-gradient(90deg, var(--accent), var(--accent-2));
        color: white;
    }

    .msg-time {
        font-size: 0.7rem;
        color: white;
        margin-top: 5px;
        text-align: right;
    }

    .chat-input-area {
        display: flex;
        gap: 10px;
    }

    input {
        flex: 1;
        padding: 12px;
        border-radius: 10px;
        background: #1a1c22;
        border: 1px solid rgba(255,255,255,0.05);
        color: white;
        outline: none;
    }

    button {
        background: linear-gradient(90deg, var(--accent), var(--accent-2));
        border: none;
        padding: 12px 16px;
        border-radius: 10px;
        font-weight: bold;
        color: white;
        cursor: pointer;
        transition: 0.2s;
    }

    button:hover {
        opacity: 0.8;
    }

    .btn-ghost {
        background: transparent;
        border: 1px solid rgba(255,255,255,0.1);
        color: var(--text);
    }

    .btn-ghost:hover {
        background: rgba(255,255,255,0.05);
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--muted);
    }

    .empty-state h3 {
        margin-bottom: 10px;
        color: var(--text);
    }
</style>
</head>
<body>

<header>
    <div class="logo">
        <div class="logo-icon">L+</div>
        <div>
            <div style="font-size:1.1rem;font-weight:700;">Litwave</div>
            <div style="font-size:0.8rem;color:var(--muted)">Chat de Grupo</div>
        </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center">
        <span style="color:var(--muted)">Olá, <?php echo htmlspecialchars($usuario_info['nome']); ?></span>
        <button class="btn-ghost" onclick="window.location.href='biblioteca.php'">
            Voltar para Biblioteca
        </button>
    </div>
</header>

<div class="container">

    <!-- ESQUERDA — USUÁRIOS -->
    <div class="users-panel">
        <div class="panel-title">👥 Participantes</div>

        <!-- Usuário logado -->
        <div class="user">
            <div class="avatar">
                <img src="<?php echo $fotos_perfil[0]; ?>" alt="<?php echo htmlspecialchars($usuario_info['nome']); ?>">
            </div>
            <?php echo htmlspecialchars($usuario_info['nome']); ?> (Você)
        </div>

        <!-- Amigos/Participantes -->
        <?php if(!empty($amigos)): ?>
            <?php foreach($amigos as $index => $amigo): ?>
                <div class="user">
                    <div class="avatar">
                        <img src="<?php echo $fotos_perfil[($index + 1) % count($fotos_perfil)]; ?>" 
                             alt="<?php echo htmlspecialchars($amigo['nome']); ?>">
                    </div>
                    <?php echo htmlspecialchars($amigo['nome']); ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="color:var(--muted); font-size:0.9rem; text-align:center; padding:10px;">
                Nenhum participante
            </div>
        <?php endif; ?>
    </div>

    <!-- CENTRO — CHAT ATUAL -->
    <div class="chat-area">
        <div class="chat-card">
            <?php if($livro_atual): ?>
                <div class="livro-header">
                    <?php if($livro_atual['capa_url']): ?>
                        <img src="<?php echo htmlspecialchars($livro_atual['capa_url']); ?>" 
                             alt="<?php echo htmlspecialchars($livro_atual['titulo']); ?>" 
                             class="livro-capa">
                    <?php else: ?>
                        <div class="livro-capa"></div>
                    <?php endif; ?>
                    <div class="livro-info">
                        <h2><?php echo htmlspecialchars($livro_atual['titulo']); ?></h2>
                        <div class="livro-autor">por <?php echo htmlspecialchars($livro_atual['autor']); ?></div>
                    </div>
                </div>
                
                <div id="chatMessages"></div>
                <div class="chat-input-area">
                    <input id="msg" placeholder="Escreva uma mensagem sobre o livro...">
                    <button onclick="sendMessage()">Enviar</button>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>Nenhum livro na estante</h3>
                    <p>Adicione livros à sua estante para começar a conversar sobre eles!</p>
                    <button class="btn-ghost" onclick="window.location.href='biblioteca.php'" style="margin-top:15px">
                        Explorar Biblioteca
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DIREITA — OUTROS CHATS -->
    <div class="chats-panel">
        <div class="panel-title">📚 Seus Livros</div>
        
        <?php if(!empty($livros_estante)): ?>
            <?php foreach($livros_estante as $livro): ?>
                <div class="chat-item <?php echo $livro_atual && $livro['id'] == $livro_atual['id'] ? 'active' : ''; ?>" 
                     onclick="window.location.href='chat.php?livro_id=<?php echo $livro['id']; ?>'">
                    <div><strong><?php echo htmlspecialchars($livro['titulo']); ?></strong></div>
                    <div class="livro-autor"><?php echo htmlspecialchars($livro['autor']); ?></div>
                    <div class="livro-status">
                        Status: <?php 
                        $status_labels = [
                            'LENDO' => '📖 Lendo',
                            'LIDO' => '✅ Lido', 
                            'QUERO_LER' => '📚 Quero Ler',
                            'ABANDONADO' => '❌ Abandonado'
                        ];
                        echo $status_labels[$livro['status']] ?? $livro['status'];
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>Nenhum livro na estante</p>
                <button class="btn-ghost" onclick="window.location.href='biblioteca.php'" style="margin-top:10px; font-size:0.8rem">
                    Adicionar Livros
                </button>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
    const key = "chat_livro_<?php echo $livro_atual ? $livro_atual['id'] : 'default'; ?>";
    const userName = "<?php echo htmlspecialchars($usuario_info['nome']); ?>";

    function loadChat() {
        const area = document.getElementById("chatMessages");
        if (!area) return;

        const messages = JSON.parse(localStorage.getItem(key) || "[]");

        area.innerHTML = "";

        if (messages.length === 0) {
            // Mensagem inicial padrão
            area.innerHTML = `
                <div class="msg">
                    <b>Sistema</b><br>
                    Bem-vindo ao chat sobre <strong><?php echo $livro_atual ? htmlspecialchars($livro_atual['titulo']) : 'o livro'; ?></strong>! 
                    Compartilhe suas impressões, dúvidas e opiniões com outros leitores.
                    <div class="msg-time">Agora</div>
                </div>
            `;
            return;
        }

        messages.forEach(msg => {
            const div = document.createElement("div");
            div.className = "msg";
            if (msg.self) div.classList.add("self");

            div.innerHTML = `<b>${msg.name}</b><br>${msg.text}<div class="msg-time">${msg.time || 'Agora'}</div>`;
            area.appendChild(div);
        });

        area.scrollTop = area.scrollHeight;
    }

    function sendMessage() {
        const text = document.getElementById("msg").value.trim();
        if (!text) return;

        const messages = JSON.parse(localStorage.getItem(key) || "[]");

        messages.push({
            name: userName,
            text: text,
            self: true,
            time: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
        });

        localStorage.setItem(key, JSON.stringify(messages));
        document.getElementById("msg").value = "";

        loadChat();
    }

    // Enter para enviar mensagem
    document.getElementById('msg')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    loadChat();
</script>

</body>
</html>