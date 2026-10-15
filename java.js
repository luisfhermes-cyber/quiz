document.addEventListener("DOMContentLoaded", function () {

    // =========================
    // ELEMENTOS DAS TELAS
    // =========================

    var telaLogin = document.getElementById("login");
    var telaIntro = document.getElementById("intro");
    var telaQuiz = document.getElementById("quiz");
    var telaResultado = document.getElementById("result");
    var telaExplicacao = document.getElementById("result-explanation");

    var nomeInput = document.getElementById("nome");
    var celularInput = document.getElementById("celular");
    var instagramInput = document.getElementById("instagram");
    var loginError = document.getElementById("login-error");
    var nomeAluno = document.getElementById("nome-aluno");

    var botaoLogin = document.getElementById("login-btn");
    var botaoComecar = document.getElementById("start");
    var botaoRefazer = document.getElementById("redo");
    var botaoVerExplicacao = document.getElementById("ver-explicacao");
    var botaoRefazerExplicacao = document.getElementById("redo-explanation");
    var botaoInstagram = document.getElementById("share-instagram");

    var questionElement = document.getElementById("question");
    var stepElement = document.getElementById("step");
    var progressFill = document.getElementById("progress-fill");
    var scaleElement = document.getElementById("scale");

    var winnerElement = document.getElementById("winner");
    var rankingElement = document.getElementById("ranking");
    var explanationElement = document.getElementById("explanation");

    // =========================
    // CONFIGURAÇÃO
    // =========================

    var PERGUNTAS_POR_AREA = 3;    // sorteadas de cada área (5 áreas = 15 perguntas)
    var PERGUNTAS_DESEMPATE = 2;   // sorteadas de cada área empatada, a cada rodada
    var PROFISSOES_EXIBIDAS = 4;   // profissões mostradas na tela de explicação
    var MAX_RODADAS = 20;          // segurança: depois disso sorteia entre as empatadas
    var API_URL = "api/";          // pasta da API em PHP/MySQL (use "" para desligar o banco de dados)

    // =========================
    // CURSOS (5 áreas, 9 perguntas cada)
    // =========================

    var cursos = {
        eletro: {
            nome: "Eletrotécnica",
            cor: "#d97706",
            emoji: "⚡",
            imagemStory: "img/eletrotecnica.svg",
            descricao: "Suas respostas mostram interesse por energia, eletricidade, equipamentos e soluções para tornar o uso da energia mais eficiente.",
            pontos: 0,
            extra: 0,
            profissoes: [
                "Técnico em Eletrotécnica",
                "Técnico de instalações elétricas",
                "Técnico de manutenção elétrica",
                "Técnico de projetos elétricos",
                "Técnico de sistemas elétricos industriais",
                "Profissional de manutenção predial/industrial",
                "Profissional de sistemas elétricos de potência",
                "Profissional ligado à eficiência energética"
            ],
            sobreCurso: "A formação do SENAI inclui instalações e projetos prediais e industriais, acionamentos, manutenção, eficiência energética e sistemas elétricos de potência.",
            perguntas: [
                "Você gosta de entender como a energia é distribuída para fazer diferentes equipamentos funcionarem?",
                "Você se sente confortável seguindo esquemas, desenhos ou representações para descobrir como algo deve ser montado?",
                "Você teria interesse em trabalhar com projetos que precisam seguir medidas, padrões e especificações bem definidas?",
                "Você gosta de procurar maneiras de fazer algo funcionar gastando menos recursos?",
                "Você prefere atividades em que pequenos detalhes podem fazer uma grande diferença no resultado final?",
                "Você teria interesse em acompanhar equipamentos e instalações para identificar problemas antes que eles causem uma interrupção?",
                "Você gosta de pensar em como uma estrutura poderia ser planejada antes de sua execução?",
                "Você se interessaria por atividades relacionadas ao funcionamento e à manutenção de grandes sistemas que fornecem energia?",
                "Você gostaria de trabalhar com soluções que precisam funcionar de forma confiável durante longos períodos?"
            ]
        },

        eletrom: {
            nome: "Eletromecânica",
            cor: "#7c3aed",
            emoji: "⚙️",
            imagemStory: "img/eletromecanica.svg",
            descricao: "Suas respostas mostram interesse por máquinas, ferramentas, manutenção e pela combinação entre eletricidade e mecânica.",
            pontos: 0,
            extra: 0,
            profissoes: [
                "Técnico em Eletromecânica",
                "Técnico/profissional de manutenção eletromecânica",
                "Profissional de manutenção de máquinas e equipamentos",
                "Técnico de montagem de equipamentos industriais",
                "Técnico de instalação de sistemas eletromecânicos",
                "Profissional de assistência técnica/serviços de campo",
                "Profissional de projetos de sistemas eletromecânicos"
            ],
            sobreCurso: "O SENAI relaciona o curso a montagem, manutenção e projetos de sistemas mecânicos, elétricos e automatizados, além de instalações e manutenção de equipamentos industriais.",
            perguntas: [
                "Você gosta de entender como diferentes partes de uma máquina trabalham juntas para realizar uma tarefa?",
                "Quando um equipamento deixa de funcionar, você teria curiosidade de investigar o que provocou o problema?",
                "Você prefere atividades que envolvam tanto raciocínio quanto trabalho prático?",
                "Você se interessa por observar movimentos, peças e mecanismos para entender como tudo funciona?",
                "Você gosta de montar, desmontar ou ajustar objetos para descobrir como eles funcionam?",
                "Você teria interesse em acompanhar máquinas e equipamentos para evitar que apresentem problemas inesperados?",
                "Você gosta de seguir desenhos ou instruções para montar algo seguindo determinadas medidas e especificações?",
                "Você se interessaria por encontrar maneiras de melhorar o funcionamento ou a eficiência de equipamentos?",
                "Você gostaria de trabalhar em atividades nas quais fosse necessário combinar diferentes tipos de conhecimento para resolver um problema?"
            ]
        },

        autom: {
            nome: "Automação Industrial",
            cor: "#0891b2",
            emoji: "🤖",
            imagemStory: "img/automacao-industrial.svg",
            descricao: "Suas respostas mostram interesse por tecnologia, máquinas automáticas, robótica e processos que podem funcionar de forma mais eficiente.",
            pontos: 0,
            extra: 0,
            profissoes: [
                "Técnico em Automação Industrial",
                "Técnico de automação",
                "Técnico de instrumentação",
                "Instrumentista industrial",
                "Técnico de controle de processos",
                "Profissional de programação/configuração de CLPs",
                "Técnico de manutenção de sistemas automatizados",
                "Técnico de comissionamento",
                "Profissional de integração de sistemas automatizados"
            ],
            sobreCurso: "O SENAI relaciona Automação Industrial a sistemas lógicos programáveis, instrumentação e controle de processos, sistemas microcontrolados, acionamentos, supervisão, integração, manutenção e comissionamento.",
            perguntas: [
                "Você gosta da ideia de fazer uma tarefa acontecer automaticamente depois que determinadas condições são definidas?",
                "Você se interessa por descobrir como máquinas podem tomar determinadas decisões a partir de informações recebidas?",
                "Você gosta de situações em que é necessário acompanhar várias informações ao mesmo tempo para perceber se tudo está funcionando corretamente?",
                "Você teria curiosidade em entender como sensores conseguem perceber mudanças no ambiente ou em uma máquina?",
                "Você gosta de procurar maneiras de tornar um processo mais rápido, preciso ou organizado?",
                "Você se interessaria por controlar equipamentos utilizando sistemas digitais?",
                "Você gosta de atividades que misturam raciocínio lógico, tecnologia e resolução de problemas práticos?",
                "Você teria interesse em acompanhar o funcionamento de um processo e fazer ajustes quando alguma variável sair do esperado?",
                "Você gostaria de participar da criação ou implantação de sistemas que fazem máquinas e equipamentos trabalharem de forma integrada?"
            ]
        },

        seg: {
            nome: "Segurança do Trabalho",
            cor: "#16a34a",
            emoji: "🦺",
            imagemStory: "img/seguranca-trabalho.svg",
            descricao: "Suas respostas mostram atenção aos detalhes, prevenção de riscos, organização e interesse em tornar os ambientes de trabalho mais seguros.",
            pontos: 0,
            extra: 0,
            profissoes: [
                "Técnico em Segurança do Trabalho",
                "Profissional de prevenção de riscos ocupacionais",
                "Assistente/analista de Saúde, Segurança e Meio Ambiente (SSMA/EHS)",
                "Profissional de higiene ocupacional",
                "Profissional de apoio a auditorias e documentação de segurança",
                "Profissional de treinamento e ações educativas de segurança",
                "Profissional de prevenção e análise de acidentes"
            ],
            sobreCurso: "O SENAI descreve atuação envolvendo prevenção, identificação e avaliação de riscos, higiene ocupacional, ações educativas, auditorias, documentação e análise de acidentes.",
            perguntas: [
                "Você costuma perceber situações que podem causar problemas antes que eles realmente aconteçam?",
                "Você se sente confortável chamando a atenção de alguém quando percebe que uma regra importante não está sendo seguida?",
                "Você gosta de observar como as pessoas realizam suas atividades e pensar em maneiras de torná-las mais seguras?",
                "Você teria facilidade para explicar uma orientação importante para um grupo de pessoas?",
                "Você gosta de atividades que envolvem observar, registrar informações e depois analisar o que foi encontrado?",
                "Quando acontece um problema, você prefere investigar suas possíveis causas antes de simplesmente procurar um culpado?",
                "Você se interessa por situações que exigem organização, acompanhamento de procedimentos e cumprimento de regras?",
                "Você teria interesse em participar de treinamentos e campanhas voltados para melhorar os hábitos das pessoas em um ambiente de trabalho?",
                "Você gosta da ideia de ajudar uma organização a prevenir problemas e melhorar continuamente suas práticas?"
            ]
        },

        ds: {
            nome: "Desenvolvimento de Sistemas",
            cor: "#dc2626",
            emoji: "💻",
            imagemStory: "img/desenvolvimento-sistemas.svg",
            descricao: "Suas respostas mostram interesse por lógica, tecnologia, programação e criação de soluções digitais para resolver problemas.",
            pontos: 0,
            extra: 0,
            profissoes: [
                "Desenvolvedor de sistemas",
                "Desenvolvedor web",
                "Desenvolvedor de aplicativos",
                "Técnico/analista de suporte e implantação de sistemas",
                "Testador de software / QA",
                "Profissional de banco de dados",
                "Profissional de manutenção de sistemas",
                "Profissional ligado a IoT"
            ],
            sobreCurso: "O curso do SENAI inclui lógica de programação, banco de dados, aplicativos, IoT, testes, implantação, modelagem, manutenção e desenvolvimento de sistemas.",
            perguntas: [
                "Você gosta de transformar uma ideia em algo que outras pessoas possam utilizar no dia a dia?",
                "Quando encontra um problema, você costuma tentar dividi-lo em pequenas partes para encontrar uma solução?",
                "Você teria interesse em criar algo que pudesse funcionar de maneira diferente dependendo das escolhas de cada pessoa?",
                "Você se sente confortável passando bastante tempo concentrado em uma tarefa que exige atenção aos detalhes?",
                "Você gosta da ideia de encontrar erros em algo e investigar até descobrir exatamente onde está o problema?",
                "Você teria curiosidade em entender como grandes quantidades de informações podem ser organizadas e encontradas rapidamente?",
                "Você gosta de aprender ferramentas novas sozinho, explorando e testando suas funções?",
                "Você se interessaria por criar soluções que pudessem ser utilizadas em computadores, celulares ou outros dispositivos?",
                "Você prefere atividades nas quais seja possível testar uma solução, perceber o resultado e fazer melhorias várias vezes?"
            ]
        }
    };

    // ==========================================
    // VARIÁVEIS DO TESTE
    // ==========================================

    var perguntaAtual = 0;
    var fila = [];               // perguntas exibidas: { texto, curso }
    var perguntasJaUsadas = {};  // texto -> true
    var fase = "normal";         // "normal" ou "desempate"
    var inicioDesempate = 0;
    var vencedorChave = null;
    var rodada = 0;              // rodada de desempate atual
    var empatadasAtuais = [];    // áreas ainda empatadas
    var pontosRodada = {};       // pontos da rodada atual

    // Dados enviados ao banco (api/salvar.php) quando o quiz termina
    var historico = [];          // respostas dadas, na ordem: { pergunta, valor, fase, rodada }
    var participante = { nome: "", celular: "", instagram: "" };
    var salvarPromise = null;    // resolve com o id da tentativa no banco (ou null se não salvou)

    // ==========================================
    // UTILIDADES
    // ==========================================

    function embaralhar(lista) {
        var copia = lista.slice();
        for (var i = copia.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var temp = copia[i];
            copia[i] = copia[j];
            copia[j] = temp;
        }
        return copia;
    }

    function mostrarTela(tela) {
        [telaLogin, telaIntro, telaQuiz, telaResultado, telaExplicacao].forEach(function (item) {
            if (item) {
                item.classList.add("hidden");
            }
        });

        if (tela) {
            tela.classList.remove("hidden");
        }
    }

    function zerarPontos() {
        for (var curso in cursos) {
            cursos[curso].pontos = 0;
            cursos[curso].extra = 0;
        }
    }

    // ==========================================
    // LOGIN
    // ==========================================

    botaoLogin.addEventListener("click", function () {

        var nome = nomeInput.value.trim();
        var celular = celularInput.value.replace(/\D/g, "");
        var instagram = instagramInput ? instagramInput.value.trim() : "";

        if (instagram && instagram.charAt(0) !== "@") {
            instagram = "@" + instagram;
            instagramInput.value = instagram;
        }

        loginError.textContent = "";

        if (nome.length < 2) {
            loginError.textContent = "Digite seu nome.";
            nomeInput.focus();
            return;
        }

        if (celular.length < 10 || celular.length > 11) {
            loginError.textContent = "Digite um número de celular válido.";
            celularInput.focus();
            return;
        }

        participante = { nome: nome, celular: celular, instagram: instagram };

        nomeAluno.textContent = nome;

        mostrarTela(telaIntro);
    });

    // ==========================================
    // COMEÇAR TESTE (sorteia 3 de 9 por área)
    // ==========================================

    botaoComecar.addEventListener("click", function () {

        zerarPontos();

        perguntaAtual = 0;
        fase = "normal";
        inicioDesempate = 0;
        vencedorChave = null;
        rodada = 0;
        empatadasAtuais = [];
        pontosRodada = {};
        historico = [];
        salvarPromise = null;
        perguntasJaUsadas = {};
        fila = [];

        Object.keys(cursos).forEach(function (chave) {
            embaralhar(cursos[chave].perguntas)
                .slice(0, PERGUNTAS_POR_AREA)
                .forEach(function (texto) {
                    perguntasJaUsadas[texto] = true;
                    fila.push({ texto: texto, curso: chave });
                });
        });

        fila = embaralhar(fila);

        mostrarTela(telaQuiz);
        mostrarPergunta();
    });

    // ==========================================
    // MOSTRAR PERGUNTA
    // ==========================================

    function mostrarPergunta() {

        if (perguntaAtual >= fila.length) {
            verificarResultado();
            return;
        }

        var item = fila[perguntaAtual];
        var posicao, total;

        questionElement.textContent = item.texto;

        if (fase === "desempate") {
            posicao = perguntaAtual - inicioDesempate + 1;
            total = fila.length - inicioDesempate;
            stepElement.textContent = "Desempate" + (rodada > 1 ? " (rodada " + rodada + ")" : "") + " " + posicao + " de " + total;
        } else {
            posicao = perguntaAtual + 1;
            total = fila.length;
            stepElement.textContent = "Pergunta " + posicao + " de " + total;
        }

        progressFill.style.width = ((posicao - 1) / total) * 100 + "%";

        scaleElement.innerHTML = "";

        for (var valor = 1; valor <= 5; valor++) {

            var botao = document.createElement("button");

            botao.type = "button";
            botao.className = "answer-btn";
            botao.textContent = valor;

            botao.addEventListener("click", function () {

                var resposta = Number(this.textContent);

                if (fase === "desempate") {
                    cursos[item.curso].extra += resposta;
                    pontosRodada[item.curso] = (pontosRodada[item.curso] || 0) + resposta;
                } else {
                    cursos[item.curso].pontos += resposta;
                }

                historico.push({
                    pergunta: item.texto,
                    valor: resposta,
                    fase: fase,
                    rodada: fase === "desempate" ? rodada : 0
                });

                perguntaAtual++;
                mostrarPergunta();
            });

            scaleElement.appendChild(botao);
        }
    }

    // ==========================================
    // VERIFICAR RESULTADO / DESEMPATE
    // ==========================================

    function areasComMaiorPontuacao() {

        var maior = -Infinity;

        for (var curso in cursos) {
            if (cursos[curso].pontos > maior) {
                maior = cursos[curso].pontos;
            }
        }

        return Object.keys(cursos).filter(function (chave) {
            return cursos[chave].pontos === maior;
        });
    }

    function verificarResultado() {

        // Fim das 15 perguntas
        if (fase === "normal") {

            var empatadas = areasComMaiorPontuacao();

            if (empatadas.length === 1) {
                vencedorChave = empatadas[0];
                mostrarResultado();
                return;
            }

            rodada = 0;
            iniciarRodadaDesempate(empatadas);
            return;
        }

        // Fim de uma rodada de desempate: continua só quem fez mais pontos nela
        var maiorRodada = Math.max.apply(null, empatadasAtuais.map(function (chave) {
            return pontosRodada[chave] || 0;
        }));

        var seguem = empatadasAtuais.filter(function (chave) {
            return (pontosRodada[chave] || 0) === maiorRodada;
        });

        if (seguem.length === 1 || rodada >= MAX_RODADAS) {
            vencedorChave = seguem[Math.floor(Math.random() * seguem.length)];
            mostrarResultado();
            return;
        }

        // Ainda empatou: nova rodada só com as áreas que seguem empatadas
        iniciarRodadaDesempate(seguem);
    }

    // Sorteia perguntas ainda não usadas; se acabarem, repete perguntas da área
    function sortearPerguntasDesempate(chave) {

        var todas = cursos[chave].perguntas;

        var novas = embaralhar(todas.filter(function (texto) {
            return !perguntasJaUsadas[texto];
        })).slice(0, PERGUNTAS_DESEMPATE);

        if (novas.length < PERGUNTAS_DESEMPATE) {

            var outras = embaralhar(todas.filter(function (texto) {
                return novas.indexOf(texto) === -1;
            }));

            novas = novas.concat(outras.slice(0, PERGUNTAS_DESEMPATE - novas.length));
        }

        return novas;
    }

    function iniciarRodadaDesempate(empatadas) {

        fase = "desempate";
        rodada++;
        empatadasAtuais = empatadas.slice();
        pontosRodada = {};
        inicioDesempate = fila.length;

        var novas = [];

        empatadas.forEach(function (chave) {

            pontosRodada[chave] = 0;

            sortearPerguntasDesempate(chave).forEach(function (texto) {
                perguntasJaUsadas[texto] = true;
                novas.push({ texto: texto, curso: chave });
            });
        });

        fila = fila.concat(embaralhar(novas));

        mostrarPergunta();
    }

    // ==========================================
    // ÍCONES (ilustrações em SVG com gradientes e sombras)
    // ==========================================

    var contadorIcones = 0;

    function caminhoEngrenagem(cx, cy, R, r, dentes, giro) {

        var passo = (Math.PI * 2) / dentes;
        var pontos = [];

        for (var i = 0; i < dentes; i++) {

            var base = giro + i * passo;

            [[r, 0], [R, 0.18], [R, 0.45], [r, 0.63]].forEach(function (p) {
                var ang = base + p[1] * passo;
                pontos.push(
                    (cx + p[0] * Math.cos(ang)).toFixed(1) + " " +
                    (cy + p[0] * Math.sin(ang)).toFixed(1)
                );
            });
        }

        return "M" + pontos.join(" L") + " Z";
    }

    function conteudoIcone(chave, u) {

        // ---- Eletrotécnica: lâmpada ----
        if (chave === "eletro") {
            return `
            <defs>
              <radialGradient id="vidro${u}" cx="45%" cy="35%" r="70%">
                <stop offset="0" stop-color="#ffffff"/><stop offset=".45" stop-color="#fef3c7"/><stop offset="1" stop-color="#fbbf24"/>
              </radialGradient>
              <radialGradient id="brilho${u}" cx="50%" cy="50%" r="50%">
                <stop offset="0" stop-color="#fde68a" stop-opacity=".95"/><stop offset="1" stop-color="#fde68a" stop-opacity="0"/>
              </radialGradient>
              <linearGradient id="metal${u}" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#6b7280"/><stop offset=".3" stop-color="#f3f4f6"/><stop offset=".65" stop-color="#9ca3af"/><stop offset="1" stop-color="#4b5563"/>
              </linearGradient>
            </defs>
            <circle cx="100" cy="82" r="82" fill="url(#brilho${u})"/>
            <path d="M100 14C58 14 34 46 34 80c0 24 12 38 24 52 6 8 8 14 8 22h68c0-8 2-14 8-22 12-14 24-28 24-52 0-34-24-66-66-66z" fill="url(#vidro${u})" stroke="#d97706" stroke-width="2"/>
            <path d="M62 62C66 42 82 29 98 27" fill="none" stroke="#ffffff" stroke-opacity=".9" stroke-width="6" stroke-linecap="round"/>
            <path d="M85 154v-40c0-16 10-20 15-8 5-12 15-8 15 8v40" fill="none" stroke="#b45309" stroke-width="3" stroke-linecap="round"/>
            <path d="M92 112l8-14 8 14" fill="none" stroke="#f97316" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <rect x="66" y="154" width="68" height="13" rx="3" fill="url(#metal${u})" stroke="#4b5563"/>
            <rect x="70" y="167" width="60" height="11" rx="3" fill="url(#metal${u})" stroke="#4b5563"/>
            <rect x="74" y="178" width="52" height="10" rx="3" fill="url(#metal${u})" stroke="#4b5563"/>
            <path d="M84 188h32a16 9 0 0 1-32 0z" fill="#374151"/>`;
        }

        // ---- Eletromecânica: engrenagens ----
        if (chave === "eletrom") {
            return `
            <defs>
              <linearGradient id="aco${u}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#f3f4f6"/><stop offset=".5" stop-color="#9ca3af"/><stop offset="1" stop-color="#4b5563"/>
              </linearGradient>
              <linearGradient id="acoInv${u}" x1="1" y1="1" x2="0" y2="0">
                <stop offset="0" stop-color="#e5e7eb"/><stop offset=".5" stop-color="#8b95a3"/><stop offset="1" stop-color="#374151"/>
              </linearGradient>
            </defs>
            <ellipse cx="100" cy="188" rx="80" ry="8" fill="#000" opacity=".15"/>
            <path d="${caminhoEngrenagem(80, 118, 62, 50, 14, 0.1)}" fill="url(#aco${u})" stroke="#374151" stroke-width="2" stroke-linejoin="round"/>
            <circle cx="80" cy="118" r="38" fill="none" stroke="#6b7280" stroke-width="3" opacity=".6"/>
            <circle cx="80" cy="118" r="22" fill="#374151"/>
            <circle cx="80" cy="118" r="15" fill="url(#acoInv${u})"/>
            <circle cx="80" cy="118" r="5" fill="#1f2937"/>
            <path d="${caminhoEngrenagem(150, 56, 38, 30, 9, 0.35)}" fill="url(#acoInv${u})" stroke="#374151" stroke-width="2" stroke-linejoin="round"/>
            <circle cx="150" cy="56" r="20" fill="none" stroke="#6b7280" stroke-width="2.5" opacity=".6"/>
            <circle cx="150" cy="56" r="12" fill="#374151"/>
            <circle cx="150" cy="56" r="7" fill="url(#aco${u})"/>
            <path d="M40 86C48 68 62 58 78 56" fill="none" stroke="#ffffff" stroke-opacity=".7" stroke-width="5" stroke-linecap="round"/>`;
        }

        // ---- Automação: braço robótico ----
        if (chave === "autom") {
            return `
            <defs>
              <linearGradient id="laranja${u}" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#fdba74"/><stop offset=".45" stop-color="#f97316"/><stop offset="1" stop-color="#b45309"/>
              </linearGradient>
              <linearGradient id="aco${u}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#9ca3af"/><stop offset="1" stop-color="#374151"/>
              </linearGradient>
              <radialGradient id="junta${u}" cx="40%" cy="35%" r="70%">
                <stop offset="0" stop-color="#f3f4f6"/><stop offset="1" stop-color="#4b5563"/>
              </radialGradient>
            </defs>
            <ellipse cx="100" cy="188" rx="76" ry="7" fill="#000" opacity=".18"/>
            <rect x="40" y="168" width="120" height="18" rx="6" fill="url(#aco${u})" stroke="#1f2937"/>
            <rect x="84" y="138" width="32" height="32" rx="4" fill="url(#aco${u})" stroke="#1f2937"/>
            <g transform="rotate(35 100 138)">
              <rect x="88" y="64" width="24" height="76" rx="11" fill="url(#laranja${u})" stroke="#9a3412" stroke-width="1.5"/>
            </g>
            <circle cx="100" cy="138" r="16" fill="url(#junta${u})" stroke="#1f2937" stroke-width="1.5"/>
            <g transform="rotate(-50 141 79)">
              <rect x="130" y="26" width="22" height="58" rx="10" fill="url(#laranja${u})" stroke="#9a3412" stroke-width="1.5"/>
            </g>
            <circle cx="141" cy="79" r="13" fill="url(#junta${u})" stroke="#1f2937" stroke-width="1.5"/>
            <g transform="translate(101 46) rotate(-50)">
              <rect x="-13" y="-8" width="26" height="12" rx="3" fill="url(#aco${u})" stroke="#1f2937"/>
              <rect x="-13" y="-26" width="7" height="20" rx="2" fill="url(#aco${u})" stroke="#1f2937"/>
              <rect x="6" y="-26" width="7" height="20" rx="2" fill="url(#aco${u})" stroke="#1f2937"/>
            </g>
            <circle cx="100" cy="138" r="5" fill="#1f2937"/><circle cx="141" cy="79" r="4" fill="#1f2937"/>`;
        }

        // ---- Segurança do Trabalho: capacete ----
        if (chave === "seg") {
            return `
            <defs>
              <linearGradient id="amarelo${u}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#fef08a"/><stop offset=".55" stop-color="#facc15"/><stop offset="1" stop-color="#ca8a04"/>
              </linearGradient>
            </defs>
            <ellipse cx="100" cy="172" rx="82" ry="10" fill="#000" opacity=".18"/>
            <path d="M22 142c0-12 18-18 38-18h80c20 0 38 6 38 18s-18 18-38 18H60c-20 0-38-6-38-18z" fill="url(#amarelo${u})" stroke="#a16207" stroke-width="2"/>
            <path d="M36 130C36 70 64 34 100 34s64 36 64 96z" fill="url(#amarelo${u})" stroke="#a16207" stroke-width="2"/>
            <path d="M87 36c-3 28-3 64-1 94h28c2-30 2-66-1-94-8-3-18-3-26 0z" fill="#eab308" stroke="#a16207" stroke-width="1.5"/>
            <path d="M68 44c-12 16-20 46-20 86M132 44c12 16 20 46 20 86" fill="none" stroke="#ca8a04" stroke-width="2.5" opacity=".7"/>
            <rect x="36" y="122" width="128" height="10" rx="3" fill="#ca8a04" opacity=".85"/>
            <path d="M56 100C58 76 70 56 88 46" fill="none" stroke="#fffbe0" stroke-width="7" stroke-linecap="round" opacity=".8"/>`;
        }

        // ---- Desenvolvimento de Sistemas: notebook com código ----
        return `
            <defs>
              <linearGradient id="tela${u}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#0f172a"/><stop offset="1" stop-color="#1e293b"/>
              </linearGradient>
              <linearGradient id="base${u}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#e5e7eb"/><stop offset="1" stop-color="#9ca3af"/>
              </linearGradient>
              <radialGradient id="luz${u}" cx="50%" cy="40%" r="60%">
                <stop offset="0" stop-color="#38bdf8" stop-opacity=".35"/><stop offset="1" stop-color="#38bdf8" stop-opacity="0"/>
              </radialGradient>
            </defs>
            <ellipse cx="100" cy="170" rx="86" ry="8" fill="#000" opacity=".18"/>
            <rect x="38" y="34" width="124" height="86" rx="8" fill="#374151" stroke="#111827" stroke-width="2"/>
            <rect x="45" y="41" width="110" height="72" rx="3" fill="url(#tela${u})"/>
            <rect x="45" y="41" width="110" height="72" rx="3" fill="url(#luz${u})"/>
            <rect x="53" y="50" width="34" height="5" rx="2.5" fill="#4ade80"/>
            <rect x="60" y="60" width="52" height="5" rx="2.5" fill="#60a5fa"/>
            <rect x="60" y="70" width="40" height="5" rx="2.5" fill="#fb923c"/>
            <rect x="67" y="80" width="58" height="5" rx="2.5" fill="#c084fc"/>
            <rect x="60" y="90" width="30" height="5" rx="2.5" fill="#f472b6"/>
            <rect x="53" y="100" width="24" height="5" rx="2.5" fill="#4ade80"/>
            <rect x="82" y="100" width="3" height="6" fill="#e2e8f0"/>
            <path d="M45 41h60l-30 72H45z" fill="#ffffff" opacity=".05"/>
            <path d="M16 126h168l-12 28H28z" fill="url(#base${u})" stroke="#6b7280" stroke-width="1.5"/>
            <path d="M16 126h168" stroke="#4b5563" stroke-width="3"/>
            <path d="M40 134h120M38 141h124M36 148h128" stroke="#6b7280" stroke-width="3" stroke-dasharray="6 3" opacity=".7"/>
            <rect x="82" y="148" width="36" height="4" rx="2" fill="#6b7280"/>`;
    }

    // Retorna o SVG pronto (cada um com ids únicos para não conflitar na página)
    function icone(chave, tamanho) {

        contadorIcones++;

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 200 200\" width=\"" + tamanho +
               "\" height=\"" + tamanho + "\" style=\"vertical-align:middle;flex-shrink:0;\" role=\"img\" aria-label=\"" +
               cursos[chave].nome + "\">" + conteudoIcone(chave, contadorIcones) + "</svg>";
    }

    // ==========================================
    // RESULTADO
    // ==========================================

    // Resumo do desempate: quem empatou e quantos pontos cada uma fez nas perguntas extras
    function resumoDesempate() {

        var envolvidas = Object.keys(cursos).filter(function (chave) {
            return cursos[chave].extra > 0;
        });

        if (envolvidas.length < 2) { return ""; }

        envolvidas.sort(function (a, b) {
            if (a === vencedorChave) { return -1; }
            if (b === vencedorChave) { return 1; }
            return cursos[b].extra - cursos[a].extra;
        });

        var partes = envolvidas.map(function (chave) {
            return (chave === vencedorChave ? "<strong>" + cursos[chave].nome + "</strong>" : cursos[chave].nome) +
                   ": " + cursos[chave].extra;
        });

        return "Empate em " + cursos[vencedorChave].pontos + " pontos. Pontos no desempate → " + partes.join(" · ");
    }

    // somenteOutros = true: mostra só os outros cursos, em barras menores
    function montarBarras(somenteOutros) {

        var maxPontos = PERGUNTAS_POR_AREA * 5;

        var chaves = Object.keys(cursos).sort(function (a, b) {
            if (a === vencedorChave) { return -1; }
            if (b === vencedorChave) { return 1; }

            return (cursos[b].pontos - cursos[a].pontos) ||
                   (cursos[b].extra - cursos[a].extra);
        });

        if (somenteOutros) {
            chaves = chaves.filter(function (chave) { return chave !== vencedorChave; });
        }

        var tamanhoIcone = somenteOutros ? 24 : 30;
        var alturaBarra = somenteOutros ? 10 : 14;
        var margem = somenteOutros ? 9 : 14;

        var html = "";

        chaves.forEach(function (chave) {

            var curso = cursos[chave];
            var porcentagem = (curso.pontos / maxPontos) * 100;
            var ehVencedor = !somenteOutros && chave === vencedorChave;

            html +=
                "<div style=\"margin:" + margem + "px 0;\">" +
                    "<div style=\"display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:.92rem;font-weight:" + (ehVencedor ? "800" : "600") + ";\">" +
                        "<span style=\"display:flex;align-items:center;gap:8px;\">" + icone(chave, tamanhoIcone) + curso.nome + (ehVencedor ? " 🏆" : "") + "</span>" +
                        "<span>" + curso.pontos + " / " + maxPontos +
                        (curso.extra > 0 ? " <strong style=\"color:" + curso.cor + ";\">+ " + curso.extra + " desempate</strong>" : "") + "</span>" +
                    "</div>" +
                    "<div style=\"background:#e5e7eb;border-radius:999px;height:" + alturaBarra + "px;overflow:hidden;margin-top:5px;\">" +
                        "<div style=\"width:" + porcentagem + "%;height:100%;background:" + curso.cor + ";border-radius:999px;\"></div>" +
                    "</div>" +
                "</div>";
        });

        if (!somenteOutros && rodada > 0) {
            html += "<p style=\"font-size:.85rem;opacity:.75;margin-top:12px;\">" +
                "Houve empate na maior pontuação, então você respondeu perguntas extras de desempate" +
                (rodada > 1 ? " (" + rodada + " rodadas)" : "") + ".<br>" + resumoDesempate() + "</p>";
        }

        return html;
    }

    function mostrarResultado() {

        progressFill.style.width = "100%";

        if (!vencedorChave) {
            vencedorChave = areasComMaiorPontuacao()[0];
        }

        var vencedor = cursos[vencedorChave];

        salvarResultado();   // grava no banco (não atrapalha o quiz se falhar)

        if (winnerElement) {
            winnerElement.style.background = vencedor.cor;
            winnerElement.style.color = "#ffffff";
            winnerElement.style.borderRadius = "14px";
            winnerElement.style.padding = "18px";

            winnerElement.innerHTML =
                "<div style=\"display:flex;justify-content:center;margin-bottom:8px;\"><span style=\"background:#ffffff;border-radius:50%;padding:8px;display:inline-flex;box-shadow:0 3px 10px rgba(0,0,0,.25);\">" + icone(vencedorChave, 84) + "</span></div>" +
                "<strong style=\"color:#ffffff;\">" + vencedor.nome + "</strong><br>" +
                "<span style=\"color:#ffffff;\">Essa área teve a maior pontuação nas suas respostas.</span>";
        }

        if (rankingElement) {
            rankingElement.innerHTML = montarBarras(false);
        }

        // HTML sem tela de resultado separada: vai direto para a explicação
        if (!telaResultado) {
            mostrarExplicacaoResultado();
            return;
        }

        mostrarTela(telaResultado);
    }

    // ==========================================
    // EXPLICAÇÃO DO RESULTADO (compacta: cabe sem rolar)
    // ==========================================

    // Cria (se não existir) um elemento dentro da tela de explicação, antes dos botões
    function elementoDaExplicacao(id, antesDosBotoes) {

        var el = document.getElementById(id);

        if (!el) {
            el = document.createElement(id === "share-status" ? "p" : "div");
            el.id = id;

            var acoes = telaExplicacao.querySelector(".actions");

            if (antesDosBotoes && acoes) {
                telaExplicacao.insertBefore(el, acoes);
            } else {
                telaExplicacao.appendChild(el);
            }
        }

        return el;
    }

    function mostrarExplicacaoResultado() {

        var vencedor = cursos[vencedorChave];

        if (!vencedor) {
            return;
        }

        var maxPontos = PERGUNTAS_POR_AREA * 5;

        var etiquetas = (vencedor.profissoes || [])
            .slice(0, PROFISSOES_EXIBIDAS)
            .map(function (profissao) {
                return "<div style=\"background:#ffffff;color:#1f2937;border-radius:10px;padding:8px 10px;" +
                       "font-size:.82rem;font-weight:600;line-height:1.25;\">" + profissao + "</div>";
            }).join("");

        explanationElement.style.background = vencedor.cor;
        explanationElement.style.color = "#ffffff";
        explanationElement.style.borderRadius = "14px";
        explanationElement.style.padding = "16px";

        explanationElement.innerHTML =
            "<div style=\"display:flex;align-items:center;gap:14px;flex-wrap:wrap;\">" +
                "<span style=\"background:#ffffff;border-radius:50%;padding:6px;display:inline-flex;flex-shrink:0;box-shadow:0 3px 10px rgba(0,0,0,.25);\">" +
                    icone(vencedorChave, 76) +
                "</span>" +
                "<div style=\"flex:1 1 220px;min-width:0;\">" +
                    "<span class=\"winner-label\" style=\"display:block;color:#ffffff;margin:0;font-size:.7rem;\">" +
                        "Sua maior afinidade · " + vencedor.pontos + "/" + maxPontos + " pontos" +
                        (vencedor.extra > 0 ? " + " + vencedor.extra + " no desempate" : "") +
                    "</span>" +
                    "<span class=\"winner-name\" style=\"display:block;color:#ffffff;margin:2px 0 4px;font-size:1.4rem;line-height:1.15;\">" +
                        vencedor.nome +
                    "</span>" +
                    "<span class=\"winner-desc\" style=\"display:block;color:#ffffff;margin:0;font-size:.9rem;line-height:1.35;\">" +
                        (vencedor.descricao || "Suas respostas indicam maior afinidade com esta área.") +
                    "</span>" +
                    (resumoDesempate()
                        ? "<span style=\"display:block;margin-top:6px;background:rgba(255,255,255,.2);border-radius:8px;padding:6px 9px;font-size:.8rem;line-height:1.3;\">" + resumoDesempate() + "</span>"
                        : "") +
                "</div>" +
            "</div>" +
            (etiquetas
                ? "<div style=\"margin-top:12px;\">" +
                      "<div style=\"font-weight:800;font-size:.9rem;margin-bottom:8px;color:#ffffff;\">Profissões que essa área abre</div>" +
                      "<div style=\"display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;\">" + etiquetas + "</div>" +
                  "</div>"
                : "");

        // Abaixo do card (precisa rolar): pontuação dos outros cursos e os botões
        var placar = elementoDaExplicacao("scores", true);

        placar.style.margin = "14px 0 6px";
        placar.innerHTML =
            "<div style=\"font-weight:800;font-size:.95rem;margin-bottom:4px;\">Pontuação dos outros cursos</div>" +
            montarBarras(true);

        var aviso = elementoDaExplicacao("share-status", false);
        aviso.textContent = "";

        mostrarTela(telaExplicacao);
        prepararStory();
        montarPublicacaoInstagram();
    }

    // ==========================================
    // VOLTAR PARA A TELA DE NOME (LOGIN)
    // ==========================================

    function voltarParaLogin() {

        perguntaAtual = 0;
        vencedorChave = null;
        fase = "normal";
        rodada = 0;
        historico = [];
        salvarPromise = null;
        storyPromise = null;
        instagramEscola = { ativo: false, conta: CONTA_PADRAO };
        var painelIg = document.getElementById("publicar-ig");
        if (painelIg && painelIg.parentNode) { painelIg.parentNode.removeChild(painelIg); }
        participante = { nome: "", celular: "", instagram: "" };
        perguntasJaUsadas = {};
        fila = [];

        zerarPontos();

        nomeInput.value = "";
        celularInput.value = "";
        if (instagramInput) { instagramInput.value = ""; }

        loginError.textContent = "";

        mostrarTela(telaLogin);
    }

    if (botaoVerExplicacao) { botaoVerExplicacao.addEventListener("click", mostrarExplicacaoResultado); }
    if (botaoRefazer) { botaoRefazer.addEventListener("click", voltarParaLogin); }
    if (botaoRefazerExplicacao) { botaoRefazerExplicacao.addEventListener("click", voltarParaLogin); }

    // ==========================================
    // BANCO DE DADOS (API em PHP + MySQL)
    // ==========================================

    // Envia JSON para a API. Tenta de novo uma vez se o servidor estiver indisponível.
    function enviarJson(arquivo, dados, novasTentativas) {

        return fetch(API_URL + arquivo, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(dados),
            keepalive: true
        }).then(function (resposta) {

            return resposta.json().catch(function () { return {}; }).then(function (json) {

                if (resposta.ok && json.ok) {
                    return json;
                }

                var erro = new Error(json.erro || ("HTTP " + resposta.status));
                erro.status = resposta.status;
                throw erro;
            });

        }).catch(function (erro) {

            var temporario = !erro.status || erro.status >= 500;

            if (temporario && novasTentativas > 0) {
                return new Promise(function (ok) { setTimeout(ok, 2500); }).then(function () {
                    return enviarJson(arquivo, dados, novasTentativas - 1);
                });
            }

            throw erro;
        });
    }

    // Grava o resultado do quiz. Se a API estiver fora do ar, o quiz continua normalmente.
    function salvarResultado() {

        if (!API_URL || salvarPromise) {
            return;
        }

        salvarPromise = enviarJson("salvar.php", {
            nome: participante.nome,
            celular: participante.celular,
            instagram: participante.instagram,
            vencedor: vencedorChave,
            respostas: historico
        }, 1).then(function (json) {
            // O servidor informa se a escola publica nos Stories e de qual conta
            if (json.instagram && json.instagram.ativo && json.instagram.conta) {
                instagramEscola = { ativo: true, conta: json.instagram.conta };
            }
            return json.id;
        }).catch(function (erro) {
            console.warn("Resultado não foi salvo no banco de dados:", erro.message);
            return null;
        });
    }

    // Registra o clique em "Compartilhar no Instagram"
    function registrarCompartilhamento() {

        if (!API_URL || !salvarPromise) {
            return;
        }

        salvarPromise.then(function (id) {
            if (id) {
                return enviarJson("compartilhar.php", { tentativa_id: id }, 1);
            }
        }).catch(function () {});
    }

    // ==========================================
    // COMPARTILHAR NO INSTAGRAM
    // ==========================================

    var storyPromise = null;

    // Conta do Instagram da escola (definida no painel do administrador)
    var CONTA_PADRAO = "@senai.maravilha";
    var instagramEscola = { ativo: false, conta: CONTA_PADRAO };

    function instagramDigitado() {

        var instagram = instagramInput ? instagramInput.value.trim() : "";

        if (instagram && instagram.charAt(0) !== "@") {
            instagram = "@" + instagram;
            instagramInput.value = instagram;
        }

        return instagram;
    }

    function carregarImagem(src) {
        return new Promise(function (resolve) {
            var img = new Image();
            var terminou = false;

            function fim() {
                if (!terminou) { terminou = true; resolve(img); }
            }

            img.onload = fim;
            img.onerror = fim;
            setTimeout(fim, 3000);   // não trava se a imagem demorar
            img.src = src;
        });
    }

    function quebrarTexto(ctx, texto, larguraMax) {

        var palavras = texto.split(" ");
        var linha = "";
        var linhas = [];

        palavras.forEach(function (palavra) {
            var teste = linha ? linha + " " + palavra : palavra;

            if (ctx.measureText(teste).width > larguraMax && linha) {
                linhas.push(linha);
                linha = palavra;
            } else {
                linha = teste;
            }
        });

        if (linha) { linhas.push(linha); }

        return linhas;
    }

    // Desenha a imagem 1080x1920 (formato Story) e devolve um arquivo PNG
    async function criarArquivoStory(chave, instagram, conta) {

        var vencedor = cursos[chave];

        var canvas = document.createElement("canvas");
        canvas.width = 1080;
        canvas.height = 1920;

        var ctx = canvas.getContext("2d");

        // Fundo
        var fundo = ctx.createLinearGradient(0, 0, 0, 1920);
        fundo.addColorStop(0, "#0b2536");
        fundo.addColorStop(1, "#123044");
        ctx.fillStyle = fundo;
        ctx.fillRect(0, 0, 1080, 1920);

        // Faixa na cor da área
        ctx.fillStyle = vencedor.cor;
        ctx.fillRect(0, 0, 1080, 24);

        ctx.fillStyle = "#ffffff";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";

        ctx.font = "bold 64px Arial";
        ctx.fillText("MEU RESULTADO", 540, 230);

        ctx.font = "bold 72px Arial";
        quebrarTexto(ctx, vencedor.nome.toUpperCase(), 940).forEach(function (linha, i) {
            ctx.fillText(linha, 540, 420 + i * 84);
        });

        // Ilustração da área sobre um círculo branco
        ctx.beginPath();
        ctx.arc(540, 790, 250, 0, Math.PI * 2);
        ctx.fillStyle = "#ffffff";
        ctx.fill();

        var svg = icone(chave, 400);
        var imagemArea = await carregarImagem("data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg));

        if (imagemArea.naturalWidth > 0) {
            ctx.drawImage(imagemArea, 340, 590, 400, 400);
        }

        // Linha
        ctx.fillStyle = vencedor.cor;
        ctx.fillRect(280, 1075, 520, 8);

        // Descrição
        ctx.fillStyle = "#ffffff";
        ctx.font = "30px Arial";

        quebrarTexto(ctx, vencedor.descricao || "", 860).slice(0, 3).forEach(function (linha, i) {
            ctx.fillText(linha, 540, 1135 + i * 42);
        });

        // Profissões
        ctx.font = "bold 34px Arial";
        ctx.fillText("Possíveis profissões", 540, 1295);

        ctx.font = "30px Arial";

        var y = 1350;

        (vencedor.profissoes || []).slice(0, 3).forEach(function (profissao) {
            ctx.fillText("• " + profissao, 540, y);
            y += 52;
        });

        // Instagram da pessoa (se informou) e do SENAI
        ctx.font = "bold 46px Arial";

        if (instagram) {
            ctx.fillText(instagram, 540, 1560);
        }

        ctx.fillText(conta || CONTA_PADRAO, 540, 1640);

        ctx.font = "30px Arial";
        ctx.fillText("Descubra seu caminho profissional!", 540, 1715);

        // Logo SENAI (se existir)
        var logo = await carregarImagem("img/senai.png");

        if (logo.naturalWidth > 0) {
            var logoLargura = 190;
            var logoAltura = logoLargura * (logo.naturalHeight / logo.naturalWidth);

            ctx.drawImage(logo, 1080 - logoLargura - 45, 1920 - logoAltura - 45, logoLargura, logoAltura);
        }

        var blob = await new Promise(function (resolve) {
            canvas.toBlob(resolve, "image/png");
        });

        return new File([blob], "resultado-story.png", { type: "image/png" });
    }

    // Gera a imagem antes do clique (o navegador só libera o compartilhamento
    // logo após o toque do usuário, por isso ela precisa estar pronta)
    function prepararStory() {

        if (!vencedorChave) { return; }

        // Espera (no máximo 3 s) o servidor dizer qual é a conta da escola
        var esperaServidor = salvarPromise
            ? Promise.race([salvarPromise, new Promise(function (ok) { setTimeout(ok, 3000); })])
            : Promise.resolve();

        storyPromise = esperaServidor
            .then(function () { return criarArquivoStory(vencedorChave, instagramDigitado(), instagramEscola.conta); })
            .catch(function (erro) {
                console.error("Não foi possível criar a imagem:", erro);
                return null;
            });
    }

    function baixarArquivo(arquivo) {

        var url = URL.createObjectURL(arquivo);
        var link = document.createElement("a");

        link.href = url;
        link.download = arquivo.name;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
    }

    function avisoCompartilhar(texto) {

        var aviso = elementoDaExplicacao("share-status", false);

        aviso.style.fontSize = ".9rem";
        aviso.style.marginTop = "10px";
        aviso.setAttribute("aria-live", "polite");
        aviso.textContent = texto;
    }

    // Abre o Instagram direto: no celular abre a câmera de Stories do app;
    // no computador abre o site em uma nova aba.
    if (botaoInstagram) botaoInstagram.addEventListener("click", async function () {

        var vencedor = cursos[vencedorChave];

        if (!vencedor) {
            return;
        }

        registrarCompartilhamento();

        var instagram = instagramDigitado();
        var celular = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

        var texto =
            "Meu resultado no teste de profissões foi " + vencedor.nome + "!\n\n" +
            "Possíveis profissões: " + (vencedor.profissoes || []).slice(0, 3).join(", ") + "\n\n" +
            "Descubra qual área combina com você!\n" + instagramEscola.conta +
            (instagram ? "\n\nMeu Instagram: " + instagram : "");

        // Computador: abre a aba do Instagram já no clique (evita bloqueio de pop-up)
        if (!celular) {
            window.open("https://www.instagram.com/", "_blank");
        }

        // Salva a imagem do Story e copia o texto para colar na publicação
        if (!storyPromise) { prepararStory(); }

        var arquivo = await storyPromise;

        if (arquivo) {
            baixarArquivo(arquivo);
        }

        try {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(texto).catch(function () {});
            }
        } catch (erro) {}

        if (!celular) {
            avisoCompartilhar("Imagem salva e texto copiado! No Instagram, publique a imagem nos Stories.");
            return;
        }

        avisoCompartilhar("Imagem salva e texto copiado! No Instagram, escolha a imagem da galeria nos Stories.");

        // Pequena pausa para o download começar, depois abre o app do Instagram
        setTimeout(function () {
            window.location.href = "instagram://story-camera";
        }, 600);

        // Se o app não abriu (não instalado), abre o site
        setTimeout(function () {
            if (!document.hidden) {
                window.open("https://www.instagram.com/", "_blank");
            }
        }, 2600);
    });


    // ==========================================
    // POSTAR NO INSTAGRAM DA ESCOLA (marcando o @ da pessoa)
    // ==========================================

    function escaparHtml(texto) {
        return String(texto).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[c];
        });
    }

    function usuarioInstagramValido(texto) {
        return /^@[A-Za-z0-9._]{1,30}$/.test(texto);
    }

    // Mostra o bloco "Postar no Instagram do SENAI" se a escola ativou isso no painel
    function montarPublicacaoInstagram() {

        var painel = document.getElementById("publicar-ig");

        if (painel && painel.parentNode) { painel.parentNode.removeChild(painel); }

        if (!API_URL || !salvarPromise) { return; }

        var promessa = salvarPromise;

        promessa.then(function (tentativaId) {

            if (promessa !== salvarPromise || !tentativaId || !instagramEscola.ativo) {
                return;
            }

            var conta = instagramEscola.conta;
            var jaTem = usuarioInstagramValido(instagramDigitado());

            var bloco = elementoDaExplicacao("publicar-ig", true);

            bloco.style.cssText = "margin:10px 0 4px;padding:12px;border:1px solid #d7dde3;border-radius:12px;text-align:left;";
            bloco.innerHTML =
                "<div style=\"font-weight:800;margin-bottom:6px;\">Poste no Instagram do SENAI</div>" +
                "<p style=\"font-size:.85rem;margin:0 0 8px;\">Seu resultado vai para os Stories de <strong>" + escaparHtml(conta) +
                "</strong> marcando o seu @.</p>" +
                (jaTem ? "" :
                    "<input type=\"text\" id=\"ig-pessoa\" placeholder=\"Seu @ do Instagram (ex.: @maria)\" maxlength=\"31\" " +
                    "autocapitalize=\"none\" autocorrect=\"off\" style=\"width:100%;box-sizing:border-box;padding:10px;border:1px solid #c5ccd3;border-radius:8px;margin-bottom:8px;\">") +
                "<label style=\"display:flex;gap:8px;align-items:flex-start;font-size:.8rem;margin-bottom:8px;\">" +
                "<input type=\"checkbox\" id=\"ig-aceite\" style=\"margin-top:2px;\">" +
                "<span>Autorizo o SENAI a publicar meu resultado e marcar meu @ em " + escaparHtml(conta) +
                ". Se tenho menos de 18 anos, tenho autorização do meu responsável. Perfis privados não podem ser marcados.</span></label>" +
                "<button type=\"button\" class=\"btn\" id=\"ig-postar\">Postar no Instagram do SENAI</button>" +
                "<p id=\"ig-status\" aria-live=\"polite\" style=\"font-size:.85rem;margin:8px 0 0;\"></p>";

            if (botaoInstagram) { botaoInstagram.textContent = "Salvar imagem e postar no meu perfil"; }

            document.getElementById("ig-postar").addEventListener("click", function () {
                enviarParaInstagramDaEscola(tentativaId, this);
            });
        }).catch(function () {});
    }

    async function enviarParaInstagramDaEscola(tentativaId, botao) {

        var status = document.getElementById("ig-status");
        var campo = document.getElementById("ig-pessoa");
        var aceite = document.getElementById("ig-aceite");

        function dizer(texto, erro) {
            status.textContent = texto;
            status.style.color = erro ? "#b3261e" : "#1b6e3a";
        }

        var pessoa = campo ? campo.value.trim() : instagramDigitado();

        if (pessoa && pessoa.charAt(0) !== "@") { pessoa = "@" + pessoa; }

        if (!usuarioInstagramValido(pessoa)) {
            dizer("Digite um @ de Instagram válido para ser marcado.", true);
            return;
        }

        if (!aceite.checked) {
            dizer("Marque a autorização para poder postar.", true);
            return;
        }

        botao.disabled = true;
        dizer("Enviando…", false);

        try {
            // Imagem refeita com o @ digitado agora
            var arquivo = await criarArquivoStory(vencedorChave, pessoa, instagramEscola.conta);

            var dados = new FormData();
            dados.append("tentativa_id", tentativaId);
            dados.append("instagram", pessoa);
            dados.append("imagem", arquivo);

            var resposta = await fetch(API_URL + "publicar.php", { method: "POST", body: dados });
            var json = await resposta.json().catch(function () { return {}; });

            if (json.indisponivel) {
                botao.style.display = "none";
                dizer("A postagem automática não está disponível agora. Use \"Salvar imagem e postar no meu perfil\".", true);
                return;
            }

            if (!resposta.ok || !json.ok) {
                botao.disabled = false;
                dizer(json.erro || "Não foi possível enviar agora. Tente de novo.", true);
                return;
            }

            if (campo) { campo.disabled = true; }
            aceite.disabled = true;

            if (json.status === "publicada") {
                dizer("Já publicado nos Stories de " + json.conta + "!", false);
            } else {
                dizer("Enviado! Depois que a equipe do SENAI aprovar, seu resultado aparece nos Stories de " + json.conta + " marcando " + pessoa + ".", false);
            }

            botao.textContent = "Enviado";
        } catch (erro) {
            console.warn("Falha ao enviar para o Instagram:", erro);
            botao.disabled = false;
            dizer("Sem conexão com o servidor. Tente de novo.", true);
        }
    }

    // ==========================================
    // INÍCIO
    // ==========================================

    mostrarTela(telaLogin);

});
