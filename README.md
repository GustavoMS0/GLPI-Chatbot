# GLPI Chatbot

**Assistente guiado para abertura de chamados no GLPI.** Em vez de um formulário, a pessoa conversa com o assistente, que pergunta uma coisa de cada vez e abre o chamado já na categoria certa.

```
💬 Olá, Caio! 👋 Vou te ajudar a abrir um chamado.
   É um problema (algo parou de funcionar) ou uma solicitação (um pedido)?
   [ 🔧 Problema ]  [ 📋 Solicitação ]
   → Qual área?          [ TI ] [ RH ] [ Financeiro ] [ Marketing ]
   → Sobre qual assunto? [ Impressoras ] [ Hardware ] [ Rede e Internet ] ...
   → Qual opção?         [ Atolamento de papel ] [ Impressora não imprime ]
   → Resumo, detalhes e urgência
   → Confira o seu chamado... [ ✅ Abrir chamado ] [ ✏️ Corrigir ]
✅ Pronto! Seu chamado #123 foi aberto e encaminhado para a equipe responsável.
```

| Versão do GLPI | Suporte |
|---|---|
| **11.0.x** | ✅ |
| 10.0.x | ❌ |

> **Sem inteligência artificial:** o assistente é guiado por botões. Não depende de serviços externos, não tem custo de uso e nenhum dado sai do seu servidor.

---

## Funcionalidades

- **Botão "Abrir chamado"** flutuante e compacto, no autoatendimento e na interface padrão, para quem pode abrir chamados. Ele sobe sozinho para não cobrir botões como **Salvar** e não aparece nas telas de formulário.
- **Tela dedicada** em **Assistência › Assistente de chamados** (e no menu **Plugins** do autoatendimento), com o assistente ocupando a área de conteúdo.
- **Conversa guiada:** tipo, unidade, área, assunto, categoria, resumo, detalhes e urgência, com botão **Voltar** em cada nível e **busca** quando há muitas opções.
- **Categorias certas para cada pessoa:** usa as mesmas regras do GLPI (entidade, "visível na interface simplificada" e tipo incidente ou requisição). Em um **problema**, só aparecem categorias de incidente.
- **"Outro assunto de..."** quando nenhuma opção serve: o chamado vai para a categoria do nível atual (por exemplo *TI › Impressoras*), sem ficar sem dono.
- **Matriz e filiais:** se a pessoa tem acesso a mais de uma unidade, o assistente pergunta para qual é o chamado.
- **Resumo antes de abrir**, com a opção de **corrigir** só o campo errado.
- **Chamado aberto como o próprio usuário:** regras de negócio, atribuição automática pela categoria, modelos de chamado e notificações por e-mail funcionam normalmente.
- **Seguro:** os endpoints exigem login, o envio é protegido pelo token CSRF do GLPI, a categoria é validada no servidor e o texto digitado nunca é interpretado como HTML.
- **Visual do GLPI:** usa as cores do tema da instância (claro ou escuro) e ocupa a tela inteira no celular.
- **Não altera o banco de dados:** instalar ou remover o plugin não cria nem apaga nada.

---

## Instalação

1. Baixe o arquivo **`glpichatbot-x.y.z.zip`** da [última Release](https://github.com/GustavoMS0/GLPI-Chatbot/releases/latest).
2. Extraia na pasta de plugins do GLPI. A estrutura deve ficar assim:
   ```
   glpi/plugins/glpichatbot/setup.php
   ```
3. No GLPI, acesse **Configurar › Plugins** e clique em **Instalar** e depois em **Ativar** no **GLPI Chatbot**.

Pronto. O botão **💬 Abrir chamado** aparece no canto inferior direito.

### Pelo terminal (servidor Linux)

```bash
cd /var/www/glpi/plugins            # ajuste para o caminho do seu GLPI
VERSAO=$(curl -fsSL https://api.github.com/repos/GustavoMS0/GLPI-Chatbot/releases/latest | grep -oP '"tag_name": "v\K[^"]+')
sudo curl -fsSL -o glpichatbot.zip "https://github.com/GustavoMS0/GLPI-Chatbot/releases/download/v$VERSAO/glpichatbot-$VERSAO.zip"
sudo unzip -o glpichatbot.zip && rm glpichatbot.zip
sudo chown -R root:root glpichatbot

sudo -u www-data php ../bin/console plugin:install --username=glpi glpichatbot
sudo -u www-data php ../bin/console plugin:activate glpichatbot
```

---

## Para o assistente funcionar bem

O assistente mostra **as categorias que já existem no seu GLPI**. Para uma boa experiência:

- **Organize as categorias em níveis** (área › assunto › caso) em **Configurar › Listas suspensas › Categorias ITIL**.
- Marque em cada categoria se ela vale para **incidente**, **requisição** ou ambos, e se é **visível na interface simplificada**.
- Defina o **grupo responsável** de cada categoria, para os chamados caírem direto na equipe certa.

> Usando o [**Install-Gm**](https://github.com/GustavoMS0/Install-Gm) para instalar o GLPI, tudo isso já vem pronto: catálogo com TI, RH, Financeiro e Marketing, grupos de atendimento, perfis de acesso e regras.

Combina com o plugin [**Cascater**](https://github.com/GustavoMS0/Cascater), que deixa o campo de categoria em cascata no formulário de chamado dos técnicos.

---

## Perguntas frequentes

**Quem vê o botão?**
Qualquer usuário logado com permissão de **criar chamados** (Assistência › Chamados › Criar) no perfil ativo. Quem não tem essa permissão não vê o botão.

**O chamado aberto pelo assistente é diferente de um chamado normal?**
Não. Ele é criado pelo mesmo mecanismo do GLPI, com o usuário como requerente. A descrição termina com a observação *"Aberto pelo assistente de chamados."*

**E se a categoria tiver um modelo de chamado com campos obrigatórios?**
O GLPI valida normalmente. Se faltar algum campo que o assistente não pergunta, ele mostra a mensagem do GLPI e oferece **Corrigir** ou **Cancelar**. Nesse caso, use o formulário padrão para essa categoria.

**Dá para anexar arquivos?**
Sim! O assistente conta com a etapa de anexos para envio de imagens, prints, documentos e PDFs, vinculados diretamente ao chamado no GLPI. Além disso, após a abertura é possível anexar arquivos adicionais pelo acompanhamento no link **Ver chamado**.

**Funciona com SSO ou LDAP?**
Sim. O assistente usa a sessão do usuário já logado no GLPI, qualquer que seja a forma de login.

---

## Solução de problemas

| Sintoma | O que verificar |
|---|---|
| O botão não aparece | Plugin **Ativado** em Configurar › Plugins; perfil com permissão de criar chamados; limpe o cache do navegador (Ctrl+F5) |
| "Não há categorias disponíveis" | Categorias marcadas para o tipo escolhido (incidente ou requisição), visíveis na interface simplificada e na entidade do usuário |
| "Não consegui abrir o chamado: ..." | A mensagem após os dois pontos vem do GLPI (por exemplo, campo obrigatório do modelo de chamado). Veja também o `php-errors.log` do GLPI (em `files/_log/` ou, se instalado pelo Install-Gm, em `/var/log/glpi/`) |
| Erro 404 para `chatbot.js` no console do navegador | A pasta precisa se chamar exatamente `glpichatbot`, com a estrutura `plugins/glpichatbot/public/js/chatbot.js` |

---

## Licença

Distribuído sob a licença **GNU General Public License v3.0 ou posterior**. Veja o arquivo [LICENSE](LICENSE).
