/* 蜗牛图床 WONIU | 作者 QQ 34995549 | 官网 www.zhujitown.com */
(function () {
    'use strict';


    const THEME_KEY = 'woniu_theme';
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.querySelectorAll('[data-theme-toggle]').forEach(function (el) {
            el.setAttribute('title', theme === 'dark' ? '切换到浅色' : '切换到深色');
        });
    }
    function initTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(saved || (prefersDark ? 'dark' : 'light'));
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                applyTheme(next);
                localStorage.setItem(THEME_KEY, next);
            });
        });
    }


    function toast(message, type) {
        let host = document.querySelector('.toast-host');
        if (!host) {
            host = document.createElement('div');
            host.className = 'toast-host';
            document.body.appendChild(host);
        }
        const el = document.createElement('div');
        el.className = 'toast ' + (type || 'info');
        el.textContent = message;
        host.appendChild(el);
        setTimeout(function () {
            el.style.opacity = '0';
            el.style.transform = 'translateY(-6px)';
            el.style.transition = '.25s';
            setTimeout(function () { el.remove(); }, 260);
        }, 3200);
    }
    window.toast = toast;


    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () { toast('已复制到剪贴板', 'success'); });
        }
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); toast('已复制到剪贴板', 'success'); }
        catch (e) { toast('复制失败，请手动选择', 'error'); }
        ta.remove();
    }
    window.copyText = copyText;

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-copy]');
        if (btn) {
            e.preventDefault();
            copyText(btn.getAttribute('data-copy'));
        }
        const modalOpen = e.target.closest('[data-modal-open]');
        if (modalOpen) {
            e.preventDefault();
            const modal = document.querySelector(modalOpen.getAttribute('data-modal-open'));
            if (modal) {
                modal.classList.add('show');
                modal.dispatchEvent(new CustomEvent('modal:open', { detail: { trigger: modalOpen } }));
            }
        }
        const modalClose = e.target.closest('[data-modal-close]');
        if (modalClose) {
            e.preventDefault();
            const modal = modalClose.closest('.modal');
            if (modal) { modal.classList.remove('show'); }
        }
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('show');
        }
        const sidebarToggle = e.target.closest('[data-sidebar-toggle]');
        if (sidebarToggle) {
            e.preventDefault();
            toggleSidebar();
        }
    });

    /* 侧栏两种形态：宽屏收成 68px 图标轨道，窄屏当抽屉开关用
       状态写 localStorage，翻页不丢；跨断点时按当前宽度重新判定 */
    const SIDEBAR_KEY = 'woniu_sidebar_collapsed';
    const WIDE = window.matchMedia('(min-width: 861px)');

    function applySidebar(collapsed) {
        const shell = document.querySelector('.shell');
        if (!shell) { return; }
        const on = collapsed && WIDE.matches;
        shell.classList.toggle('sidebar-collapsed', on);
        const btn = document.querySelector('[data-sidebar-toggle]');
        if (btn) {
            btn.setAttribute('aria-expanded', on ? 'false' : 'true');
            btn.setAttribute('title', on ? '展开侧栏' : '收起侧栏');
        }
        // 收起时文字被隐藏，靠浮层提示补回来
        document.querySelectorAll('.sidebar .nav-item').forEach(function (item) {
            const label = item.querySelector('span:not(.badge-count)');
            if (label) {
                item.setAttribute('data-tip', label.textContent.trim());
            }
        });
    }

    function toggleSidebar() {
        const shell = document.querySelector('.shell');
        const sidebar = document.querySelector('.sidebar');
        if (!shell || !sidebar) { return; }
        if (WIDE.matches) {
            const next = !shell.classList.contains('sidebar-collapsed');
            localStorage.setItem(SIDEBAR_KEY, next ? 'collapsed' : 'open');
            applySidebar(next);
            return;
        }
        sidebar.classList.toggle('open');
    }

    function initSidebar() {
        const shell = document.querySelector('.shell');
        if (!shell) { return; }
        applySidebar(localStorage.getItem(SIDEBAR_KEY) === 'collapsed');

        // 侧栏滚动位置按前后台分开记：点菜单是整页跳转，不记的话新页面总是回到顶部
        const topKey = 'woniu_sidebar_top_' + (location.pathname.indexOf('/admin') === 0 ? 'admin' : 'app');

        // 收起后只剩图标，鼠标移上去用 fixed 浮层补文字，侧栏滚动也不会被裁
        const tip = document.createElement('div');
        tip.className = 'sidebar-tip';
        document.body.appendChild(tip);

        document.querySelectorAll('.sidebar .nav-item').forEach(function (item) {
            const label = item.querySelector('span:not(.badge-count)');
            if (label) {
                item.setAttribute('data-tip', label.textContent.trim());
            }
            item.addEventListener('mouseenter', function () {
                if (!shell.classList.contains('sidebar-collapsed')) { return; }
                const rect = item.getBoundingClientRect();
                tip.textContent = item.getAttribute('data-tip') || '';
                tip.style.left = (rect.right + 12) + 'px';
                tip.style.top = (rect.top + rect.height / 2) + 'px';
                tip.classList.add('show');
            });
            item.addEventListener('mouseleave', function () {
                tip.classList.remove('show');
            });
            // 点菜单跳走之前先把当前位置记下来，回来时接着滚
            item.addEventListener('click', function () {
                const bar = document.querySelector('.sidebar');
                if (bar) { localStorage.setItem(topKey, String(bar.scrollTop)); }
            });
        });
        // 滚动时图标位置变了，浮层跟着收起来，避免停在半空
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.addEventListener('scroll', function () {
                tip.classList.remove('show');
                localStorage.setItem(topKey, String(sidebar.scrollTop));
            });
            // 布局稳定后恢复上次的滚动位置
            const saved = parseInt(localStorage.getItem(topKey) || '0', 10);
            if (saved > 0) {
                requestAnimationFrame(function () { sidebar.scrollTop = saved; });
            }
        }

        // 拖窗口跨过断点时收起态失效，回到宽屏要按记忆恢复
        const onChange = function () {
            document.querySelectorAll('.sidebar').forEach(function (s) { s.classList.remove('open'); });
            applySidebar(localStorage.getItem(SIDEBAR_KEY) === 'collapsed');
        };
        if (WIDE.addEventListener) {
            WIDE.addEventListener('change', onChange);
        } else if (WIDE.addListener) {
            WIDE.addListener(onChange);
        }
    }

    // 顶栏头像下拉：点开、互斥收起、点空白与 Esc 关闭
    function closeUserMenus() {
        document.querySelectorAll('.user-dropdown.show').forEach(function (dd) { dd.classList.remove('show'); });
        document.querySelectorAll('[data-user-menu][aria-expanded="true"]').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
    }

    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('[data-user-menu]');
        if (toggle) {
            e.preventDefault();
            const box = toggle.closest('.topbar-user');
            const dd = box ? box.querySelector('.user-dropdown') : null;
            const willOpen = dd !== null && !dd.classList.contains('show');
            closeUserMenus();
            if (willOpen) {
                dd.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
            }
            return;
        }
        if (!e.target.closest('.user-dropdown')) {
            closeUserMenus();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeUserMenus(); }
    });


    document.addEventListener('submit', function (e) {
        const form = e.target;
        // 同一个表单里多个提交按钮时，按钮自己的 data-confirm 优先
        const submitter = e.submitter;
        const confirmText = (submitter && submitter.getAttribute('data-confirm'))
            || form.getAttribute('data-confirm');
        if (confirmText && !window.confirm(confirmText)) {
            e.preventDefault();
            return;
        }

        const submitBtn = form.querySelector('[type=submit]');
        if (submitBtn && form.getAttribute('data-once') === '1') {
            setTimeout(function () { submitBtn.disabled = true; }, 0);
        }
    });


    function initUploader(root) {
        const dropzone = root.querySelector('[data-dropzone]');
        const input = root.querySelector('[data-file-input]');
        const queue = root.querySelector('[data-queue]');
        const results = root.querySelector('[data-results]');
        const albumSelect = root.querySelector('[name=album_id]');
        const storageSelect = root.querySelector('[name=storage_id]');
        const urlForm = root.querySelector('[data-url-form]');
        if (!dropzone || !input) { return; }

        dropzone.addEventListener('click', function () { input.click(); });
        ['dragenter', 'dragover'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                uploadFiles(e.dataTransfer.files);
            }
        });
        input.addEventListener('change', function () {
            if (input.files.length) { uploadFiles(input.files); }
            input.value = '';
        });

        document.addEventListener('paste', function (e) {
            if (!e.clipboardData) { return; }
            const items = e.clipboardData.items;
            const files = [];
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') === 0) {
                    const f = items[i].getAsFile();
                    if (f) { files.push(f); }
                }
            }
            if (files.length) {
                e.preventDefault();
                uploadFiles(files);
            }
        });

        if (urlForm) {
            urlForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const urlInput = urlForm.querySelector('input[name=url]');
                if (!urlInput || !urlInput.value.trim()) { return; }
                const fd = new FormData();
                fd.append('url', urlInput.value.trim());
                fd.append('album_id', albumSelect ? albumSelect.value : '');
                fd.append('storage_id', storageSelect ? storageSelect.value : '');
                fd.append('_token', root.getAttribute('data-token'));
                sendRequest(fd, [{ name: urlInput.value.split('/').pop(), size: 0 }]);
                urlInput.value = '';
            });
        }

        function uploadFiles(fileList) {
            const files = Array.prototype.slice.call(fileList);
            const maxSize = parseInt(root.getAttribute('data-max-size') || '0', 10);
            const allowed = (root.getAttribute('data-allowed') || '').split(',').filter(Boolean);
            const valid = [];

            files.forEach(function (file) {
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                if (allowed.length && allowed.indexOf(ext) === -1) {
                    queueItem(file.name, '不支持的格式：' + ext, 'error');
                    return;
                }
                if (maxSize > 0 && file.size > maxSize) {
                    queueItem(file.name, '超过大小限制', 'error');
                    return;
                }
                valid.push(file);
            });

            if (!valid.length) { return; }

            const fd = new FormData();
            valid.forEach(function (file) { fd.append('files[]', file, file.name); });
            fd.append('album_id', albumSelect ? albumSelect.value : '');
            fd.append('storage_id', storageSelect ? storageSelect.value : '');
            fd.append('_token', root.getAttribute('data-token'));
            sendRequest(fd, valid);
        }

        function queueItem(name, meta, state) {
            if (!queue) { return null; }
            const el = document.createElement('div');
            el.className = 'queue-item';
            el.innerHTML = '<div class="queue-info"><div class="queue-name"></div>'
                + '<div class="queue-meta"></div>'
                + '<div class="progress"><span></span></div></div>';
            el.querySelector('.queue-name').textContent = name;
            const metaEl = el.querySelector('.queue-meta');
            metaEl.textContent = meta;
            if (state === 'error') { metaEl.style.color = 'var(--danger)'; }
            queue.appendChild(el);
            return el;
        }

        function sendRequest(formData, files) {
            const items = [];
            files.forEach(function (file) {
                const el = queueItem(file.name, '等待上传…');
                if (el) { items.push({ el: el, file: file }); }
            });

            const xhr = new XMLHttpRequest();
            xhr.open('POST', root.getAttribute('data-endpoint') || '/upload', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.onprogress = function (e) {
                if (!e.lengthComputable) { return; }
                const percent = Math.round(e.loaded / e.total * 100);
                items.forEach(function (item) {
                    item.el.querySelector('.progress span').style.width = percent + '%';
                    item.el.querySelector('.queue-meta').textContent = percent + '%';
                });
            };
            xhr.onload = function () {
                let payload = null;
                try { payload = JSON.parse(xhr.responseText); } catch (err) { payload = null; }
                if (!payload) {
                    items.forEach(function (item) {
                        item.el.querySelector('.queue-meta').textContent = '服务器返回异常';
                        item.el.querySelector('.queue-meta').style.color = 'var(--danger)';
                    });
                    toast('上传失败：响应格式异常', 'error');
                    return;
                }
                const ok = !!payload.status;
                items.forEach(function (item) {
                    const bar = item.el.querySelector('.progress span');
                    bar.style.width = '100%';
                    bar.style.background = ok ? 'var(--success)' : 'var(--danger)';
                    item.el.querySelector('.queue-meta').textContent = ok ? '上传成功' : (payload.message || '失败');
                    item.el.querySelector('.queue-meta').style.color = ok ? 'var(--success)' : 'var(--danger)';
                });
                toast(payload.message || (ok ? '上传成功' : '上传失败'), ok ? 'success' : 'error');

                if (ok && payload.data) {
                    const list = Array.isArray(payload.data) ? payload.data : [payload.data];
                    renderResults(list);
                }
            };
            xhr.onerror = function () {
                toast('网络错误，上传失败', 'error');
            };
            xhr.send(formData);
        }

        function renderResults(list) {
            if (!results) { return; }
            list.forEach(function (item) {
                const links = item.links || {};
                const card = document.createElement('div');
                card.className = 'result-item';
                card.innerHTML = '<img alt="" loading="lazy">'
                    + '<div class="result-actions">'
                    + '<button class="btn btn-sm" type="button" data-copy="">URL</button>'
                    + '<button class="btn btn-sm" type="button" data-copy="">MD</button>'
                    + '<a class="btn btn-sm btn-ghost" target="_blank" rel="noopener">打开</a>'
                    + '</div>';
                card.querySelector('img').src = links.thumbnail_url || links.url || '';
                const buttons = card.querySelectorAll('[data-copy]');
                if (buttons[0]) { buttons[0].setAttribute('data-copy', links.url || ''); }
                if (buttons[1]) { buttons[1].setAttribute('data-copy', links.markdown || ''); }
                const link = card.querySelector('a');
                if (link) { link.href = links.url || '#'; }
                results.appendChild(card);
            });
        }
    }


    function initImageGrid(grid) {
        const bulkbar = document.querySelector('[data-bulkbar]');
        const counter = bulkbar ? bulkbar.querySelector('.count') : null;
        const selected = new Set();

        function sync() {
            if (counter) { counter.textContent = '已选 ' + selected.size + ' 项'; }
            if (bulkbar) { bulkbar.classList.toggle('show', selected.size > 0); }
            grid.querySelectorAll('.image-card').forEach(function (card) {
                card.classList.toggle('selected', selected.has(card.getAttribute('data-id')));
            });
            bulkbar && bulkbar.querySelectorAll('[data-need-selection]').forEach(function (btn) {
                btn.disabled = selected.size === 0;
            });
        }

        grid.addEventListener('click', function (e) {
            const check = e.target.closest('.check');
            const card = e.target.closest('.image-card');
            if (!card) { return; }
            if (check || e.target.closest('[data-select]')) {
                e.preventDefault();
                const id = card.getAttribute('data-id');
                if (selected.has(id)) { selected.delete(id); } else { selected.add(id); }
                sync();
            }
        });

        if (bulkbar) {
            bulkbar.addEventListener('click', function (e) {
                const action = e.target.closest('[data-action]');
                if (!action) { return; }
                const ids = Array.from(selected);
                if (!ids.length) { return; }
                const type = action.getAttribute('data-action');

                if (type === 'move') {
                    const modal = document.querySelector('#move-modal');
                    if (modal) {
                        modal.classList.add('show');
                        modal.querySelector('[name=ids-holder]').value = ids.join(',');
                    }
                    return;
                }
                if (type === 'download') {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = action.getAttribute('data-endpoint') || '';
                    form.style.display = 'none';
                    const tokenInput = document.createElement('input');
                    tokenInput.type = 'hidden';
                    tokenInput.name = '_token';
                    tokenInput.value = grid.getAttribute('data-token') || '';
                    form.appendChild(tokenInput);
                    ids.forEach(function (id) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = id;
                        form.appendChild(input);
                    });
                    document.body.appendChild(form);
                    form.submit();
                    setTimeout(function () { form.remove(); }, 3000);
                    return;
                }
                if (type === 'copy') {
                    const urls = ids.map(function (id) {
                        const card = grid.querySelector('.image-card[data-id="' + id + '"]');
                        return card ? card.getAttribute('data-url') : '';
                    }).filter(Boolean);
                    copyText(urls.join('\n'));
                    return;
                }
                const labels = {
                    delete: '确定把选中的图片移入回收站？',
                    destroy: '彻底删除后不可恢复，确定继续？',
                    restore: '确定恢复选中的图片？',
                    approve: '确定通过选中图片的审核？',
                    reject: '确定拒绝选中图片？'
                };
                if (labels[type] && !window.confirm(labels[type])) { return; }

                const fd = new FormData();
                ids.forEach(function (id) { fd.append('ids[]', id); });
                fd.append('_token', grid.getAttribute('data-token') || '');
                let url = action.getAttribute('data-endpoint');
                if (type === 'destroy') { fd.append('force', '1'); }
                if (type === 'approve') { fd.append('audit_status', '1'); }
                if (type === 'reject') { fd.append('audit_status', '2'); }
                if (e.target.closest('[name=audit_status]') ) {  }

                fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        toast(res.message || (res.status ? '操作成功' : '操作失败'), res.status ? 'success' : 'error');
                        if (res.status) { setTimeout(function () { location.reload(); }, 600); }
                    })
                    .catch(function () { toast('请求失败', 'error'); });
            });
        }


        const moveModal = document.querySelector('#move-modal');
        if (moveModal) {
            moveModal.addEventListener('submit', function (e) {
                e.preventDefault();
                const holder = moveModal.querySelector('[name=ids-holder]');
                const select = moveModal.querySelector('[name=album_id]');
                const fd = new FormData();
                (holder.value || '').split(',').filter(Boolean).forEach(function (id) { fd.append('ids[]', id); });
                fd.append('album_id', select.value);
                fd.append('_token', grid.getAttribute('data-token') || '');
                fetch(moveModal.getAttribute('data-endpoint'), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        toast(res.message, res.status ? 'success' : 'error');
                        if (res.status) { setTimeout(function () { location.reload(); }, 500); }
                    });
            });
        }
    }


    function initStorageForm() {
        document.querySelectorAll('[data-storage-form]').forEach(function (form) {
            const typeSelect = form.querySelector('[name=type]');
            const groups = form.querySelectorAll('[data-config-for]');
            if (!typeSelect) { return; }

            function sync() {
                groups.forEach(function (group) {
                    const types = (group.getAttribute('data-config-for') || '').split(',');
                    const visible = types.indexOf(typeSelect.value) !== -1;
                    group.style.display = visible ? '' : 'none';

                    group.querySelectorAll('input,select,textarea').forEach(function (field) {
                        field.disabled = !visible;
                    });
                });
            }

            typeSelect.addEventListener('change', sync);
            form.addEventListener('form:filled', sync);
            sync();
        });
    }


    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form.hasAttribute('data-ajax')) { return; }
        e.preventDefault();

        const fd = new FormData(form);
        const submitBtn = form.querySelector('[type=submit]');
        if (submitBtn) { submitBtn.disabled = true; }

        fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                toast(res.message || (res.status ? '操作成功' : '操作失败'), res.status ? 'success' : 'error');
                if (res.status) {
                    const modal = form.closest('.modal');
                    if (modal) { modal.classList.remove('show'); }
                    setTimeout(function () { location.reload(); }, 500);
                }
            })
            .catch(function () { toast('请求失败', 'error'); })
            .finally(function () { if (submitBtn) { submitBtn.disabled = false; } });
    });


    document.addEventListener('click', function (e) {
        const del = e.target.closest('[data-delete-image]');
        const restore = e.target.closest('[data-restore]');
        if (!del && !restore) { return; }
        if (!window.confirm(del ? '把这张图片移入回收站？' : '恢复这张图片？')) { return; }

        const isDelete = !!del;
        const id = del ? del.getAttribute('data-delete-image') : restore.getAttribute('data-restore');
        const tokenMeta = document.querySelector('meta[name=csrf-token]');
        const fd = new FormData();
        fd.append('ids[]', id);
        fd.append('_token', tokenMeta ? tokenMeta.getAttribute('content') : '');

        const grid = document.querySelector('[data-image-grid]');
        const token = grid ? grid.getAttribute('data-token') : (tokenMeta ? tokenMeta.getAttribute('content') : '');
        if (token) { fd.set('_token', token); }

        fetch(isDelete ? '/images/delete' : '/images/restore', {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); })
            .then(function (res) {
                toast(res.message, res.status ? 'success' : 'error');
                if (res.status) { setTimeout(function () { location.reload(); }, 450); }
            })
            .catch(function () { toast('请求失败', 'error'); });
    });


    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-reset-form]');
        if (!trigger) { return; }
        const form = document.querySelector(trigger.getAttribute('data-reset-form'));
        if (!form) { return; }
        form.reset();
        const createAction = trigger.getAttribute('data-create-action');
        if (createAction) { form.setAttribute('action', createAction); }
        form.querySelectorAll('input[type=checkbox]').forEach(function (box) {
            if (box.hasAttribute('checked') || box.defaultChecked) { box.checked = true; }
        });
        form.dispatchEvent(new CustomEvent('form:filled'));
    });


    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-fill]');
        if (!trigger) { return; }
        const data = JSON.parse(trigger.getAttribute('data-fill') || '{}');
        const formSelector = trigger.getAttribute('data-fill-target');
        const form = document.querySelector(formSelector);
        if (!form) { return; }
        Object.keys(data).forEach(function (key) {
            const field = form.querySelector('[name="' + key + '"]');
            if (!field) { return; }
            if (field.type === 'checkbox') {
                // 服务端可能给布尔 true / 数字 1 / 字符串 "1"，统一按「真值」判定，
                // 只认 parseInt 的话 true 会变成 NaN，开关会被错判成关闭
                const value = data[key];
                field.checked = value === true || value === 1 || value === '1' || value === 'true' || value === 'on';
            } else {
                field.value = data[key] === null ? '' : data[key];
            }
        });

        const config = data.config_array || {};
        form.querySelectorAll('[name^="config["]').forEach(function (field) {
            const key = field.name.replace(/^config\[/, '').replace(/\]$/, '');
            if (Object.prototype.hasOwnProperty.call(config, key)) {
                if (field.type === 'checkbox') { field.checked = !!config[key]; }
                else { field.value = config[key]; }
            } else if (field.type !== 'checkbox') {
                field.value = '';
            }
        });
        // 图片编辑弹窗：按这张图当前是否加密，切换状态提示与「清除密码」开关
        const pwdState = form.querySelector('[data-password-state]');
        if (pwdState) {
            const locked = trigger.getAttribute('data-locked') === '1';
            pwdState.textContent = locked ? '当前状态：已设置访问密码' : '当前状态：无密码，任何人可访问';
            const clearWrap = form.querySelector('[data-clear-password]');
            if (clearWrap) { clearWrap.style.display = locked ? '' : 'none'; }
            const pwdInput = form.querySelector('[name="password"]');
            if (pwdInput) { pwdInput.disabled = false; }
        }

        const action = trigger.getAttribute('data-action');
        if (action) { form.setAttribute('action', action); }
        const title = form.querySelector('[data-form-title]');
        if (title) { title.textContent = trigger.getAttribute('data-title') || '编辑'; }
        // 弹窗定位：优先看触发按钮上的 data-modal（编辑按钮都写在这里），
        // 其次看表单自己的 data-modal，最后才落到存储页的默认弹窗
        const modalSel = trigger.getAttribute('data-modal') || form.getAttribute('data-modal') || '#storage-modal';
        const modal = document.querySelector(modalSel);
        if (modal) { modal.classList.add('show'); }
        form.dispatchEvent(new CustomEvent('form:filled'));
    });


    // 清除密码与填写新密码互斥：勾了「清除」就不该再提交一个新密码
    document.addEventListener('change', function (e) {
        const box = e.target.closest('[name="clear_password"]');
        if (!box) { return; }
        const form = box.closest('form');
        const pwd = form ? form.querySelector('[name="password"]') : null;
        if (!pwd) { return; }
        if (box.checked) { pwd.value = ''; pwd.disabled = true; }
        else { pwd.disabled = false; }
    });


    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-storage-test]');
        if (!btn) { return; }
        const form = btn.closest('form');
        const fd = new FormData(form);
        fd.append('_token', form.querySelector('[name=_token]').value);
        btn.disabled = true;
        btn.textContent = '测试中…';
        fetch('/admin/storages/test', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) { toast(res.message, res.status ? 'success' : 'error'); })
            .catch(function () { toast('测试请求失败', 'error'); })
            .finally(function () { btn.disabled = false; btn.textContent = '测试连接'; });
    });


    function csrfToken() {
        const meta = document.querySelector('meta[name=csrf-token]');
        return meta ? meta.getAttribute('content') : '';
    }

    function initCheckAll() {
        document.querySelectorAll('[data-check-all]').forEach(function (master) {
            const scope = master.closest('[data-check-scope]') || document;
            const form = master.closest('form');
            const counter = form ? form.querySelector('[data-check-count]') : null;
            const rows = function () {
                return Array.prototype.slice.call(scope.querySelectorAll('[data-row-check]'));
            };
            const refresh = function () {
                const list = rows();
                const picked = list.filter(function (box) { return box.checked; });
                if (counter) {
                    counter.textContent = picked.length ? ('已选 ' + picked.length + ' 条') : '未选择';
                }
                master.checked = list.length > 0 && picked.length === list.length;
                master.indeterminate = picked.length > 0 && picked.length < list.length;
            };
            master.addEventListener('change', function () {
                rows().forEach(function (box) { box.checked = master.checked; });
                refresh();
            });
            scope.addEventListener('change', function (e) {
                if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-row-check')) {
                    refresh();
                }
            });
            refresh();
        });
    }

    function initReportModal() {
        const form = document.getElementById('report-form');
        if (!form) { return; }
        const nameBox = document.getElementById('report-target-name');
        const offlineRow = document.getElementById('report-offline-row');
        const decision = form.querySelector('[name="decision"]');
        // 共享弹窗：每次打开都要把提交地址换成当前这条举报的地址，否则会 POST 到列表页
        document.querySelectorAll('[data-report-url]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const hasImage = btn.getAttribute('data-report-image') !== '0';
                form.setAttribute('action', btn.getAttribute('data-report-url'));
                form.dataset.hasImage = hasImage ? '1' : '0';
                if (nameBox) { nameBox.textContent = btn.getAttribute('data-report-target') || ''; }
                // 相册举报没有图片可下线，隐藏该开关
                if (offlineRow) { offlineRow.style.display = hasImage ? '' : 'none'; }
            });
        });
        if (decision) {
            decision.addEventListener('change', function () {
                if (!offlineRow) { return; }
                // 驳回时不需要下线图片；处理时按该条记录是否关联图片决定是否显示
                offlineRow.style.display = (decision.value !== 'reject' && form.dataset.hasImage !== '0') ? '' : 'none';
            });
        }
        form.addEventListener('submit', function (e) {
            if (!form.getAttribute('action')) {
                e.preventDefault();
                toast('请从列表里点击「处理」再提交', 'error');
            }
        });
    }

    function initDriverTest() {
        const modal = document.getElementById('driver-test-modal');
        if (!modal) { return; }
        const idInput = document.getElementById('driver-test-id');
        const resultBox = document.getElementById('driver-test-result');

        function open(id) {
            idInput.value = id;
            if (resultBox) { resultBox.style.display = 'none'; resultBox.className = 'test-result'; }
            modal.classList.add('show');
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-driver-test]');
            if (!btn) { return; }
            open(btn.getAttribute('data-driver-test'));
        });

        const run = document.getElementById('driver-test-run');
        if (!run) { return; }
        run.addEventListener('click', function () {
            const body = new URLSearchParams();
            body.append('_token', csrfToken());
            body.append('id', idInput.value);
            body.append('to', (document.getElementById('driver-test-to') || {}).value || '');
            body.append('phone', (document.getElementById('driver-test-phone') || {}).value || '');
            body.append('url', (document.getElementById('driver-test-url') || {}).value || '');

            run.disabled = true;
            run.textContent = '测试中…';
            fetch('/admin/drivers/test', {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!resultBox) { toast(res.message, res.status ? 'success' : 'error'); return; }
                    resultBox.style.display = 'block';
                    resultBox.className = 'test-result ' + (res.status ? 'ok' : 'bad');
                    resultBox.textContent = res.message || (res.status ? '通过' : '失败');
                })
                .catch(function () {
                    if (resultBox) {
                        resultBox.style.display = 'block';
                        resultBox.className = 'test-result bad';
                        resultBox.textContent = '请求失败，请检查网络或服务端日志';
                    }
                })
                .finally(function () { run.disabled = false; run.textContent = '开始测试'; });
        });
    }

    function initCheckout() {
        const form = document.querySelector('[data-checkout-form]');
        if (!form) { return; }

        const methods = form.querySelectorAll('.pay-method');
        methods.forEach(function (item) {
            item.addEventListener('click', function () {
                methods.forEach(function (m) { m.classList.remove('active'); });
                item.classList.add('active');
                const radio = item.querySelector('input[type=radio]');
                if (radio) { radio.checked = true; }
            });
        });

        const couponInput = form.querySelector('[name=coupon]');
        const applyBtn = form.querySelector('[data-apply-coupon]');
        const totalEl = form.querySelector('[data-total-text]');
        const discountRow = form.querySelector('[data-discount-row]');
        const discountEl = form.querySelector('[data-discount-text]');
        const hintEl = form.querySelector('[data-coupon-hint]');

        if (applyBtn && couponInput) {
            applyBtn.addEventListener('click', function () {
                const body = new URLSearchParams();
                body.append('_token', csrfToken());
                body.append('package_id', form.getAttribute('data-package-id') || '0');
                body.append('coupon', couponInput.value.trim());

                applyBtn.disabled = true;
                fetch('/shop/quote', {
                    method: 'POST',
                    body: body,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.status) {
                            if (totalEl) { totalEl.textContent = res.data.total_text; }
                            if (discountEl) { discountEl.textContent = '- ' + res.data.discount_text; }
                            if (discountRow) { discountRow.style.display = res.data.discount > 0 ? '' : 'none'; }
                            if (hintEl) { hintEl.textContent = '优惠已生效'; hintEl.className = 'hint'; }
                            toast('优惠码已应用', 'success');
                        } else {
                            if (hintEl) { hintEl.textContent = res.message; hintEl.className = 'hint'; }
                            toast(res.message, 'error');
                        }
                    })
                    .catch(function () { toast('优惠码校验失败', 'error'); })
                    .finally(function () { applyBtn.disabled = false; });
            });
        }
    }

    function initOrderPoll() {
        const box = document.querySelector('[data-order-poll]');
        if (!box) { return; }
        const no = box.getAttribute('data-order-poll');
        if (!no) { return; }
        let tries = 0;

        const timer = setInterval(function () {
            tries++;
            if (tries > 100) { clearInterval(timer); return; }
            fetch('/shop/status/' + encodeURIComponent(no), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.status && res.data && res.data.paid) {
                        clearInterval(timer);
                        toast('支付成功，正在跳转', 'success');
                        setTimeout(function () {
                            window.location.href = box.getAttribute('data-success-url') || ('/shop/orders/' + no);
                        }, 900);
                    }
                })
                .catch(function () {});
        }, 3000);
    }

    /**
     * 缩略图加载失败时补一块白：文件被清理、外链失联、图床返回 403/404 都会走到这里
     * 不补的话浏览器会顶出一个破图标加 alt 文字，卡片高度也跟着塌，比占位图更难看
     * 宽高优先取 data-w/data-h（模板里带上原图尺寸），比例对了高度才和同列一致
     */
    function initImageFallback() {
        const script = document.currentScript || document.querySelector('script[src*="app.js"]');
        const root = script ? script.src.replace(/assets\/app\.js.*$/, '') : '/';

        document.addEventListener('error', function (event) {
            const img = event.target;
            if (!img || img.tagName !== 'IMG' || img.getAttribute('data-blanked') === '1') {
                return;
            }
            img.setAttribute('data-blanked', '1');
            let w = parseInt(img.getAttribute('data-w') || '0', 10);
            let h = parseInt(img.getAttribute('data-h') || '0', 10);
            if (!(w > 0) || !(h > 0)) {
                // 没标 data-w/data-h 的（广场封面这类定点尺寸）退回标签上的 width/height
                w = parseInt(img.getAttribute('width') || '0', 10);
                h = parseInt(img.getAttribute('height') || '0', 10);
            }
            if (!(w > 0) || !(h > 0)) {
                w = 1200;
                h = 900;
            }
            img.src = root + 'blank/' + w + 'x' + h + '.jpg';
        }, true);
    }

    // 监听器要立刻挂上：图片可能在 DOMContentLoaded 之前就已经 404 了
    initImageFallback();

    // 账号自动退出倒计时：服务端 session_ttl 注入剩余秒数，到点跳登录；
    // 最后 60 秒提示一次，点页面任意处刷新续期
    function initSessionTimer() {
        let left = parseInt(window.__SESSION_LEFT || '0', 10);
        if (!left || left <= 0) { return; }
        let warned = false;
        const timer = setInterval(function () {
            left--;
            if (left <= 0) {
                clearInterval(timer);
                location.href = '/login?redirect=' + encodeURIComponent(location.pathname + location.search);
                return;
            }
            if (left <= 60 && !warned) {
                warned = true;
                toast('账号将在 ' + left + ' 秒后自动退出，点击页面任意处续期', 'error');
                document.addEventListener('click', function () { location.reload(); }, { once: true });
            }
        }, 1000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        initSidebar();
        initSessionTimer();
        document.querySelectorAll('[data-uploader]').forEach(initUploader);
        document.querySelectorAll('[data-image-grid]').forEach(initImageGrid);
        initStorageForm();
        initDriverTest();
        initReportModal();
        initCheckAll();
        initCheckout();
        initOrderPoll();

        document.querySelectorAll('[data-flash]').forEach(function (el) {
            toast(el.getAttribute('data-flash'), el.getAttribute('data-flash-type') || 'info');
        });
    });
})();
