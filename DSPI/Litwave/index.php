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

# Buscar todos os livros disponíveis para a biblioteca (evitando repetição por título)
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
        }
        
        h1 {
            margin: 0;
            font-size: 1rem;
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
        
        .muted {
            color: var(--muted);
        }
        
        .small {
            font-size: 0.86rem;
        }
        
        .menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .menu button {
            background: transparent;
            border: 0;
            color: var(--muted);
            text-align: left;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .menu button:hover {
            background: rgba(138,92,246,0.05);
        }
        
        .menu button.active {
            background: rgba(138,92,246,0.08);
            color: var(--text);
        }
        
        [data-theme="light"] .menu button.active {
            background: rgba(79,70,229,0.08);
        }
        
        /* LIVROS MAIORES E BOTÕES AJUSTADOS */
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
        
        .controls {
            display: flex;
            gap: 8px;
            align-items: center;
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
        
        .panel {
            display: flex;
            gap: 12px;
        }
        
        .panel .left {
            width: 420px;
        }
        
        .panel .right {
            flex: 1;
        }
        
        .book-full {
            display: flex;
            gap: 12px;
        }
        
        .thumb {
            width: 200px;
            height: 300px;
            border-radius: 10px;
            overflow: hidden;
            background: #070708;
        }
        
        [data-theme="light"] .thumb {
            background: #f0f0f0;
        }
        
        .progress {
            height: 10px;
            background: rgba(255,255,255,0.04);
            border-radius: 999px;
            overflow: hidden;
        }
        
        [data-theme="light"] .progress {
            background: rgba(0,0,0,0.08);
        }
        
        .progress > span {
            display: block;
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            transition: width 300ms;
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
        
        [data-theme="light"] .toast {
            border: 1px solid rgba(0,0,0,0.08);
        }
        
        .friend {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            border-radius: 8px;
        }
        
        .dict-popup {
            position: fixed;
            background: var(--card);
            padding: 10px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.04);
            z-index: 1060;
            max-width: 300px;
            color: var(--text);
        }
        
        [data-theme="light"] .dict-popup {
            border: 1px solid rgba(0,0,0,0.08);
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
        
        .book-actions {
            display: flex;
            gap: 6px;
            margin-top: 10px;
        }
        
        .book-actions .ghost {
            flex: 1;
            padding: 8px;
            font-size: 0.8rem;
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
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 5px;
            display: inline-block;
        }
        
        .status-lendo { background: #38d39f; color: #000; }
        .status-lido { background: #6c5ce7; color: #fff; }
        .status-quero-ler { background: #fd9644; color: #000; }
        
        .book-removing {
            opacity: 0.5;
            transform: scale(0.95);
            pointer-events: none;
            transition: all 0.3s ease;
        }
        
        .search-container {
            display: none;
            margin-bottom: 15px;
        }
        
        .search-container.active {
            display: block;
        }
        
        @media (max-width: 1000px) {
            main {
                flex-direction: column;
            }
            
            nav.side {
                width: 100%;
            }
            
            .panel .left {
                width: 100%;
            }
            
            .panel {
                flex-direction: column;
            }
            
            .grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
        }
    </style>
</head>
<body data-theme="dark">
    <header>
        <div style="display:flex;align-items:center;gap:12px">
            <div class="logo-wrap" title="Leitura+">
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

        <div style="display:flex;gap:10px;align-items:center">
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
                    <div style="width:56px;height:56px;border-radius:10px;background:linear-gradient(180deg,#fff0,#fff1);display:flex;align-items:center;justify-content:center;font-weight:700;color:#12021a">
                        <?php echo substr($usuario_info['nome'], 0, 2); ?>
                    </div>
                    <div>
                        <div style="font-weight:700" id="profileName"><?php echo htmlspecialchars($usuario_info['nome']); ?></div>
                        <div class="muted small" id="profileEmail"><?php echo htmlspecialchars($usuario_info['email']); ?></div>
                        <div class="muted small">RA: <?php echo htmlspecialchars($usuario_info['ra']); ?></div>
                    </div>
                </div>

                <div style="margin-top:12px" class="menu">
                    <button class="active" data-view="library">Biblioteca</button>
                    <button data-view="shelf">Minha Estante</button>
                    <button onclick="window.location.href='chat.php'">Chats</button>
                    <button data-view="friends">Amigos</button>
                    <button data-view="profile">Perfil</button>
                </div>
            </div>

            <div style="height:12px"></div>

            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <div class="small muted">Estatísticas</div>
                        <div style="font-weight:700">Leituras: <span id="statReads"><?php echo $estatisticas['total_livros'] ?: '0'; ?></span></div>
                    </div>
                    <div style="text-align:right">
                        <div class="small muted">Seguindo</div>
                        <div style="font-weight:700" id="statFollowing">0</div>
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;margin-top:8px">
                    <div class="small status-lendo status-badge">Lendo: <?php echo $estatisticas['lendo'] ?: '0'; ?></div>
                    <div class="small status-lido status-badge">Lidos: <?php echo $estatisticas['lido'] ?: '0'; ?></div>
                    <div class="small status-quero-ler status-badge">Quero ler: <?php echo $estatisticas['quer_ler'] ?: '0'; ?></div>
                </div>
            </div>
        </nav>

        <section class="content" style="flex:1;display:flex;flex-direction:column;gap:14px">
            <!-- Barra de pesquisa (aparece apenas em Biblioteca e Minha Estante) -->
            <div id="searchContainer" class="search-container">
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

            <!-- View Minha Estante -->
            <div id="viewShelf" style="display:none">
                <div class="card">
                    <h3>Minha Estante</h3>
                    <div id="shelfGrid" class="grid" style="margin-top:10px">
                        <?php if(empty($livros_estante)): ?>
                            <div class="muted">Sua estante está vazia — adicione livros!</div>
                        <?php else: ?>
                            <?php foreach($livros_estante as $livro): 
                                $status_class = 'status-' . strtolower(str_replace('_', '-', $livro['status']));
                            ?>
                                <div class="book" data-id="<?php echo $livro['id']; ?>">
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- View Amigos -->
            <div id="viewFriends" style="display:none">
                <div class="card">
                    <h3>Amigos</h3>
                    <div id="friendsList" style="margin-top:8px">
                        <div class="muted">Funcionalidade em desenvolvimento...</div>
                    </div>
                </div>
            </div>

            <!-- View Perfil -->
            <div id="viewProfile" style="display:none">
                <div class="card">
                    <h3>Meu Perfil</h3>
                    <div style="margin-top:8px">
                        <div class="muted">Página de perfil em desenvolvimento...</div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div class="toast-area" id="toastArea"></div>
    <div id="dictPopup" class="dict-popup" style="display:none"></div>

    <footer>
        © 2025 Leitura+ — Bem-vindo, <?php echo htmlspecialchars($usuario_info['nome']); ?>!
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
                    NOTIFS: 'leituraplus_notifs_v2',
                    COMMENTS: 'leituraplus_comments_v1'
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
                    } else if (this.currentView === 'shelf') {
                        // Filtrar livros da estante
                        const shelfBooks = this.BOOKS.filter(book => this.shelf.includes(book.id));
                        const filteredShelf = shelfBooks.filter(b => 
                            (b.title + b.author + b.genre).toLowerCase().includes(q)
                        );
                        this.renderGrid(filteredShelf, this.qs('#shelfGrid'));
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
                    // Permitir que links normais funcionem (Checar Informações)
                    if (e.target.matches('a[href*="view.php"]')) {
                        return; // Deixar o link funcionar normalmente
                    }
                    
                    if (e.target.matches('.btn-fav')) {
                        this.toggleFavorite(Number(e.target.dataset.id));
                        this.renderGrid(this.BOOKS, this.qs('#sectionGrid'));
                    }
                    if (e.target.matches('.btn-unfav')) {
                        this.toggleFavorite(Number(e.target.dataset.id));
                        this.renderGrid(this.BOOKS, this.qs('#sectionGrid'));
                    }
                    
                    // Click no livro inteiro (exceto em links e botões) também abre as informações
                    if (e.target.closest('.book') && !e.target.matches('a') && !e.target.matches('button')) {
                        const bookId = e.target.closest('.book').dataset.id;
                        window.location.href = `view.php?id=${bookId}`;
                    }
                });

                // Grid de estante - Minha Estante (prevenir que o JavaScript interfira nos links)
                this.qs('#shelfGrid').addEventListener('click', e => {
                    // Permitir que links normais funcionem
                    if (e.target.matches('.btn-ler-livro')) {
                        // Deixar o link funcionar normalmente - não fazer nada aqui
                        return;
                    }
                    
                    // Para outros botões na estante
                    if (e.target.matches('.btn-change-status')) {
                        this.mudarStatusLivro(e.target.dataset.id);
                    }
                    
                    // Prevenir que clicks no livro inteiro interfiram
                    if (e.target.closest('.book') && !e.target.matches('a') && !e.target.matches('button')) {
                        const bookId = e.target.closest('.book').dataset.id;
                        window.location.href = `view.php?id=${bookId}`;
                    }
                });

                // Navegação
                this.qsa('.menu button').forEach(b => {
                    b.addEventListener('click', () => {
                        const view = b.dataset.view;
                        
                        this.qsa('.menu button').forEach(x => x.classList.remove('active'));
                        b.classList.add('active');
                        this.showView(view);
                    });
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

                // Playlist do usuário
                this.qs('#userPlaylist').addEventListener('click', e => {
                    e.preventDefault();
                    const url = prompt('Cole o link da sua playlist Spotify (simulado):', 'https://open.spotify.com/');
                    if (!url) return;
                    
                    this.qs('#userPlaylist').dataset.link = url;
                    this.qs('#userPlaylist').textContent = 'Minha playlist (salva)';
                    localStorage.setItem('leituraplus_user_playlist', url);
                    this.mostrarMensagem('✅ Playlist salva!', 'success');
                });
            }

            // --- Funcionalidades Principais ---
            showView(viewName) {
                this.currentView = viewName;
                this.hideAllViews();
                
                // Mostrar/ocultar barra de pesquisa
                const searchContainer = this.qs('#searchContainer');
                if (viewName === 'library' || viewName === 'shelf') {
                    searchContainer.classList.add('active');
                    // Limpar pesquisa ao mudar de view
                    this.qs('#searchInput').value = '';
                    
                    if (viewName === 'shelf') {
                        // Não renderizar via JavaScript para manter os links PHP funcionando
                        // A estante já vem renderizada do PHP
                    } else if (viewName === 'library') {
                        // Renderizar biblioteca completa
                        this.renderGrid(this.BOOKS, this.qs('#sectionGrid'));
                    }
                } else {
                    searchContainer.classList.remove('active');
                }
                
                // Mostrar view selecionada
                this.qs(`#view${viewName.charAt(0).toUpperCase() + viewName.slice(1)}`).style.display = '';
            }

            hideAllViews() {
                this.qs('#viewLibrary').style.display = 'none';
                this.qs('#viewShelf').style.display = 'none';
                this.qs('#viewFriends').style.display = 'none';
                this.qs('#viewProfile').style.display = 'none';
            }

            // --- Mudar status do livro na estante ---
            mudarStatusLivro(bookId) {
                const statusAtual = prompt('Alterar status para (LENDO, LIDO, QUERO_LER):');
                if (statusAtual && ['LENDO', 'LIDO', 'QUERO_LER'].includes(statusAtual.toUpperCase())) {
                    // Aqui você pode fazer uma requisição para atualizar no banco
                    this.mostrarMensagem(`✅ Status alterado para ${statusAtual}`, 'success');
                    // Recarregar a página para refletir as mudanças
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
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
                
                // Animação de entrada
                setTimeout(() => toast.classList.add('show'), 100);
                
                // Remover após 4 segundos
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

        // Simular atividade de amigos (demo)
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