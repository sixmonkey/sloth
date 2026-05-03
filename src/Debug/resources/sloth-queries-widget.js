(function () {
    const csscls = PhpDebugBar.utils.makecsscls('phpdebugbar-widgets-');

    const style = document.createElement('style');
    style.textContent = `
        div.phpdebugbar-widgets-sqlqueries span.${csscls('source')} {
            float: right;
            margin-left: 8px;
            color: var(--debugbar-text-muted);
            font-family: var(--debugbar-font-mono);
            font-size: 11px;
        }
    `;
    document.head.append(style);

    class SlothQueriesWidget extends PhpDebugBar.Widget {
        get className() {
            return csscls('sqlqueries');
        }

        itemRenderer(li, stmt) {
            stmt.type = stmt.type || 'query';
            if (stmt.slow) {
                li.classList.add(csscls('sql-slow'));
            }
            if (stmt.duration_str) {
                const duration = document.createElement('span');
                duration.setAttribute('title', 'Duration');
                duration.classList.add(csscls('duration'));
                duration.textContent = stmt.duration_str;
                li.append(duration);
            }
            if (stmt.source) {
                const source = document.createElement('span');
                source.setAttribute('title', 'Source');
                source.classList.add(csscls('source'));
                source.textContent = stmt.source;
                li.append(source);
            }
            const code = document.createElement('code');
            code.classList.add(csscls('sql'));
            const formatted = typeof phpdebugbar_sqlformatter !== 'undefined'
                ? phpdebugbar_sqlformatter.format(stmt.sql)
                : stmt.sql;
            code.innerHTML = PhpDebugBar.Widgets.highlight(formatted, 'sql');
            li.append(code);

            if (typeof stmt.is_success !== 'undefined' && !stmt.is_success) {
                li.classList.add(csscls('error'));
                const errorSpan = document.createElement('span');
                errorSpan.classList.add(csscls('error'));
                errorSpan.textContent = `[${stmt.error_code}] ${stmt.error_message}`;
                li.append(errorSpan);
            }

            const table = document.createElement('table');
            table.classList.add(csscls('params'));
            table.hidden = true;

            if (stmt.params && Object.keys(stmt.params).length > 0) {
                const thead = document.createElement('thead');
                const tr = document.createElement('tr');
                const th = document.createElement('th');
                th.colSpan = 2;
                th.classList.add(csscls('name'));
                th.textContent = 'Params';
                tr.append(th);
                thead.append(tr);
                table.append(thead);

                const tbody = document.createElement('tbody');
                stmt.params.forEach(function (param, i) {
                    const row = document.createElement('tr');
                    const keyTd = document.createElement('td');
                    keyTd.classList.add('phpdebugbar-text-muted');
                    keyTd.textContent = i;
                    row.append(keyTd);
                    const valueTd = document.createElement('td');
                    valueTd.textContent = typeof param === 'object' ? JSON.stringify(param) : param;
                    row.append(valueTd);
                    tbody.append(row);
                });
                table.append(tbody);
            }

            if (!table.querySelectorAll('tr').length) {
                table.style.display = 'none';
            }
            li.append(table);
            li.style.cursor = 'pointer';
            li.addEventListener('click', function (event) {
                if (window.getSelection().type === 'Range' || event.target.closest('.sf-dump')) {
                    return '';
                }
                table.hidden = !table.hidden;
                const code = li.querySelector('code');
                if (code && typeof phpdebugbar_sqlformatter !== 'undefined') {
                    let sql = stmt.sql;
                    if (!table.hidden) {
                        sql = phpdebugbar_sqlformatter.format(stmt.sql);
                    }
                    code.innerHTML = PhpDebugBar.Widgets.highlight(sql, 'sql');
                }
            });
        }

        render() {
            this.status = document.createElement('div');
            this.status.classList.add(csscls('status'));
            this.el.append(this.status);

            this.list = new PhpDebugBar.Widgets.ListWidget({
                itemRenderer: (li, stmt) => this.itemRenderer(li, stmt)
            });

            this.bindAttr('data', function (data) {
                if (data.length <= 0 || !data.statements) {
                    return false;
                }
                this.list.set('data', data.statements);
                this.status.innerHTML = '';

                const t = document.createElement('span');
                t.textContent = data.nb_statements + ' statements were executed';
                this.status.append(t);

                if (data.accumulated_duration_str) {
                    const duration = document.createElement('span');
                    duration.setAttribute('title', 'Accumulated duration');
                    duration.classList.add(csscls('duration'));
                    duration.textContent = data.accumulated_duration_str;
                    this.status.append(duration);
                }
            });
            this.el.append(this.list.el);
        }
    }

    PhpDebugBar.Widgets.SlothQueriesWidget = SlothQueriesWidget;
})();
