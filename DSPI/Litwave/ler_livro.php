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
        // BUSCA INTELIGENTE POR PDF - ENCONTRA O ARQUIVO MAIS PARECIDO
        $tem_pdf = false;
        $caminho_pdf = '';
        $melhor_pontuacao = 0;
        
        // Primeiro: verifica se tem PDF específico no banco
        if(!empty($livro['arquivo_pdf']) && file_exists($livro['arquivo_pdf'])) {
            $tem_pdf = true;
            $caminho_pdf = $livro['arquivo_pdf'];
        } else {
            // Busca inteligente na pasta arquivosPDF
            $titulo_livro = strtolower(trim($livro['titulo']));
            $palavras_chave = explode(' ', $titulo_livro);
            
            // Remove palavras muito comuns
            $palavras_comuns = ['o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'da', 'do', 'das', 'dos', 'em', 'no', 'na', 'nos', 'nas', 'por', 'para', 'com', 'sem', 'sob', 'sobre', 'e'];
            $palavras_chave = array_diff($palavras_chave, $palavras_comuns);
            $palavras_chave = array_values($palavras_chave);
            
            if(is_dir('arquivosPDF')) {
                $todos_pdfs = glob('arquivosPDF/*.pdf');
                
                foreach($todos_pdfs as $pdf) {
                    $nome_pdf = strtolower(pathinfo($pdf, PATHINFO_FILENAME));
                    $pontuacao = 0;
                    
                    // Verifica correspondência exata
                    if($nome_pdf === $titulo_livro) {
                        $pontuacao = 100;
                    }
                    // Verifica se o título está contido no nome do PDF
                    else if(strpos($nome_pdf, $titulo_livro) !== false) {
                        $pontuacao = 90;
                    }
                    // Verifica palavras-chave
                    else {
                        foreach($palavras_chave as $palavra) {
                            if(strlen($palavra) > 2) { // Só considera palavras com mais de 2 letras
                                if(strpos($nome_pdf, $palavra) !== false) {
                                    $pontuacao += 20;
                                }
                            }
                        }
                    }
                    
                    // Verifica correspondência por ID
                    if(strpos($nome_pdf, (string)$livro_id) !== false) {
                        $pontuacao += 50;
                    }
                    
                    // Atualiza o melhor PDF encontrado
                    if($pontuacao > $melhor_pontuacao) {
                        $melhor_pontuacao = $pontuacao;
                        $caminho_pdf = $pdf;
                        $tem_pdf = true;
                    }
                }
                
                // Se encontrou um PDF com boa pontuação, usa ele
                if($tem_pdf && $melhor_pontuacao >= 10) {
                    // Atualiza o banco para futuras consultas
                    $sql_update = "UPDATE livros SET arquivo_pdf = ? WHERE id = ?";
                    $stmt_update = mysqli_prepare($conexao, $sql_update);
                    mysqli_stmt_bind_param($stmt_update, "si", $caminho_pdf, $livro_id);
                    mysqli_stmt_execute($stmt_update);
                } else if($tem_pdf) {
                    // Pontuação muito baixa, provavelmente não é o PDF correto
                    $tem_pdf = false;
                    $caminho_pdf = '';
                }
            }
        }
        
        // DEBUG - Mostrar informações da busca
        echo "<!-- DEBUG: Título: " . $livro['titulo'] . " -->";
        echo "<!-- DEBUG: PDF Encontrado: " . ($tem_pdf ? 'SIM' : 'NÃO') . " -->";
        echo "<!-- DEBUG: Caminho: " . $caminho_pdf . " -->";
        echo "<!-- DEBUG: Pontuação: " . $melhor_pontuacao . " -->";
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

// SISTEMA DE AVALIAÇÕES
if(isset($_POST['avaliar_livro'])) {
    $nota = floatval($_POST['nota']);
    $comentario = mysqli_real_escape_string($conexao, $_POST['comentario'] ?? '');
    
    // Verificar se já avaliou
    $sql_check = "SELECT id FROM avaliacoes WHERE usuario_id = ? AND livro_id = ?";
    $stmt_check = mysqli_prepare($conexao, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "ii", $_SESSION['usuario_id'], $livro_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    
    if(mysqli_num_rows($result_check) > 0) {
        // Atualizar avaliação existente
        $sql_avaliacao = "UPDATE avaliacoes SET nota = ?, comentario = ? WHERE usuario_id = ? AND livro_id = ?";
        $stmt_avaliacao = mysqli_prepare($conexao, $sql_avaliacao);
        mysqli_stmt_bind_param($stmt_avaliacao, "dsii", $nota, $comentario, $_SESSION['usuario_id'], $livro_id);
    } else {
        // Nova avaliação
        $sql_avaliacao = "INSERT INTO avaliacoes (usuario_id, livro_id, nota, comentario) VALUES (?, ?, ?, ?)";
        $stmt_avaliacao = mysqli_prepare($conexao, $sql_avaliacao);
        mysqli_stmt_bind_param($stmt_avaliacao, "iids", $_SESSION['usuario_id'], $livro_id, $nota, $comentario);
    }
    
    mysqli_stmt_execute($stmt_avaliacao);
    
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

// Verificar se usuário já avaliou
$minha_avaliacao = null;
$sql_minha_avaliacao = "SELECT nota, comentario FROM avaliacoes WHERE usuario_id = ? AND livro_id = ?";
$stmt_minha_avaliacao = mysqli_prepare($conexao, $sql_minha_avaliacao);
mysqli_stmt_bind_param($stmt_minha_avaliacao, "ii", $_SESSION['usuario_id'], $livro_id);
mysqli_stmt_execute($stmt_minha_avaliacao);
$result_minha_avaliacao = mysqli_stmt_get_result($stmt_minha_avaliacao);
if($row = mysqli_fetch_assoc($result_minha_avaliacao)) {
    $minha_avaliacao = $row;
}
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
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Inter", sans-serif;
        }
        
        body {
            background: linear-gradient(180deg, var(--bg), #050507);
            color: var(--text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* HEADER MODERNO */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: var(--card);
            border-bottom: 1px solid rgba(0,0,0,0.06);
            margin-bottom: 20px;
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

        /* CONTEÚDO PRINCIPAL */
        .header-content {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
            align-items: flex-start;
        }
        
        .capa-livro {
            width: 240px;
            height: 360px;
            object-fit: cover;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.4);
        }
        
        .info-livro {
            flex: 1;
        }
        
        .titulo-livro {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--text), var(--muted));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .autor-livro {
            font-size: 1.3rem;
            color: var(--purple-light);
            margin-bottom: 25px;
            font-weight: 600;
        }
        
        .detalhes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .detalhe-item {
            background: linear-gradient(135deg, var(--glass), rgba(0,0,0,0.1));
            padding: 18px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
        }
        
        .detalhe-label {
            font-size: 0.8rem;
            color: var(--muted);
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .detalhe-valor {
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .avaliacao-geral {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px;
            background: var(--card);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .estrelas {
            color: #ffd700;
            font-size: 1.5rem;
        }
        
        .estrelas-avaliacao {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
        }

        .estrela-input {
            display: none;
        }

        .estrela-label {
            font-size: 2rem;
            color: #444;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .estrela-label:hover,
        .estrela-label:hover ~ .estrela-label {
            color: #ffd700;
        }

        .estrela-input:checked ~ .estrela-label {
            color: #ffd700;
        }

        .estrelas-avaliacao:hover .estrela-label {
            color: #ffd700;
        }

        .estrela-input:checked + .estrela-label ~ .estrela-label {
            color: #444;
        }

        .estrelas-avaliacao .estrela-label:hover,
        .estrelas-avaliacao .estrela-label:hover ~ .estrela-label {
            color: #ffd700;
        }

        .nota-media {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .total-avaliacoes {
            color: var(--muted);
            font-size: 1rem;
        }
        
        .descricao {
            background: var(--card);
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 25px;
            line-height: 1.7;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .acoes-leitura {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .btn {
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(138,92,246,0.3);
        }
        
        .btn-small {
            padding: 10px 18px;
            font-size: 0.85rem;
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
            background: linear-gradient(135deg, rgba(138,92,246,0.1), rgba(207,163,255,0.05));
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid rgba(138,92,246,0.2);
            border-left: 4px solid var(--accent);
        }
        
        /* LEITOR PDF - VERSÃO CORRIGIDA */
        .leitor-pdf {
            background: var(--card);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 40px;
            position: relative;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .header-pdf {
            background: var(--panel);
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pdf-container {
            height: 80vh;
            min-height: 600px;
            background: white;
            position: relative;
            width: 100%;
        }

        .pdf-container iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
        
        .pdf-placeholder {
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--panel);
            color: var(--muted);
            flex-direction: column;
            gap: 20px;
            padding: 40px;
        }
        
        /* AVALIAÇÕES */
        .avaliacoes-lista {
            background: var(--card);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .avaliacao-item {
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .avaliacao-item:last-child {
            border-bottom: none;
        }
        
        .avaliacao-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .avaliacao-usuario {
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .avaliacao-data {
            color: var(--muted);
            font-size: 0.85rem;
        }
        
        .voltar {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 25px;
            padding: 10px 16px;
            border-radius: 8px;
            background: rgba(138,92,246,0.1);
            transition: all 0.2s ease;
        }
        
        .voltar:hover {
            background: rgba(138,92,246,0.2);
            transform: translateX(-5px);
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
            padding: 0 25px;
            pointer-events: none;
            z-index: 1000;
            transform: translateY(-50%);
        }

        .seta-navegacao {
            background: rgba(138, 92, 246, 0.95);
            color: white;
            border: none;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            cursor: pointer;
            pointer-events: all;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
            backdrop-filter: blur(10px);
        }

        .seta-navegacao:hover {
            background: var(--accent);
            transform: scale(1.15);
            box-shadow: 0 8px 25px rgba(138,92,246,0.5);
        }

        .seta-navegacao:disabled {
            background: rgba(255,255,255,0.3);
            cursor: not-allowed;
            transform: scale(1);
        }

        .contador-pagina {
            position: absolute;
            top: 25px;
            right: 25px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            z-index: 1000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
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
            width: 80px;
            height: 80px;
            border-radius: 50%;
            font-size: 2rem;
            cursor: pointer;
            box-shadow: 0 8px 30px rgba(138, 92, 246, 0.6);
            z-index: 9999;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .botao-assistente-ia:hover {
            transform: scale(1.15) rotate(5deg);
            box-shadow: 0 12px 35px rgba(138, 92, 246, 0.8);
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulsando {
            animation: pulse 2s infinite;
        }

        /* FORMULÁRIO DE AVALIAÇÃO */
        .form-avaliacao {
            background: var(--card);
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        textarea {
            width: 100%;
            background: var(--glass);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text);
            padding: 15px;
            border-radius: 10px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 15px;
        }

        textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        /* RATING STARS */
        .rating-stars {
            display: flex;
            gap: 2px;
            margin: 5px 0;
        }

        .star {
            color: #ffd700;
            font-size: 1.1rem;
        }

        .star.empty {
            color: #444;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .capa-livro {
                width: 200px;
                height: 300px;
                margin: 0 auto;
            }
            
            .acoes-leitura {
                justify-content: center;
            }
            
            .header-pdf {
                flex-direction: column;
                text-align: center;
            }
            
            .nav-menu {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER MODERNO -->
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
                <div class="small muted">Rede SENAI pela literária & leitura</div>
            </div>
        </div>

        <nav class="nav-menu">
            <a href="index.php">Biblioteca</a>
            <a href="minha_estante.php">Minha Estante</a>
            <a href="chat.php">Chats</a>
            <a href="amigos.php">Amigos</a>
            <a href="perfil.php">Perfil</a>
        </nav>

        <div class="controls">
            <button class="btn btn-small" onclick="window.history.back()">← Voltar</button>
        </div>
    </header>

    <div class="container">
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
        
        <div class="header-content">
            <img src="<?php echo htmlspecialchars($livro['capa_url']); ?>" 
                 alt="Capa do Livro" 
                 class="capa-livro"
                 onerror="this.src='https://via.placeholder.com/240x360.png?text=Capa+Não+Disponível'">
            
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
                        'LENDO' => '📖 Você está lendo este livro',
                        'LIDO' => '✅ Você já leu este livro', 
                        'QUERO_LER' => '⭐ Você quer ler este livro',
                        'ABANDONADO' => '⏸️ Você abandonou este livro'
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
                </div>
            </div>
        </div>
        
        <?php if($livro['descricao']): ?>
        <div class="descricao">
            <h3 style="margin-bottom: 15px; color: var(--purple-light);">📝 Sinopse</h3>
            <p><?php echo nl2br(htmlspecialchars($livro['descricao'])); ?></p>
        </div>
        <?php endif; ?>

        <!-- FORMULÁRIO DE AVALIAÇÃO -->
        <div class="form-avaliacao">
            <h3 style="margin-bottom: 20px; color: var(--purple-light);">
                <?php echo $minha_avaliacao ? '✏️ Editar sua Avaliação' : '⭐ Avaliar este Livro'; ?>
            </h3>
            
            <form method="POST">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600;">Nota:</label>
                    <div style="display: flex; gap: 5px;">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                        <label style="font-size: 2rem; cursor: pointer; color: <?php echo ($minha_avaliacao && $minha_avaliacao['nota'] >= $i) ? '#ffd700' : '#444'; ?>;">
                            <input type="radio" name="nota" value="<?php echo $i; ?>" 
                                   style="display: none;"
                                   <?php echo ($minha_avaliacao && $minha_avaliacao['nota'] == $i) ? 'checked' : ''; ?>>
                            ★
                        </label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <textarea name="comentario" placeholder="Deixe um comentário (opcional)..."><?php echo $minha_avaliacao['comentario'] ?? ''; ?></textarea>
                
                <button type="submit" name="avaliar_livro" class="btn">
                    <?php echo $minha_avaliacao ? '📝 Atualizar Avaliação' : '⭐ Publicar Avaliação'; ?>
                </button>
            </form>
        </div>
        
        <!-- LEITOR DE PDF COM SETAS - VERSÃO CORRIGIDA -->
        <div class="leitor-pdf" id="ler-pdf">
            <div class="header-pdf">
                <h3 style="margin: 0; color: var(--purple-light);"><?php echo htmlspecialchars($livro['titulo']); ?></h3>
                
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <form method="POST" id="formNavegacao" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                        <button type="button" id="btnAnterior" class="btn btn-small" <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>>◀ Anterior</button>
                        
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span style="color: var(--muted);">Página</span>
                            <input type="number" id="inputPagina" name="pagina_atual" value="<?php echo $pagina_atual; ?>" 
                                   min="1" 
                                   style="width: 70px; padding: 8px; background: var(--glass); color: white; border: 1px solid rgba(255,255,255,0.2); border-radius: 6px;">
                        </div>
                        
                        <button type="button" id="btnProxima" class="btn btn-small">Próxima ▶</button>
                        <button type="submit" name="salvar_pagina" class="btn btn-small btn-verde">💾 Salvar</button>
                    </form>
                    
                    <?php if($tem_pdf): ?>
                    <div style="display: flex; gap: 8px; margin-left: 10px;">
                        <a href="<?php echo $caminho_pdf; ?>" download class="btn btn-small">💾 Download</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($tem_pdf && file_exists($caminho_pdf)): ?>
            
            <!-- CONTADOR DE PÁGINA FLUTUANTE -->
            <div class="contador-pagina" id="contadorPagina">
                📖 Página <?php echo $pagina_atual; ?>
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
                <!-- SOLUÇÃO: Voltamos ao object mas com método de atualização correto -->
                <object data="<?php echo $caminho_pdf; ?>#page=<?php echo $pagina_atual; ?>&toolbar=0&navpanes=0&scrollbar=0&view=FitH" 
                        type="application/pdf" 
                        width="100%" 
                        height="100%"
                        style="border: none;"
                        id="pdfObject">
                    <div style="padding: 40px; text-align: center; color: var(--muted);">
                        <p style="font-size: 1.2rem; margin-bottom: 20px;">Seu navegador não suporta visualização de PDF.</p>
                        <a href="<?php echo $caminho_pdf; ?>" class="btn" target="_blank">Abrir PDF em nova aba</a>
                    </div>
                </object>
            </div>
            
            <script>
            // VARIÁVEIS GLOBAIS
            let paginaAtual = <?php echo $pagina_atual; ?>;
            const livroId = <?php echo $livro_id; ?>;
            let timeoutContador;

            // FUNÇÃO PARA MUDAR PÁGINA - VERSÃO DEFINITIVA
            function mudarPagina(novaPagina) {
                if (novaPagina < 1) return;
                
                paginaAtual = novaPagina;
                
                // Atualizar input e contador
                document.getElementById('inputPagina').value = paginaAtual;
                document.getElementById('contadorPagina').textContent = '📖 Página ' + paginaAtual;
                
                const contador = document.getElementById('contadorPagina');
                contador.classList.add('mostrar');
                
                if (timeoutContador) clearTimeout(timeoutContador);
                timeoutContador = setTimeout(() => {
                    contador.classList.remove('mostrar');
                }, 1500);
                
                // MÉTODO DEFINITIVO: Forçar recarregamento completo do object
                const pdfContainer = document.querySelector('.pdf-container');
                const novoObject = document.createElement('object');
                
                // URL com timestamp para evitar cache
                const timestamp = new Date().getTime();
                const pdfUrl = '<?php echo $caminho_pdf; ?>?t=' + timestamp + '#page=' + paginaAtual + '&toolbar=0&navpanes=0&scrollbar=0&view=FitH';
                
                novoObject.data = pdfUrl;
                novoObject.type = 'application/pdf';
                novoObject.width = '100%';
                novoObject.height = '100%';
                novoObject.style.border = 'none';
                novoObject.id = 'pdfObject';
                
                // Fallback
                const fallback = document.createElement('div');
                fallback.style.padding = '40px';
                fallback.style.textAlign = 'center';
                fallback.style.color = 'var(--muted)';
                fallback.innerHTML = `
                    <p style="font-size: 1.2rem; margin-bottom: 20px;">Seu navegador não suporta visualização de PDF.</p>
                    <a href="<?php echo $caminho_pdf; ?>" class="btn" target="_blank">Abrir PDF em nova aba</a>
                `;
                novoObject.appendChild(fallback);
                
                // Substituir completamente o object antigo
                const oldObject = document.getElementById('pdfObject');
                if (oldObject) {
                    pdfContainer.removeChild(oldObject);
                }
                pdfContainer.appendChild(novoObject);
                
                // Atualizar estado dos botões
                document.getElementById('setaEsquerda').disabled = paginaAtual <= 1;
                document.getElementById('btnAnterior').disabled = paginaAtual <= 1;
                
                // Salvar no servidor
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax_mudar_pagina=true&pagina=' + paginaAtual
                }).catch(error => {
                    console.log('Erro ao salvar página:', error);
                });
            }

            // EVENT LISTENERS SIMPLIFICADOS
            document.getElementById('setaEsquerda').addEventListener('click', function() {
                if (paginaAtual > 1) mudarPagina(paginaAtual - 1);
            });
            
            document.getElementById('setaDireita').addEventListener('click', function() {
                mudarPagina(paginaAtual + 1);
            });
            
            document.getElementById('btnAnterior').addEventListener('click', function() {
                if (paginaAtual > 1) mudarPagina(paginaAtual - 1);
            });
            
            document.getElementById('btnProxima').addEventListener('click', function() {
                mudarPagina(paginaAtual + 1);
            });

            document.getElementById('inputPagina').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const novaPagina = parseInt(this.value);
                    if (!isNaN(novaPagina) && novaPagina > 0) {
                        mudarPagina(novaPagina);
                    }
                }
            });

            document.getElementById('inputPagina').addEventListener('blur', function() {
                const novaPagina = parseInt(this.value);
                if (!isNaN(novaPagina) && novaPagina > 0 && novaPagina !== paginaAtual) {
                    mudarPagina(novaPagina);
                }
            });

            // NAVEGAÇÃO POR TECLADO
            document.addEventListener('keydown', function(e) {
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
                        case ' ':
                            e.preventDefault();
                            mudarPagina(paginaAtual + 1);
                            break;
                        case 'Home':
                            e.preventDefault();
                            mudarPagina(1);
                            break;
                        case 'End':
                            e.preventDefault();
                            mudarPagina(100);
                            break;
                    }
                }
            });

            // Tooltips
            document.getElementById('setaEsquerda').title = 'Página Anterior (←, PageUp ou Espaço)';
            document.getElementById('setaDireita').title = 'Próxima Página (→, PageDown ou Espaço)';
            </script>
            
            <?php else: ?>
            <div class="pdf-placeholder">
                <div style="font-size: 4rem;">📄</div>
                <h3>PDF Não Disponível</h3>
                <p style="text-align: center; max-width: 400px;">
                    O arquivo PDF deste livro não foi encontrado no servidor.
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- AVALIAÇÕES -->
        <div class="avaliacoes-lista">
            <h3 style="margin-bottom: 25px; color: var(--purple-light);">
                ⭐ Avaliações (<?php echo $total_avaliacoes; ?>)
            </h3>
            
            <?php if(mysqli_num_rows($avaliacoes) > 0): ?>
                <?php while($avaliacao = mysqli_fetch_assoc($avaliacoes)): ?>
                <div class="avaliacao-item">
                    <div class="avaliacao-header">
                        <div class="avaliacao-usuario">
                            <?php echo htmlspecialchars($avaliacao['nome']); ?>
                            <div class="rating-stars">
                                <?php 
                                $nota = $avaliacao['nota'];
                                for($i = 1; $i <= 5; $i++): 
                                    $class = $i <= $nota ? 'star' : 'star empty';
                                ?>
                                    <span class="<?php echo $class; ?>">★</span>
                                <?php endfor; ?>
                                <span style="margin-left: 8px; color: #ffd700; font-weight: 600;">
                                    <?php echo number_format($nota, 1); ?>
                                </span>
                            </div>
                        </div>
                        <div class="avaliacao-data">
                            <?php echo date('d/m/Y \à\s H:i', strtotime($avaliacao['created_at'])); ?>
                        </div>
                    </div>
                    <?php if($avaliacao['comentario']): ?>
                    <p style="line-height: 1.6; margin-top: 10px;"><?php echo htmlspecialchars($avaliacao['comentario']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; color: var(--muted); padding: 60px 20px;">
                    <div style="font-size: 4rem; margin-bottom: 20px;">⭐</div>
                    <h4 style="margin-bottom: 10px;">Nenhuma avaliação ainda</h4>
                    <p>Seja o primeiro a compartilhar sua opinião sobre este livro!</p>
                </div>
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