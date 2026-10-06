# Changelog

## [1.1.0] - 2026-10-05

### Adicionado
- **Tela dedicada do assistente** (`front/chatbot.php`), no menu **Assistência › Assistente de chamados** (interface padrão) e no menu **Plugins** do autoatendimento.
- Botão **⤢ Abrir em tela cheia** no cabeçalho do painel flutuante.
- **Anexo** enviado pelo assistente é gravado como documento do chamado.
- **Consultar meus chamados:** o assistente lista os até 10 chamados em andamento em que o usuário é requerente, com link para cada um.
- **Autoatendimento abre direto no assistente:** a página inicial do perfil de autoatendimento leva à tela dedicada (para ver a página inicial normal: `?no_chatbot=1`).

### Corrigido
- O botão flutuante cobria botões como **Salvar** e **Adicionar**: agora ele sobe automaticamente quando ficaria por cima de um botão ou rodapé da página, e não aparece nas telas de formulário (`*.form.php`).
- **Anexo:** o documento era criado sem o arquivo (o chamado mostrava o anexo, mas ele não abria). Agora o arquivo é gravado pelo próprio GLPI, que confere o tipo e o tamanho. Se o anexo for recusado (por exemplo, `.exe`), o chamado é aberto e o usuário vê o motivo.
- O menu da sessão era refeito em toda página para quem não pode abrir chamados; agora a atualização do menu acontece uma vez por sessão.

### Alterado
- Botão flutuante compacto (só o ícone 💬); o texto "Abrir chamado" aparece ao passar o mouse.
- `z-index` abaixo das janelas modais do GLPI.

## [1.0.0] - 2026-10-01

### Adicionado
- Assistente guiado (sem IA) para abertura de chamados no GLPI 11.
- Fluxo: tipo (problema/solicitação) → unidade → área → assunto → categoria → resumo → detalhes → urgência → confirmação.
- Botão "Voltar" em cada nível, busca quando há muitas opções e "Outro assunto de..." para usar a categoria do nível atual.
- Resumo antes de abrir o chamado, com correção de um campo específico.
- Categorias filtradas pelas regras do GLPI: entidade, visibilidade na interface simplificada e tipo do chamado.
- Escolha da unidade (matriz/filiais) quando o usuário tem acesso a mais de uma entidade.
- Chamado criado como o próprio usuário: regras de negócio, atribuição por categoria, modelos e notificações funcionam normalmente.
- Endpoints com login obrigatório, proteção CSRF do GLPI e validação da categoria no servidor.
- Visual integrado ao tema do GLPI (claro/escuro), com tela cheia no celular.
