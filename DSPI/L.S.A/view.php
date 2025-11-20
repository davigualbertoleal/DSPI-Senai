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

# Buscar informações do livro
$livro_id = $_GET['id'] ?? 0;
$sql_livro = "SELECT * FROM livros WHERE id = '$livro_id'";
$result_livro = mysqli_query($conexao, $sql_livro);
$livro = mysqli_fetch_assoc($result_livro);

if(!$livro) {
    die("Livro não encontrado!");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Litwave - <?php echo htmlspecialchars($livro['titulo']); ?></title>
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .book-header {
            display: flex;
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .book-cover {
            width: 320px;
            height: 440px;
            border-radius: 12px;
            overflow: hidden;
            background: #070708;
            flex-shrink: 0;
            /* Melhorias de qualidade */
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            /* Otimizações de qualidade */
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            image-rendering: pixelated;
            filter: brightness(1.05) contrast(1.05);
            transition: transform 0.3s ease;
        }

        .book-cover img:hover {
            transform: scale(1.02);
        }
        
        .book-info {
            flex: 1;
        }
        
        .book-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--accent-2);
        }
        
        .book-author {
            font-size: 1.3rem;
            color: var(--muted);
            margin-bottom: 20px;
        }
        
        .book-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            background: rgba(255,255,255,0.03);
            padding: 10px 16px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .book-description {
            line-height: 1.6;
            font-size: 1.1rem;
            color: var(--muted);
            margin-bottom: 30px;
        }
        
        .book-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
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
            gap: 8px;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .btn.ghost {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text) !important;
        }
        
        .comments-section {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid rgba(255,255,255,0.03);
            margin-bottom: 20px;
        }
        
        .section-title {
            margin-bottom: 20px;
            color: var(--accent-2);
            font-size: 1.4rem;
        }
        
        .comment-form {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .comment-form input, .comment-form textarea {
            background: var(--glass);
            border: 1px solid rgba(255,255,255,0.03);
            color: var(--text);
            padding: 12px;
            border-radius: 8px;
            flex: 1;
            font-family: inherit;
            resize: vertical;
            min-height: 44px;
        }
        
        .comment-form input:focus, .comment-form textarea:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .user-info {
            background: rgba(255,255,255,0.05);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .user-name {
            font-weight: 600;
            color: var(--accent-2);
        }
        
        .user-email {
            font-size: 0.9rem;
            color: var(--muted);
        }
        
        .comments-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .comment {
            background: rgba(255,255,255,0.02);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.03);
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .comment-author {
            font-weight: 600;
            color: var(--accent-2);
        }
        
        .comment-date {
            font-size: 0.85rem;
            color: var(--muted);
        }
        
        .comment-text {
            line-height: 1.5;
        }
        
        .media-recommendation {
            background: rgba(138, 92, 246, 0.1);
            border: 1px solid rgba(138, 92, 246, 0.2);
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .media-type {
            display: inline-block;
            background: var(--accent);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }
        
        .tab {
            background: transparent;
            border: none;
            color: var(--muted);
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .tab.active {
            background: var(--accent);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .book-header {
                flex-direction: column;
            }
            
            .book-cover {
                width: 100%;
                max-width: 280px;
                margin: 0 auto;
            }
            
            .book-title {
                font-size: 1.8rem;
            }
            
            .book-author {
                font-size: 1.1rem;
            }
            
            .book-meta {
                gap: 10px;
            }
            
            .meta-item {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
            
            .comment-form {
                flex-direction: column;
            }
        }
    </style>
</head>
<body data-theme="dark">
    <header>
        <div style="display:flex;align-items:center;gap:12px">
            <div style="width:46px;height:46px;border-radius:10px;background:linear-gradient(135deg, var(--accent), var(--accent-2));display:flex;align-items:center;justify-content:center;">
                <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden width="24" height="24">
                    <path d="M8 12c0 0 10-6 24-6s24 6 24 6v32c0 0-10-6-24-6S8 44 8 44V12z" fill="white"/>
                </svg>
            </div>
            <div>
                <h1 style="margin:0;font-size:1rem">L.S.A</h1>
                <div style="font-size:0.86rem;color:var(--muted)">Detalhes do Livro</div>
            </div>
        </div>

        <div style="display:flex;gap:10px;align-items:center">
            <button class="btn ghost" id="btnTheme">Modo Claro</button>
            <a href="index.php" class="btn ghost">Voltar à Biblioteca</a>
        </div>
    </header>

    <div class="container">
        <div class="book-header">
            <div class="book-cover">
                <img src="<?php echo htmlspecialchars($livro['capa_url'] ?? 'https://via.placeholder.com/400x600.png?text=' . urlencode($livro['titulo'])); ?>" 
                     alt="<?php echo htmlspecialchars($livro['titulo']); ?>">
            </div>
            
            <div class="book-info">
                <h1 class="book-title"><?php echo htmlspecialchars($livro['titulo']); ?></h1>
                <div class="book-author">por <?php echo htmlspecialchars($livro['autor']); ?></div>
                
                <div class="book-meta">
                    <div class="meta-item">
                        <strong>Gênero:</strong> <?php echo htmlspecialchars($livro['genero']); ?>
                    </div>
                    <div class="meta-item">
                        <strong>Páginas:</strong> <?php echo htmlspecialchars($livro['paginas'] ?? 'N/A'); ?>
                    </div>
                    <div class="meta-item">
                        <strong>Editora:</strong> <?php echo htmlspecialchars($livro['editora'] ?? 'Editora Desconhecida'); ?>
                    </div>
                    <div class="meta-item">
                        <strong>Ano:</strong> <?php echo htmlspecialchars($livro['ano_publicacao'] ?? 'N/A'); ?>
                    </div>
                    <div class="meta-item">
                        <strong>ISBN:</strong> <?php echo htmlspecialchars($livro['isbn'] ?? 'N/A'); ?>
                    </div>
                </div>
                
                <div class="book-description">
                    <?php echo htmlspecialchars($livro['descricao'] ?? 'Descrição não disponível para este livro.'); ?>
                </div>
                
                <div class="book-actions">
                    <button class="btn" id="btnFavorite">
                        <span>♡</span> Favoritar
                    </button>
                    <button class="btn ghost" id="btnAddShelf">
                        <span>+</span> Minha Estante
                    </button>
                    <a href="chat.php" class="btn ghost">
                        <span>💬</span> Chat do Livro
                    </a>
                </div>
            </div>
        </div>
        
        <div class="comments-section">
            <h2 class="section-title">Comentários e Recomendações</h2>
            
            <div class="tabs">
                <button class="tab active" data-tab="comments">Comentários</button>
                <button class="tab" data-tab="media">Mídias Recomendadas</button>
            </div>
            
            <!-- Informações do usuário -->
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($usuario_info['nome']); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($usuario_info['email']); ?></div>
                <?php if(!empty($usuario_info['ra'])): ?>
                    <div class="user-email">RA: <?php echo htmlspecialchars($usuario_info['ra']); ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Aba de Comentários -->
            <div class="tab-content active" id="commentsTab">
                <div class="comment-form">
                    <input type="text" id="commentText" placeholder="Escreva um comentário sobre o livro...">
                    <button class="btn" id="btnAddComment">Comentar</button>
                </div>
                
                <div class="comments-list" id="commentsList">
                    <div class="empty-state">
                        Nenhum comentário ainda — seja o primeiro a comentar!
                    </div>
                </div>
            </div>
            
            <!-- Aba de Mídias Recomendadas -->
            <div class="tab-content" id="mediaTab">
                <div style="margin-bottom: 20px; color: var(--muted);">
                    Recomende mídias que combinem com esse livro, como músicas, filmes, séries, etc.
                </div>
                
                <div class="comment-form">
                    <select id="mediaType" style="background: var(--glass); border: 1px solid rgba(255,255,255,0.03); color: var(--text); padding: 12px; border-radius: 8px; font-family: inherit;">
                        <option value="music">🎵 Música</option>
                        <option value="movie">🎬 Filme</option>
                        <option value="series">📺 Série</option>
                        <option value="podcast">🎙️ Podcast</option>
                        <option value="game">🎮 Jogo</option>
                        <option value="other">📱 Outro</option>
                    </select>
                    <input type="text" id="mediaTitle" placeholder="Título da mídia...">
                    <textarea id="mediaDescription" placeholder="Por que você recomenda esta mídia? Como ela se relaciona com o livro?" rows="2"></textarea>
                    <button class="btn" id="btnAddMedia">Recomendar</button>
                </div>
                
                <div class="comments-list" id="mediaList">
                    <div class="empty-state">
                        Nenhuma mídia recomendada ainda — seja o primeiro a recomendar!
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- Sistema de Tabs ---
        function setupTabs() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    tab.classList.add('active');
                    document.getElementById(`${tabId}Tab`).classList.add('active');
                });
            });
        }
        
        // --- Sistema de Comentários ---
        function setupComments() {
            const btnAddComment = document.getElementById('btnAddComment');
            const commentText = document.getElementById('commentText');
            const commentsList = document.getElementById('commentsList');
            
            // Carregar comentários existentes
            loadComments();
            
            btnAddComment.addEventListener('click', addComment);
            commentText.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') addComment();
            });
        }
        
        function loadComments() {
            const bookId = <?php echo $livro['id']; ?>;
            const key = `leituraplus_comments_${bookId}`;
            const comments = JSON.parse(localStorage.getItem(key) || '[]');
            const commentsList = document.getElementById('commentsList');
            
            if (comments.length === 0) {
                commentsList.innerHTML = '<div class="empty-state">Nenhum comentário ainda — seja o primeiro a comentar!</div>';
                return;
            }
            
            commentsList.innerHTML = comments.map(comment => `
                <div class="comment">
                    <div class="comment-header">
                        <span class="comment-author"><?php echo htmlspecialchars($usuario_info['nome']); ?></span>
                        <span class="comment-date">${escapeHtml(comment.date)}</span>
                    </div>
                    <div class="comment-text">${escapeHtml(comment.text)}</div>
                </div>
            `).join('');
        }
        
        function addComment() {
            const text = document.getElementById('commentText').value.trim();
            
            if (!text) {
                alert('Por favor, escreva um comentário.');
                return;
            }
            
            const bookId = <?php echo $livro['id']; ?>;
            const key = `leituraplus_comments_${bookId}`;
            const comments = JSON.parse(localStorage.getItem(key) || '[]');
            
            comments.unshift({
                user: '<?php echo htmlspecialchars($usuario_info['nome']); ?>',
                text,
                date: new Date().toLocaleString()
            });
            
            localStorage.setItem(key, JSON.stringify(comments));
            document.getElementById('commentText').value = '';
            loadComments();
        }
        
        // --- Sistema de Mídias Recomendadas ---
        function setupMedia() {
            const btnAddMedia = document.getElementById('btnAddMedia');
            const mediaTitle = document.getElementById('mediaTitle');
            const mediaDescription = document.getElementById('mediaDescription');
            
            // Carregar mídias existentes
            loadMedia();
            
            btnAddMedia.addEventListener('click', addMedia);
            mediaTitle.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') addMedia();
            });
        }
        
        function loadMedia() {
            const bookId = <?php echo $livro['id']; ?>;
            const key = `leituraplus_media_${bookId}`;
            const media = JSON.parse(localStorage.getItem(key) || '[]');
            const mediaList = document.getElementById('mediaList');
            
            if (media.length === 0) {
                mediaList.innerHTML = '<div class="empty-state">Nenhuma mídia recomendada ainda — seja o primeiro a recomendar!</div>';
                return;
            }
            
            mediaList.innerHTML = media.map(item => `
                <div class="comment media-recommendation">
                    <div class="media-type">${getMediaTypeIcon(item.type)} ${getMediaTypeName(item.type)}</div>
                    <div class="comment-header">
                        <span class="comment-author"><?php echo htmlspecialchars($usuario_info['nome']); ?></span>
                        <span class="comment-date">${escapeHtml(item.date)}</span>
                    </div>
                    <div class="comment-text">
                        <strong>${escapeHtml(item.title)}</strong><br>
                        ${escapeHtml(item.description)}
                    </div>
                </div>
            `).join('');
        }
        
        function addMedia() {
            const type = document.getElementById('mediaType').value;
            const title = document.getElementById('mediaTitle').value.trim();
            const description = document.getElementById('mediaDescription').value.trim();
            
            if (!title) {
                alert('Por favor, informe o título da mídia.');
                return;
            }
            
            if (!description) {
                alert('Por favor, explique por que você recomenda esta mídia.');
                return;
            }
            
            const bookId = <?php echo $livro['id']; ?>;
            const key = `leituraplus_media_${bookId}`;
            const media = JSON.parse(localStorage.getItem(key) || '[]');
            
            media.unshift({
                type,
                title,
                description,
                user: '<?php echo htmlspecialchars($usuario_info['nome']); ?>',
                date: new Date().toLocaleString()
            });
            
            localStorage.setItem(key, JSON.stringify(media));
            document.getElementById('mediaTitle').value = '';
            document.getElementById('mediaDescription').value = '';
            loadMedia();
        }
        
        function getMediaTypeIcon(type) {
            const icons = {
                'music': '🎵',
                'movie': '🎬',
                'series': '📺',
                'podcast': '🎙️',
                'game': '🎮',
                'other': '📱'
            };
            return icons[type] || '📱';
        }
        
        function getMediaTypeName(type) {
            const names = {
                'music': 'Música',
                'movie': 'Filme',
                'series': 'Série',
                'podcast': 'Podcast',
                'game': 'Jogo',
                'other': 'Outro'
            };
            return names[type] || 'Mídia';
        }
        
        // --- Sistema de Favoritos ---
        function setupFavorites() {
            const btnFavorite = document.getElementById('btnFavorite');
            const bookId = <?php echo $livro['id']; ?>;
            
            // Verificar se já é favorito
            const favorites = JSON.parse(localStorage.getItem('leituraplus_favorites') || '[]');
            if (favorites.includes(bookId)) {
                btnFavorite.innerHTML = '<span>♥</span> Favorito';
            }
            
            btnFavorite.addEventListener('click', () => {
                const favorites = JSON.parse(localStorage.getItem('leituraplus_favorites') || '[]');
                
                if (favorites.includes(bookId)) {
                    // Remover dos favoritos
                    const newFavorites = favorites.filter(id => id !== bookId);
                    localStorage.setItem('leituraplus_favorites', JSON.stringify(newFavorites));
                    btnFavorite.innerHTML = '<span>♡</span> Favoritar';
                    alert('Removido dos favoritos!');
                } else {
                    // Adicionar aos favoritos
                    favorites.push(bookId);
                    localStorage.setItem('leituraplus_favorites', JSON.stringify(favorites));
                    btnFavorite.innerHTML = '<span>♥</span> Favorito';
                    alert('Adicionado aos favoritos!');
                }
            });
        }
        
        // --- Sistema de Estante ---
        function setupShelf() {
            const btnAddShelf = document.getElementById('btnAddShelf');
            const bookId = <?php echo $livro['id']; ?>;
            
            btnAddShelf.addEventListener('click', () => {
                const shelf = JSON.parse(localStorage.getItem('leituraplus_shelf') || '[]');
                
                if (!shelf.includes(bookId)) {
                    shelf.unshift(bookId);
                    localStorage.setItem('leituraplus_shelf', JSON.stringify(shelf));
                    alert('Livro adicionado à sua estante!');
                } else {
                    alert('Este livro já está na sua estante!');
                }
            });
        }
        
        // --- Tema ---
        function setupTheme() {
            const btnTheme = document.getElementById('btnTheme');
            
            btnTheme.addEventListener('click', () => {
                const body = document.body;
                const cur = body.getAttribute('data-theme');
                if (cur === 'dark') {
                    body.setAttribute('data-theme', 'light');
                    btnTheme.textContent = 'Modo Escuro';
                } else {
                    body.setAttribute('data-theme', 'dark');
                    btnTheme.textContent = 'Modo Claro';
                }
            });
        }
        
        // --- Utilitários ---
        function escapeHtml(s) { 
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); 
        }
        
        // --- Inicialização ---
        document.addEventListener('DOMContentLoaded', () => {
            setupTabs();
            setupComments();
            setupMedia();
            setupFavorites();
            setupShelf();
            setupTheme();
        });
    </script>
</body>
</html>