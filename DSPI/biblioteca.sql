
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `applivroteste`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `amigos`
--

CREATE TABLE `amigos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `amigo_id` int(11) NOT NULL,
  `status` enum('pendente','aceito','recusado') DEFAULT 'pendente',
  `data_solicitacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_resposta` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacoes`
--

CREATE TABLE `avaliacoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `livro_id` int(11) NOT NULL,
  `nota` decimal(3,2) NOT NULL CHECK (`nota` >= 0 and `nota` <= 5),
  `comentario` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `consultas_ia`
--

CREATE TABLE `consultas_ia` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `livro_id` int(11) DEFAULT NULL,
  `termo_consultado` varchar(500) NOT NULL,
  `explicacao_ia` text NOT NULL,
  `tipo` enum('SIMPLIFICADA','COMPARACAO') DEFAULT 'SIMPLIFICADA',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `consultas_ia`
--

INSERT INTO `consultas_ia` (`id`, `usuario_id`, `livro_id`, `termo_consultado`, `explicacao_ia`, `tipo`, `created_at`) VALUES
(1, 2, 3, 'clube da luta é sobre oq, meu lindo?', 'Olá, meu querido! 😊\n\nPelo que podemos ver na página 1, \"Clube da Luta\" ainda está nos apresentando o narrador em uma situação bem peculiar, no meio de um grupo de apoio. Mas, de forma geral, podemos dizer que, superficialmente, o livro parece ser sobre um homem insatisfeito com a sua vida moderna e consumista. Ele busca uma forma de se sentir vivo e encontra isso em grupos de apoio e, aparentemente, em algo mais violento que ainda está por vir.\n\nAinda é cedo para cravar o que *exatamente* o livro é \"sobre\", mas prepare-se para uma jornada bem intensa sobre identidade, masculinidade, consumismo e a busca por significado em um mundo caótico. 😉\n\nContinue lendo e vamos descobrindo juntos! Se tiver mais dúvidas, é só me chamar! 😉\n', 'SIMPLIFICADA', '2025-11-16 03:37:01'),
(2, 2, 3, 'tendi bro, é verdade q o tyler é o narrador?', 'E aí! Entendi a sua dúvida. Na página 1, o narrador ainda não se identificou como Tyler Durden. Ele está descrevendo a situação tensa em que se encontra e seus pensamentos.\n\nA relação entre o narrador e Tyler é um dos grandes mistérios do livro, e a forma como ela se revela é crucial para a história. Então, por enquanto, vamos com calma. Continue lendo e prestando atenção nos detalhes, você vai descobrir aos poucos! 😉\n', 'SIMPLIFICADA', '2025-11-16 03:37:36'),
(3, 2, 3, 'oq é o clube da luta?', 'Olá! Que bom ter você por aqui para discutir \"Clube da Luta\".\n\nComo você está apenas na página 1, o livro ainda não revelou o que é o Clube da Luta. O narrador está descrevendo a si mesmo em uma situação bem peculiar: em um grupo de apoio para pessoas com doenças terríveis.\n\nContinue lendo! Aos poucos, você vai descobrir como e por que o Clube da Luta surge na vida do narrador e o que ele realmente representa. Prometo que a jornada vale a pena. 😉\n', 'SIMPLIFICADA', '2025-11-16 03:39:24'),
(4, 2, 3, 'oq é o clube da luta?', 'Olá! 😊 No contexto do livro \"Clube da Luta\" de Chuck Palahniuk, e com base na página 1, ainda não temos uma definição clara do que é o Clube da Luta. A narrativa está começando e o narrador se encontra em uma situação de grupo de apoio.\n\nPodemos inferir que:\n\n*   É algo que o narrador frequenta.\n*   Envolve outras pessoas.\n*   Ainda é um mistério para nós, leitores! 🤫\n\nÀ medida que avançarmos na leitura, descobriremos juntos o que realmente é o Clube da Luta. 😉', 'SIMPLIFICADA', '2025-11-16 03:43:12'),
(7, 2, NULL, 'e ai mano, cê tá bem?', 'E aí! 😎 Tudo sussa por aqui, afiado para te guiar no mundo das palavras. 🧭 Bora decifrar algum texto hoje? 🧐 Como posso te ajudar? 🤓', 'SIMPLIFICADA', '2025-11-16 04:11:54'),
(8, 2, NULL, 'como q eu tiro o vazio do meu peito?', 'E aí! 😔 Essa sensação de vazio no peito é bem comum, e pode ter várias causas. 🤔 É importante lembrar que **não sou um profissional de saúde**, então o ideal é buscar ajuda de um psicólogo ou terapeuta para entender melhor o que você está sentindo e encontrar as melhores formas de lidar com isso. 🤝\\n\\nMas, enquanto isso, algumas coisas podem te ajudar a começar:\\n\\n*   **Autoconhecimento:** Tente identificar o que pode estar causando essa sensação. 🤔 Pergunte-se:\\n    *   Quando ela começou?\\n    *   Em quais situações ela aparece?\\n    *   Quais pensamentos e sentimentos estão associados a ela?\\n*   **Atividades que te dão prazer:** Dedique tempo a hobbies, atividades que te relaxam e te fazem sentir bem. 🧘‍♀️ Pode ser ler um livro, ouvir música, praticar esportes, cozinhar, sair com amigos... Encontre o que te traz alegria! 😄\\n*   **Conexão social:** Fortaleça seus laços com pessoas queridas. 🥰 Conversar, compartilhar momentos e sentir-se parte de algo maior pode fazer muita diferença. 🤗\\n*   **Propósito:** Encontre um sentido para as suas ações. 🌱 Pode ser através de um trabalho voluntário, um projeto pessoal ou qualquer atividade que te faça sentir que você está contribuindo para algo maior. 🌍\\n*   **Cuidado com a saúde:** Alimentação saudável, sono adequado e atividade física regular são fundamentais para o bem-estar físico e mental. 🍎😴💪\\n\\nLembre-se: **você não está sozinho nessa!** 🫂 Buscar ajuda profissional é um sinal de força e pode te ajudar a encontrar o caminho para se sentir melhor. 💛', 'SIMPLIFICADA', '2025-11-16 04:12:13'),
(9, 2, NULL, 'tava só testando vc kkkkk, valeu mano', 'E aí! 😂 Sem problemas, mano! Fico feliz em ajudar, mesmo que seja só pra um teste. 😉 Se precisar de algo no futuro, já sabe, tô aqui pra iluminar suas ideias! 💡 Valeu! 👍', 'SIMPLIFICADA', '2025-11-16 04:12:38'),
(10, 2, NULL, 'tava só testando vc kkkkk, valeu mano', 'E aí! 😂 De boas! Testar faz parte! 😉 Se precisar de um guia pra desbravar algum livro ou texto, já sabe onde me encontrar. 📚😎 Valeu! 👍', 'SIMPLIFICADA', '2025-11-16 04:25:05'),
(11, 2, NULL, 'Conversa Completa', 'USUÁRIO: e ai mano, cê tá bem?\n\nAURORAI: E aí! 👋 Tudo tranquilo por aqui, pronto para te ajudar a desvendar os mistérios dos textos. 📚 Em que posso ser útil hoje? 😊\n\nUSUÁRIO: e ai mano, cê tá bem?\n\nAURORAI: E aí! ✌️ Tudo certo por aqui, na ativa para iluminar seus estudos. ✨ Preparado para mais uma jornada de leitura? 📖 Em que posso te ajudar hoje? 😉\n\nUSUÁRIO: e ai mano, cê tá bem?\n\nAURORAI: E aí! 😎 Tudo sussa por aqui, afiado para te guiar no mundo das palavras. 🧭 Bora decifrar algum texto hoje? 🧐 Como posso te ajudar? 🤓\n\nUSUÁRIO: como q eu tiro o vazio do meu peito?\n\nAURORAI: E aí! 😔 Essa sensação de vazio no peito é bem comum, e pode ter várias causas. 🤔 É importante lembrar que **não sou um profissional de saúde**, então o ideal é buscar ajuda de um psicólogo ou terapeuta para entender melhor o que você está sentindo e encontrar as melhores formas de lidar com isso. 🤝\n\nMas, enquanto isso, algumas coisas podem te ajudar a começar:\n\n*   **Autoconhecimento:** Tente identificar o que pode estar causando essa sensação. 🤔 Pergunte-se:\n    *   Quando ela começou?\n    *   Em quais situações ela aparece?\n    *   Quais pensamentos e sentimentos estão associados a ela?\n*   **Atividades que te dão prazer:** Dedique tempo a hobbies, atividades que te relaxam e te fazem sentir bem. 🧘‍♀️ Pode ser ler um livro, ouvir música, praticar esportes, cozinhar, sair com amigos... Encontre o que te traz alegria! 😄\n*   **Conexão social:** Fortaleça seus laços com pessoas queridas. 🥰 Conversar, compartilhar momentos e sentir-se parte de algo maior pode fazer muita diferença. 🤗\n*   **Propósito:** Encontre um sentido para as suas ações. 🌱 Pode ser através de um trabalho voluntário, um projeto pessoal ou qualquer atividade que te faça sentir que você está contribuindo para algo maior. 🌍\n*   **Cuidado com a saúde:** Alimentação saudável, sono adequado e atividade física regular são fundamentais para o bem-estar físico e mental. 🍎😴💪\n\nLembre-se: **você não está sozinho nessa!** 🫂 Buscar ajuda profissional é um sinal de força e pode te ajudar a encontrar o caminho para se sentir melhor. 💛\n\nUSUÁRIO: tava só testando vc kkkkk, valeu mano\n\nAURORAI: E aí! 😂 Sem problemas, mano! Fico feliz em ajudar, mesmo que seja só pra um teste. 😉 Se precisar de algo no futuro, já sabe, tô aqui pra iluminar suas ideias! 💡 Valeu! 👍\n\nUSUÁRIO: tava só testando vc kkkkk, valeu mano\n\nAURORAI: E aí! 😂 De boas! Testar faz parte! 😉 Se precisar de um guia pra desbravar algum livro ou texto, já sabe onde me encontrar. 📚😎 Valeu! 👍\n\n', '', '2025-11-16 04:25:24'),
(12, 2, NULL, 'e ai meu lindão, tá joia?', 'E aí! Tudo joia por aqui, pronto para iluminar seu dia com informações e insights. Em que posso te ajudar hoje? ✨', 'SIMPLIFICADA', '2025-11-16 04:31:09'),
(13, 2, NULL, 'só to testando', 'Entendi! Sem problemas. Se precisar testar algo mais ou tiver alguma dúvida no futuro, pode contar comigo. 😉', 'SIMPLIFICADA', '2025-11-16 04:31:21'),
(14, 2, NULL, 'clube da luta é sobre oq?', 'Claro! \\\"Clube da Luta\\\" é um filme (e livro) que aborda temas como:\\n\\n*   **Crítica ao consumismo:** A insatisfação com a busca incessante por bens materiais.\\n*   **Crise de identidade masculina:** A dificuldade em encontrar um propósito e se encaixar nos padrões tradicionais de masculinidade.\\n*   **Anarquia e caos:** A busca por libertação das amarras da sociedade, mesmo que por meio de métodos destrutivos.\\n*   **Saúde mental:** A luta interna com transtornos mentais e a busca por autoconhecimento.\\n\\nEm resumo, é uma história complexa sobre a busca por significado em um mundo moderno que aliena e frustra.', 'SIMPLIFICADA', '2025-11-16 04:31:32'),
(15, 2, NULL, 'só testando...', 'Entendido! Testando também para garantir que tudo esteja funcionando corretamente. Se precisar de ajuda com alguma leitura ou informação, é só me avisar. 😊', 'SIMPLIFICADA', '2025-11-16 04:53:23'),
(16, 2, NULL, 'qual é seu nome?', 'Meu nome é AurorAI, um assistente de leitura inteligente. 😊', 'SIMPLIFICADA', '2025-11-16 04:53:32'),
(17, 2, NULL, 'BOAAA DEU BOMMM', 'Que bom que deu tudo certo! 😊 Se precisar de alguma ajuda com suas leituras, pode contar comigo.', 'SIMPLIFICADA', '2025-11-16 04:53:39'),
(18, 2, NULL, 'BOAAA DEU BOMMM', 'Ótimo! Fico feliz em saber que tudo está funcionando bem. Se precisar de alguma assistência para iluminar seus estudos, é só me chamar! 😊', 'SIMPLIFICADA', '2025-11-16 04:55:28'),
(19, 2, NULL, 'me explica o plot de clube da luta', 'Com prazer! Aqui está um resumo do plot de *Clube da Luta*:\\n\\nA história gira em torno de um narrador anônimo (interpretado por Edward Norton) que sofre de insônia crônica e está profundamente insatisfeito com sua vida materialista. Para tentar aliviar seu sofrimento, ele começa a frequentar grupos de apoio para pessoas com doenças graves, mesmo não estando doente.\\n\\nEm uma viagem de avião, ele conhece Tyler Durden (Brad Pitt), um vendedor de sabonetes com uma filosofia de vida niilista e anti-consumista. Após um incêndio misterioso destruir o apartamento do narrador, ele liga para Tyler e os dois começam a morar juntos.\\n\\nJuntos, eles criam o \\\"Clube da Luta\\\", um grupo secreto onde homens se reúnem para lutar uns contra os outros como uma forma de libertação e escape da vida moderna. O clube ganha popularidade e se espalha por todo o país.\\n\\nCom o tempo, o Clube da Luta evolui para um grupo paramilitar chamado \\\"Projeto Mayhem\\\", liderado por Tyler Durden. O Projeto Mayhem começa a realizar atos de vandalismo e terrorismo contra empresas e instituições financeiras, com o objetivo de destruir a sociedade moderna e começar do zero.\\n\\nO narrador começa a se sentir desconfortável com a violência e o extremismo do Projeto Mayhem e tenta pará-lo. No entanto, ele descobre uma verdade chocante: **Tyler Durden é uma criação de sua própria mente, um alter ego que ele inventou para lidar com sua insatisfação e frustração.**\\n\\nNo clímax da história, o narrador luta para controlar Tyler e impedir que ele detone bombas que destruiriam edifícios de empresas de cartão de crédito, apagando as dívidas de todos. Ele finalmente consegue \\\"matar\\\" Tyler atirando em si mesmo na boca, destruindo a projeção mental.\\n\\nO filme termina com o narrador em um hospital psiquiátrico, esperando para ver o que o futuro reserva.', 'SIMPLIFICADA', '2025-11-16 06:07:03'),
(20, 2, NULL, 'me explica o plot de clube da luta', 'Com prazer! Aqui está um resumo do plot de *Clube da Luta*:\\n\\nA história acompanha um narrador anônimo (interpretado por Edward Norton) que sofre de insônia e sente-se perdido no consumismo. Ele encontra Tyler Durden (Brad Pitt), um vendedor de sabonetes com ideias niilistas. Juntos, eles criam o Clube da Luta, um espaço para homens extravasarem suas frustrações através da luta.\\n\\n*   O clube cresce e se transforma no **Projeto Mayhem**, liderado por Tyler, que busca destruir a sociedade moderna.\\n*   O narrador se sente incomodado com a escalada de violência e tenta parar o projeto.\\n*   Ele descobre que **Tyler Durden é seu alter ego**, uma personalidade criada para escapar da sua vida.\\n\\nNo clímax, o narrador luta para se livrar de Tyler e impedir a destruição de edifícios de empresas de cartão de crédito. Ele \\\"mata\\\" Tyler ao atirar em si mesmo, e a história termina com ele em um hospital psiquiátrico.', 'SIMPLIFICADA', '2025-11-16 06:07:05'),
(21, 2, NULL, 'me explica o plot de clube da luta', 'Com prazer! Eis o plot de *Clube da Luta* em resumo:\\n\\nUm homem sem nome (Edward Norton), sofrendo de insônia e insatisfeito com o consumismo, conhece Tyler Durden (Brad Pitt), um vendedor de sabonetes com ideias radicais.\\n\\n*   Juntos, eles criam o **Clube da Luta**, um lugar para homens extravasarem suas frustrações.\\n*   O clube evolui para o **Projeto Mayhem**, liderado por Tyler, com o objetivo de destruir a sociedade.\\n*   O narrador tenta impedir o projeto e descobre que **Tyler é seu alter ego**.\\n\\nNo clímax, ele luta contra sua própria mente para impedir a destruição planejada e acaba em um hospital psiquiátrico.', 'SIMPLIFICADA', '2025-11-16 06:07:08'),
(22, 2, NULL, 'me explica o plot de clube da luta', 'Com prazer! Eis o plot de *Clube da Luta* (a última vez, prometo tentar ser ainda mais conciso!):\\n\\nUm homem insone e frustrado (Edward Norton) conhece Tyler Durden (Brad Pitt), um sujeito com ideias anarquistas.\\n\\n*   Eles criam o **Clube da Luta**.\\n*   O clube vira o **Projeto Mayhem** (destruição da sociedade).\\n*   Revelação: **Tyler é o alter ego** do narrador.\\n\\nFinal: Luta mental, hospital psiquiátrico.', 'SIMPLIFICADA', '2025-11-16 06:07:10'),
(23, 2, NULL, 'damn, que foda', 'Que bom que achou interessante! É um filme que gera bastante discussão. Se quiser explorar algum aspecto específico da história, como os temas que aborda, a interpretação do final ou o simbolismo dos personagens, me diga!', 'SIMPLIFICADA', '2025-11-16 06:07:30'),
(24, 2, NULL, 'é do livro bro :sob:', 'Ops! Perdão!\\n\\nSim, você tem toda razão. *Clube da Luta* é originalmente um livro de Chuck Palahniuk, publicado em 1996. O filme é uma adaptação da obra.\\n\\nMinha memória me pregou uma peça. Desculpe a confusão! Quer saber algo específico sobre o *livro* Clube da Luta?', 'SIMPLIFICADA', '2025-11-16 06:07:42'),
(25, 2, 3, 'oi gatinho', 'Olá! Tudo bem? Em que posso te ajudar com a leitura de *Clube da Luta* hoje?', 'SIMPLIFICADA', '2025-11-16 17:55:57'),
(26, 2, 3, 'tyler durden n é um gostoso?', 'Olá!\\n\\nEssa é uma questão de opinião pessoal, mas Tyler Durden é certamente um personagem que atrai muita atenção! Ele é carismático e tem uma certa rebeldia que pode ser vista como atraente por alguns leitores. \\n\\nNo entanto, vale lembrar que ele também é uma figura complexa e controversa, com comportamentos destrutivos e ideias extremas. O que você acha dele até agora na página 1?', 'SIMPLIFICADA', '2025-11-16 17:56:19');

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_leitura`
--

CREATE TABLE `historico_leitura` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `livro_id` int(11) NOT NULL,
  `status` enum('LENDO','LIDO','QUERO_LER','ABANDONADO') NOT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultima_pagina` int(11) DEFAULT 1,
  `total_paginas` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `historico_leitura`
--

INSERT INTO `historico_leitura` (`id`, `usuario_id`, `livro_id`, `status`, `data_inicio`, `data_fim`, `updated_at`, `ultima_pagina`, `total_paginas`) VALUES
(1, 2, 2, 'LENDO', '2025-11-15', NULL, '2025-11-15 21:51:08', 1, 0),
(2, 2, 3, 'LENDO', '2025-11-15', NULL, '2025-11-16 04:57:23', 1, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `livros`
--

CREATE TABLE `livros` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `autor` varchar(100) NOT NULL,
  `editora` varchar(100) DEFAULT NULL,
  `ano_publicacao` int(11) DEFAULT NULL,
  `genero` varchar(50) DEFAULT NULL,
  `tipo` enum('LITERARIO','TECNICO') NOT NULL,
  `status` enum('DISPONIVEL','INDISPONIVEL') DEFAULT 'DISPONIVEL',
  `nota_media` decimal(3,2) DEFAULT 0.00,
  `descricao` text DEFAULT NULL,
  `capa_url` varchar(500) DEFAULT NULL,
  `arquivo_pdf` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `livros`
--

INSERT INTO `livros` (`id`, `titulo`, `autor`, `editora`, `ano_publicacao`, `genero`, `tipo`, `status`, `nota_media`, `descricao`, `capa_url`, `arquivo_pdf`, `created_at`) VALUES
(2, 'Clube da luta', 'Chuck Palahniuk', NULL, NULL, 'Fiction', 'TECNICO', 'DISPONIVEL', 0.00, 'O clube da luta é idealizado por Tyler Durden, que acredita ter encontrado uma maneira de viver fora dos limites da sociedade e das regras sem sentido. Mas o que está por vir de sua mente pode piorar muito. O livro serviu de base para um filme de 1999, procurando adaptar a atmosfera do livro, o mundo caótico do personagem e o humor negro do autor.', 'https://via.placeholder.com/120x180.png?text=Sem+Capa', 'arquivosPDF/clube-da-luta.pdf', '2025-11-15 20:39:00'),
(3, 'Clube da luta', 'Chuck Palahniuk', NULL, NULL, 'Fiction', 'TECNICO', 'DISPONIVEL', 0.00, 'O clube da luta é idealizado por Tyler Durden, que acredita ter encontrado uma maneira de viver fora dos limites da sociedade e das regras sem sentido. Mas o que está por vir de sua mente pode piorar muito. O livro serviu de base para um filme de 1999, procurando adaptar a atmosfera do livro, o mundo caótico do personagem e o humor negro do autor.', 'http://books.google.com/books/content?id=oUMg6nsMljEC&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api', 'arquivosPDF/clube-da-luta.pdf', '2025-11-15 20:46:52'),
(5, 'Harry Potter e a Pedra Filosofal', 'J.K. Rowling', NULL, NULL, 'Juvenile Fiction', 'TECNICO', 'DISPONIVEL', 0.00, 'Harry Potter não é um herói habitual. É apenas um miúdo magricela, míope e desajeitado com uma estranha cicatriz na testa. Estranha, de facto, porque afinal encerra misteriosos poderes que o distinguem do cinzento mundo dos muggles (os complicados humanos) e que irá fazer dele uma criança especialmente dotada para o universo da magia. Admitido na escola de Hogwarts onde se formam os mais famosos feiticeiros do mundo, Harry Potter irá viver todas as aventuras que a sua imaginação lhe irá proporcionar.', 'http://books.google.com/books/content?id=PDcQCwAAQBAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-11-16 05:01:04'),
(6, 'Manifesto Comunista', 'Karl Marx', NULL, NULL, 'Geral', 'LITERARIO', 'DISPONIVEL', 0.00, 'Sem descrição disponível.', 'http://books.google.com/books/content?id=nKefMvOTCJkC&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api', NULL, '2025-11-16 17:09:15');

-- --------------------------------------------------------

--
-- Estrutura para tabela `livro_saga`
--

CREATE TABLE `livro_saga` (
  `id` int(11) NOT NULL,
  `livro_id` int(11) NOT NULL,
  `saga_id` int(11) NOT NULL,
  `ordem_na_saga` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mensagens_chat`
--

CREATE TABLE `mensagens_chat` (
  `id` int(11) NOT NULL,
  `livro_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mensagem` text NOT NULL,
  `data_envio` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `mensagens_chat`
--

INSERT INTO `mensagens_chat` (`id`, `livro_id`, `usuario_id`, `mensagem`, `data_envio`) VALUES
(1, 3, 2, 'se mate lucas', '2025-11-16 15:23:02');

-- --------------------------------------------------------

--
-- Estrutura para tabela `sagas`
--

CREATE TABLE `sagas` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `total_livros` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `email` varchar(120) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `bio` text DEFAULT NULL,
  `foto_perfil` varchar(500) DEFAULT NULL,
  `privacidade` enum('publico','privado') DEFAULT 'publico',
  `data_nascimento` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ra` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `cpf`, `email`, `senha`, `bio`, `foto_perfil`, `privacidade`, `data_nascimento`, `created_at`, `ra`) VALUES
(1, 'violao456', '648.165.952-36', 'violao@gmail.com', '$2y$10$NnSWBgbiXACHno7bNpJfdOCVAyaSnGwDcmQhjRrggIgYpWQphXRHK', NULL, NULL, 'publico', NULL, '2025-11-15 01:10:01', '964789'),
(2, 'seupai', '902.721.783-14', 'pirocagorda@gmail.com', '$2y$10$f4EsFAsnhOkyOVJqju1RO.ma7F8KokRK4NMhSTAGxWJ/PpVBtSK9.', 'eu daria a bunda pela gang', 'profileImages/perfil_2_1763316252.png', 'publico', NULL, '2025-11-15 18:03:40', '340084');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `amigos`
--
ALTER TABLE `amigos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_amizade` (`usuario_id`,`amigo_id`),
  ADD KEY `amigo_id` (`amigo_id`);

--
-- Índices de tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_avaliacao` (`usuario_id`,`livro_id`),
  ADD KEY `livro_id` (`livro_id`);

--
-- Índices de tabela `consultas_ia`
--
ALTER TABLE `consultas_ia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `livro_id` (`livro_id`);

--
-- Índices de tabela `historico_leitura`
--
ALTER TABLE `historico_leitura`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_historico` (`usuario_id`,`livro_id`),
  ADD KEY `livro_id` (`livro_id`);

--
-- Índices de tabela `livros`
--
ALTER TABLE `livros`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `livro_saga`
--
ALTER TABLE `livro_saga`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_livro_saga` (`livro_id`,`saga_id`),
  ADD KEY `saga_id` (`saga_id`);

--
-- Índices de tabela `mensagens_chat`
--
ALTER TABLE `mensagens_chat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livro_id` (`livro_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `sagas`
--
ALTER TABLE `sagas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cpf` (`cpf`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `amigos`
--
ALTER TABLE `amigos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `consultas_ia`
--
ALTER TABLE `consultas_ia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de tabela `historico_leitura`
--
ALTER TABLE `historico_leitura`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT de tabela `livros`
--
ALTER TABLE `livros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `livro_saga`
--
ALTER TABLE `livro_saga`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mensagens_chat`
--
ALTER TABLE `mensagens_chat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `sagas`
--
ALTER TABLE `sagas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `amigos`
--
ALTER TABLE `amigos`
  ADD CONSTRAINT `amigos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `amigos_ibfk_2` FOREIGN KEY (`amigo_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD CONSTRAINT `avaliacoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `avaliacoes_ibfk_2` FOREIGN KEY (`livro_id`) REFERENCES `livros` (`id`);

--
-- Restrições para tabelas `consultas_ia`
--
ALTER TABLE `consultas_ia`
  ADD CONSTRAINT `consultas_ia_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `historico_leitura`
--
ALTER TABLE `historico_leitura`
  ADD CONSTRAINT `historico_leitura_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `historico_leitura_ibfk_2` FOREIGN KEY (`livro_id`) REFERENCES `livros` (`id`);

--
-- Restrições para tabelas `livro_saga`
--
ALTER TABLE `livro_saga`
  ADD CONSTRAINT `livro_saga_ibfk_1` FOREIGN KEY (`livro_id`) REFERENCES `livros` (`id`),
  ADD CONSTRAINT `livro_saga_ibfk_2` FOREIGN KEY (`saga_id`) REFERENCES `sagas` (`id`);

--
-- Restrições para tabelas `mensagens_chat`
--
ALTER TABLE `mensagens_chat`
  ADD CONSTRAINT `mensagens_chat_ibfk_1` FOREIGN KEY (`livro_id`) REFERENCES `livros` (`id`),
  ADD CONSTRAINT `mensagens_chat_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
