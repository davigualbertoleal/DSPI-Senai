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

# Buscar informações do usuário (AGORA COM FOTO)
$usuario_id = $_SESSION['usuario_id'];
$sql_usuario = "SELECT nome, email, ra, foto_perfil FROM usuarios WHERE id = '$usuario_id'";
$result_usuario = mysqli_query($conexao, $sql_usuario);
$usuario_info = mysqli_fetch_assoc($result_usuario);

# Buscar estatísticas do usuário
$sql_estatisticas = "SELECT 
    COUNT(*) as total_livros,
    SUM(CASE WHEN status = 'LENDO' THEN 1 ELSE 0 END) as lendo,
    SUM(CASE WHEN status = 'LIDO' THEN 1 ELSE 0 END) as lido,
    SUM(CASE WHEN status = 'QUERO_LER' THEN 1 ELSE 0 END) as quer_ler
    FROM historico_leitura WHERE usuario_id = '$usuario_id'";
$result_estatisticas = mysqli_query($conexao, $sql_estatisticas);
$estatisticas = mysqli_fetch_assoc($result_estatisticas);

# Buscar livros do usuário para a estante
$sql_estante = "SELECT l.*, hl.status 
                FROM historico_leitura hl 
                JOIN livros l ON hl.livro_id = l.id 
                WHERE hl.usuario_id = '$usuario_id' 
                ORDER BY hl.updated_at DESC 
                LIMIT 20";
$result_estante = mysqli_query($conexao, $sql_estante);
$livros_estante = [];
if($result_estante) {
    while($row = mysqli_fetch_assoc($result_estante)){
        $livros_estante[] = $row;
    }
}

# Buscar gêneros preferidos do usuário para recomendações
$sql_generos_preferidos = "SELECT l.genero, COUNT(*) as total 
                          FROM historico_leitura hl 
                          JOIN livros l ON hl.livro_id = l.id 
                          WHERE hl.usuario_id = '$usuario_id' AND hl.status IN ('LENDO', 'LIDO')
                          GROUP BY l.genero 
                          ORDER BY total DESC 
                          LIMIT 3";
$result_generos = mysqli_query($conexao, $sql_generos_preferidos);
$generos_preferidos = [];
if($result_generos) {
    while($row = mysqli_fetch_assoc($result_generos)){
        if(!empty($row['genero'])) {
            $generos_preferidos[] = $row['genero'];
        }
    }
}

# Buscar recomendações baseadas nos gêneros preferidos
$recomendacoes = [];
if(!empty($generos_preferidos)) {
    $generos_condicao = "'" . implode("','", $generos_preferidos) . "'";
    $sql_recomendacoes = "SELECT * FROM livros 
                         WHERE genero IN ($generos_condicao) 
                         AND status = 'DISPONIVEL'
                         AND id NOT IN (
                             SELECT livro_id FROM historico_leitura 
                             WHERE usuario_id = '$usuario_id'
                         )
                         ORDER BY RAND() 
                         LIMIT 6";
    $result_recomendacoes = mysqli_query($conexao, $sql_recomendacoes);
    if($result_recomendacoes) {
        while($row = mysqli_fetch_assoc($result_recomendacoes)){
            $recomendacoes[] = $row;
        }
    }
}

# Se não tiver recomendações, buscar livros populares
if(empty($recomendacoes)) {
    $sql_populares = "SELECT * FROM livros 
                     WHERE status = 'DISPONIVEL'
                     AND id NOT IN (
                         SELECT livro_id FROM historico_leitura 
                         WHERE usuario_id = '$usuario_id'
                     )
                     ORDER BY RAND() 
                     LIMIT 6";
    $result_populares = mysqli_query($conexao, $sql_populares);
    if($result_populares) {
        while($row = mysqli_fetch_assoc($result_populares)){
            $recomendacoes[] = $row;
        }
    }
}

# Buscar todos os livros disponíveis para a biblioteca
$sql_livros = "SELECT * FROM livros WHERE status = 'DISPONIVEL' GROUP BY titulo ORDER BY titulo LIMIT 50";
$result_livros = mysqli_query($conexao, $sql_livros);
$todos_livros = [];
if($result_livros) {
    while($row = mysqli_fetch_assoc($result_livros)){
        $todos_livros[] = $row;
    }
}

# Converter livros do banco para o formato do JavaScript
$livros_js = [];
foreach($todos_livros as $livro) {
    $livros_js[] = [
        'id' => $livro['id'],
        'title' => $livro['titulo'],
        'author' => $livro['autor'],
        'cover' => $livro['capa_url'] ?? 'https://via.placeholder.com/400x600.png?text=' . urlencode($livro['titulo']),
        'genre' => $livro['genero'],
        'pages' => $livro['paginas'] ?? 200,
        'publisher' => $livro['editora'] ?? 'Editora Desconhecida',
        'year' => $livro['ano_publicacao'] ?? 2000,
        'isbn' => $livro['isbn'] ?? '000-0000000000',
        'description' => $livro['descricao'] ?? 'Descrição não disponível.'
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Leitura+ - Biblioteca</title>
    <!-- ÍCONE PERSONALIZADO -->
    <link rel="icon" href="icon.png" type="image/png">
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
        
        /* HEADER SUPERIOR - CENTRALIZADO */
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
        
        /* ESTATÍSTICAS MAIS BONITAS */
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
            -webkit-line-camp: 2;
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
        
        .badge {
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            padding: 6px 8px;
            border-radius: 999px;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        .search-container {
            display: none;
            margin-bottom: 15px;
        }
        
        .search-container.active {
            display: block;
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
                <!-- ÍCONE PERSONALIZADO NO HEADER -->
                <img src="icon.png" alt="Litwave Icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <!-- FALLBACK CASO A IMAGEM NÃO CARREGUE -->
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
            <a href="#" class="active" data-view="library">Biblioteca</a>
            <a href="minha_estante.php">Minha Estante</a>
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
                    <!-- FOTO DO USUÁRIO DO BD -->
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
                            <div class="stat-number"><?php echo $estatisticas['total_livros'] ?: '0'; ?></div>
                            <div class="stat-label">Total de Livros</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $estatisticas['lendo'] ?: '0'; ?></div>
                            <div class="stat-label">Lendo Agora</div>
                        </div>
                    </div>
                    
                    <div class="status-badges">
                        <div class="status-badge status-lido">
                            <span>Lidos</span>
                            <span class="status-count"><?php echo $estatisticas['lido'] ?: '0'; ?></span>
                        </div>
                        <div class="status-badge status-quero-ler">
                            <span>Quero Ler</span>
                            <span class="status-count"><?php echo $estatisticas['quer_ler'] ?: '0'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <section class="content" style="flex:1;display:flex;flex-direction:column;gap:14px">
            <!-- Barra de pesquisa -->
            <div id="searchContainer" class="search-container active">
                <input id="searchInput" placeholder="Pesquisar livros..." style="width:100%;max-width:400px" />
            </div>

            <!-- View Biblioteca -->
            <div id="viewLibrary" class="card">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <h2 style="margin:0">Explorar</h2>
                        <div class="small muted">Encontre livros e playlists</div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <select id="filterGenre" style="width:150px">
                            <option value="">Todos</option>
                            <?php 
                            $generos = array_unique(array_column($todos_livros, 'genero'));
                            foreach($generos as $genero): 
                                if(!empty($genero)):
                            ?>
                                <option value="<?php echo htmlspecialchars($genero); ?>"><?php echo htmlspecialchars($genero); ?></option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </select>
                        <button class="ghost" id="btnSort">Ordenar: Título</button>
                    </div>
                </div>

                <div style="margin-top:12px">
                    <div id="sectionGrid" class="grid" aria-live="polite">
                        <!-- Os livros serão carregados via JavaScript -->
                    </div>
                </div>
            </div>

            <!-- View Amigos -->
            <div id="viewFriends" style="display:none">
                <div class="card">
                    <h3>Amigos</h3>
                    <div style="margin-top:8px; text-align: center; padding: 20px;">
                        <div class="muted" style="margin-bottom: 15px;">Você será redirecionado para a página de amigos</div>
                        <a href="amigos.php" class="btn" style="text-decoration: none;">Ir para Amigos</a>
                    </div>
                </div>
            </div>

            <!-- View Perfil -->
            <div id="viewProfile" style="display:none">
                <div class="card">
                    <h3>Meu Perfil</h3>
                    <div style="margin-top:8px; text-align: center; padding: 20px;">
                        <div class="muted" style="margin-bottom: 15px;">Você será redirecionado para a página de perfil</div>
                        <a href="perfil.php" class="btn" style="text-decoration: none;">Ir para Perfil</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div class="toast-area" id="toastArea"></div>

    <footer>
        © 2025 L.S.A — Bem-vindo, <?php echo htmlspecialchars($usuario_info['nome']); ?>!
    </footer>

    <script>
        // --- Sistema Organizado Integrado com PHP ---
        class LeituraPlusApp {
            constructor() {
                // Usar livros do PHP
                this.BOOKS = <?php echo json_encode($livros_js); ?>;
                
                this.currentBook = null;
                this.currentView = 'library';
                
                // Chaves do localStorage
                this.LS_KEYS = {
                    FAV: 'leituraplus_favorites_v2',
                    SHELF: 'leituraplus_shelf_v2',
                    PROGRESS: 'leituraplus_progress_v2',
                    FOLLOW: 'leituraplus_follow_v2',
                    NOTIFS: 'leituraplus_notifs_v2'
                };
                
                // Estado
                this.favorites = JSON.parse(localStorage.getItem(this.LS_KEYS.FAV) || '[]');
                this.shelf = JSON.parse(localStorage.getItem(this.LS_KEYS.SHELF) || '[]');
                this.progress = JSON.parse(localStorage.getItem(this.LS_KEYS.PROGRESS) || '{}');
                this.follows = JSON.parse(localStorage.getItem(this.LS_KEYS.FOLLOW) || '[]');
                this.notifs = JSON.parse(localStorage.getItem(this.LS_KEYS.NOTIFS) || '[]');
                
                this.init();
            }

            // --- Inicialização ---
            init() {
                this.renderGrid(this.BOOKS, document.getElementById('sectionGrid'));
                this.updateNotifCount();
                this.setupEventListeners();
                this.showView('library');
                this.setupNavMenu();
            }

            // --- Menu de Navegação Superior ---
            setupNavMenu() {
                const navLinks = document.querySelectorAll('.nav-menu a');
                navLinks.forEach(link => {
                    link.addEventListener('click', (e) => {
                        const href = link.getAttribute('href');
                        
                        // Se for link externo, redireciona
                        if (href && href !== '#' && !href.includes('javascript')) {
                            if (href === 'chat.php' || href === 'amigos.php' || href === 'perfil.php' || href === 'minha_estante.php') {
                                window.location.href = href;
                                return;
                            }
                        }
                        
                        e.preventDefault();
                        
                        // Remover active de todos
                        navLinks.forEach(l => l.classList.remove('active'));
                        // Adicionar active ao clicado
                        link.classList.add('active');
                        
                        const view = link.dataset.view;
                        if (view) {
                            this.showView(view);
                        }
                    });
                });
            }

            // --- Utilitários ---
            escapeHtml(s) { 
                return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); 
            }

            qs(sel) { return document.querySelector(sel); }
            qsa(sel) { return Array.from(document.querySelectorAll(sel)); }

            // --- Renderizadores ---
            uniqueGenres() { 
                return Array.from(new Set(this.BOOKS.map(b => b.genre))).sort(); 
            }

            renderGrid(list, container) {
                container.innerHTML = '';
                list.forEach(book => {
                    const el = document.createElement('div');
                    el.className = 'book';
                    el.dataset.id = book.id;
                    el.innerHTML = `
                        <div class="cover">
                            <img src="${book.cover}" alt="${this.escapeHtml(book.title)}" 
                                 onerror="this.src='https://via.placeholder.com/400x600.png?text=Capa+Não+Encontrada'">
                        </div>
                        <div class="book-info">
                            <div>
                                <div class="title">${this.escapeHtml(book.title)}</div>
                                <div class="small muted">${this.escapeHtml(book.author)}</div>
                                <div class="small muted">${this.escapeHtml(book.genre)}</div>
                            </div>
                            <div class="book-buttons">
                                <a href="view.php?id=${book.id}" class="ghost">Checar Informações</a>
                                ${this.favorites.includes(book.id) ? 
                                    '<button class="btn btn-unfav" data-id="' + book.id + '">♥ Favorito</button>' : 
                                    '<button class="ghost btn-fav" data-id="' + book.id + '">♡ Favoritar</button>'}
                            </div>
                        </div>
                    `;
                    container.appendChild(el);
                });
            }

            // --- Event Listeners ---
            setupEventListeners() {
                // Pesquisa e filtros
                this.qs('#searchInput').addEventListener('input', e => {
                    const q = e.target.value.trim().toLowerCase();
                    const g = this.qs('#filterGenre').value;
                    let res = this.BOOKS.filter(b => 
                        (!g || b.genre === g) && 
                        (b.title + b.author + b.genre).toLowerCase().includes(q)
                    );
                    
                    if (this.currentView === 'library') {
                        this.renderGrid(res, this.qs('#sectionGrid'));
                    }
                });

                this.qs('#filterGenre').addEventListener('change', () => {
                    this.qs('#searchInput').dispatchEvent(new Event('input'));
                });

                this.qs('#btnSort').addEventListener('click', () => {
                    this.BOOKS.sort((a, b) => a.title.localeCompare(b.title));
                    this.renderGrid(this.BOOKS, this.qs('#sectionGrid'));
                });

                // Grid de livros - Biblioteca
                this.qs('#sectionGrid').addEventListener('click', e => {
                    if (e.target.matches('a[href*="view.php"]')) {
                        return;
                    }
                    
                    if (e.target.matches('.btn-fav')) {
                        this.toggleFavorite(Number(e.target.dataset.id));
                        this.renderGrid(this.BOOKS, this.qs('#sectionGrid'));
                    }
                    if (e.target.matches('.btn-unfav')) {
                        this.toggleFavorite(Number(e.target.dataset.id));
                        this.renderGrid(this.BOOKS, this.qs('#sectionGrid'));
                    }
                    
                    if (e.target.closest('.book') && !e.target.matches('a') && !e.target.matches('button')) {
                        const bookId = e.target.closest('.book').dataset.id;
                        window.location.href = `view.php?id=${bookId}`;
                    }
                });

                // Notificações
                this.qs('#btnNotifications').addEventListener('click', () => {
                    const list = this.notifs.slice(0, 10);
                    if (!list.length) return this.mostrarMensagem('ℹ️ Sem notificações', 'success');
                    
                    let text = 'Notificações:\n\n';
                    list.forEach(n => text += `${n.date} — ${n.text}\n`);
                    alert(text);
                    
                    this.notifs = [];
                    localStorage.setItem(this.LS_KEYS.NOTIFS, JSON.stringify(this.notifs));
                    this.updateNotifCount();
                });

                // Tema
                this.qs('#btnTheme').addEventListener('click', () => {
                    const body = document.body;
                    const cur = body.getAttribute('data-theme');
                    if (cur === 'dark') {
                        body.setAttribute('data-theme', 'light');
                        this.qs('#btnTheme').textContent = 'Modo Escuro';
                    } else {
                        body.setAttribute('data-theme', 'dark');
                        this.qs('#btnTheme').textContent = 'Modo Claro';
                    }
                });

                // Novo livro
                this.qs('#btnNewBook').addEventListener('click', () => {
                    window.location.href = 'adicionar_livro.php';
                });
            }

            // --- Funcionalidades Principais ---
            showView(viewName) {
                this.currentView = viewName;
                this.hideAllViews();
                
                const searchContainer = this.qs('#searchContainer');
                if (viewName === 'library') {
                    searchContainer.classList.add('active');
                    this.qs('#searchInput').value = '';
                    this.renderGrid(this.BOOKS, this.qs('#sectionGrid'));
                } else {
                    searchContainer.classList.remove('active');
                }
                
                this.qs(`#view${viewName.charAt(0).toUpperCase() + viewName.slice(1)}`).style.display = '';
            }

            hideAllViews() {
                this.qs('#viewLibrary').style.display = 'none';
                this.qs('#viewFriends').style.display = 'none';
                this.qs('#viewProfile').style.display = 'none';
            }

            // --- Favoritos ---
            toggleFavorite(id) {
                if (this.favorites.includes(id)) {
                    this.favorites = this.favorites.filter(x => x !== id);
                    this.pushNotif('Removido dos favoritos');
                    this.mostrarMensagem('❌ Removido dos favoritos', 'success');
                } else {
                    this.favorites.push(id);
                    this.pushNotif('Adicionado aos favoritos');
                    this.mostrarMensagem('✅ Adicionado aos favoritos', 'success');
                }
                localStorage.setItem(this.LS_KEYS.FAV, JSON.stringify(this.favorites));
            }

            // --- Notificações ---
            pushNotif(text) {
                const item = {
                    text,
                    date: new Date().toLocaleString()
                };
                
                this.notifs.unshift(item);
                localStorage.setItem(this.LS_KEYS.NOTIFS, JSON.stringify(this.notifs));
                this.showToast(item);
                this.updateNotifCount();
            }

            showToast(item) {
                const area = this.qs('#toastArea');
                const t = document.createElement('div');
                t.className = 'toast';
                t.textContent = item.text;
                area.appendChild(t);
                
                setTimeout(() => {
                    t.style.opacity = '0';
                    t.style.transition = 'opacity 400ms';
                    setTimeout(() => t.remove(), 400);
                }, 4000);
            }

            updateNotifCount() {
                this.qs('#notifCount').textContent = this.notifs.length || 0;
            }

            // --- Sistema de Mensagens Toast Melhorado ---
            mostrarMensagem(mensagem, tipo) {
                const toastArea = document.getElementById('toastArea');
                const toast = document.createElement('div');
                toast.className = `toast ${tipo}`;
                toast.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="font-size: 1.2rem;">${tipo === 'success' ? '✅' : '❌'}</div>
                        <div>${mensagem}</div>
                    </div>
                `;
                
                toastArea.appendChild(toast);
                
                setTimeout(() => toast.classList.add('show'), 100);
                
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }, 4000);
            }
        }

        // Inicializar a aplicação quando o DOM estiver carregado
        document.addEventListener('DOMContentLoaded', () => {
            window.app = new LeituraPlusApp();
        });

        // Simular atividade de amigos
        setInterval(() => {
            if (window.app) {
                const sampleFriends = [
                    {id:1, name:'Camila R.', status:'Lendo Clean Code'},
                    {id:2, name:'Lucas M.', status:'Comentou Dom Casmurro'},
                    {id:3, name:'Beatriz S.', status:'Seguindo a saga'}
                ];
                
                const f = sampleFriends[Math.floor(Math.random() * sampleFriends.length)];
                const b = window.app.BOOKS[Math.floor(Math.random() * window.app.BOOKS.length)];
                if (b) {
                    window.app.pushNotif(`${f.name} comentou em ${b.title}`);
                }
            }
        }, 25000);
    </script>
</body>
</html>