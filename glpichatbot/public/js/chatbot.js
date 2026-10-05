/**
 * GLPI Chatbot - assistente guiado para abertura de chamados (GLPI 11)
 *
 * Fluxo: tipo -> unidade (se houver mais de uma) -> área -> assunto -> caso
 *        -> resumo -> detalhes -> urgência -> confirmação -> chamado aberto.
 *
 * Todo texto é inserido com textContent (nunca innerHTML) para evitar XSS.
 *
 * This file is part of GLPI Chatbot (GPLv3+).
 */
(function () {
    'use strict';

    // .../plugins/glpichatbot/js/chatbot.js?v=x  ->  .../plugins/glpichatbot
    const scriptSrc = (document.currentScript && document.currentScript.src) || '';
    let BASE = scriptSrc.replace(/\/js\/chatbot\.js(\?.*)?$/, '').replace(/\/public$/, '');
    if (!BASE) {
        const rootDoc = (window.CFG_GLPI && window.CFG_GLPI.root_doc !== undefined) ? window.CFG_GLPI.root_doc : '';
        BASE = rootDoc + '/plugins/glpichatbot';
    }
    const URLS = {
        bootstrap: BASE + '/ajax/bootstrap.php',
        categories: BASE + '/ajax/categories.php',
        create: BASE + '/ajax/create.php',
    };
    const PAGE_URL = BASE + '/front/chatbot.php';
    const TYPE_LABELS = { 1: 'Problema', 2: 'Solicitação' };
    const URGENCIES = [
        { value: 2, label: '🟢 Baixa', hint: 'pode esperar' },
        { value: 3, label: '🟡 Média', hint: 'atrapalha, mas dá para trabalhar' },
        { value: 4, label: '🟠 Alta', hint: 'impede o meu trabalho' },
        { value: 5, label: '🔴 Muito alta', hint: 'para várias pessoas ou um setor' },
    ];
    const FILTER_THRESHOLD = 8;

    let boot = null;   // dados do usuário (bootstrap.php)
    let ui = null;     // elementos da interface
    let state = null;  // respostas da conversa atual
    let tree = null;   // árvore de categorias carregada

    // ------------------------------------------------------------ utilidades
    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    async function getJSON(url) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const type = response.headers.get('content-type') || '';
        if (!type.includes('application/json')) {
            throw new Error('Resposta inesperada (HTTP ' + response.status + ')');
        }
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'HTTP ' + response.status);
        return data;
    }

    async function postForm(url, fields) {
        const token = document.querySelector('meta[property="glpi:csrf_token"]');
        const body = (fields instanceof FormData) ? fields : new URLSearchParams(fields);
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Glpi-Csrf-Token': token ? token.getAttribute('content') : '',
            },
            body: body,
        });
        let data = {};
        try { data = await response.json(); } catch (e) { /* resposta não JSON */ }
        if (!response.ok) throw new Error(data.error || 'Não foi possível abrir o chamado (HTTP ' + response.status + ').');
        return data;
    }

    function buildTree(list) {
        const byId = {};
        const children = {};
        list.forEach((node) => { byId[node.id] = node; });
        list.forEach((node) => {
            const parent = byId[node.parent] ? node.parent : 0;
            (children[parent] = children[parent] || []).push(node);
        });
        Object.values(children).forEach((items) => items.sort(
            (a, b) => a.name.localeCompare(b.name, 'pt-BR', { sensitivity: 'base', numeric: true })
        ));
        return { byId, children };
    }

    function categoryPath(id) {
        const names = [];
        for (let guard = 0; id && tree.byId[id] && guard < 50; guard++) {
            names.unshift(tree.byId[id].name);
            id = tree.byId[id].parent;
        }
        return names.join(' › ');
    }

    function entityName(id) {
        const entity = boot.entities.find((e) => e.id === id);
        return entity ? entity.name : '';
    }

    // ------------------------------------------------------------ interface
    // mode: 'float' (botão flutuante + painel) | 'page' (tela dedicada)
    function buildUI(mode, container) {
        const isPage = mode === 'page';
        const panel = el('section', 'glpichatbot-panel' + (isPage ? ' glpichatbot-panel-page' : ''));
        panel.setAttribute('role', isPage ? 'region' : 'dialog');
        panel.setAttribute('aria-label', 'Assistente de chamados');
        panel.hidden = !isPage;

        const header = el('header', 'glpichatbot-header');
        const title = el('div', 'glpichatbot-title');
        title.append(el('strong', null, 'Assistente de chamados'), el('small', null, 'Vou te guiar passo a passo'));
        header.append(title);

        if (!isPage) {
            const full = el('a', 'glpichatbot-icon-btn', '⤢');
            full.href = PAGE_URL;
            full.title = 'Abrir em tela cheia';
            full.setAttribute('aria-label', 'Abrir em tela cheia');
            header.append(full);
        }
        const restart = el('button', 'glpichatbot-icon-btn', '↺');
        restart.type = 'button';
        restart.title = 'Recomeçar';
        restart.setAttribute('aria-label', 'Recomeçar');
        header.append(restart);
        restart.addEventListener('click', start);

        const messages = el('div', 'glpichatbot-messages');
        messages.setAttribute('aria-live', 'polite');
        const actions = el('div', 'glpichatbot-actions');
        panel.append(header, messages, actions);

        let launcher = null;
        if (isPage) {
            container.replaceChildren(panel);
        } else {
            const close = el('button', 'glpichatbot-icon-btn', '✕');
            close.type = 'button';
            close.title = 'Fechar';
            close.setAttribute('aria-label', 'Fechar');
            header.append(close);
            close.addEventListener('click', hide);

            launcher = el('button', 'glpichatbot-launcher');
            launcher.type = 'button';
            launcher.title = 'Abrir chamado com o assistente';
            launcher.setAttribute('aria-label', 'Abrir chamado com o assistente');
            launcher.setAttribute('aria-expanded', 'false');
            launcher.append(el('span', 'glpichatbot-launcher-icon', '💬'), el('span', 'glpichatbot-launcher-text', 'Abrir chamado'));
            document.body.append(launcher, panel);

            launcher.addEventListener('click', () => (panel.hidden ? open() : hide()));
            panel.addEventListener('keydown', (event) => { if (event.key === 'Escape') hide(); });
        }

        ui = { mode, launcher, panel, messages, actions };
        if (!isPage) watchPlacement();
    }

    function open() {
        ui.panel.hidden = false;
        if (ui.launcher) {
            ui.launcher.setAttribute('aria-expanded', 'true');
            ui.launcher.classList.add('is-open');
        }
        if (!state) start();
        placeLauncher();
        focusFirstAction();
    }

    function hide() {
        if (ui.mode === 'page') return;
        ui.panel.hidden = true;
        ui.launcher.setAttribute('aria-expanded', 'false');
        ui.launcher.classList.remove('is-open');
        ui.launcher.focus();
        placeLauncher();
    }

    // ------------------------------------------------- posição do botão flutuante
    // Sobe o botão (e o painel) quando ele cobriria um botão/ação da página,
    // como "Salvar" ou "Adicionar" nos rodapés fixos dos formulários do GLPI.
    const BASE_OFFSET = 20;
    const GAP = 12;
    const OBSTACLES = 'button, input[type="submit"], input[type="button"], .btn, a.btn, [role="button"], .sticky-bottom, .card-footer';

    function intersects(a, b, margin) {
        return !(a.right + margin < b.left || a.left - margin > b.right || a.bottom + margin < b.top || a.top - margin > b.bottom);
    }

    function placeLauncher() {
        if (!ui || !ui.launcher) return;
        const launcher = ui.launcher;
        let bottom = BASE_OFFSET;
        launcher.style.bottom = bottom + 'px';

        const zone = window.innerHeight - 260; // só olha a parte de baixo da tela
        const candidates = [];
        document.querySelectorAll(OBSTACLES).forEach((node) => {
            if (ui.panel.contains(node) || launcher.contains(node)) return;
            const rect = node.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0 || rect.bottom < zone || rect.top > window.innerHeight) return;
            const style = window.getComputedStyle(node);
            if (style.visibility === 'hidden' || style.display === 'none') return;
            candidates.push(rect);
        });

        for (let guard = 0; guard < 6; guard++) {
            const mine = launcher.getBoundingClientRect();
            const hit = candidates.find((rect) => intersects(mine, rect, 6));
            if (!hit) break;
            bottom = Math.min(window.innerHeight - mine.height - 10, window.innerHeight - hit.top + GAP);
            launcher.style.bottom = bottom + 'px';
        }

        const launcherHeight = launcher.getBoundingClientRect().height || 48;
        if (window.innerWidth <= 576) { // celular: painel em tela cheia (CSS)
            ui.panel.style.bottom = '';
            ui.panel.style.maxHeight = '';
            return;
        }
        ui.panel.style.bottom = (bottom + launcherHeight + 12) + 'px';
        ui.panel.style.maxHeight = 'calc(100vh - ' + (bottom + launcherHeight + 30) + 'px)';
    }

    function watchPlacement() {
        let scheduled = false;
        const schedule = () => {
            if (scheduled) return;
            scheduled = true;
            window.requestAnimationFrame(() => { scheduled = false; placeLauncher(); });
        };
        window.addEventListener('resize', schedule, { passive: true });
        window.addEventListener('scroll', schedule, { passive: true, capture: true });
        let timer = null;
        new MutationObserver((mutations) => {
            const external = mutations.some((m) => !ui.panel.contains(m.target) && !ui.launcher.contains(m.target));
            if (!external) return;
            clearTimeout(timer);
            timer = setTimeout(schedule, 250);
        }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style', 'hidden'] });
        schedule();
        setTimeout(schedule, 800); // após o GLPI terminar de montar a tela
    }

    function scrollDown() {
        ui.messages.scrollTop = ui.messages.scrollHeight;
    }

    function focusFirstAction() {
        const target = ui.actions.querySelector('input, textarea, button');
        if (target && !ui.panel.hidden) target.focus();
    }

    function bot(text) {
        const bubble = el('div', 'glpichatbot-msg glpichatbot-msg-bot', text);
        ui.messages.append(bubble);
        scrollDown();
        return bubble;
    }

    function user(text) {
        ui.messages.append(el('div', 'glpichatbot-msg glpichatbot-msg-user', text));
        scrollDown();
    }

    function clearActions() {
        ui.actions.replaceChildren();
    }

    /**
     * Mostra botões de escolha. options: [{label, value, hint?, className?}]
     */
    function choices(options, onPick) {
        clearActions();
        const list = el('div', 'glpichatbot-choices');
        let filter = null;

        if (options.length > FILTER_THRESHOLD) {
            filter = el('input', 'glpichatbot-filter');
            filter.type = 'search';
            filter.placeholder = 'Digite para filtrar...';
            filter.setAttribute('aria-label', 'Filtrar opções');
            ui.actions.append(filter);
        }

        options.forEach((option) => {
            const button = el('button', 'glpichatbot-choice' + (option.className ? ' ' + option.className : ''));
            button.type = 'button';
            button.append(el('span', null, option.label));
            if (option.hint) button.append(el('small', null, option.hint));
            button.addEventListener('click', () => {
                user(option.label);
                clearActions();
                onPick(option.value);
            });
            list.append(button);
        });
        ui.actions.append(list);

        if (filter) {
            filter.addEventListener('input', () => {
                const term = filter.value.trim().toLocaleLowerCase('pt-BR');
                list.querySelectorAll('.glpichatbot-choice').forEach((button) => {
                    const fixed = button.classList.contains('glpichatbot-choice-nav');
                    button.hidden = !fixed && term !== '' && !button.textContent.toLocaleLowerCase('pt-BR').includes(term);
                });
            });
        }
        focusFirstAction();
    }

    /**
     * Pede um texto. kind: 'text' | 'textarea'
     */
    function ask(kind, { placeholder, min, max, value }, onSubmit) {
        clearActions();
        const form = el('form', 'glpichatbot-input');
        const field = el(kind === 'textarea' ? 'textarea' : 'input', 'glpichatbot-field');
        if (kind !== 'textarea') field.type = 'text';
        if (kind === 'textarea') field.rows = 4;
        field.placeholder = placeholder;
        field.maxLength = max;
        field.value = value || '';
        field.setAttribute('aria-label', placeholder);
        const send = el('button', 'glpichatbot-send', 'Enviar');
        send.type = 'submit';
        const error = el('div', 'glpichatbot-error');
        const counter = el('div', 'glpichatbot-hint', kind === 'textarea' ? 'Ctrl+Enter para enviar' : '');
        form.append(field, send);
        ui.actions.append(form, error, counter);

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const text = field.value.trim();
            if (text.length < min) {
                error.textContent = 'Escreva pelo menos ' + min + ' caracteres.';
                field.focus();
                return;
            }
            user(text.length > 400 ? text.slice(0, 400) + '…' : text);
            clearActions();
            onSubmit(text);
        });
        if (kind === 'textarea') {
            field.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) form.requestSubmit();
            });
        }
        focusFirstAction();
    }

    // ------------------------------------------------------------ conversa
    function start() {
        state = { type: 0, entity: boot.default_entity, category: 0, title: '', description: '', urgency: 0, editing: false };
        tree = null;
        ui.messages.replaceChildren();
        bot('Olá, ' + boot.user + '! 👋 Vou te ajudar a abrir um chamado.');
        askType();
    }

    function askType() {
        bot('É um problema (algo parou de funcionar) ou uma solicitação (um pedido)?');
        choices([
            { label: '🔧 Problema', hint: 'algo quebrou ou parou de funcionar', value: 1 },
            { label: '📋 Solicitação', hint: 'um pedido, acesso, compra, dúvida', value: 2 },
        ], (type) => {
            state.type = type;
            askEntity();
        });
    }

    function askEntity() {
        if (boot.entities.length <= 1) {
            state.entity = boot.entities.length ? boot.entities[0].id : boot.default_entity;
            loadTree();
            return;
        }
        bot('Para qual unidade é o chamado?');
        choices(boot.entities.map((e) => ({ label: e.name, value: e.id })), (entity) => {
            state.entity = entity;
            loadTree();
        });
    }

    async function loadTree() {
        const waiting = bot('Carregando as opções…');
        waiting.classList.add('glpichatbot-msg-wait');
        try {
            const list = await getJSON(URLS.categories + '?' + new URLSearchParams({ entity: state.entity, type: state.type }));
            waiting.remove();
            tree = buildTree(list);
        } catch (e) {
            waiting.remove();
            bot('Não consegui carregar as categorias. Tente novamente em instantes.');
            choices([{ label: 'Tentar novamente', value: 'retry' }], loadTree);
            return;
        }
        if (!(tree.children[0] || []).length) {
            bot('Não há categorias disponíveis para este tipo de chamado. Fale com a TI ou tente o outro tipo.');
            choices([{ label: '↺ Recomeçar', value: 'restart' }], start);
            return;
        }
        askLevel(0);
    }

    function askLevel(parentId) {
        const items = tree.children[parentId] || [];
        const depth = categoryPath(parentId) ? categoryPath(parentId).split(' › ').length : 0;
        bot(depth === 0 ? 'Qual área pode te ajudar?' : depth === 1 ? 'Sobre qual assunto?' : 'Qual opção descreve melhor?');

        const options = items.map((node) => ({ label: node.name, value: node.id }));
        const parent = tree.byId[parentId];
        if (parent && parent.selectable) {
            options.push({ label: 'Outro assunto de ' + parent.name, value: 'here', className: 'glpichatbot-choice-nav' });
        }
        options.push({ label: '⬅ Voltar', value: 'back', className: 'glpichatbot-choice-nav' });

        choices(options, (value) => {
            if (value === 'back') {
                if (parentId === 0) {
                    askType();
                } else {
                    askLevel(tree.byId[parentId].parent && tree.byId[tree.byId[parentId].parent] ? tree.byId[parentId].parent : 0);
                }
            } else if (value === 'here') {
                selectCategory(parentId);
            } else if ((tree.children[value] || []).length) {
                askLevel(value);
            } else {
                selectCategory(value);
            }
        });
    }

    function selectCategory(id) {
        state.category = id;
        if (state.editing) {
            summary();
        } else {
            askTitle();
        }
    }

    function askTitle() {
        bot('Resuma em uma frase. Ex.: "Impressora do 2º andar atolando papel".');
        ask('text', { placeholder: 'Resumo do chamado', min: 3, max: 250, value: state.title }, (text) => {
            state.title = text;
            state.editing ? summary() : askDescription();
        });
    }

    function askDescription() {
        bot('Agora conte os detalhes: o que aconteceu, desde quando, onde e se mais alguém é afetado.');
        ask('textarea', { placeholder: 'Detalhes do chamado', min: 3, max: 20000, value: state.description }, (text) => {
            state.description = text;
            state.editing ? summary() : askAttachment();
        });
    }

    function askAttachment() {
        bot('Deseja anexar algum arquivo ou imagem (ex.: print do erro, PDF)?');
        choices([
            { label: '📎 Anexar arquivo', value: 'attach' },
            { label: '➡️ Pular esta etapa', value: 'skip' },
        ], (val) => {
            if (val === 'skip') {
                state.file = null;
                state.editing ? summary() : askUrgency();
            } else {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = 'image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.log,.zip';
                input.onchange = () => {
                    if (input.files && input.files[0]) {
                        state.file = input.files[0];
                        bot('📎 Arquivo selecionado: ' + state.file.name);
                    }
                    state.editing ? summary() : askUrgency();
                };
                input.click();
            }
        });
    }

    function askUrgency() {
        bot('Qual a urgência?');
        choices(URGENCIES, (urgency) => {
            state.urgency = urgency;
            summary();
        });
    }

    function summary() {
        state.editing = false;
        bot('Confira o seu chamado:');
        const card = el('dl', 'glpichatbot-summary');
        const rows = [
            ['Tipo', TYPE_LABELS[state.type]],
            boot.entities.length > 1 ? ['Unidade', entityName(state.entity)] : null,
            ['Categoria', categoryPath(state.category)],
            ['Resumo', state.title],
            ['Detalhes', state.description.length > 300 ? state.description.slice(0, 300) + '…' : state.description],
            state.file ? ['Anexo', state.file.name] : null,
            ['Urgência', (URGENCIES.find((u) => u.value === state.urgency) || {}).label],
        ].filter(Boolean);
        rows.forEach(([label, value]) => card.append(el('dt', null, label), el('dd', null, value)));
        ui.messages.append(card);
        scrollDown();

        choices([
            { label: '✅ Abrir chamado', value: 'confirm', className: 'glpichatbot-choice-primary' },
            { label: '✏️ Corrigir', value: 'edit' },
            { label: '✕ Cancelar', value: 'cancel', className: 'glpichatbot-choice-nav' },
        ], (value) => {
            if (value === 'confirm') submit();
            else if (value === 'edit') askEdit();
            else cancel();
        });
    }

    function askEdit() {
        bot('O que você quer corrigir?');
        choices([
            { label: 'Tipo e categoria', value: 'category' },
            { label: 'Resumo', value: 'title' },
            { label: 'Detalhes', value: 'description' },
            { label: 'Anexo', value: 'file' },
            { label: 'Urgência', value: 'urgency' },
            { label: '⬅ Voltar ao resumo', value: 'back', className: 'glpichatbot-choice-nav' },
        ], (value) => {
            state.editing = true;
            if (value === 'category') askType();
            else if (value === 'title') askTitle();
            else if (value === 'description') askDescription();
            else if (value === 'file') askAttachment();
            else if (value === 'urgency') { state.editing = false; askUrgency(); }
            else summary();
        });
    }

    function cancel() {
        bot('Tudo bem, nada foi aberto. Quando precisar, é só me chamar. 🙂');
        choices([{ label: 'Abrir um chamado', value: 'restart' }], start);
    }

    async function submit() {
        const waiting = bot('Abrindo o seu chamado…');
        waiting.classList.add('glpichatbot-msg-wait');
        try {
            const formData = new FormData();
            formData.append('type', state.type);
            formData.append('entity', state.entity);
            formData.append('category', state.category);
            formData.append('title', state.title);
            formData.append('description', state.description);
            formData.append('urgency', state.urgency);
            if (state.file) {
                formData.append('file', state.file);
            }
            const result = await postForm(URLS.create, formData);
            waiting.remove();
            done(result.id);
        } catch (e) {
            waiting.remove();
            bot('Não consegui abrir o chamado: ' + e.message);
            choices([
                { label: 'Tentar novamente', value: 'retry' },
                { label: '✏️ Corrigir', value: 'edit' },
                { label: '✕ Cancelar', value: 'cancel', className: 'glpichatbot-choice-nav' },
            ], (value) => {
                if (value === 'retry') submit();
                else if (value === 'edit') askEdit();
                else cancel();
            });
        }
    }

    function done(id) {
        bot('✅ Pronto! Seu chamado #' + id + ' foi aberto e já foi encaminhado para a equipe responsável. Você vai receber as atualizações por aqui e por e-mail.');
        clearActions();
        const list = el('div', 'glpichatbot-choices');
        const link = el('a', 'glpichatbot-choice glpichatbot-choice-primary', 'Ver chamado #' + id);
        link.href = boot.ticket_url + encodeURIComponent(id);
        const again = el('button', 'glpichatbot-choice', 'Abrir outro chamado');
        again.type = 'button';
        again.addEventListener('click', start);
        list.append(link, again);
        ui.actions.append(list);
        focusFirstAction();
        state = null;
    }

    // ------------------------------------------------------------ início
    // Telas de formulário (ticket.form.php, computer.form.php...) têm botões de
    // Salvar/Adicionar no rodapé: nelas o botão flutuante não aparece.
    function isFormScreen() {
        return /\.form\.php$/i.test(window.location.pathname);
    }

    async function init() {
        if (!BASE || document.querySelector('#login_name') || window.top !== window.self) return;
        const pageContainer = document.getElementById('glpichatbot-page');
        if (!pageContainer && isFormScreen()) return;

        try {
            boot = await getJSON(URLS.bootstrap);
        } catch (e) {
            if (pageContainer) pageContainer.textContent = 'Não foi possível carregar o assistente. Recarregue a página.';
            return; // não logado ou sem acesso: não mostra o assistente
        }
        if (!boot.can_create) return;

        if (pageContainer) {
            buildUI('page', pageContainer);
            start();
        } else {
            buildUI('float');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
