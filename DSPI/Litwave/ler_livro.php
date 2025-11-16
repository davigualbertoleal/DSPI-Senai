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

// VERIFICAR SE O LIVRO EXISTE
$livro_id = $_GET['id'] ?? null;
$livro = null;
$tem_pdf = false;
$caminho_pdf = '';

if($livro_id) {
    $sql = "SELECT * FROM livros WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "i", $livro_id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $livro = mysqli_fetch_assoc($resultado);
    
    if($livro) {
        // PROCURAR PDF NA PASTA ARQUIVOSPDF
        $titulo_arquivo = preg_replace('/[^a-zA-Z0-9]/', '_', $livro['titulo']);
        $possiveis_pdfs = [
            "arquivosPDF/{$titulo_arquivo}.pdf",
            "arquivosPDF/{$livro_id}.pdf", 
            "arquivosPDF/livro_{$livro_id}.pdf",
            "arquivosPDF/{$livro['titulo']}.pdf"
        ];
        
        foreach($possiveis_pdfs as $caminho) {
            if(file_exists($caminho)) {
                $tem_pdf = true;
                $caminho_pdf = $caminho;
                break;
            }
        }
        
        // Se ainda não encontrou, lista todos os PDFs disponíveis
        if(!$tem_pdf && is_dir('arquivosPDF')) {
            $todos_pdfs = glob('arquivosPDF/*.pdf');
            if(count($todos_pdfs) > 0) {
                $caminho_pdf = $todos_pdfs[0];
                $tem_pdf = true;
            }
        }
    }
}

// SE O LIVRO NÃO EXISTIR, REDIRECIONA
if(!$livro) {
    header("Location: index.php");
    exit();
}

// CONTROLE DE PÁGINAS SIMPLES
$pagina_atual = $_POST['pagina_atual'] ?? $_GET['pagina'] ?? 1;

// Processar navegação
if(isset($_POST['mudar_pagina']) || isset($_POST['salvar_pagina'])) {
    $pagina_atual = intval($_POST['pagina_atual']);
    
    if(isset($_POST['mudar_pagina'])) {
        if($_POST['mudar_pagina'] === 'proxima') {
            $pagina_atual++;
        } elseif($_POST['mudar_pagina'] === 'anterior' && $pagina_atual > 1) {
            $pagina_atual--;
        }
    }
    
    // Salvar no histórico
    $sql = "INSERT INTO historico_leitura (usuario_id, livro_id, status) 
            VALUES (?, ?, 'LENDO')
            ON DUPLICATE KEY UPDATE 
            status = 'LENDO',
            data_inicio = IF(data_inicio IS NULL, CURDATE(), data_inicio),
            updated_at = CURRENT_TIMESTAMP";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $_SESSION['usuario_id'], $livro_id);
    mysqli_stmt_execute($stmt);
    
    // Redirecionar para a página correta
    header("Location: ler_livro.php?id=$livro_id&pagina=$pagina_atual");
    exit();
}

// API PARA TROCAR PÁGINA VIA AJAX
if(isset($_POST['ajax_mudar_pagina'])) {
    $nova_pagina = intval($_POST['pagina']);
    
    // Salvar no histórico
    $sql = "INSERT INTO historico_leitura (usuario_id, livro_id, status) 
            VALUES (?, ?, 'LENDO')
            ON DUPLICATE KEY UPDATE 
            status = 'LENDO',
            data_inicio = IF(data_inicio IS NULL, CURDATE(), data_inicio),
            updated_at = CURRENT_TIMESTAMP";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $_SESSION['usuario_id'], $livro_id);
    mysqli_stmt_execute($stmt);
    
    // Retornar sucesso
    echo json_encode(['success' => true, 'pagina' => $nova_pagina]);
    exit();
}

// Status de leitura do usuário
$status_leitura = null;
$sql_status = "SELECT status FROM historico_leitura WHERE usuario_id = ? AND livro_id = ?";
$stmt_status = mysqli_prepare($conexao, $sql_status);
mysqli_stmt_bind_param($stmt_status, "ii", $_SESSION['usuario_id'], $livro_id);
mysqli_stmt_execute($stmt_status);
$resultado_status = mysqli_stmt_get_result($stmt_status);
if($row = mysqli_fetch_assoc($resultado_status)) {
    $status_leitura = $row['status'];
}

// Atualizar status de leitura
if(isset($_POST['atualizar_status'])) {
    $novo_status = $_POST['status'];
    
    $sql_update = "INSERT INTO historico_leitura (usuario_id, livro_id, status, data_inicio, data_fim) 
                   VALUES (?, ?, ?, CURDATE(), ?)
                   ON DUPLICATE KEY UPDATE 
                   status = VALUES(status), 
                   data_inicio = IF(VALUES(status) = 'LENDO' AND data_inicio IS NULL, CURDATE(), data_inicio),
                   data_fim = IF(VALUES(status) = 'LIDO', CURDATE(), NULL),
                   updated_at = CURRENT_TIMESTAMP";
    
    $data_fim = ($novo_status == 'LIDO') ? 'CURDATE()' : 'NULL';
    
    $stmt_update = mysqli_prepare($conexao, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "iiss", $_SESSION['usuario_id'], $livro_id, $novo_status, $data_fim);
    mysqli_stmt_execute($stmt_update);
    
    // Recarregar a página
    header("Location: ler_livro.php?id=" . $livro_id);
    exit();
}

// Buscar avaliações do livro
$sql_avaliacoes = "SELECT u.nome, a.nota, a.comentario, a.created_at 
                   FROM avaliacoes a 
                   JOIN usuarios u ON a.usuario_id = u.id 
                   WHERE a.livro_id = ? 
                   ORDER BY a.created_at DESC";
$stmt_avaliacoes = mysqli_prepare($conexao, $sql_avaliacoes);
mysqli_stmt_bind_param($stmt_avaliacoes, "i", $livro_id);
mysqli_stmt_execute($stmt_avaliacoes);
$avaliacoes = mysqli_stmt_get_result($stmt_avaliacoes);

// Média das avaliações
$sql_media = "SELECT AVG(nota) as media, COUNT(*) as total FROM avaliacoes WHERE livro_id = ?";
$stmt_media = mysqli_prepare($conexao, $sql_media);
mysqli_stmt_bind_param($stmt_media, "i", $livro_id);
mysqli_stmt_execute($stmt_media);
$result_media = mysqli_stmt_get_result($stmt_media);
$media_info = mysqli_fetch_assoc($result_media);
$nota_media = $media_info['media'] ?? 0;
$total_avaliacoes = $media_info['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($livro['titulo']); ?> - LitWave</title>
    <style>
        :root {
            --bg: #0b0b0d;
            --panel: #0f0f14;
            --card: #111217;
            --accent: #8a5cf6;
            --accent-2: #cfa3ff;
            --muted: #9aa0b4;
            --text: #e7e9ee;
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
        
        .header {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            align-items: flex-start;
        }
        
        .capa-livro {
            width: 200px;
            height: 300px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        .info-livro {
            flex: 1;
        }
        
        .titulo-livro {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: white;
        }
        
        .autor-livro {
            font-size: 1.2rem;
            color: var(--muted);
            margin-bottom: 20px;
        }
        
        .detalhes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .detalhe-item {
            background: var(--card);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .detalhe-label {
            font-size: 0.8rem;
            color: var(--muted);
            margin-bottom: 5px;
        }
        
        .detalhe-valor {
            font-size: 1rem;
            font-weight: 600;
        }
        
        .avaliacao-geral {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .estrelas {
            color: #ffd700;
            font-size: 1.2rem;
        }
        
        .nota-media {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .total-avaliacoes {
            color: var(--muted);
            font-size: 0.9rem;
        }
        
        .descricao {
            background: var(--card);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .acoes-leitura {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .btn {
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        .btn-secundario {
            background: var(--card);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .btn-verde {
            background: linear-gradient(90deg, #38d39f, #32b88a);
        }
        
        .btn-vermelho {
            background: linear-gradient(90deg, #e74c3c, #c0392b);
        }
        
        .status-atual {
            background: var(--panel);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--accent);
        }
        
        .leitor-pdf {
            background: var(--card);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 30px;
            position: relative;
        }
        
        .header-pdf {
            background: var(--panel);
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .pdf-container {
            height: 70vh;
            min-height: 500px;
            background: white;
            position: relative;
        }
        
        .pdf-placeholder {
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--panel);
            color: var(--muted);
            flex-direction: column;
            gap: 15px;
        }
        
        .avaliacoes-lista {
            background: var(--card);
            border-radius: 12px;
            padding: 20px;
        }
        
        .avaliacao-item {
            padding: 15px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .avaliacao-item:last-child {
            border-bottom: none;
        }
        
        .avaliacao-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .avaliacao-usuario {
            font-weight: 600;
        }
        
        .avaliacao-data {
            color: var(--muted);
            font-size: 0.8rem;
        }
        
        .voltar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .visualizador-pdf {
            width: 100%;
            height: 100%;
        }
        
        .debug-info {
            background: #ff4444;
            color: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
        }

        /* SETAS DE NAVEGAÇÃO */
        .navegacao-setas {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
            pointer-events: none;
            z-index: 1000;
            transform: translateY(-50%);
        }

        .seta-navegacao {
            background: rgba(138, 92, 246, 0.9);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            pointer-events: all;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .seta-navegacao:hover {
            background: var(--accent);
            transform: scale(1.1);
        }

        .seta-navegacao:disabled {
            background: rgba(255,255,255,0.3);
            cursor: not-allowed;
            transform: scale(1);
        }

        .contador-pagina {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            z-index: 1000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .contador-pagina.mostrar {
            opacity: 1;
        }

        /* BOTÃO ASSISTENTE IA FLUTUANTE */
        .botao-assistente-ia {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #8a5cf6, #ff6b6b);
            color: white;
            border: none;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            font-size: 1.8rem;
            cursor: pointer;
            box-shadow: 0 6px 25px rgba(138, 92, 246, 0.5);
            z-index: 9999;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .botao-assistente-ia:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 8px 30px rgba(138, 92, 246, 0.7);
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulsando {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="voltar">← Voltar para o Menu</a>
        
        <!-- DEBUG INFO -->
        <?php if($_SESSION['usuario_id'] == 1): ?>
        <div class="debug-info">
            <h4>🔍 DEBUG INFO (Admin only)</h4>
            <p><strong>Livro ID:</strong> <?php echo $livro_id; ?></p>
            <p><strong>Título:</strong> <?php echo htmlspecialchars($livro['titulo']); ?></p>
            <p><strong>PDF Encontrado:</strong> <?php echo $tem_pdf ? '✅ SIM' : '❌ NÃO'; ?></p>
            <p><strong>Caminho PDF:</strong> <?php echo $caminho_pdf; ?></p>
            <p><strong>Arquivo Existe:</strong> <?php echo file_exists($caminho_pdf) ? '✅ SIM' : '❌ NÃO'; ?></p>
        </div>
        <?php endif; ?>
        
        <div class="header">
            <img src="<?php echo htmlspecialchars($livro['capa_url']); ?>" 
                 alt="Capa do Livro" 
                 class="capa-livro"
                 onerror="this.src='https://via.placeholder.com/200x300.png?text=Capa+Não+Disponível'">
            
            <div class="info-livro">
                <h1 class="titulo-livro"><?php echo htmlspecialchars($livro['titulo']); ?></h1>
                <p class="autor-livro">por <?php echo htmlspecialchars($livro['autor']); ?></p>
                
                <div class="detalhes">
                    <div class="detalhe-item">
                        <div class="detalhe-label">📚 Gênero</div>
                        <div class="detalhe-valor"><?php echo htmlspecialchars($livro['genero'] ?? 'Não informado'); ?></div>
                    </div>
                    <div class="detalhe-item">
                        <div class="detalhe-label">🏷️ Tipo</div>
                        <div class="detalhe-valor"><?php echo $livro['tipo'] == 'TECNICO' ? 'Técnico' : 'Literário'; ?></div>
                    </div>
                    <div class="detalhe-item">
                        <div class="detalhe-label">📖 Status</div>
                        <div class="detalhe-valor"><?php echo $livro['status'] == 'DISPONIVEL' ? '✅ Disponível' : '❌ Indisponível'; ?></div>
                    </div>
                    <?php if($livro['ano_publicacao']): ?>
                    <div class="detalhe-item">
                        <div class="detalhe-label">📅 Ano</div>
                        <div class="detalhe-valor"><?php echo htmlspecialchars($livro['ano_publicacao']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="avaliacao-geral">
                    <div class="estrelas">⭐</div>
                    <div class="nota-media"><?php echo number_format($nota_media, 1); ?></div>
                    <div class="total-avaliacoes">(<?php echo $total_avaliacoes; ?> avaliações)</div>
                </div>
                
                <?php if($status_leitura): ?>
                <div class="status-atual">
                    <strong>📖 Status de Leitura:</strong> 
                    <?php 
                    $status_texto = [
                        'LENDO' => 'Você está lendo este livro',
                        'LIDO' => 'Você já leu este livro',
                        'QUERO_LER' => 'Você quer ler este livro',
                        'ABANDONADO' => 'Você abandonou este livro'
                    ];
                    echo $status_texto[$status_leitura]; 
                    ?>
                </div>
                <?php endif; ?>
                
                <div class="acoes-leitura">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="status" value="LENDO">
                        <button type="submit" name="atualizar_status" class="btn">📖 Ler Agora</button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="status" value="LIDO">
                        <button type="submit" name="atualizar_status" class="btn btn-verde">✅ Marcar como Lido</button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="status" value="QUERO_LER">
                        <button type="submit" name="atualizar_status" class="btn btn-secundario">⭐ Quero Ler</button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="status" value="ABANDONADO">
                        <button type="submit" name="atualizar_status" class="btn btn-vermelho">⏸️ Abandonar</button>
                    </form>
                </div>
                
                <?php if($tem_pdf): ?>
                <a href="#ler-pdf" class="btn">📖 Ler PDF</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if($livro['descricao']): ?>
        <div class="descricao">
            <h3 style="margin-bottom: 15px;">📝 Sinopse</h3>
            <p><?php echo nl2br(htmlspecialchars($livro['descricao'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- LEITOR DE PDF COM SETAS -->
        <div class="leitor-pdf" id="ler-pdf">
            <div class="header-pdf" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <h3 style="margin: 0;">📖 <?php echo htmlspecialchars($livro['titulo']); ?></h3>
                
                <div style="display: flex; gap: 10px; align-items: center;">
                    <form method="POST" id="formNavegacao" style="display: flex; gap: 5px; align-items: center; margin: 0;">
                        <button type="button" id="btnAnterior" class="btn btn-small" <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>>◀ Anterior</button>
                        
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <span style="color: var(--muted);">Página</span>
                            <input type="number" id="inputPagina" name="pagina_atual" value="<?php echo $pagina_atual; ?>" 
                                   min="1" 
                                   style="width: 60px; padding: 5px; background: var(--card); color: white; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px;">
                        </div>
                        
                        <button type="button" id="btnProxima" class="btn btn-small">Próxima ▶</button>
                        <button type="submit" name="salvar_pagina" class="btn btn-small btn-verde">💾 Salvar</button>
                    </form>
                    
                    <?php if($tem_pdf): ?>
                    <div style="display: flex; gap: 5px; margin-left: 10px;">
                        <a href="<?php echo $caminho_pdf; ?>" target="_blank" class="btn btn-small">📥 Abrir em Nova Aba</a>
                        <a href="<?php echo $caminho_pdf; ?>" download class="btn btn-small">💾 Download</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($tem_pdf && file_exists($caminho_pdf)): ?>
            
            <!-- CONTADOR DE PÁGINA FLUTUANTE -->
            <div class="contador-pagina" id="contadorPagina">
                📖
            </div>

            <!-- SETAS DE NAVEGAÇÃO -->
            <div class="navegacao-setas">
                <button class="seta-navegacao" id="setaEsquerda" <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>>
                    ◀
                </button>
                <button class="seta-navegacao" id="setaDireita">
                    ▶
                </button>
            </div>
            
            <div class="pdf-container">
                <!-- OBJECT TAG - SOLUÇÃO DEFINITIVA -->
                <object data="<?php echo $caminho_pdf; ?>#page=<?php echo $pagina_atual; ?>&toolbar=0&navpanes=0&scrollbar=0" 
                        type="application/pdf" 
                        width="100%" 
                        height="100%"
                        style="border: none;"
                        id="pdfObject">
                    <div style="padding: 20px; text-align: center; color: var(--muted);">
                        <p>Seu navegador não suporta visualização de PDF.</p>
                        <a href="<?php echo $caminho_pdf; ?>" class="btn btn-small" target="_blank">Abrir PDF em nova aba</a>
                    </div>
                </object>
            </div>
            
            <script>
            // VARIÁVEIS GLOBAIS
            let paginaAtual = <?php echo $pagina_atual; ?>;
            const livroId = <?php echo $livro_id; ?>;
            let timeoutContador;

            // FUNÇÃO PARA MUDAR PÁGINA VIA AJAX - CORRIGIDA
            function mudarPagina(novaPagina) {
                if (novaPagina < 1) return;
                
                paginaAtual = novaPagina;
                
                // Atualizar input
                document.getElementById('inputPagina').value = paginaAtual;
                
                // Mostrar contador brevemente
                const contador = document.getElementById('contadorPagina');
                contador.classList.add('mostrar');
                
                // Limpar timeout anterior
                if (timeoutContador) {
                    clearTimeout(timeoutContador);
                }
                
                // Esconder contador após 1 segundo
                timeoutContador = setTimeout(() => {
                    contador.classList.remove('mostrar');
                }, 1000);
                
                // SOLUÇÃO DEFINITIVA PARA O BUG DO PDF BRANCO
                const pdfObject = document.getElementById('pdfObject');
                
                // Método 1: Recriar o object completamente
                const novoObject = document.createElement('object');
                novoObject.data = '<?php echo $caminho_pdf; ?>#page=' + paginaAtual + '&toolbar=0&navpanes=0&scrollbar=0';
                novoObject.type = 'application/pdf';
                novoObject.width = '100%';
                novoObject.height = '100%';
                novoObject.style.border = 'none';
                novoObject.id = 'pdfObject';
                
                // Adicionar fallback
                const fallback = document.createElement('div');
                fallback.style.padding = '20px';
                fallback.style.textAlign = 'center';
                fallback.style.color = 'var(--muted)';
                fallback.innerHTML = `
                    <p>Seu navegador não suporta visualização de PDF.</p>
                    <a href="<?php echo $caminho_pdf; ?>" class="btn btn-small" target="_blank">Abrir PDF em nova aba</a>
                `;
                novoObject.appendChild(fallback);
                
                // Substituir o object antigo
                pdfObject.parentNode.replaceChild(novoObject, pdfObject);
                
                // Atualizar estado dos botões
                document.getElementById('setaEsquerda').disabled = paginaAtual <= 1;
                document.getElementById('btnAnterior').disabled = paginaAtual <= 1;
                
                // Salvar no servidor via AJAX
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax_mudar_pagina=true&pagina=' + paginaAtual
                });
            }

            // EVENT LISTENERS PARA NAVEGAÇÃO
            document.getElementById('setaEsquerda').addEventListener('click', function() {
                if (paginaAtual > 1) {
                    mudarPagina(paginaAtual - 1);
                }
            });

            document.getElementById('setaDireita').addEventListener('click', function() {
                mudarPagina(paginaAtual + 1);
            });

            document.getElementById('btnAnterior').addEventListener('click', function() {
                if (paginaAtual > 1) {
                    mudarPagina(paginaAtual - 1);
                }
            });

            document.getElementById('btnProxima').addEventListener('click', function() {
                mudarPagina(paginaAtual + 1);
            });

            // ENTER NO INPUT
            document.getElementById('inputPagina').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const novaPagina = parseInt(this.value);
                    if (!isNaN(novaPagina) && novaPagina > 0) {
                        mudarPagina(novaPagina);
                    }
                }
            });

            // NAVEGAÇÃO POR TECLADO - MELHORADA
            document.addEventListener('keydown', function(e) {
                // Só navega se não estiver digitando
                if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                    switch(e.key) {
                        case 'ArrowLeft':
                        case 'PageUp':
                            if (paginaAtual > 1) {
                                e.preventDefault();
                                mudarPagina(paginaAtual - 1);
                            }
                            break;
                            
                        case 'ArrowRight':
                        case 'PageDown':
                            e.preventDefault();
                            mudarPagina(paginaAtual + 1);
                            break;
                            
                        case 'Home':
                            e.preventDefault();
                            mudarPagina(1);
                            break;
                    }
                }
            });

            // TOOLTIP DAS SETAS
            document.getElementById('setaEsquerda').title = 'Página Anterior (← ou PageUp)';
            document.getElementById('setaDireita').title = 'Próxima Página (→ ou PageDown)';

            // MOSTRAR CONTADOR AO PASSAR MOUSE NAS SETAS
            document.querySelectorAll('.seta-navegacao').forEach(seta => {
                seta.addEventListener('mouseenter', function() {
                    document.getElementById('contadorPagina').classList.add('mostrar');
                });
                
                seta.addEventListener('mouseleave', function() {
                    setTimeout(() => {
                        document.getElementById('contadorPagina').classList.remove('mostrar');
                    }, 1000);
                });
            });
            </script>
            
            <?php else: ?>
            <div class="pdf-placeholder">
                <div style="font-size: 3rem;">📄</div>
                <h3>PDF Não Disponível</h3>
                <p>O arquivo PDF deste livro não foi encontrado no servidor.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- AVALIAÇÕES -->
        <div class="avaliacoes-lista">
            <h3 style="margin-bottom: 20px;">⭐ Avaliações (<?php echo $total_avaliacoes; ?>)</h3>
            
            <?php if(mysqli_num_rows($avaliacoes) > 0): ?>
                <?php while($avaliacao = mysqli_fetch_assoc($avaliacoes)): ?>
                <div class="avaliacao-item">
                    <div class="avaliacao-header">
                        <div class="avaliacao-usuario">
                            <?php echo htmlspecialchars($avaliacao['nome']); ?>
                            <span class="estrelas">⭐ <?php echo number_format($avaliacao['nota'], 1); ?></span>
                        </div>
                        <div class="avaliacao-data">
                            <?php echo date('d/m/Y', strtotime($avaliacao['created_at'])); ?>
                        </div>
                    </div>
                    <?php if($avaliacao['comentario']): ?>
                    <p><?php echo htmlspecialchars($avaliacao['comentario']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; color: var(--muted); padding: 40px;">
                    Nenhuma avaliação ainda. Seja o primeiro a avaliar!
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- BOTÃO FLUTUANTE DO ASSISTENTE IA -->
    <a href="assistente_ia.php?livro_id=<?php echo $livro_id; ?>&pagina=<?php echo $pagina_atual; ?>" 
       class="botao-assistente-ia pulsando" 
       title="Precisa de ajuda com o livro? Clique aqui!">
        🤖
    </a>

</body>
</html>