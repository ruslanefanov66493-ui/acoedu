(function () {
    var form = document.querySelector('.lh-chat__compose');
    if (!form) {
        return;
    }

    var text = form.querySelector('textarea');
    var file = form.querySelector('input[type="file"]');
    var send = form.querySelector('.lh-chat__send');
    var picks = form.querySelector('.lh-chat__picks');
    var warn = form.querySelector('.lh-chat__warn');
    var log = document.querySelector('.lh-chat__log');
    var maxBytes = parseInt(form.getAttribute('data-max-bytes') || '0', 10);
    var maxFiles = parseInt(form.getAttribute('data-max-files') || '5', 10);
    var selected = [];

    function setWarn(message) {
        if (!warn) {
            return;
        }
        warn.textContent = message || '';
        warn.hidden = !message;
    }

    function sync() {
        var hasText = text && text.value.trim().length > 0;
        if (send) {
            send.disabled = !hasText && selected.length === 0;
        }
        if (text) {
            text.style.height = 'auto';
            text.style.height = Math.min(text.scrollHeight, 140) + 'px';
        }
    }

    function writeInput() {
        if (!file || typeof DataTransfer === 'undefined') {
            return;
        }
        var data = new DataTransfer();
        selected.forEach(function (item) {
            data.items.add(item.file);
        });
        file.files = data.files;
    }

    function renderPicks() {
        if (!picks) {
            return;
        }
        picks.innerHTML = '';
        picks.hidden = selected.length === 0;
        selected.forEach(function (item, index) {
            var chip = document.createElement('span');
            chip.className = 'lh-chat__pick';
            if (item.file.type && item.file.type.indexOf('image/') === 0) {
                var img = document.createElement('img');
                img.src = item.url;
                img.alt = '';
                chip.appendChild(img);
            }
            var name = document.createElement('span');
            name.textContent = item.file.name;
            chip.appendChild(name);
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.setAttribute('aria-label', 'Убрать файл');
            remove.textContent = '×';
            remove.addEventListener('click', function () {
                if (item.url) {
                    URL.revokeObjectURL(item.url);
                }
                selected.splice(index, 1);
                writeInput();
                renderPicks();
                sync();
            });
            chip.appendChild(remove);
            picks.appendChild(chip);
        });
    }

    if (file) {
        file.addEventListener('change', function () {
            var rejected = '';
            Array.prototype.forEach.call(file.files, function (picked) {
                if (selected.length >= maxFiles) {
                    rejected = 'Можно прикрепить не больше ' + maxFiles + ' файлов.';
                    return;
                }
                if (maxBytes && picked.size > maxBytes) {
                    rejected = '«' + picked.name + '» больше допустимого размера.';
                    return;
                }
                selected.push({
                    file: picked,
                    url: URL.createObjectURL(picked)
                });
            });
            writeInput();
            renderPicks();
            setWarn(rejected);
            sync();
        });
    }

    if (text) {
        text.addEventListener('input', function () {
            setWarn('');
            sync();
        });
        text.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                if (send && !send.disabled) {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
            }
        });
    }

    form.addEventListener('submit', function () {
        window.setTimeout(function () {
            if (send) {
                send.disabled = true;
            }
        }, 0);
    });

    sync();
    if (log) {
        log.scrollTop = log.scrollHeight;
    }
    if (text) {
        text.focus();
    }
}());
