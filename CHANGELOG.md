# Changelog

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
