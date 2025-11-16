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

// Buscar livros na Google Books
if(isset($_POST['buscar'])){
    $busca = urlencode($_POST['busca']);
    $url = "https://www.googleapis.com/books/v1/volumes?q={$busca}&maxResults=20&langRestrict=pt";
    
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
            
            // Só adiciona se NÃO for conteúdo proibido
            if(!$conteudo_proibido){
                $resultados_filtrados[] = $item;
            }
        }
        
        // ORDENAR POR MELHORES AVALIADOS
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
    <title>Buscar Livros - Google Books API</title>
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
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
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
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
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
        }
        
        .livro-card:hover {
            transform: translateY(-5px);
        }
        
        .livro-capa {
            width: 120px;
            height: 180px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 12px;
        }
        
        .livro-titulo {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 6px;
            line-height: 1.3;
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
            background: #38d39f;
            color: #000;
        }
        
        .erro {
            background: #e74c3c;
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
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .sugestao:hover {
            background: rgba(138, 92, 246, 0.2);
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Buscar Livros - Google Books API</h1>
            <p class="muted">Encontre e adicione livros automaticamente</p>
            <div class="filtro-info">🚫 Conteúdo adulto explícito é filtrado automaticamente</div>
        </div>
        
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
                <div class="sugestao" onclick="document.querySelector('input[name=\"busca\"]').value='Harry Potter'">Harry Potter</div>
                <div class="sugestao" onclick="document.querySelector('input[name=\"busca\"]').value='Clube da Luta'">Clube da Luta</div>
                <div class="sugestao" onclick="document.querySelector('input[name=\"busca\"]').value='1984 George Orwell'">1984</div>
                <div class="sugestao" onclick="document.querySelector('input[name=\"busca\"]').value='Clean Code'">Clean Code</div>
                <div class="sugestao" onclick="document.querySelector('input[name=\"busca\"]').value='Laranja Mecânica'">Laranja Mecânica</div>
                <div class="sugestao" onclick="document.querySelector('input[name=\"busca\"]').value='Stephen King'">Stephen King</div>
                <div class="sugestao" onclick="document.querySelector('input[name=\"busca\"]').value='Automação Industrial'">Automação</div>
                <div class="sugestao" onclick="document.querySelector('input[name=\"busca\"]').value='Arquitetura Software'">Arquitetura</div>
            </div>
        </div>

        <?php if(isset($resultados['items'])): ?>
            <?php if(empty($resultados['items'])): ?>
                <div class="sem-resultados">
                    <h3>📭 Nenhum livro encontrado</h3>
                    <p>Nenhum livro adequado foi encontrado para "<strong><?php echo $_POST['busca']; ?></strong>"</p>
                    <p class="muted">Tente outros termos de busca ou use as sugestões acima</p>
                </div>
            <?php else: ?>
                <div class="ordenacao-info">
                    📊 <strong>Ordenado por:</strong> Melhores avaliados primeiro (nota + quantidade de avaliações)
                </div>
                
                <h2 style="margin-bottom: 20px;">📚 Resultados encontrados (<?php echo count($resultados['items']); ?>):</h2>
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
                            <div class="livro-titulo"><?php echo $livro['title']; ?></div>
                            <div class="livro-autor"><?php echo $autor; ?></div>
                            
                            <?php if($rating > 0): ?>
                                <div class="avaliacao">
                                    <span class="estrela">⭐</span>
                                    <span class="nota"><?php echo number_format($rating, 1); ?></span>
                                    <?php if($ratingsCount > 0): ?>
                                        <span class="avaliacoes-count">(<?php echo $ratingsCount; ?>)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="small muted" style="margin-bottom: 10px; font-size: 0.75rem;">
                                <?php echo $genero; ?>
                            </div>
                            
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
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php elseif(isset($_POST['buscar'])): ?>
            <div class="sem-resultados">
                <h3>📭 Nenhum livro encontrado</h3>
                <p>Nenhum livro adequado foi encontrado para "<strong><?php echo $_POST['busca']; ?></strong>"</p>
                <p class="muted">Tente outros termos de busca ou use as sugestões acima</p>
            </div>
        <?php endif; ?>

        <a href="index.php" class="voltar">← Voltar para o Menu Principal</a>
    </div>

    <script>
        // Auto-focus no campo de busca
        document.querySelector('input[name="busca"]')?.focus();
        
        // Preview rápido das sugestões
        document.querySelectorAll('.sugestao').forEach(sugestao => {
            sugestao.addEventListener('click', function() {
                document.querySelector('button[name="buscar"]').click();
            });
        });
    </script>
</body>
</html>