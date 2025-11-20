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

# Processar ações de amizade
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['enviar_solicitacao'])) {
        $amigo_email = mysqli_real_escape_string($conexao, $_POST['amigo_email']);
        
        # Buscar usuário pelo email
        $sql_busca = "SELECT id FROM usuarios WHERE email = '$amigo_email' AND id != '$usuario_id'";
        $result_busca = mysqli_query($conexao, $sql_busca);
        
        if(mysqli_num_rows($result_busca) > 0) {
            $amigo = mysqli_fetch_assoc($result_busca);
            $amigo_id = $amigo['id'];
            
            # Verificar se já existe solicitação
            $sql_verifica = "SELECT * FROM amigos WHERE usuario_id = '$usuario_id' AND amigo_id = '$amigo_id'";
            $result_verifica = mysqli_query($conexao, $sql_verifica);
            
            if(mysqli_num_rows($result_verifica) == 0) {
                # Enviar solicitação
                $sql_solicita = "INSERT INTO amigos (usuario_id, amigo_id, status) VALUES ('$usuario_id', '$amigo_id', 'pendente')";
                if(mysqli_query($conexao, $sql_solicita)) {
                    $mensagem = "✅ Solicitação enviada para $amigo_email";
                } else {
                    $mensagem = "❌ Erro ao enviar solicitação";
                }
            } else {
                $mensagem = "❌ Solicitação já enviada para este usuário";
            }
        } else {
            $mensagem = "❌ Usuário não encontrado";
        }
    }
    
    if(isset($_POST['aceitar_solicitacao'])) {
        $solicitacao_id = $_POST['solicitacao_id'];
        $sql_aceita = "UPDATE amigos SET status = 'aceito', data_resposta = NOW() WHERE id = '$solicitacao_id' AND amigo_id = '$usuario_id'";
        if(mysqli_query($conexao, $sql_aceita)) {
            $mensagem = "✅ Solicitação aceita!";
        }
    }
    
    if(isset($_POST['recusar_solicitacao'])) {
        $solicitacao_id = $_POST['solicitacao_id'];
        $sql_recusa = "UPDATE amigos SET status = 'recusado', data_resposta = NOW() WHERE id = '$solicitacao_id' AND amigo_id = '$usuario_id'";
        if(mysqli_query($conexao, $sql_recusa)) {
            $mensagem = "❌ Solicitação recusada";
        }
    }
    
    if(isset($_POST['remover_amigo'])) {
        $amigo_id = $_POST['amigo_id'];
        $sql_remove = "DELETE FROM amigos WHERE (usuario_id = '$usuario_id' AND amigo_id = '$amigo_id') OR (usuario_id = '$amigo_id' AND amigo_id = '$usuario_id')";
        if(mysqli_query($conexao, $sql_remove)) {
            $mensagem = "👋 Amigo removido";
        }
    }
}

# Buscar amigos do usuário
$sql_amigos = "SELECT u.id, u.nome, u.email, u.ra 
               FROM amigos a 
               JOIN usuarios u ON a.amigo_id = u.id 
               WHERE a.usuario_id = '$usuario_id' AND a.status = 'aceito'
               UNION
               SELECT u.id, u.nome, u.email, u.ra 
               FROM amigos a 
               JOIN usuarios u ON a.usuario_id = u.id 
               WHERE a.amigo_id = '$usuario_id' AND a.status = 'aceito'";
$result_amigos = mysqli_query($conexao, $sql_amigos);
$amigos = [];
if($result_amigos) {
    while($row = mysqli_fetch_assoc($result_amigos)){
        $amigos[] = $row;
    }
}

# Buscar solicitações pendentes recebidas
$sql_solicitacoes = "SELECT a.id, u.nome, u.email, u.ra, a.data_solicitacao 
                     FROM amigos a 
                     JOIN usuarios u ON a.usuario_id = u.id 
                     WHERE a.amigo_id = '$usuario_id' AND a.status = 'pendente'";
$result_solicitacoes = mysqli_query($conexao, $sql_solicitacoes);
$solicitacoes = [];
if($result_solicitacoes) {
    while($row = mysqli_fetch_assoc($result_solicitacoes)){
        $solicitacoes[] = $row;
    }
}

# Buscar solicitações enviadas pendentes
$sql_enviadas = "SELECT a.id, u.nome, u.email, u.ra, a.data_solicitacao 
                 FROM amigos a 
                 JOIN usuarios u ON a.amigo_id = u.id 
                 WHERE a.usuario_id = '$usuario_id' AND a.status = 'pendente'";
$result_enviadas = mysqli_query($conexao, $sql_enviadas);
$solicitacoes_enviadas = [];
if($result_enviadas) {
    while($row = mysqli_fetch_assoc($result_enviadas)){
        $solicitacoes_enviadas[] = $row;
    }
}

# Buscar estatísticas de leitura dos amigos
$estatisticas_amigos = [];
foreach($amigos as $amigo) {
    $amigo_id = $amigo['id'];
    $sql_stats = "SELECT 
        COUNT(*) as total_livros,
        SUM(CASE WHEN status = 'LENDO' THEN 1 ELSE 0 END) as lendo,
        SUM(CASE WHEN status = 'LIDO' THEN 1 ELSE 0 END) as lido
        FROM historico_leitura WHERE usuario_id = '$amigo_id'";
    $result_stats = mysqli_query($conexao, $sql_stats);
    $stats = mysqli_fetch_assoc($result_stats);
    $estatisticas_amigos[$amigo_id] = $stats;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>L.S.A</title>
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
            --warning: #f59e0b;
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
            --warning: #d97706;
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
        
        /* HEADER IDÊNTICO AO INDEX */
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
        }
        
        .nav-menu a {
            padding: 12px 20px;
            text-decoration: none;
            color: var(--muted);
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
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

        /* CONTEÚDO PRINCIPAL */
        main {
            padding: 18px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card {
            background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.02));
            border-radius: 12px;
            padding: 18px;
            border: 1px solid rgba(255,255,255,0.03);
            margin-bottom: 18px;
        }
        
        [data-theme="light"] .card {
            background: var(--card);
            border: 1px solid rgba(0,0,0,0.08);
        }
        
        h2 {
            margin: 0 0 8px 0;
            font-size: 1.5rem;
        }
        
        h3 {
            margin: 0 0 12px 0;
            font-size: 1.2rem;
        }
        
        /* FORMULÁRIOS */
        .form-group {
            margin-bottom: 15px;
        }
        
        input, select, textarea {
            background: var(--glass) !important;
            border: 1px solid rgba(255,255,255,0.03) !important;
            color: var(--text) !important;
            padding: 10px 12px;
            border-radius: 8px;
            font-family: inherit;
            width: 100%;
        }
        
        [data-theme="light"] input, 
        [data-theme="light"] select, 
        [data-theme="light"] textarea {
            background: var(--glass) !important;
            border: 1px solid rgba(0,0,0,0.1) !important;
            color: var(--text) !important;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent) !important;
        }
        
        ::placeholder {
            color: var(--muted) !important;
        }
        
        .btn {
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            padding: 10px 16px;
            border-radius: 10px;
            border: 0;
            color: #ffffff !important;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: var(--success);
        }
        
        .btn-danger {
            background: var(--danger);
        }
        
        .btn-warning {
            background: var(--warning);
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
        
        /* LISTAS DE AMIGOS */
        .friends-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .friend-card {
            background: var(--card);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid rgba(255,255,255,0.03);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        [data-theme="light"] .friend-card {
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .friend-avatar {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 16px;
        }
        
        .friend-info {
            flex: 1;
        }
        
        .friend-name {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .friend-stats {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }
        
        .stat {
            background: rgba(255,255,255,0.05);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        
        .friend-actions {
            display: flex;
            gap: 8px;
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
        
        /* ABAS */
        .tabs {
            display: flex;
            gap: 2px;
            background: rgba(255,255,255,0.02);
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .tab.active {
            background: rgba(138,92,246,0.15);
            color: var(--accent-2);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* MENSAGENS */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid;
        }
        
        .alert-success {
            background: rgba(56,211,159,0.1);
            border-color: rgba(56,211,159,0.3);
            color: #38d39f;
        }
        
        .alert-danger {
            background: rgba(231,76,60,0.1);
            border-color: rgba(231,76,60,0.3);
            color: #e74c3c;
        }
        
        footer {
            padding: 18px;
            text-align: center;
            color: var(--muted);
            font-size: 0.9rem;
            margin-top: 40px;
        }
        
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                height: auto;
                gap: 10px;
            }
            
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .friends-grid {
                grid-template-columns: 1fr;
            }
            
            .friend-card {
                flex-direction: column;
                text-align: center;
            }
            
            .friend-actions {
                justify-content: center;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body data-theme="dark">
    <!-- HEADER IDÊNTICO AO INDEX -->
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
                <h1>L.S.A</h1>
                <div class="small muted">Rede SENAI pela literária & leitura</div>
            </div>
        </div>

        <nav class="nav-menu">
            <a href="index.php">Biblioteca</a>
            <a href="index.php">Minha Estante</a>
            <a href="chat.php">Chats</a>
            <a href="amigos.php" class="active">Amigos</a>
            <a href="perfil.php">Perfil</a>
        </nav>

        <div class="controls">
            <button class="ghost" id="btnTheme">Modo Claro</button>
            <a href="login.php" class="ghost" style="text-decoration:none;color:inherit">Sair</a>
        </div>
    </header>

    <main>
        <?php if(isset($mensagem)): ?>
            <div class="alert <?php echo strpos($mensagem, '✅') !== false ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>👥 Sistema de Amigos</h2>
            <p class="muted">Conecte-se com outros leitores e compartilhe suas experiências literárias</p>
            
            <!-- Formulário de Adicionar Amigo -->
            <div style="margin-top: 20px;">
                <h3>🔍 Encontrar Amigos</h3>
                <form method="POST" style="display: flex; gap: 10px; align-items: end;">
                    <div class="form-group" style="flex: 1;">
                        <label class="small muted">Digite o email do usuário</label>
                        <input type="email" name="amigo_email" placeholder="exemplo@email.com" required>
                    </div>
                    <button type="submit" name="enviar_solicitacao" class="btn">Enviar Solicitação</button>
                </form>
            </div>
        </div>

        <!-- Abas -->
        <div class="tabs">
            <div class="tab active" data-tab="amigos">Meus Amigos (<?php echo count($amigos); ?>)</div>
            <div class="tab" data-tab="solicitacoes">Solicitações (<?php echo count($solicitacoes); ?>)</div>
            <div class="tab" data-tab="enviadas">Enviadas (<?php echo count($solicitacoes_enviadas); ?>)</div>
        </div>

        <!-- Aba: Meus Amigos -->
        <div class="tab-content active" id="tab-amigos">
            <div class="card">
                <h3>🎯 Meus Amigos</h3>
                
                <?php if(empty($amigos)): ?>
                    <div class="empty-state">
                        <div class="icon">👥</div>
                        <h4>Nenhum amigo ainda</h4>
                        <p class="muted">Adicione amigos para compartilhar suas leituras!</p>
                    </div>
                <?php else: ?>
                    <div class="friends-grid">
                        <?php foreach($amigos as $amigo): 
                            $stats = $estatisticas_amigos[$amigo['id']] ?? ['total_livros' => 0, 'lendo' => 0, 'lido' => 0];
                        ?>
                            <div class="friend-card">
                                <div class="friend-avatar">
                                    <?php echo substr($amigo['nome'], 0, 2); ?>
                                </div>
                                <div class="friend-info">
                                    <div class="friend-name"><?php echo htmlspecialchars($amigo['nome']); ?></div>
                                    <div class="small muted"><?php echo htmlspecialchars($amigo['email']); ?></div>
                                    <div class="friend-stats">
                                        <div class="stat">📚 <?php echo $stats['total_livros']; ?> livros</div>
                                        <div class="stat">📖 <?php echo $stats['lendo']; ?> lendo</div>
                                        <div class="stat">✅ <?php echo $stats['lido']; ?> lidos</div>
                                    </div>
                                </div>
                                <div class="friend-actions">
                                    <a href="chat.php?amigo=<?php echo $amigo['id']; ?>" class="ghost" title="Enviar mensagem">💬</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="amigo_id" value="<?php echo $amigo['id']; ?>">
                                        <button type="submit" name="remover_amigo" class="ghost" title="Remover amigo" onclick="return confirm('Tem certeza que deseja remover este amigo?')">❌</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Aba: Solicitações Recebidas -->
        <div class="tab-content" id="tab-solicitacoes">
            <div class="card">
                <h3>📨 Solicitações de Amizade</h3>
                
                <?php if(empty($solicitacoes)): ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h4>Nenhuma solicitação pendente</h4>
                        <p class="muted">Quando receber solicitações, elas aparecerão aqui.</p>
                    </div>
                <?php else: ?>
                    <div class="friends-grid">
                        <?php foreach($solicitacoes as $solicitacao): ?>
                            <div class="friend-card">
                                <div class="friend-avatar">
                                    <?php echo substr($solicitacao['nome'], 0, 2); ?>
                                </div>
                                <div class="friend-info">
                                    <div class="friend-name"><?php echo htmlspecialchars($solicitacao['nome']); ?></div>
                                    <div class="small muted"><?php echo htmlspecialchars($solicitacao['email']); ?></div>
                                    <div class="small muted">Enviado em: <?php echo date('d/m/Y', strtotime($solicitacao['data_solicitacao'])); ?></div>
                                </div>
                                <div class="friend-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="solicitacao_id" value="<?php echo $solicitacao['id']; ?>">
                                        <button type="submit" name="aceitar_solicitacao" class="btn btn-success">✓ Aceitar</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="solicitacao_id" value="<?php echo $solicitacao['id']; ?>">
                                        <button type="submit" name="recusar_solicitacao" class="btn btn-danger">✗ Recusar</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Aba: Solicitações Enviadas -->
        <div class="tab-content" id="tab-enviadas">
            <div class="card">
                <h3>📤 Solicitações Enviadas</h3>
                
                <?php if(empty($solicitacoes_enviadas)): ?>
                    <div class="empty-state">
                        <div class="icon">📤</div>
                        <h4>Nenhuma solicitação enviada</h4>
                        <p class="muted">As solicitações que você enviar aparecerão aqui.</p>
                    </div>
                <?php else: ?>
                    <div class="friends-grid">
                        <?php foreach($solicitacoes_enviadas as $solicitacao): ?>
                            <div class="friend-card">
                                <div class="friend-avatar">
                                    <?php echo substr($solicitacao['nome'], 0, 2); ?>
                                </div>
                                <div class="friend-info">
                                    <div class="friend-name"><?php echo htmlspecialchars($solicitacao['nome']); ?></div>
                                    <div class="small muted"><?php echo htmlspecialchars($solicitacao['email']); ?></div>
                                    <div class="small muted">Enviado em: <?php echo date('d/m/Y', strtotime($solicitacao['data_solicitacao'])); ?></div>
                                    <div class="small" style="color: var(--warning);">⏳ Aguardando resposta</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        © 2025 L.S.A — Conectando leitores!
    </footer>

    <script>
        // Sistema de Abas
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active de todas as tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(tc => tc.classList.remove('active'));
                    
                    // Adiciona active na tab clicada
                    tab.classList.add('active');
                    const tabId = tab.getAttribute('data-tab');
                    document.getElementById(`tab-${tabId}`).classList.add('active');
                });
            });
            
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
        });
    </script>
</body>
</html>