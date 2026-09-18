/* ACO custom admin panel */
(function () {
    'use strict';

    var root = document.getElementById('lh-studio');
    if (!root || typeof acoAdmin === 'undefined') {
        return;
    }

    var state = {
        view: root.dataset.view || 'courses',
        courseId: parseInt(root.dataset.course || '0', 10) || 0,
        courses: [],
        directions: [],
        moodle: [],
        teachers: [],
        woo: true
    };

    var toastEl = root.querySelector('[data-toast]');
    var courseForm = root.querySelector('[data-form="course"]');

    function input(form, name) {
        return form ? form.querySelector('[name="' + name + '"]') : null;
    }

    function val(form, name) {
        var el = input(form, name);
        if (!el) return '';
        if (el.type === 'checkbox') return el.checked;
        return el.value;
    }

    function setVal(form, name, value) {
        var el = input(form, name);
        if (!el) return;
        if (el.type === 'checkbox') {
            el.checked = !!value;
        } else {
            el.value = value == null ? '' : String(value);
        }
    }

    function toast(msg, isError) {
        if (!toastEl) return;
        toastEl.textContent = msg;
        toastEl.hidden = !msg;
        toastEl.classList.toggle('is-error', !!isError);
    }

    function post(action, data) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', acoAdmin.nonce);
        Object.keys(data || {}).forEach(function (key) {
            var val = data[key];
            if (val === undefined || val === null) return;
            fd.append(key, typeof val === 'object' ? JSON.stringify(val) : String(val));
        });
        return fetch(acoAdmin.ajax, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.text().then(function (text) {
                try {
                    return JSON.parse(text);
                } catch (err) {
                    throw new Error('Ошибка запроса');
                }
            });
        }).then(function (res) {
            if (!res || !res.success) {
                throw new Error((res && res.data) || 'Ошибка запроса');
            }
            return res.data || {};
        });
    }

    function studioUrl(view, id) {
        var url = String(acoAdmin.home || '/aco-admin/').replace(/\/?(\?.*)?$/, '/');
        if (view === 'directions') {
            return url + '?view=directions';
        }
        if (view === 'teachers') {
            return url + '?view=teachers';
        }
        if (view === 'edit') {
            return url + '?view=edit&id=' + (id || 0);
        }
        return url;
    }

    function setView(view, courseId, replace) {
        state.view = view;
        if (typeof courseId === 'number') {
            state.courseId = courseId;
        }
        root.querySelectorAll('[data-screen]').forEach(function (el) {
            el.classList.toggle('is-active', el.dataset.screen === view);
        });
        root.querySelectorAll('.lh-studio__tab[data-nav]').forEach(function (el) {
            el.classList.toggle('is-active', el.dataset.nav === view);
        });
        var url = studioUrl(view, state.courseId);
        if (replace) {
            history.replaceState({ view: view, id: state.courseId }, '', url);
        } else {
            history.pushState({ view: view, id: state.courseId }, '', url);
        }
        root.dataset.view = view;
        root.dataset.course = String(state.courseId || 0);
    }

    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str == null ? '' : String(str);
        return d.innerHTML;
    }

    function fillSelect(select, items, valueKey, labelKey, current, emptyLabel, emptyValue) {
        if (!select) return;
        var empty = emptyValue === undefined ? '' : String(emptyValue);
        var html = emptyLabel ? '<option value="' + esc(empty) + '">' + esc(emptyLabel) + '</option>' : '';
        items.forEach(function (item) {
            html += '<option value="' + esc(item[valueKey]) + '">' + esc(item[labelKey]) + '</option>';
        });
        select.innerHTML = html;
        if (current !== undefined && current !== null && String(current) !== '') {
            select.value = String(current);
        } else {
            select.value = empty;
        }
    }

    function moodleBuilderHref(id) {
        if (!id || !acoAdmin.moodle) {
            return '';
        }
        var dest = String(acoAdmin.moodle).replace(/\/$/, '') + '/theme/learnhub/builder/index.php?id=' + encodeURIComponent(id);
        return (acoAdmin.sso || '/?aco_moodle_sso=1') + '&redirect=' + encodeURIComponent(dest);
    }

    function syncBuilderLink() {
        var sel = input(courseForm, 'moodle_course_id');
        var a = root.querySelector('[data-moodle-builder]');
        if (!a || !sel) {
            return;
        }
        var id = sel.value;
        if (id) {
            a.hidden = false;
            a.href = moodleBuilderHref(id);
        } else {
            a.hidden = true;
            a.href = '#';
        }
    }

    function renderCourses() {
        var tbody = root.querySelector('[data-list="courses"]');
        if (!tbody) return;
        if (!state.woo) {
            tbody.innerHTML = '<tr><td colspan="7" class="lh-studio-muted">WooCommerce не активен.</td></tr>';
            return;
        }
        if (!state.courses.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="lh-studio-muted">Курсов пока нет. Создайте первый.</td></tr>';
            return;
        }
        tbody.innerHTML = state.courses.map(function (c) {
            var thumb = c.image_url
                ? '<img class="lh-studio-thumb" src="' + esc(c.image_url) + '" alt="">'
                : '<span class="lh-studio-thumb lh-studio-thumb--empty">📘</span>';
            return '<tr>' +
                '<td>' + thumb + '</td>' +
                '<td><button type="button" class="lh-studio-link" data-edit="' + c.id + '">' + esc(c.title) + '</button></td>' +
                '<td>' + esc(c.category_name || '—') + '</td>' +
                '<td>' + esc(c.price_label || '—') + '</td>' +
                '<td><span class="lh-studio-pill' + (c.status === 'publish' ? ' is-on' : '') + '">' + (c.status === 'publish' ? 'Да' : 'Черновик') + '</span></td>' +
                '<td><span class="lh-studio-pill' + (c.featured ? ' is-on' : '') + '">' + (c.featured ? 'Да' : 'Нет') + '</span></td>' +
                '<td><button type="button" class="lh-studio-link" data-edit="' + c.id + '">Изменить</button> <button type="button" class="lh-studio-link lh-studio-link--danger" data-trash="' + c.id + '">Удалить</button></td>' +
                '</tr>';
        }).join('');
    }

    function addRow(kind, data) {
        var tpl = document.getElementById('tpl-' + kind + '-row');
        var holder = root.querySelector('[data-repeater="' + kind + '"]');
        if (!tpl || !holder) return;
        var node = tpl.content.firstElementChild.cloneNode(true);
        Object.keys(data || {}).forEach(function (key) {
            var field = node.querySelector('[data-f="' + key + '"]');
            if (!field) return;
            field.value = Array.isArray(data[key]) ? data[key].join('\n') : (data[key] || '');
        });
        holder.appendChild(node);
    }

    function collectRepeater(kind, mapFn) {
        var rows = [];
        root.querySelectorAll('[data-repeater="' + kind + '"] [data-row]').forEach(function (row) {
            rows.push(mapFn(row));
        });
        return rows;
    }

    function resetCourseForm() {
        if (!courseForm) return;
        courseForm.reset();
        setVal(courseForm, 'id', '0');
        setVal(courseForm, 'image_id', '');
        setVal(courseForm, 'video_id', '');
        setVal(courseForm, 'status_publish', false);
        setVal(courseForm, 'featured', false);
        root.querySelectorAll('[data-repeater]').forEach(function (el) { el.innerHTML = ''; });
        root.querySelector('[data-preview="image"]').innerHTML = 'Нет обложки';
        root.querySelector('[data-preview="video"]').innerHTML = 'Нет видео';
        var preview = root.querySelector('[data-preview-link]');
        if (preview) {
            preview.hidden = true;
            preview.href = '#';
        }
        var title = root.querySelector('[data-course-form-title]');
        if (title) title.textContent = 'Новый курс';
        fillSelect(input(courseForm, 'moodle_course_id'), state.moodle, 'id', 'label', '', '— не привязан —', '');
        fillSelect(input(courseForm, 'category_id'), state.directions, 'id', 'name', '', '— не выбрано —', '0');
        fillSelect(input(courseForm, 'teacher_user_id'), state.teachers, 'id', 'label', '0', 'Команда АЦО', '0');
        syncBuilderLink();
    }

    function fillCourse(course) {
        resetCourseForm();
        if (!course) return;
        setVal(courseForm, 'id', course.id || 0);
        setVal(courseForm, 'title', course.title || '');
        setVal(courseForm, 'excerpt', course.excerpt || '');
        setVal(courseForm, 'content', course.content || '');
        setVal(courseForm, 'regular_price', course.regular_price || '');
        setVal(courseForm, 'sale_price', course.sale_price || '');
        setVal(courseForm, 'duration', course.duration || '');
        setVal(courseForm, 'level', course.level || '');
        setVal(courseForm, 'image_id', course.image_id || '');
        setVal(courseForm, 'video_id', course.video_id || '');
        setVal(courseForm, 'status_publish', course.status === 'publish');
        setVal(courseForm, 'featured', !!course.featured);
        fillSelect(input(courseForm, 'moodle_course_id'), state.moodle, 'id', 'label', course.moodle_course_id || '', '— не привязан —', '');
        fillSelect(input(courseForm, 'category_id'), state.directions, 'id', 'name', course.category_id || 0, '— не выбрано —', '0');
        fillSelect(input(courseForm, 'teacher_user_id'), state.teachers, 'id', 'label', course.teacher_user_id || 0, 'Команда АЦО', '0');
        syncBuilderLink();
        (course.program || []).forEach(function (row) { addRow('program', row); });
        (course.benefits || []).forEach(function (row) { addRow('benefits', row); });
        (course.faq || []).forEach(function (row) { addRow('faq', row); });
        var imgBox = root.querySelector('[data-preview="image"]');
        imgBox.innerHTML = course.image_url ? '<img src="' + esc(course.image_url) + '" alt="">' : 'Нет обложки';
        var vidBox = root.querySelector('[data-preview="video"]');
        vidBox.innerHTML = course.video_url ? '<video src="' + esc(course.video_url) + '" controls></video>' : 'Нет видео';
        var preview = root.querySelector('[data-preview-link]');
        if (preview && course.permalink) {
            preview.hidden = false;
            preview.href = course.permalink;
        }
        var title = root.querySelector('[data-course-form-title]');
        if (title) title.textContent = course.title || 'Курс';
    }

    function openEditor(id, replace) {
        state.courseId = id || 0;
        setView('edit', state.courseId, replace);
        if (!id) {
            resetCourseForm();
            return;
        }
        var cached = state.courses.filter(function (c) { return c.id === id; })[0];
        if (cached) {
            fillCourse(cached);
        }
        post('aco_admin_get_course', { id: id }).then(function (data) {
            fillCourse(data.course);
        }).catch(function (err) {
            toast(err.message, true);
        });
    }

    function pickMedia(type) {
        if (!window.wp || !wp.media) {
            toast('Медиатека WordPress недоступна', true);
            return;
        }
        var frame = wp.media({
            title: type === 'video' ? 'Превью-видео' : 'Обложка курса',
            library: { type: type },
            button: { text: 'Выбрать' },
            multiple: false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            if (type === 'image') {
                setVal(courseForm, 'image_id', att.id);
                root.querySelector('[data-preview="image"]').innerHTML = '<img src="' + esc(att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url) + '" alt="">';
            } else {
                setVal(courseForm, 'video_id', att.id);
                root.querySelector('[data-preview="video"]').innerHTML = '<video src="' + esc(att.url) + '" controls></video>';
            }
        });
        frame.open();
    }

    root.addEventListener('click', function (e) {
        var nav = e.target.closest('[data-nav]');
        if (nav) {
            if (nav.dataset.nav === 'edit') {
                openEditor(nav.getAttribute('data-new') ? 0 : state.courseId);
            } else {
                setView(nav.dataset.nav, 0);
            }
            return;
        }
        var edit = e.target.closest('[data-edit]');
        if (edit) {
            openEditor(parseInt(edit.getAttribute('data-edit'), 10));
            return;
        }
        var trash = e.target.closest('[data-trash]');
        if (trash) {
            var tid = parseInt(trash.getAttribute('data-trash'), 10);
            if (!tid || !window.confirm('Удалить курс? Он попадёт в корзину WordPress.')) return;
            post('aco_admin_trash_course', { id: tid }).then(function (data) {
                state.courses = data.courses || [];
                renderCourses();
                toast('Курс удалён');
                if (state.courseId === tid) {
                    openEditor(0, true);
                }
            }).catch(function (err) { toast(err.message, true); });
            return;
        }
        var add = e.target.closest('[data-add]');
        if (add) {
            addRow(add.getAttribute('data-add'), {});
            return;
        }
        if (e.target.closest('[data-remove]')) {
            var row = e.target.closest('[data-row]');
            if (row) row.remove();
            return;
        }
        var pick = e.target.closest('[data-pick]');
        if (pick) {
            pickMedia(pick.getAttribute('data-pick'));
            return;
        }
        var clear = e.target.closest('[data-clear]');
        if (clear) {
            var kind = clear.getAttribute('data-clear');
            if (kind === 'image') {
                setVal(courseForm, 'image_id', '');
                root.querySelector('[data-preview="image"]').innerHTML = 'Нет обложки';
            } else {
                setVal(courseForm, 'video_id', '');
                root.querySelector('[data-preview="video"]').innerHTML = 'Нет видео';
            }
        }
    });

    if (courseForm) {
        courseForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var payload = {
                id: val(courseForm, 'id') || 0,
                title: val(courseForm, 'title'),
                excerpt: val(courseForm, 'excerpt'),
                content: val(courseForm, 'content'),
                status: val(courseForm, 'status_publish') ? 'publish' : 'draft',
                featured: val(courseForm, 'featured') ? 1 : 0,
                regular_price: val(courseForm, 'regular_price'),
                sale_price: val(courseForm, 'sale_price'),
                image_id: val(courseForm, 'image_id') || 0,
                video_id: val(courseForm, 'video_id') || 0,
                category_id: val(courseForm, 'category_id') || 0,
                duration: val(courseForm, 'duration'),
                level: val(courseForm, 'level'),
                teacher_user_id: val(courseForm, 'teacher_user_id') || 0,
                moodle_course_id: val(courseForm, 'moodle_course_id') || 0,
                program: collectRepeater('program', function (row) {
                    return {
                        title: row.querySelector('[data-f="title"]').value,
                        items: row.querySelector('[data-f="items"]').value.split(/\n/).map(function (s) { return s.trim(); }).filter(Boolean)
                    };
                }),
                benefits: collectRepeater('benefits', function (row) {
                    return {
                        title: row.querySelector('[data-f="title"]').value,
                        text: row.querySelector('[data-f="text"]').value
                    };
                }),
                faq: collectRepeater('faq', function (row) {
                    return {
                        q: row.querySelector('[data-f="q"]').value,
                        a: row.querySelector('[data-f="a"]').value
                    };
                })
            };
            post('aco_admin_save_course', payload).then(function (data) {
                state.courses = data.courses || [];
                renderCourses();
                fillCourse(data.course);
                state.courseId = data.course && data.course.id ? data.course.id : 0;
                setView('edit', state.courseId, true);
                toast('Курс сохранён');
            }).catch(function (err) { toast(err.message, true); });
        });
    }

    if (courseForm) {
        var moodleSel = input(courseForm, 'moodle_course_id');
        if (moodleSel) {
            moodleSel.addEventListener('change', syncBuilderLink);
        }
    }

    window.addEventListener('popstate', function (e) {
        var st = e.state || {};
        var view = st.view || 'courses';
        if (view === 'edit') {
            openEditor(parseInt(st.id, 10) || 0, true);
        } else {
            setView(view, 0, true);
        }
    });

    post('aco_admin_bootstrap', {}).then(function (data) {
        state.courses = data.courses || [];
        state.directions = data.directions || [];
        state.moodle = data.moodle || [];
        state.teachers = data.teachers || [];
        state.woo = data.woo !== false;
        renderCourses();
        fillSelect(input(courseForm, 'moodle_course_id'), state.moodle, 'id', 'label', val(courseForm, 'moodle_course_id'), '— не привязан —', '');
        fillSelect(input(courseForm, 'teacher_user_id'), state.teachers, 'id', 'label', '0', 'Команда АЦО', '0');
        syncBuilderLink();
        history.replaceState({ view: state.view, id: state.courseId }, '', window.location.href);
        if (state.view === 'edit') {
            openEditor(state.courseId, true);
        }
    }).catch(function (err) {
        toast(err.message, true);
    });
})();
