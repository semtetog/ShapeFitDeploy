<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guia Avançado: Dominando a Criação de Sites com IA</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Source+Code+Pro:wght@400;600&display=swap" rel="stylesheet">

    <style>
        /* CSS COMPLETO E AVANÇADO PARA O SITE-GUIA */
        :root {
            --cor-fundo: #f4f6f9;
            --cor-texto: #1e293b;
            --cor-primaria: #4f46e5;
            --cor-primaria-hover: #4338ca;
            --cor-card: #ffffff;
            --cor-borda: #e2e8f0;
            --cor-codigo-fundo: #1e293b;
            --cor-codigo-texto: #e2e8f0;
            --sombra-card: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --sombra-card-hover: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -2px rgb(0 0 0 / 0.1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--cor-fundo);
            color: var(--cor-texto);
            line-height: 1.8;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .container { max-width: 1100px; margin: 20px auto; padding: 20px; }
        header { text-align: center; margin-bottom: 50px; padding-bottom: 20px; }
        header h1 { font-size: 3rem; font-weight: 700; color: var(--cor-primaria); margin-bottom: 10px; line-height: 1.2; }
        header p { font-size: 1.2rem; color: #64748b; }

        .secao {
            background-color: var(--cor-card);
            border-radius: 16px;
            box-shadow: var(--sombra-card);
            margin-bottom: 50px;
            padding: 40px;
            overflow: hidden;
            border: 1px solid var(--cor-borda);
        }

        .secao h2 {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--cor-primaria);
            display: inline-block;
        }

        .secao p, .secao li { font-size: 1.1rem; margin-bottom: 18px; color: #334155; }
        .secao ul { padding-left: 25px; }

        .conceito-card {
            border-left: 5px solid var(--cor-primaria);
            padding: 20px;
            background-color: #f8fafc;
            margin-bottom: 25px;
            border-radius: 0 8px 8px 0;
        }
        .conceito-card h3 { font-size: 1.5rem; margin-bottom: 8px; }

        .code-wrapper { position: relative; margin: 25px 0; }
        pre {
            background-color: var(--cor-codigo-fundo);
            color: var(--cor-codigo-texto);
            font-family: 'Source Code Pro', monospace;
            padding: 25px;
            border-radius: 12px;
            overflow-x: auto;
            font-size: 1rem;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        code { font-family: 'Source Code Pro', monospace; }

        .copy-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: #334155;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            opacity: 0.7;
        }
        .code-wrapper:hover .copy-btn { opacity: 1; }
        .copy-btn.copied { background-color: #16a34a; transform: scale(1.05); }

        .exemplo-interativo {
            padding: 25px;
            border: 1px solid var(--cor-borda);
            border-radius: 12px;
            margin-top: 20px;
        }
        .exemplo-interativo h4 { font-size: 1.2rem; margin-bottom: 15px; }
        .exemplo-interativo button {
            padding: 10px 15px;
            font-size: 1rem;
            border-radius: 8px;
            cursor: pointer;
            border: 1px solid #ccc;
            margin-right: 10px;
        }
        #btn-com-js { background-color: var(--cor-primaria); color: white; border-color: var(--cor-primaria); }
        #mensagem-js { margin-top: 15px; padding: 15px; background-color: #eef2ff; border-left: 4px solid var(--cor-primaria); display: none; }

        .galeria-css {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .card-estilo {
            border: 1px solid var(--cor-borda);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--sombra-card);
            transition: all 0.3s ease;
        }
        .card-estilo:hover { transform: translateY(-5px); box-shadow: var(--sombra-card-hover); }
        .card-estilo .preview {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #e2e8f0;
        }
        .card-estilo .conteudo { padding: 20px; }
        .card-estilo h4 { font-size: 1.3rem; margin-bottom: 10px; }
        .card-estilo .code-wrapper { margin-top: 15px; }
        .card-estilo pre { font-size: 0.9rem; padding: 15px; }
        .card-estilo .copy-btn { top: 8px; right: 8px; padding: 6px 10px; font-size: 0.8rem; }
        
        /* Estilos de preview */
        .preview-glass {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            border:1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            width: 80%; height: 80%; color: white; display: flex; align-items: center; justify-content: center; text-align: center;
        }
        .preview-degrade-btn {
            padding: 15px 30px;
            border: none;
            border-radius: 50px;
            color: white;
            font-weight: bold;
            background: linear-gradient(45deg, #ff6b6b, #f06595, #cc5de8, #845ef7);
            background-size: 300% 300%;
            animation: gradient-animation 4s ease infinite;
            cursor: pointer;
        }
        @keyframes gradient-animation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        footer { text-align: center; padding: 30px; margin-top: 40px; font-size: 1rem; color: #64748b; }

        /* === MELHORIAS PARA MOBILE === */
        @media (max-width: 768px) {
            body {
                line-height: 1.7;
                -webkit-text-size-adjust: 100%; /* Previne zoom de fontes no iOS */
            }
            .container {
                padding: 15px;
                margin: 10px auto;
            }
            header h1 {
                font-size: 2.2rem;
            }
            header p {
                font-size: 1rem;
            }
            .secao {
                padding: 25px;
                margin-bottom: 30px;
            }
            .secao h2 {
                font-size: 1.7rem;
            }
            .secao p, .secao li {
                font-size: 1rem;
            }
            .conceito-card h3 {
                font-size: 1.3rem;
            }
            .exemplo-interativo {
                padding: 20px;
            }
            pre {
                padding: 15px;
                font-size: 0.9rem;
            }
            .galeria-css {
                grid-template-columns: 1fr; /* Força uma única coluna em telas pequenas */
                gap: 25px;
            }
            .card-estilo .preview {
                height: 180px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <h1>Guia Avançado: Dominando a Criação de Sites com IA</h1>
            <p>Um recurso prático e visual para transformar suas ideias em código funcional.</p>
        </header>

        <main>
            <section class="secao" id="fundamentos">
                <h2>1. Os Fundamentos: O Papel de Cada Tecnologia</h2>
                <p>Para dar ordens precisas à IA, você precisa entender o papel de cada peça. Pense nisso como os ingredientes de uma receita.</p>
                <div class="conceito-card">
                    <h3>HTML: A Estrutura (O Esqueleto)</h3>
                    <p>O HTML é a base de tudo. Ele organiza o conteúdo em blocos lógicos: títulos, parágrafos, listas, imagens, formulários. Sem HTML, não há site, apenas texto solto. <strong>Sempre comece pedindo o HTML.</strong></p>
                </div>
                <div class="conceito-card">
                    <h3>CSS: O Estilo (A Roupa e a Maquiagem)</h3>
                    <p>O CSS dá vida ao esqueleto do HTML. Ele controla cores, fontes, espaçamentos, layout e animações. É o que torna um site bonito e agradável. Você pode pedir estilos específicos, como verá na nossa galeria.</p>
                </div>
                <div class="conceito-card">
                    <h3>JavaScript (JS): A Interatividade (O Cérebro do Navegador)</h3>
                    <p>O JS é o que torna um site "inteligente" no navegador do usuário. Ele reage a ações como cliques e preenchimento de formulários <strong>sem precisar recarregar a página</strong>. Essencial para uma experiência de usuário moderna.</p>
                </div>
            </section>
            
            <section class="secao" id="pratica">
                <h2>2. A Mágica na Prática: Vendo o Código em Ação</h2>
                <p>Vamos ver a diferença que cada tecnologia faz com um exemplo interativo. Teste os botões abaixo:</p>
                <div class="exemplo-interativo">
                    <h4>HTML Puro (O esqueleto)</h4>
                    <button>Botão HTML</button>
                    <p><small>Apenas a estrutura. Clicável, mas não faz nada.</small></p>
                </div>
                <div class="exemplo-interativo">
                    <h4>HTML + CSS (Bonito, mas "burro")</h4>
                    <button style="background-color: var(--cor-primaria); color: white; border: none; padding: 10px 15px; border-radius: 8px; cursor: pointer;">Botão com CSS</button>
                    <p><small>Agora tem estilo, mas ainda não tem uma ação programada.</small></p>
                </div>
                <div class="exemplo-interativo">
                    <h4>HTML + CSS + JavaScript (Completo e interativo)</h4>
                    <button id="btn-com-js">Botão com JS</button>
                    <p><small>Clique neste! Ele tem uma ação definida pelo JavaScript.</small></p>
                    <div id="mensagem-js">
                        <strong>Voilà!</strong> O JavaScript foi executado. Você pode pedir à IA para fazer qualquer coisa com essa ação: abrir um pop-up, enviar um formulário, calcular algo, etc.
                    </div>
                </div>
            </section>

            <section class="secao" id="galeria-css">
                <h2>3. Galeria de Estilos CSS: Peça o Design que Quiser!</h2>
                <p>O visual do seu site é definido pelo CSS. Você pode pedir à IA para criar componentes com estilos modernos. Veja alguns exemplos, sinta-se à vontade para copiar o código e usar nos seus prompts.</p>
                <div class="galeria-css">
                    <div class="card-estilo">
                        <div class="preview" style="background-image: url('https://placehold.co/400x300/6366f1/e0e7ff?text=Fundo'); background-size: cover;">
                            <div class="preview-glass">Glassmorphism</div>
                        </div>
                        <div class="conteudo">
                            <h4>Glassmorphism</h4>
                            <p>Um efeito de "vidro fosco" que está muito em alta. Ótimo para painéis e cards.</p>
                            <div class="code-wrapper">
                                <button class="copy-btn">Copiar</button>
<pre><code>.seu-elemento {
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.3);
}</code></pre>
                            </div>
                        </div>
                    </div>
                    <div class="card-estilo">
                        <div class="preview">
                            <button class="preview-degrade-btn">Botão Animado</button>
                        </div>
                        <div class="conteudo">
                            <h4>Botão com Gradiente Animado</h4>
                            <p>Um botão que chama a atenção com um fundo em gradiente que se move.</p>
                            <div class="code-wrapper">
                                <button class="copy-btn">Copiar</button>
<pre><code>.seu-botao {
  border: none;
  color: white;
  padding: 15px 30px;
  border-radius: 50px;
  background: linear-gradient(45deg, #fa5252, #e64980, #be4bdb, #7950f2);
  background-size: 300% 300%;
  animation: gradient 5s ease infinite;
}

@keyframes gradient {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <section class="secao" id="backend">
                <h2>4. O Servidor: Quando eu Preciso de PHP e SQL?</h2>
                <p>Nem todo site precisa de PHP ou banco de dados. Entenda a diferença:</p>
                <ul>
                    <li><strong>Site Estático (HTML, CSS, JS):</strong> É como um panfleto digital. Perfeito para sites institucionais, portfólios, páginas de captura simples. As informações não mudam a menos que você edite o código.</li>
                    <li><strong>Site Dinâmico (PHP, SQL):</strong> É um sistema. Você precisa dele quando precisa:
                        <ul>
                            <li><strong>Salvar informações dos usuários:</strong> formulários de contato, cadastros, posts de um blog, produtos de uma loja.</li>
                            <li><strong>Ter um sistema de login:</strong> áreas restritas para membros ou administradores.</li>
                            <li><strong>Exibir conteúdo que muda constantemente:</strong> um feed de notícias, uma lista de produtos vinda de um catálogo.</li>
                        </ul>
                    </li>
                </ul>
                <p><strong>A regra é simples:</strong> se o site precisa "lembrar" de alguma coisa depois que o usuário fecha a aba, você vai precisar de PHP e SQL.</p>
            </section>

            <section class="secao" id="prompt-mestre">
                <h2>5. O Prompt Mestre: O Ponto de Partida</h2>
                <p>Este é o seu template principal. Use-o como base para qualquer novo projeto. Seja o mais detalhado possível nos placeholders.</p>
                <div class="code-wrapper">
                    <button class="copy-btn">Copiar</button>
<pre><code>Aja como um desenvolvedor web full-stack sênior. Sua tarefa é gerar um projeto web completo em um ÚNICO ARQUIVO chamado 'index.php'. O código deve ser limpo, seguro e bem comentado.

**1. Objetivo do Projeto:** Criar um [DESCRIÇÃO EXTREMAMENTE DETALHADA DO PROJETO].

**2. Tecnologias:** [ESCOLHA: "Apenas HTML, CSS e JS" ou "PHP, MySQL, HTML, CSS e JS"].

**3. Estrutura do Arquivo:**
- [SE USAR PHP]: Bloco PHP no topo para lógica, conexão e processamento.
- HTML: &lt;head&gt; com o CSS dentro de &lt;style&gt;, e &lt;body&gt; com a estrutura visual.
- JavaScript: Bloco &lt;script&gt; no final do &lt;body&gt;.

**4. Requisitos de Design (CSS):** Descreva o visual. [ex: "Quero um design minimalista, com tema escuro, usando a fonte 'Poppins'. Os cards devem ter o efeito de Glassmorphism e os botões principais devem ter gradiente animado."].

**5. Requisitos Funcionais:** Detalhe CADA funcionalidade. [ex: "Um formulário com campos 'nome', 'email', 'mensagem'. Ao clicar em 'enviar', o JavaScript deve validar se os campos não estão vazios. Se estiver tudo OK, os dados devem ser enviados para o PHP, que salvará no banco de dados na tabela 'contatos'."].

**6. Banco de Dados (SQL) [SE USAR]:** Peça o script SQL `CREATE TABLE` completo.

**7. Instruções Finais:** Sempre peça o código COMPLETO, sem omissões e bem comentado.</code></pre>
                </div>
            </section>
            
            <section class="secao" id="mais-prompts">
                <h2>6. A Arte do Diálogo: Mais Exemplos de Prompts</h2>
                <p>O segredo é continuar a conversa. Seu primeiro prompt cria a base, os seguintes refinam. Lembre-se: <strong>estes são exemplos, adapte a pergunta para TUDO que você precisar.</strong></p>
                
                <h3>Para Adicionar Funcionalidades</h3>
                <div class="code-wrapper">
                    <button class="copy-btn">Copiar</button>
<pre><code>"Pegue o código anterior que você gerou. Agora, adicione uma seção de 'Depoimentos' logo abaixo da seção principal. Os depoimentos devem vir do banco de dados, de uma nova tabela chamada 'depoimentos'. Crie o código PHP para buscar esses dados e exibi-los em cards estilizados. Me forneça o código 'index.php' ATUALIZADO e COMPLETO, e também o script SQL para criar a nova tabela 'depoimentos'."</code></pre>
                </div>
                
                <h3>Para Mudar o Design</h3>
                <div class="code-wrapper">
                    <button class="copy-btn">Copiar</button>
<pre><code>"Vamos alterar o design do site anterior. Quero que você reescreva TODA a seção &lt;style&gt;. Mude o esquema de cores para um tom de verde (#10b981) como cor primária. A fonte do corpo do texto deve ser 'Inter'. Deixe os cantos de todos os elementos (botões, cards, inputs) mais arredondados (border-radius: 12px). Forneça apenas a seção &lt;style&gt; completa e atualizada."</code></pre>
                </div>
                
                <h3>Para Corrigir Erros (Debugging)</h3>
                <div class="code-wrapper">
                    <button class="copy-btn">Copiar</button>
<pre><code>"Estou com um problema no código que você me deu. Quando eu envio o formulário, a página recarrega mas os dados não aparecem no banco de dados. Suspeito que o erro está no bloco de processamento do formulário em PHP. Analise o código completo que estou colando abaixo, encontre o bug, corrija-o e me explique qual era o problema."
[COLE SEU CÓDIGO COMPLETO AQUI]</code></pre>
                </div>
            </section>
            
            <section class="secao" id="hostinger">
                <h2>7. Publicando na Hostinger</h2>
                <p>Com seu arquivo `index.php` (ou `.html`) pronto, siga estes passos para colocá-lo no ar.</p>
                <ul>
                    <li><strong>Passo 1 (Se usar BD): Crie o Banco de Dados.</strong> No painel da Hostinger > "Bancos de Dados MySQL", crie um novo banco, usuário e senha. Anote os dados.</li>
                    <li><strong>Passo 2 (Se usar BD): Importe a Tabela.</strong> Acesse o "phpMyAdmin", selecione seu banco, vá na aba "SQL", cole o código `CREATE TABLE` e execute.</li>
                    <li><strong>Passo 3: Envie o Arquivo.</strong> Vá em "Gerenciador de Arquivos" > `public_html`, e faça o upload do seu arquivo.</li>
                    <li><strong>Passo 4 (Se usar BD): Conecte ao Banco.</strong> Edite seu arquivo no Gerenciador, e no topo do PHP, preencha as variáveis `$db_host`, `$db_name`, `$db_user`, e `$db_pass` com os dados que você anotou.</li>
                    <li><strong>Passo 5: Teste!</strong> Acesse seu domínio e veja a mágica acontecer.</li>
                </ul>
            </section>
        </main>

        <footer>
            <p>Guia criado para acelerar o desenvolvimento com IA. Use, adapte e construa.</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Lógica para o botão interativo com JS
            const btnComJs = document.getElementById('btn-com-js');
            const mensagemJs = document.getElementById('mensagem-js');
            if(btnComJs && mensagemJs) {
                btnComJs.addEventListener('click', () => {
                    mensagemJs.style.display = 'block';
                });
            }

            // Lógica para todos os botões de copiar
            const allCopyButtons = document.querySelectorAll('.copy-btn');
            allCopyButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const codeBlock = button.nextElementSibling;
                    const codeText = codeBlock.textContent;

                    const textArea = document.createElement('textarea');
                    textArea.value = codeText;
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        button.textContent = 'Copiado!';
                        button.classList.add('copied');
                    } catch (err) {
                        console.error('Falha ao copiar texto: ', err);
                        button.textContent = 'Erro';
                    }
                    document.body.removeChild(textArea);

                    setTimeout(() => {
                        button.textContent = 'Copiar';
                        button.classList.remove('copied');
                    }, 2000);
                });
            });
        });
    </script>
</body>
</html>

