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

$mensagem = "";
$resultados = [];

// Buscar livros populares automaticamente
if(!isset($_POST['buscar'])){
    $busquas_populares = ["Harry Potter", "Stephen King", "Jogos Vorazes", "Percy Jackson", "Clean Code", "Dom Casmurro"];
    $busca_automatica = $busquas_populares[array_rand($busquas_populares)];
    $busca = urlencode($busca_automatica);
    $url = "https://www.googleapis.com/books/v1/volumes?q={$busca}&maxResults=20&langRestrict=pt&orderBy=relevance";
    
    $dados = file_get_contents($url);
    $resultados = json_decode($dados, true);
}

// Buscar livros na Google Books
if(isset($_POST['buscar'])){
    $busca = urlencode($_POST['busca']);
    $url = "https://www.googleapis.com/books/v1/volumes?q={$busca}&maxResults=20&langRestrict=pt&orderBy=relevance";
    
    $dados = file_get_contents($url);
    $resultados = json_decode($dados, true);
    
    // FILTRO ANTI-PORNOGRAFIA - ESCONDE COMPLETAMENTE
    if(isset($resultados['items'])){
        $resultados_filtrados = [];
        
        foreach($resultados['items'] as $item){
            $livro = $item['volumeInfo'];
            $titulo = strtolower($livro['title'] ?? '');
            $descricao = strtolower($livro['description'] ?? '');
            $categorias = implode(' ', array_map('strtolower', $livro['categories'] ?? []));
            $autores = implode(' ', array_map('strtolower', $livro['authors'] ?? []));
            
            // PALAVRAS BLOQUEADAS - MAIS ABRANGENTE
            $palavras_proibidas = [
                // PORNO EXPLÍCITO
                'porn', 'xxx', 'hentai', 'nsfw', 'hardcore', 'x-rated', 'x rated',
                'sex explicit', 'explicit sex', 'adult film', 'adult video',
                'playboy', 'penthouse', 'hustler', 'erotic magazine',
                'naked', 'nude', 'nua', 'nú', 'desnuda', 'desnudo',
                
                // GÊNEROS EXPLÍCITOS  
                'erotica', 'erótica', 'adult romance', 'adult fiction', 'romance erótico',
                
                // TERMOS EXPLÍCITOS
                'fuck', 'fucking', 'cock', 'dick', 'pussy', 'asshole', 'blowjob',
                'handjob', 'fellation', 'penetration', 'orgasm', 'masturbation',
                
                // AUTORES/CONTEÚDO EXPLÍCITO
                'anita blake', 'fifty shades', 'cinquenta tons', 'sylvia day',
                'megan maxwell', 'escrava isaura', 'harlequin'
            ];
            
            $conteudo_proibido = false;
            
            // Verifica se tem alguma palavra proibida em qualquer campo
            $texto_completo = $titulo . ' ' . $descricao . ' ' . $categorias . ' ' . $autores;
            
            foreach($palavras_proibidas as $palavra){
                if(strpos($texto_completo, $palavra) !== false){
                    $conteudo_proibido = true;
                    break;
                }
            }
            
            // Verificação extra por categorias explícitas
            $categorias_explicitas = ['erotica', 'erotic', 'adult'];
            foreach($categorias_explicitas as $cat){
                if(strpos($categorias, $cat) !== false){
                    $conteudo_proibido = true;
                    break;
                }
            }
            
            // Filtrar artigos científicos e conteúdo acadêmico
            $conteudo_academico = ['proceedings', 'conference', 'journal', 'symposium', 'abstract', 'thesis'];
            foreach($conteudo_academico as $acad){
                if(strpos($texto_completo, $acad) !== false){
                    $conteudo_proibido = true;
                    break;
                }
            }
            
            // Só adiciona se NÃO for conteúdo proibido
            if(!$conteudo_proibido){
                $resultados_filtrados[] = $item;
            }
        }
        
        // ORDENAR POR MELHORES AVALIADOS E RELEVÂNCIA
        usort($resultados_filtrados, function($a, $b) {
            $ratingA = $a['volumeInfo']['averageRating'] ?? 0;
            $ratingB = $b['volumeInfo']['averageRating'] ?? 0;
            $ratingsCountA = $a['volumeInfo']['ratingsCount'] ?? 0;
            $ratingsCountB = $b['volumeInfo']['ratingsCount'] ?? 0;
            
            // Prioriza livros com mais avaliações primeiro, depois pela nota
            if ($ratingsCountA != $ratingsCountB) {
                return $ratingsCountB - $ratingsCountA;
            }
            
            return $ratingB - $ratingA;
        });
        
        $resultados['items'] = $resultados_filtrados;
    }
}

// Adicionar livro ao banco
if(isset($_POST['adicionar'])){
    $dados_livro = json_decode($_POST['dados_livro'], true);
    
    $titulo = mysqli_real_escape_string($conexao, $dados_livro['titulo'] ?? '');
    $autor = mysqli_real_escape_string($conexao, $dados_livro['autor'] ?? 'Autor Desconhecido');
    $descricao = mysqli_real_escape_string($conexao, $dados_livro['descricao'] ?? '');
    $capa_url = mysqli_real_escape_string($conexao, $dados_livro['capa'] ?? '');
    $genero = mysqli_real_escape_string($conexao, $dados_livro['genero'] ?? 'Geral');
    
    // Determinar tipo baseado no gênero
    $tipos_tecnicos = ['TI', 'Tecnologia', 'Programação', 'Computação', 'Engenharia', 'Ciência'];
    $tipo = 'LITERARIO';
    foreach($tipos_tecnicos as $tec){
        if(stripos($genero, $tec) !== false || stripos($titulo, $tec) !== false){
            $tipo = 'TECNICO';
            break;
        }
    }
    
    $sql = "INSERT INTO livros (titulo, autor, descricao, capa_url, tipo, genero, status) 
            VALUES ('$titulo', '$autor', '$descricao', '$capa_url', '$tipo', '$genero', 'DISPONIVEL')";
    
    if(mysqli_query($conexao, $sql)){
        $mensagem = "✅ Livro '{$titulo}' adicionado com sucesso!";
    } else {
        $mensagem = "❌ Erro: " . mysqli_error($conexao);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            --gold: #ffd700;
            --silver: #c0c0c0;
            --bronze: #cd7f32;
            --success: #38d39f;
            --danger: #e74c3c;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Inter", sans-serif;
        }
        
        body {
            background: linear-gradient(180deg, var(--bg), #050507);
            color: var(--text);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            margin-bottom: 30px;
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
        
        .busca {
            background: var(--card);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid rgba(255,255,255,0.03);
        }
        
        .busca-input {
            display: flex;
            gap: 12px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        input[type="text"] {
            flex: 1;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.1);
            background: #1a1d26;
            color: white;
            font-size: 1rem;
        }
        
        .btn {
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        .livros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .livro-card {
            background: var(--card);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.03);
            transition: transform 0.2s ease;
            position: relative;
            min-height: 340px;
            display: flex;
            flex-direction: column;
        }
        
        .livro-card:hover {
            transform: translateY(-5px);
        }
        
        .livro-capa {
            width: 120px;
            height: 180px;
            object-fit: cover;
            border-radius: 6px;
            margin: 0 auto 12px;
        }
        
        .livro-titulo {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 6px;
            line-height: 1.3;
            min-height: 2.8em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .livro-autor {
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 10px;
        }
        
        .avaliacao {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin-bottom: 10px;
            font-size: 0.8rem;
        }
        
        .estrela {
            color: var(--gold);
        }
        
        .nota {
            font-weight: 600;
            color: var(--gold);
        }
        
        .avaliacoes-count {
            color: var(--muted);
            font-size: 0.75rem;
        }
        
        .ranking-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--gold);
            color: #000;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.3);
        }
        
        .ranking-badge.silver {
            background: var(--silver);
            color: #000;
        }
        
        .ranking-badge.bronze {
            background: var(--bronze);
            color: #fff;
        }
        
        .msg {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
        }
        
        .sucesso {
            background: var(--success);
            color: #000;
        }
        
        .erro {
            background: var(--danger);
            color: #fff;
        }
        
        .voltar {
            display: inline-block;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            text-align: center;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .voltar:hover {
            background: rgba(138,92,246,0.1);
        }
        
        .sugestoes {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .sugestao {
            background: rgba(138, 92, 246, 0.1);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid rgba(138,92,246,0.2);
        }
        
        .sugestao:hover {
            background: rgba(138, 92, 246, 0.2);
            transform: translateY(-1px);
        }
        
        .sem-resultados {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }
        
        .filtro-info {
            text-align: center;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: var(--muted);
        }
        
        .ordenacao-info {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            background: rgba(138, 92, 246, 0.1);
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .book-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .book-buttons {
            margin-top: auto;
        }
        
        .genero-badge {
            background: rgba(138, 92, 246, 0.1);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            margin-bottom: 8px;
            display: inline-block;
        }
        
        .recomendacoes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .refresh-btn {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .refresh-btn:hover {
            background: rgba(138, 92, 246, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
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
                    <div style="font-size: 0.9rem; color: var(--muted);">Buscar Livros - Google Books API</div>
                </div>
            </div>
            <a href="index.php" class="btn">← Voltar</a>
        </header>
        
        <?php if($mensagem): ?>
            <div class="msg <?php echo strpos($mensagem, '✅') !== false ? 'sucesso' : 'erro'; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="busca">
            <form method="POST">
                <div class="busca-input">
                    <input type="text" name="busca" placeholder="Harry Potter, Clean Code, Dom Casmurro..." 
                           value="<?php echo $_POST['busca'] ?? ''; ?>" required>
                    <button type="submit" name="buscar" class="btn">🔍 Buscar</button>
                </div>
            </form>
            
            <div class="sugestoes">
                <div class="sugestao" onclick="pesquisarSugestao('Harry Potter')">Harry Potter</div>
                <div class="sugestao" onclick="pesquisarSugestao('Clube da Luta')">Clube da Luta</div>
                <div class="sugestao" onclick="pesquisarSugestao('1984 George Orwell')">1984</div>
                <div class="sugestao" onclick="pesquisarSugestao('Clean Code')">Clean Code</div>
                <div class="sugestao" onclick="pesquisarSugestao('Laranja Mecânica')">Laranja Mecânica</div>
                <div class="sugestao" onclick="pesquisarSugestao('Stephen King')">Stephen King</div>
                <div class="sugestao" onclick="pesquisarSugestao('Jogos Vorazes')">Jogos Vorazes</div>
                <div class="sugestao" onclick="pesquisarSugestao('Percy Jackson')">Percy Jackson</div>
            </div>
            
            <div class="filtro-info">
                🚫 Conteúdo adulto explícito e artigos científicos são filtrados automaticamente
            </div>
        </div>

        <?php if(isset($resultados['items']) || !isset($_POST['buscar'])): ?>
            <?php if(empty($resultados['items'])): ?>
                <div class="sem-resultados">
                    <h3>📭 Nenhum livro encontrado</h3>
                    <p>Nenhum livro adequado foi encontrado para "<strong><?php echo $_POST['busca'] ?? 'busca automática'; ?></strong>"</p>
                    <p class="muted">Tente outros termos de busca ou use as sugestões acima</p>
                </div>
            <?php else: ?>
                <div class="recomendacoes-header">
                    <h2>
                        <?php if(isset($_POST['buscar'])): ?>
                            📚 Resultados para "<?php echo $_POST['busca']; ?>" (<?php echo count($resultados['items']); ?>)
                        <?php else: ?>
                            🔥 Livros Populares Recomendados
                        <?php endif; ?>
                    </h2>
                    <?php if(!isset($_POST['buscar'])): ?>
                        <button class="refresh-btn" onclick="location.reload()">🔄 Atualizar</button>
                    <?php endif; ?>
                </div>
                
                <div class="ordenacao-info">
                    📊 <strong>Ordenado por:</strong> Melhores avaliados primeiro (nota + quantidade de avaliações)
                </div>
                
                <div class="livros-grid">
                    <?php foreach($resultados['items'] as $index => $item): 
                        $livro = $item['volumeInfo'];
                        $capa = $livro['imageLinks']['thumbnail'] ?? 'https://via.placeholder.com/120x180.png?text=' . urlencode(substr($livro['title'], 0, 20));
                        $autor = $livro['authors'][0] ?? 'Autor Desconhecido';
                        $descricao = $livro['description'] ?? 'Sem descrição disponível.';
                        $genero = $livro['categories'][0] ?? 'Geral';
                        $rating = $livro['averageRating'] ?? 0;
                        $ratingsCount = $livro['ratingsCount'] ?? 0;
                        
                        // Melhorar a qualidade da imagem
                        if(strpos($capa, 'googlebooks') !== false){
                            $capa = str_replace('zoom=1', 'zoom=2', $capa);
                            $capa = str_replace('&edge=curl', '', $capa);
                            $capa = str_replace('http://', 'https://', $capa);
                        }
                        
                        // Determinar badge de ranking
                        $rankingClass = '';
                        if ($index === 0 && $rating >= 4.0) $rankingClass = 'gold';
                        elseif ($index === 1 && $rating >= 4.0) $rankingClass = 'silver';
                        elseif ($index === 2 && $rating >= 4.0) $rankingClass = 'bronze';
                    ?>
                        <div class="livro-card">
                            <?php if($rankingClass): ?>
                                <div class="ranking-badge <?php echo $rankingClass; ?>">
                                    <?php echo $index + 1; ?>
                                </div>
                            <?php endif; ?>
                            
                            <img src="<?php echo $capa; ?>" class="livro-capa" alt="Capa" 
                                 onerror="this.src='https://via.placeholder.com/120x180.png?text=<?php echo urlencode(substr($livro['title'], 0, 15)); ?>'">
                            
                            <div class="book-info">
                                <div>
                                    <div class="livro-titulo"><?php echo $livro['title']; ?></div>
                                    <div class="livro-autor"><?php echo $autor; ?></div>
                                    
                                    <?php if($genero && $genero !== 'Geral'): ?>
                                        <div class="genero-badge"><?php echo $genero; ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if($rating > 0): ?>
                                        <div class="avaliacao">
                                            <span class="estrela">⭐</span>
                                            <span class="nota"><?php echo number_format($rating, 1); ?></span>
                                            <?php if($ratingsCount > 0): ?>
                                                <span class="avaliacoes-count">(<?php echo $ratingsCount; ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="book-buttons">
                                    <form method="POST">
                                        <input type="hidden" name="dados_livro" value='<?php echo json_encode([
                                            'titulo' => $livro['title'],
                                            'autor' => $autor,
                                            'descricao' => $descricao,
                                            'capa' => $capa,
                                            'genero' => $genero
                                        ]); ?>'>
                                        
                                        <button type="submit" name="adicionar" class="btn btn-small">➕ Adicionar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Auto-focus no campo de busca
        document.querySelector('input[name="busca"]')?.focus();
        
        // Função para pesquisar sugestões
        function pesquisarSugestao(termo) {
            document.querySelector('input[name="busca"]').value = termo;
            document.querySelector('form').submit();
        }
        
        // Preview rápido das sugestões
        document.querySelectorAll('.sugestao').forEach(sugestao => {
            sugestao.addEventListener('click', function() {
                const termo = this.textContent;
                pesquisarSugestao(termo);
            });
        });
        
        // Animações suaves
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.livro-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>