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
$sql_usuario = "SELECT nome, email, ra, bio, foto_perfil, privacidade FROM usuarios WHERE id = '$usuario_id'";
$result_usuario = mysqli_query($conexao, $sql_usuario);
$usuario_info = mysqli_fetch_assoc($result_usuario);

# Processar atualização do perfil
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['atualizar_perfil'])) {
        $novo_nome = mysqli_real_escape_string($conexao, $_POST['nome']);
        $nova_bio = mysqli_real_escape_string($conexao, $_POST['bio']);
        $nova_privacidade = mysqli_real_escape_string($conexao, $_POST['privacidade']);
        
        # Upload de foto de perfil
        $foto_perfil = $usuario_info['foto_perfil']; // Mantém a atual se não enviar nova

        if(isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == 0) {
            $foto = $_FILES['foto_perfil'];
            $extensao = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $nome_arquivo = 'perfil_' . $usuario_id . '_' . time() . '.' . $extensao;
            $caminho_destino = 'profileImages/' . $nome_arquivo;
            
            # Criar diretório se não existir
            if(!is_dir('profileImages')) {
                mkdir('profileImages', 0777, true);
            }
            
            if(move_uploaded_file($foto['tmp_name'], $caminho_destino)) {
                $foto_perfil = $caminho_destino;
            }
        }
        
        $sql_update = "UPDATE usuarios SET nome = '$novo_nome', bio = '$nova_bio', foto_perfil = '$foto_perfil', privacidade = '$nova_privacidade' WHERE id = '$usuario_id'";
        if(mysqli_query($conexao, $sql_update)) {
            $mensagem = "✅ Perfil atualizado com sucesso!";
            # Atualizar dados na sessão
            $usuario_info['nome'] = $novo_nome;
            $usuario_info['bio'] = $nova_bio;
            $usuario_info['foto_perfil'] = $foto_perfil;
            $usuario_info['privacidade'] = $nova_privacidade;
        } else {
            $mensagem = "❌ Erro ao atualizar perfil";
        }
    }
}

# Buscar estatísticas do usuário
$sql_estatisticas = "SELECT 
    COUNT(*) as total_livros,
    SUM(CASE WHEN status = 'LENDO' THEN 1 ELSE 0 END) as lendo,
    SUM(CASE WHEN status = 'LIDO' THEN 1 ELSE 0 END) as lido,
    SUM(CASE WHEN status = 'QUERO_LER' THEN 1 ELSE 0 END) as quer_ler
    FROM historico_leitura WHERE usuario_id = '$usuario_id'";
$result_estatisticas = mysqli_query($conexao, $sql_estatisticas);
$estatisticas = mysqli_fetch_assoc($result_estatisticas);

# Buscar número de amigos
$sql_amigos_count = "SELECT COUNT(*) as total_amigos 
                     FROM amigos 
                     WHERE (usuario_id = '$usuario_id' OR amigo_id = '$usuario_id') 
                     AND status = 'aceito'";
$result_amigos_count = mysqli_query($conexao, $sql_amigos_count);
$amigos_count = mysqli_fetch_assoc($result_amigos_count)['total_amigos'];

# Buscar livros favoritados (como posts do Instagram)
$sql_favoritos = "SELECT l.*, hl.status 
                  FROM historico_leitura hl 
                  JOIN livros l ON hl.livro_id = l.id 
                  WHERE hl.usuario_id = '$usuario_id' 
                  AND hl.status IN ('LENDO', 'LIDO')
                  ORDER BY hl.updated_at DESC 
                  LIMIT 12";
$result_favoritos = mysqli_query($conexao, $sql_favoritos);
$livros_favoritos = [];
if($result_favoritos) {
    while($row = mysqli_fetch_assoc($result_favoritos)){
        $livros_favoritos[] = $row;
    }
}

# Buscar livros salvos para ler depois (só o usuário logado vê os próprios)
$sql_salvos = "SELECT l.* 
               FROM historico_leitura hl 
               JOIN livros l ON hl.livro_id = l.id 
               WHERE hl.usuario_id = '$usuario_id' 
               AND hl.status = 'QUERO_LER'
               ORDER BY hl.updated_at DESC";
$result_salvos = mysqli_query($conexao, $sql_salvos);
$livros_salvos = [];
if($result_salvos) {
    while($row = mysqli_fetch_assoc($result_salvos)){
        $livros_salvos[] = $row;
    }
}

# Buscar todos os livros para adicionar aos salvos
$sql_todos_livros = "SELECT * FROM livros WHERE status = 'DISPONIVEL' ORDER BY titulo LIMIT 50";
$result_todos_livros = mysqli_query($conexao, $sql_todos_livros);
$todos_livros = [];
if($result_todos_livros) {
    while($row = mysqli_fetch_assoc($result_todos_livros)){
        $todos_livros[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Leitura+ - Perfil</title>
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

        main {
            padding: 18px;
            max-width: 1000px;
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
        
        /* PERFIL HEADER */
        .profile-header {
            display: flex;
            gap: 25px;
            align-items: flex-start;
            margin-bottom: 25px;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 2rem;
            background-size: cover;
            background-position: center;
            border: 3px solid var(--accent);
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .profile-bio {
            color: var(--muted);
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .profile-stats {
            display: flex;
            gap: 25px;
            margin-bottom: 15px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--accent);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--muted);
        }
        
        .profile-actions {
            display: flex;
            gap: 10px;
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
            flex: 1;
            text-align: center;
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
        
        /* GRID DE LIVROS (Instagram style) */
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
        }
        
        .post {
            aspect-ratio: 3/4;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            background: var(--card);
            border: 1px solid rgba(255,255,255,0.03);
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .post:hover {
            transform: scale(1.03);
        }
        
        .post img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .post-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .post:hover .post-overlay {
            opacity: 1;
        }
        
        .post-status {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-lendo { background: #38d39f; }
        .status-lido { background: var(--accent); }
        
        /* FORMULÁRIOS */
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--muted);
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
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .file-upload {
            position: relative;
            display: inline-block;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(138,92,246,0.1);
            color: var(--accent);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px dashed var(--accent);
        }
        
        .file-upload-label:hover {
            background: rgba(138,92,246,0.2);
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
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent) !important;
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
        
        /* LIVROS SALVOS */
        .saved-books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .saved-book {
            background: var(--card);
            border-radius: 10px;
            padding: 12px;
            border: 1px solid rgba(255,255,255,0.03);
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .saved-book-cover {
            width: 60px;
            height: 80px;
            border-radius: 6px;
            object-fit: cover;
        }
        
        .saved-book-info {
            flex: 1;
        }
        
        .saved-book-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 0.9rem;
        }
        
        .saved-book-author {
            font-size: 0.8rem;
            color: var(--muted);
        }
        
        .remove-btn {
            background: var(--danger);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-book-form {
            background: rgba(138,92,246,0.05);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px dashed var(--accent);
        }
        
        .form-row {
            display: flex;
            gap: 10px;
            align-items: end;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
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
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .profile-stats {
                justify-content: center;
            }
            
            .profile-actions {
                justify-content: center;
            }
            
            .posts-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .tabs {
                flex-direction: column;
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
                <h1>L.S.A</h1>
                <div class="small muted">Rede SENAI pela literária & leitura</div>
            </div>
        </div>

        <nav class="nav-menu">
            <a href="index.php">Biblioteca</a>
            <a href="index.php?view=shelf">Minha Estante</a>
            <a href="chat.php">Chats</a>
            <a href="amigos.php">Amigos</a>
            <a href="perfil.php" class="active">Perfil</a>
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

        <!-- CABEÇALHO DO PERFIL -->
        <div class="card">
            <div class="profile-header">
                <div class="profile-avatar" style="<?php echo $usuario_info['foto_perfil'] ? 'background-image: url(' . htmlspecialchars($usuario_info['foto_perfil']) . ')' : ''; ?>">
                    <?php if(!$usuario_info['foto_perfil']): ?>
                        <?php echo substr($usuario_info['nome'], 0, 2); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo htmlspecialchars($usuario_info['nome']); ?></div>
                    <div class="profile-bio">
                        <?php echo $usuario_info['bio'] ? htmlspecialchars($usuario_info['bio']) : '📚 Apaixonado por leitura • ✨ Compartilhando minhas jornadas literárias'; ?>
                    </div>
                    <div class="profile-stats">
                        <div class="stat">
                            <div class="stat-number"><?php echo $estatisticas['total_livros'] ?: '0'; ?></div>
                            <div class="stat-label">Livros</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number"><?php echo $amigos_count; ?></div>
                            <div class="stat-label">Amigos</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number"><?php echo $estatisticas['lido'] ?: '0'; ?></div>
                            <div class="stat-label">Lidos</div>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <button class="btn" onclick="abrirAba('editar')">✏️ Editar Perfil</button>
                        <button class="btn-outline ghost" onclick="abrirAba('configuracoes')">⚙️ Configurações</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ABAS -->
        <div class="tabs">
            <div class="tab active" data-tab="meus-livros">📚 Meus Livros</div>
            <div class="tab" data-tab="salvos">💾 Salvos</div>
            <div class="tab" data-tab="editar">✏️ Editar Perfil</div>
            <div class="tab" data-tab="configuracoes">⚙️ Configurações</div>
        </div>

        <!-- ABA: MEUS LIVROS (Instagram style) -->
        <div class="tab-content active" id="tab-meus-livros">
            <div class="card">
                <h3 style="margin-bottom: 15px;">📖 Minha Biblioteca Pessoal</h3>
                
                <?php if(empty($livros_favoritos)): ?>
                    <div class="empty-state">
                        <div class="icon">📚</div>
                        <h4>Nenhum livro na sua biblioteca ainda</h4>
                        <p class="muted">Adicione livros à sua estante para eles aparecerem aqui!</p>
                        <a href="index.php" class="btn" style="margin-top: 15px; text-decoration: none;">Explorar Livros</a>
                    </div>
                <?php else: ?>
                    <div class="posts-grid">
                        <?php foreach($livros_favoritos as $livro): ?>
                            <div class="post" onclick="window.location.href='view.php?id=<?php echo $livro['id']; ?>'">
                                <img src="<?php echo htmlspecialchars($livro['capa_url'] ?? 'https://via.placeholder.com/300x450.png?text=' . urlencode($livro['titulo'])); ?>" 
                                     alt="<?php echo htmlspecialchars($livro['titulo']); ?>">
                                <div class="post-status <?php echo 'status-' . strtolower($livro['status']); ?>">
                                    <?php echo $livro['status']; ?>
                                </div>
                                <div class="post-overlay">
                                    <div style="text-align: center;">
                                        <div style="font-weight: 600; margin-bottom: 5px;"><?php echo htmlspecialchars($livro['titulo']); ?></div>
                                        <div style="font-size: 0.8rem; color: #ccc;"><?php echo htmlspecialchars($livro['autor']); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ABA: LIVROS SALVOS -->
        <div class="tab-content" id="tab-salvos">
            <div class="card">
                <h3 style="margin-bottom: 15px;">💾 Quero Ler Depois</h3>
                <p class="muted" style="margin-bottom: 15px;">Estes livros são visíveis apenas para você</p>
                
                <!-- Formulário para adicionar livro aos salvos -->
                <div class="add-book-form">
                    <h4 style="margin-bottom: 10px;">➕ Adicionar Livro</h4>
                    <form method="POST" action="adicionar_estante.php">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Selecionar Livro</label>
                                <select name="livro_id" required>
                                    <option value="">Escolha um livro...</option>
                                    <?php foreach($todos_livros as $livro): ?>
                                        <option value="<?php echo $livro['id']; ?>">
                                            <?php echo htmlspecialchars($livro['titulo']); ?> - <?php echo htmlspecialchars($livro['autor']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="hidden" name="status" value="QUERO_LER">
                            <button type="submit" class="btn">Salvar</button>
                        </div>
                    </form>
                </div>
                
                <?php if(empty($livros_salvos)): ?>
                    <div class="empty-state">
                        <div class="icon">📑</div>
                        <h4>Nenhum livro salvo</h4>
                        <p class="muted">Adicione livros que você quer ler depois!</p>
                    </div>
                <?php else: ?>
                    <div class="saved-books-grid">
                        <?php foreach($livros_salvos as $livro): ?>
                            <div class="saved-book">
                                <img src="<?php echo htmlspecialchars($livro['capa_url'] ?? 'https://via.placeholder.com/300x450.png?text=' . urlencode($livro['titulo'])); ?>" 
                                     class="saved-book-cover" 
                                     alt="<?php echo htmlspecialchars($livro['titulo']); ?>">
                                <div class="saved-book-info">
                                    <div class="saved-book-title"><?php echo htmlspecialchars($livro['titulo']); ?></div>
                                    <div class="saved-book-author"><?php echo htmlspecialchars($livro['autor']); ?></div>
                                </div>
                                <form method="POST" action="remover_estante.php" style="display: inline;">
                                    <input type="hidden" name="livro_id" value="<?php echo $livro['id']; ?>">
                                    <button type="submit" class="remove-btn" title="Remover">×</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ABA: EDITAR PERFIL -->
        <div class="tab-content" id="tab-editar">
            <div class="card">
                <h3 style="margin-bottom: 15px;">✏️ Editar Perfil</h3>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Nome de Usuário</label>
                        <input type="text" name="nome" value="<?php echo htmlspecialchars($usuario_info['nome']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Biografia</label>
                        <textarea name="bio" placeholder="Conte um pouco sobre você e seus gostos literários..."><?php echo htmlspecialchars($usuario_info['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Foto de Perfil</label>
                        
                        <!-- Preview da foto atual -->
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; padding: 15px; background: rgba(255,255,255,0.02); border-radius: 10px;">
                            <div class="profile-avatar-preview" style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent-2)); background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; font-size: 1.5rem; border: 3px solid var(--accent);" 
                                id="avatarPreview"
                                style="<?php echo $usuario_info['foto_perfil'] ? 'background-image: url(' . htmlspecialchars($usuario_info['foto_perfil']) . ')' : ''; ?>">
                                <?php if(!$usuario_info['foto_perfil']): ?>
                                    <?php echo substr($usuario_info['nome'], 0, 2); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div style="font-weight: 600;">Sua foto atual</div>
                                <div class="small muted">Clique no botão abaixo para alterar</div>
                            </div>
                        </div>

                        <!-- Upload de foto -->
                        <div class="file-upload">
                            <input type="file" name="foto_perfil" accept="image/*" id="fotoInput" onchange="previewImage(this)">
                            <div class="file-upload-label">📷 Escolher nova imagem</div>
                        </div>
                        <div class="small muted" style="margin-top: 5px;">PNG, JPG, GIF ou WebP até 5MB</div>
                        
                        <!-- Preview da nova imagem -->
                        <div id="imagePreview" style="display: none; margin-top: 15px; text-align: center;">
                            <div style="font-weight: 600; margin-bottom: 10px; color: var(--accent);">📸 Prévia da nova foto:</div>
                            <img id="previewImg" style="max-width: 150px; max-height: 150px; border-radius: 10px; border: 2px solid var(--accent);">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Privacidade do Perfil</label>
                        <select name="privacidade">
                            <option value="publico" <?php echo ($usuario_info['privacidade'] ?? 'publico') == 'publico' ? 'selected' : ''; ?>>🌎 Público</option>
                            <option value="privado" <?php echo ($usuario_info['privacidade'] ?? 'publico') == 'privado' ? 'selected' : ''; ?>>🔒 Privado</option>
                        </select>
                        <div class="small muted" style="margin-top: 5px;">
                            <?php echo ($usuario_info['privacidade'] ?? 'publico') == 'publico' ? 
                                'Seu perfil é visível para todos' : 
                                'Apenas amigos podem ver seu perfil'; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="atualizar_perfil" class="btn">💾 Salvar Alterações</button>
                </form>
            </div>
        </div>

        <!-- ABA: CONFIGURAÇÕES -->
        <div class="tab-content" id="tab-configuracoes">
            <div class="card">
                <h3 style="margin-bottom: 15px;">⚙️ Configurações</h3>
                
                <div style="display: grid; gap: 15px;">
                    <div style="padding: 15px; background: rgba(255,255,255,0.02); border-radius: 8px;">
                        <h4 style="margin-bottom: 8px;">🔔 Notificações</h4>
                        <p class="muted" style="margin-bottom: 10px;">Controle as notificações que você recebe</p>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" checked> Notificações de amigos
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" checked> Novos livros adicionados
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox"> Atualizações do sistema
                            </label>
                        </div>
                    </div>
                    
                    <div style="padding: 15px; background: rgba(255,255,255,0.02); border-radius: 8px;">
                        <h4 style="margin-bottom: 8px;">🎨 Aparência</h4>
                        <p class="muted" style="margin-bottom: 10px;">Personalize a aparência do L.S.A</p>
                        <button class="btn-outline ghost" id="btnThemeConfig">🌙 Alternar Tema</button>
                    </div>
                    
                    <div style="padding: 15px; background: rgba(231,76,60,0.1); border-radius: 8px; border: 1px solid rgba(231,76,60,0.3);">
                        <h4 style="margin-bottom: 8px; color: #e74c3c;">⚠️ Zona de Perigo</h4>
                        <p class="muted" style="margin-bottom: 10px;">Ações irreversíveis</p>
                        <button class="btn" style="background: var(--danger);">🗑️ Excluir Conta</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        © 2025 L.S.A — Perfil de <?php echo htmlspecialchars($usuario_info['nome']); ?>
    </footer>

    <script>
        // Sistema de Abas
        function abrirAba(abaNome) {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(tc => tc.classList.remove('active'));
            
            document.querySelector(`[data-tab="${abaNome}"]`).classList.add('active');
            document.getElementById(`tab-${abaNome}`).classList.add('active');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    abrirAba(tabId);
                });
            });
            
            // Tema
            const btnTheme = document.getElementById('btnTheme');
            const btnThemeConfig = document.getElementById('btnThemeConfig');
            
            function alternarTema() {
                const body = document.body;
                const cur = body.getAttribute('data-theme');
                if (cur === 'dark') {
                    body.setAttribute('data-theme', 'light');
                    btnTheme.textContent = 'Modo Escuro';
                    if(btnThemeConfig) btnThemeConfig.textContent = '🌙 Modo Escuro';
                } else {
                    body.setAttribute('data-theme', 'dark');
                    btnTheme.textContent = 'Modo Claro';
                    if(btnThemeConfig) btnThemeConfig.textContent = '☀️ Modo Claro';
                }
            }
            
            btnTheme.addEventListener('click', alternarTema);
            if(btnThemeConfig) {
                btnThemeConfig.addEventListener('click', alternarTema);
            }
            
            // Preview da imagem selecionada
            const fileInput = document.querySelector('input[type="file"]');
            const avatar = document.querySelector('.profile-avatar');
            
            fileInput?.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        avatar.style.backgroundImage = `url(${e.target.result})`;
                        avatar.innerHTML = '';
                    }
                    reader.readAsDataURL(file);
                }
            });
        });

        // Preview da imagem antes de enviar
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const img = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Verificar tamanho (5MB máximo)
                if (file.size > 5 * 1024 * 1024) {
                    alert('❌ A imagem é muito grande. Máximo 5MB permitido.');
                    input.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                // Verificar tipo
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('❌ Por favor, selecione uma imagem válida (JPG, PNG, GIF ou WebP)');
                    input.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }

        // Atualizar preview do avatar quando a página carrega
        document.addEventListener('DOMContentLoaded', function() {
            const avatarPreview = document.getElementById('avatarPreview');
            const currentPhoto = '<?php echo $usuario_info['foto_perfil'] ?? ""; ?>';
            
            if (currentPhoto) {
                avatarPreview.style.backgroundImage = `url(${currentPhoto})`;
                avatarPreview.innerHTML = '';
            }
        });
    </script>
</body>
</html>