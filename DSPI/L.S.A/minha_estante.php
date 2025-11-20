<?php
// SOLUÇÃO DEFINITIVA - Desliga warnings temporariamente
error_reporting(0);
ini_set('display_errors', 0);

session_start();
if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php");
    exit();
}

// Configurações do banco
$servidor = "localhost";
$usuario = "root";
$senha = "";
$database = "appLivroTeste";

// Conectar ao banco
$conexao = mysqli_connect($servidor, $usuario, $senha, $database);
if (!$conexao) {
    die("Erro de conexão com o banco de dados");
}

// VALORES PADRÃO - Isso evita TODOS os warnings
$usuario_info = [
    'nome' => 'Usuário', 
    'email' => 'email@exemplo.com', 
    'ra' => 'N/A',
    'foto_perfil' => null
];

$estatisticas = [
    'total_livros' => 0,
    'lendo' => 0, 
    'lido' => 0,
    'quer_ler' => 0
];

$livros_estante = [];
$recomendacoes = [];

// 1. BUSCAR DADOS DO USUÁRIO
$usuario_id = $_SESSION['usuario_id'];
$sql_usuario = "SELECT nome, email, ra, foto_perfil FROM usuarios WHERE id = ?";
$stmt = mysqli_prepare($conexao, $sql_usuario);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $usuario_id);
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $usuario_info = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// 2. BUSCAR ESTATÍSTICAS
$sql_stats = "SELECT 
    COUNT(*) as total_livros,
    SUM(CASE WHEN status = 'LENDO' THEN 1 ELSE 0 END) as lendo,
    SUM(CASE WHEN status = 'LIDO' THEN 1 ELSE 0 END) as lido,
    SUM(CASE WHEN status = 'QUERO_LER' THEN 1 ELSE 0 END) as quer_ler
    FROM historico_leitura WHERE usuario_id = ?";
    
$stmt = mysqli_prepare($conexao, $sql_stats);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $usuario_id);
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $estatisticas = array_map(function($value) {
                return $value === null ? 0 : $value;
            }, $row);
        }
    }
    mysqli_stmt_close($stmt);
}

// 3. BUSCAR LIVROS DA ESTANTE
$sql_estante = "SELECT l.*, hl.status 
                FROM historico_leitura hl 
                JOIN livros l ON hl.livro_id = l.id 
                WHERE hl.usuario_id = ? 
                ORDER BY hl.updated_at DESC 
                LIMIT 20";
                
$stmt = mysqli_prepare($conexao, $sql_estante);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $usuario_id);
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while($row = mysqli_fetch_assoc($result)){
                $livros_estante[] = $row;
            }
        }
    }
    mysqli_stmt_close($stmt);
}

// 4. RECOMENDAÇÕES SIMPLES
$sql_recomendacoes = "SELECT * FROM livros 
                     WHERE status = 'DISPONIVEL'
                     ORDER BY RAND() 
                     LIMIT 6";
                     
$result = mysqli_query($conexao, $sql_recomendacoes);
if ($result) {
    while($row = mysqli_fetch_assoc($result)){
        $recomendacoes[] = $row;
    }
}

mysqli_close($conexao);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Litwave - Minha Estante</title>
    <link rel="icon" href="icon.jpeg" type="image/jpeg">
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
        
        /* MENU CENTRALIZADO */
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
            display: flex;
            gap: 18px;
            padding: 18px;
        }
        
        nav.side {
            width: 300px;
        }
        
        .card {
            background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.02));
            border-radius: 12px;
            padding: 12px;
            border: 1px solid rgba(255,255,255,0.03);
        }
        
        [data-theme="light"] .card {
            background: var(--card);
            border: 1px solid rgba(0,0,0,0.08);
        }
        
        /* ESTATÍSTICAS */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(138,92,246,0.1), rgba(207,163,255,0.05));
            border: 1px solid rgba(138,92,246,0.2);
            border-radius: 10px;
            padding: 12px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--purple-light);
            margin-top: 4px;
        }
        
        .status-badges {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 12px;
        }
        
        .status-badge {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-lendo { 
            background: linear-gradient(135deg, rgba(56,211,159,0.15), rgba(56,211,159,0.05));
            border: 1px solid rgba(56,211,159,0.3);
            color: #38d39f;
        }
        
        .status-lido { 
            background: linear-gradient(135deg, rgba(138,92,246,0.15), rgba(138,92,246,0.05));
            border: 1px solid rgba(138,92,246,0.3);
            color: var(--purple-light);
        }
        
        .status-quero-ler { 
            background: linear-gradient(135deg, rgba(253,150,68,0.15), rgba(253,150,68,0.05));
            border: 1px solid rgba(253,150,68,0.3);
            color: #fd9644;
        }
        
        .status-count {
            background: rgba(255,255,255,0.1);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        /* LIVROS */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .book {
            background: var(--card);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.02);
            transition: transform 0.2s ease;
            min-height: 340px;
        }
        
        .book:hover {
            transform: translateY(-2px);
        }
        
        [data-theme="light"] .book {
            background: var(--card);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .cover {
            width: 100%;
            height: 220px;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0a0d;
        }
        
        [data-theme="light"] .cover {
            background: #f0f0f0;
        }
        
        .cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .title {
            font-size: 0.95rem;
            margin: 0;
            font-weight: 600;
            line-height: 1.3;
            min-height: 2.8em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .book-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .book-buttons {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 8px;
        }
        
        .book-buttons .ghost {
            padding: 6px 8px;
            font-size: 0.75rem;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .btn {
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            padding: 8px 12px;
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
        
        .ghost {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.04);
            padding: 7px 10px;
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
        
        .search-container {
            margin-bottom: 15px;
        }

        /* RECOMENDAÇÕES */
        .recomendacoes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .refresh-btn {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.8rem;
        }
        
        .refresh-btn:hover {
            background: rgba(138, 92, 246, 0.1);
        }
        
        .genero-tag {
            background: rgba(138, 92, 246, 0.1);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            margin-bottom: 8px;
            display: inline-block;
            color: var(--purple-light);
        }
        
        footer {
            padding: 18px;
            text-align: center;
            color: var(--muted);
            font-size: 0.9rem;
        }
        
        input, select, textarea {
            background: var(--glass) !important;
            border: 1px solid rgba(255,255,255,0.03) !important;
            color: var(--text) !important;
            padding: 8px;
            border-radius: 8px;
            font-family: inherit;
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
            border: none;
            padding: 6px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.75rem;
            flex: 1;
        }
        
        .btn-danger:hover {
            background: #c53030;
            transform: translateY(-1px);
        }
        
        .toast-area {
            position: fixed;
            right: 18px;
            top: 70px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 1000;
        }
        
        .toast {
            background: var(--card);
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.04);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            min-width: 250px;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        /* FILTROS DE ESTANTE */
        .filtros-estante {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .filtro-btn {
            padding: 6px 12px;
            border-radius: 8px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.8rem;
        }
        
        .filtro-btn.active {
            background: rgba(138, 92, 246, 0.2);
            border-color: var(--accent);
            color: var(--text);
        }
        
        .filtro-btn:hover {
            background: rgba(138, 92, 246, 0.1);
        }
        
        /* MENSAGEM DE ESTANTE VAZIA */
        .empty-shelf {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }
        
        .empty-shelf-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        @media (max-width: 1000px) {
            main {
                flex-direction: column;
            }
            
            nav.side {
                width: 100%;
            }
            
            .grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
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
        }
    </style>
</head>
<body data-theme="dark">
    <!-- HEADER COM MENU CENTRALIZADO -->
    <header>
        <div class="brand">
            <div class="logo-wrap">
                <img src="icon.png" alt="Litwave Icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden width="30" height="30" style="display:none">
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

        <!-- MENU CENTRALIZADO -->
        <nav class="nav-menu">
            <a href="index.php">Biblioteca</a>
            <a href="#" class="active">Minha Estante</a>
            <a href="chat.php">Chats</a>
            <a href="amigos.php">Amigos</a>
            <a href="perfil.php">Perfil</a>
        </nav>

        <div class="controls">
            <button class="ghost" id="btnTheme">Modo Claro</button>
            <button class="ghost" id="btnNotifications">🔔 <span id="notifCount">0</span></button>
            <button class="btn" id="btnNewBook">+ Adicionar livro</button>
            <a href="login.php" class="ghost" style="text-decoration:none;color:inherit">Sair</a>
        </div>
    </header>

    <main>
        <nav class="side">
            <div class="card">
                <div style="display:flex;gap:12px;align-items:center">
                    <?php if(!empty($usuario_info['foto_perfil'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario_info['foto_perfil']); ?>" 
                             alt="Foto de perfil" 
                             style="width:56px;height:56px;border-radius:10px;object-fit:cover;border:2px solid var(--accent)">
                    <?php else: ?>
                        <div style="width:56px;height:56px;border-radius:10px;background:linear-gradient(180deg,var(--accent),var(--accent-2));display:flex;align-items:center;justify-content:center;font-weight:700;color:#ffffff">
                            <?php echo substr($usuario_info['nome'], 0, 2); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div style="font-weight:700" id="profileName"><?php echo htmlspecialchars($usuario_info['nome']); ?></div>
                        <div class="muted small" id="profileEmail"><?php echo htmlspecialchars($usuario_info['email']); ?></div>
                        <div class="muted small">RA: <?php echo htmlspecialchars($usuario_info['ra']); ?></div>
                    </div>
                </div>

                <!-- ESTATÍSTICAS -->
                <div style="margin-top:16px">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $estatisticas['total_livros']; ?></div>
                            <div class="stat-label">Total de Livros</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $estatisticas['lendo']; ?></div>
                            <div class="stat-label">Lendo Agora</div>
                        </div>
                    </div>
                    
                    <div class="status-badges">
                        <div class="status-badge status-lido">
                            <span>Lidos</span>
                            <span class="status-count"><?php echo $estatisticas['lido']; ?></span>
                        </div>
                        <div class="status-badge status-quero-ler">
                            <span>Quero Ler</span>
                            <span class="status-count"><?php echo $estatisticas['quer_ler']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <section class="content" style="flex:1;display:flex;flex-direction:column;gap:14px">
            <!-- Barra de pesquisa -->
            <div id="searchContainer" class="search-container">
                <input id="searchInput" placeholder="Pesquisar na minha estante..." style="width:100%;max-width:400px" />
            </div>

            <!-- View Minha Estante -->
            <div id="viewShelf">
                <div class="card">
                    <div class="recomendacoes-header">
                        <div>
                            <h3>Minha Estante</h3>
                            <div class="small muted">Gerencie seus livros e acompanhe seu progresso</div>
                        </div>
                        <div style="display:flex;gap:8px">
                            <button class="refresh-btn" onclick="location.reload()">🔄 Atualizar</button>
                            <button class="btn" id="btnAddToShelf">+ Adicionar Livro</button>
                        </div>
                    </div>
                    
                    <!-- Filtros de Status -->
                    <div class="filtros-estante">
                        <button class="filtro-btn active" data-status="todos">Todos (<?php echo $estatisticas['total_livros']; ?>)</button>
                        <button class="filtro-btn" data-status="LENDO">Lendo (<?php echo $estatisticas['lendo']; ?>)</button>
                        <button class="filtro-btn" data-status="LIDO">Lidos (<?php echo $estatisticas['lido']; ?>)</button>
                        <button class="filtro-btn" data-status="QUERO_LER">Quero Ler (<?php echo $estatisticas['quer_ler']; ?>)</button>
                    </div>
                    
                    <!-- Livros da Estante -->
                    <div id="shelfGrid">
                        <?php if(empty($livros_estante)): ?>
                            <div class="empty-shelf">
                                <div class="empty-shelf-icon">📚</div>
                                <h4>Sua estante está vazia</h4>
                                <p class="muted">Adicione livros para começar sua jornada literária!</p>
                                <a href="adicionar_livro.php" class="btn" style="margin-top: 15px; text-decoration: none;">+ Adicionar Primeiro Livro</a>
                            </div>
                        <?php else: ?>
                            <div class="grid">
                                <?php foreach($livros_estante as $livro): 
                                    $status_class = 'status-' . strtolower(str_replace('_', '-', $livro['status']));
                                ?>
                                    <div class="book" data-id="<?php echo $livro['id']; ?>" data-status="<?php echo $livro['status']; ?>">
                                        <div class="cover">
                                            <img src="<?php echo htmlspecialchars($livro['capa_url'] ?? 'https://via.placeholder.com/400x600.png?text=' . urlencode($livro['titulo'])); ?>" 
                                                alt="<?php echo htmlspecialchars($livro['titulo']); ?>">
                                        </div>
                                        <div class="book-info">
                                            <div>
                                                <div class="title"><?php echo htmlspecialchars($livro['titulo']); ?></div>
                                                <div class="small muted"><?php echo htmlspecialchars($livro['autor']); ?></div>
                                                <div class="small <?php echo $status_class; ?> status-badge"><?php echo $livro['status']; ?></div>
                                            </div>
                                            <div class="book-buttons">
                                                <button class="ghost btn-change-status" data-id="<?php echo $livro['id']; ?>">Alterar Status</button>
                                                <a href="ler_livro.php?id=<?php echo $livro['id']; ?>" class="ghost btn-ler-livro">📖 Ler</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Seção de Recomendações -->
                    <?php if(!empty($recomendacoes)): ?>
                        <div style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 20px;">
                            <h4 style="margin-bottom: 12px; color: var(--purple-light); font-size: 1rem;">
                                💡 Recomendações para você
                            </h4>
                            <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px;">
                                <?php foreach($recomendacoes as $livro): ?>
                                    <div class="book" data-id="<?php echo $livro['id']; ?>" style="min-height: 280px; padding: 10px;">
                                        <div class="cover" style="height: 160px;">
                                            <img src="<?php echo htmlspecialchars($livro['capa_url'] ?? 'https://via.placeholder.com/300x450.png?text=' . urlencode($livro['titulo'])); ?>" 
                                                alt="<?php echo htmlspecialchars($livro['titulo']); ?>" style="object-fit: cover;">
                                        </div>
                                        <div class="book-info">
                                            <div>
                                                <?php if(!empty($livro['genero'])): ?>
                                                    <div class="genero-tag" style="font-size: 0.6rem; padding: 2px 6px;"><?php echo htmlspecialchars($livro['genero']); ?></div>
                                                <?php endif; ?>
                                                <div class="title" style="font-size: 0.85rem; min-height: 2.4em;"><?php echo htmlspecialchars($livro['titulo']); ?></div>
                                                <div class="small muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($livro['autor']); ?></div>
                                            </div>
                                            <div class="book-buttons">
                                                <form method="POST" action="adicionar_estante.php" style="display: inline; width: 100%;">
                                                    <input type="hidden" name="livro_id" value="<?php echo $livro['id']; ?>">
                                                    <input type="hidden" name="status" value="QUERO_LER">
                                                    <button type="submit" class="btn" style="width: 100%; padding: 6px 8px; font-size: 0.7rem;">+ Estante</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <div class="toast-area" id="toastArea"></div>

    <footer>
        © 2025 L.S.A — Bem-vindo, <?php echo htmlspecialchars($usuario_info['nome']); ?>!
    </footer>

    <script>
        // Sistema JavaScript simplificado
        document.addEventListener('DOMContentLoaded', function() {
            // Tema
            document.getElementById('btnTheme').addEventListener('click', function() {
                const body = document.body;
                const currentTheme = body.getAttribute('data-theme');
                if (currentTheme === 'dark') {
                    body.setAttribute('data-theme', 'light');
                    this.textContent = 'Modo Escuro';
                } else {
                    body.setAttribute('data-theme', 'dark');
                    this.textContent = 'Modo Claro';
                }
            });

            // Filtros
            document.querySelectorAll('.filtro-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    const status = this.dataset.status;
                    filterBooks(status);
                });
            });

            // Pesquisa
            document.getElementById('searchInput').addEventListener('input', function() {
                filterBooks();
            });

            // Botões de ação
            document.getElementById('btnNewBook').addEventListener('click', function() {
                window.location.href = 'adicionar_livro.php';
            });

            document.getElementById('btnAddToShelf').addEventListener('click', function() {
                window.location.href = 'adicionar_livro.php';
            });

            function filterBooks(statusFilter = null) {
                const searchTerm = document.getElementById('searchInput').value.toLowerCase();
                const activeFilter = statusFilter || document.querySelector('.filtro-btn.active').dataset.status;
                const books = document.querySelectorAll('#shelfGrid .book');
                
                books.forEach(book => {
                    const title = book.querySelector('.title').textContent.toLowerCase();
                    const author = book.querySelector('.small.muted').textContent.toLowerCase();
                    const status = book.dataset.status;
                    
                    const matchesSearch = title.includes(searchTerm) || author.includes(searchTerm);
                    const matchesFilter = activeFilter === 'todos' || status === activeFilter;
                    
                    if (matchesSearch && matchesFilter) {
                        book.style.display = '';
                    } else {
                        book.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>