/**
 * Custom DebugBar widget for Sloth SQL queries.
 *
 * Extends the default SqlQueriesWidget to display a Source column
 * (Eloquent / WPDB) for each query. SQL is formatted on load
 * using phpdebugbar_sqlformatter when available.
 *
 * Params table toggles on click — formatted SQL is shown when expanded.
 */
(function () {
    const csscls = PhpDebugBar.utils.makecsscls('phpdebugbar-widgets-');

    // Inline CSS for the Source column (right-aligned, monospace)
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

    /**
     * Widget that renders each SQL query row with duration, source, and formatted SQL.
     */
    class SlothQueriesWidget extends PhpDebugBar.Widget {
        get className() {
            return csscls('sqlqueries');
        }

        /**
         * Build a single query list item.
         *
         * @param {HTMLElement} li    The list item element to populate.
         * @param {Object}      stmt  Query data (sql, time, source, params, …).
         */
        itemRenderer(li, stmt) {
            stmt.type = stmt.type || 'query';

            // Mark slow queries for special styling
            if (stmt.slow) {
                li.classList.add(csscls('sql-slow'));
            }

            // Duration badge (e.g. "2.34ms")
            if (stmt.duration_str) {
                const duration = document.createElement('span');
                duration.setAttribute('title', 'Duration');
                duration.classList.add(csscls('duration'));
                duration.textContent = stmt.duration_str;
                li.append(duration);
            }

            // Source badge (e.g. "Eloquent" or "WPDB — functions.php:42")
            if (stmt.source) {
                const source = document.createElement('span');
                source.setAttribute('title', 'Source');
                source.classList.add(csscls('source'));
                source.textContent = stmt.source;
                li.append(source);
            }

            // Formatted SQL with syntax highlighting
            const code = document.createElement('code');
            code.classList.add(csscls('sql'));
            const formatted = typeof phpdebugbar_sqlformatter !== 'undefined'
                ? phpdebugbar_sqlformatter.format(stmt.sql)
                : stmt.sql;
            code.innerHTML = PhpDebugBar.Widgets.highlight(formatted, 'sql');
            li.append(code);

            // Error display for failed queries
            if (typeof stmt.is_success !== 'undefined' && !stmt.is_success) {
                li.classList.add(csscls('error'));
                const errorSpan = document.createElement('span');
                errorSpan.classList.add(csscls('error'));
                errorSpan.textContent = `[${stmt.error_code}] ${stmt.error_message}`;
                li.append(errorSpan);
            }

            // Hidden params table — shown on click
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

            // Click to toggle params table + re-render SQL
            li.style.cursor = 'pointer';
            li.addEventListener('click', function (event) {
                // Don't toggle when user is selecting text or interacting with debug dumps
                if (window.getSelection().type === 'Range' || event.target.closest('.sf-dump')) {
                    return '';
                }
                table.hidden = !table.hidden;
                const code = li.querySelector('code');
                if (code && typeof phpdebugbar_sqlformatter !== 'undefined') {
                    let sql = stmt.sql;
                    // Show formatted SQL when params are visible, raw SQL when collapsed
                    if (!table.hidden) {
                        sql = phpdebugbar_sqlformatter.format(stmt.sql);
                    }
                    code.innerHTML = PhpDebugBar.Widgets.highlight(sql, 'sql');
                }
            });
        }

        /**
         * Render the widget container with status bar and query list.
         */
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

                // Query count summary
                const t = document.createElement('span');
                t.textContent = data.nb_statements + ' statements were executed';
                this.status.append(t);

                // Total accumulated duration
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
