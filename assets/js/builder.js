(function () {
    var toggle = document.getElementById('signatur_link_toggle');
    var field = document.getElementById('signatur_link_field');
    var input = document.getElementById('signatur_link');

    toggle.addEventListener('click', function () {
        field.style.display = '';
        input.focus();
    });

    var modalOverlay = document.getElementById('signatur_modal_overlay');
    var modalTitle = document.getElementById('signatur_modal_title');
    var modalBody = document.getElementById('signatur_modal_body');
    var modalCancel = document.getElementById('signatur_modal_cancel');
    var modalSave = document.getElementById('signatur_modal_save');
    var widthTemplate = document.getElementById('signatur_modal_template_width');
    var rowGroup = document.getElementById('signatur_row_group');
    var rowTopWrap = document.getElementById('signatur_row_top_wrap');
    var rowAddWrap = document.getElementById('signatur_row_add_wrap');
    var fieldTable = document.getElementById('signatur_field_table');
    var fieldTbody = document.getElementById('signatur_field_tbody');
    var rowsActions = document.getElementById('signatur_rows_actions');
    var rowActionsTemplate = document.getElementById('signatur_row_actions_template');
    var colgroup = document.getElementById('signatur_field_colgroup');
    var headerRow = document.getElementById('signatur_field_header_row');
    var resizeHandles = document.getElementById('signatur_col_resize_handles');
    var minWidth = 200;
    var minColumnWidth = 20;
    var paddingSides = ['top', 'right', 'bottom', 'left'];
    var activeModalType = null;
    var columnWidths = Array.prototype.map.call(colgroup.children, function (col) {
        return parseInt(col.style.width, 10) || minColumnWidth;
    });

    function clampWidth(value) {
        var width = parseInt(value, 10);

        if (isNaN(width) || width < minWidth) {
            return minWidth;
        }

        return width;
    }

    function clampPadding(value) {
        var padding = parseInt(value, 10);

        if (isNaN(padding) || padding < 0) {
            return 0;
        }

        return padding;
    }

    function openModal(title, type) {
        modalTitle.textContent = title;
        modalBody.innerHTML = '';
        activeModalType = type || null;

        if ('width' === type) {
            modalBody.appendChild(widthTemplate.content.cloneNode(true));
            var widthInput = document.getElementById('signatur_width_input');
            widthInput.value = parseInt(fieldTable.style.width, 10) || 300;

            widthInput.addEventListener('blur', function () {
                widthInput.value = clampWidth(widthInput.value);
            });

            paddingSides.forEach(function (side) {
                var paddingInput = document.getElementById('signatur_padding_' + side);
                var computed = fieldTable.style['padding' + side.charAt(0).toUpperCase() + side.slice(1)];
                paddingInput.value = computed ? (parseInt(computed, 10) || '') : '';

                paddingInput.addEventListener('blur', function () {
                    if ('' !== paddingInput.value) {
                        paddingInput.value = clampPadding(paddingInput.value);
                    }
                });
            });
        }

        modalOverlay.style.display = 'flex';
    }

    function closeModal() {
        modalOverlay.style.display = 'none';
    }

    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-modal-title]');

        if (button) {
            openModal(button.getAttribute('data-modal-title'), button.getAttribute('data-modal-type'));
        }
    });

    var rowAddButton = document.getElementById('signatur_row_add');
    var rowAddIndicator = document.getElementById('signatur_row_add_indicator');

    rowAddButton.addEventListener('mouseenter', function () {
        var rect = fieldTable.getBoundingClientRect();

        rowAddIndicator.style.top = (rect.bottom - 1) + 'px';
        rowAddIndicator.style.left = rect.left + 'px';
        rowAddIndicator.style.width = rect.width + 'px';
        rowAddIndicator.style.display = 'block';
    });

    rowAddButton.addEventListener('mouseleave', function () {
        rowAddIndicator.style.display = 'none';
    });

    var columnAddEndButton = document.getElementById('signatur_column_add_end');
    var columnAddIndicator = document.getElementById('signatur_column_add_indicator');

    columnAddEndButton.addEventListener('mouseenter', function () {
        var rect = fieldTable.getBoundingClientRect();

        columnAddIndicator.style.top = rect.top + 'px';
        columnAddIndicator.style.left = (rect.right - 1) + 'px';
        columnAddIndicator.style.height = rect.height + 'px';
        columnAddIndicator.style.display = 'block';
    });

    columnAddEndButton.addEventListener('mouseleave', function () {
        columnAddIndicator.style.display = 'none';
    });

    columnAddEndButton.addEventListener('click', function () {
        pushUndoSnapshot();

        /*
         * The table must stay exactly as wide as configured - so the
         * new column's width comes out of the existing columns,
         * shrinking them all proportionally, rather than growing the
         * table itself.
         */
        var totalWidth = parseInt(fieldTable.style.width, 10) || minWidth;
        var newWidth = Math.max(minColumnWidth, Math.floor(totalWidth / (columnWidths.length + 1)));
        var targetWidth = totalWidth - newWidth;
        var oldWidth = columnWidths.reduce(function (sum, w) {
            return sum + w;
        }, 0);
        var ratio = targetWidth / oldWidth;
        var used = 0;

        columnWidths = columnWidths.map(function (w, i) {
            if (i === columnWidths.length - 1) {
                return Math.max(minColumnWidth, targetWidth - used);
            }

            var scaled = Math.max(minColumnWidth, Math.round(w * ratio));
            used += scaled;

            return scaled;
        });

        insertColumnAt(columnWidths.length, newWidth);
    });

    var rowHoverIndicator = document.getElementById('signatur_row_hover_indicator');
    var editSignatureButton = document.getElementById('signatur_edit_signature_button');

    editSignatureButton.addEventListener('mouseenter', function () {
        var tableRect = fieldTable.getBoundingClientRect();

        rowHoverIndicator.style.top = tableRect.top + 'px';
        rowHoverIndicator.style.left = tableRect.left + 'px';
        rowHoverIndicator.style.width = tableRect.width + 'px';
        rowHoverIndicator.style.height = tableRect.height + 'px';
        rowHoverIndicator.style.display = 'block';
    });

    editSignatureButton.addEventListener('mouseleave', function () {
        rowHoverIndicator.style.display = 'none';
    });

    function getCellColSpan(cell) {
        return parseInt(cell.getAttribute('colspan'), 10) || 1;
    }

    function setCellColSpan(cell, span) {
        if (span > 1) {
            cell.setAttribute('colspan', span);
        } else {
            cell.removeAttribute('colspan');
        }
    }

    function getCellRowSpan(cell) {
        return parseInt(cell.getAttribute('rowspan'), 10) || 1;
    }

    function setCellRowSpan(cell, span) {
        if (span > 1) {
            cell.setAttribute('rowspan', span);
        } else {
            cell.removeAttribute('rowspan');
        }
    }

    /**
     * Build the full occupancy grid of the table: grid[row][col] tells
     * which <td> owns that slot, and whether this row is where that
     * cell actually lives in the DOM (`owner`) or merely a slot it
     * covers via rowspan from an earlier row (pass-through).
     */
    function buildGrid() {
        var rows = Array.prototype.slice.call(fieldTbody.children);
        var pending = {};
        var grid = [];

        rows.forEach(function (row) {
            var cells = Array.prototype.slice.call(row.children);
            var col = 0;
            var cellPos = 0;
            var rowSlots = [];

            for (;;) {
                while (pending[col] && pending[col].remaining > 0) {
                    var p = pending[col];

                    rowSlots[col] = { cell: p.cell, start: p.start, end: p.end, owner: false };
                    p.remaining--;

                    if (0 === p.remaining) {
                        delete pending[col];
                    }

                    col++;
                }

                if (cellPos >= cells.length) {
                    break;
                }

                var cell = cells[cellPos];
                var colspan = getCellColSpan(cell);
                var rowspan = getCellRowSpan(cell);
                var start = col;
                var end = col + colspan;

                for (var c = start; c < end; c++) {
                    rowSlots[c] = { cell: cell, start: start, end: end, owner: c === start };
                }

                if (rowspan > 1) {
                    for (var c2 = start; c2 < end; c2++) {
                        pending[c2] = { cell: cell, start: start, end: end, remaining: rowspan - 1 };
                    }
                }

                col = end;
                cellPos++;
            }

            grid.push(rowSlots);
        });

        return grid;
    }

    function findCellPosition(grid, targetCell) {
        for (var r = 0; r < grid.length; r++) {
            for (var c = 0; c < grid[r].length; c++) {
                if (grid[r][c] && grid[r][c].cell === targetCell) {
                    return { row: r, col: c };
                }
            }
        }

        return null;
    }

    function renderColgroup() {
        colgroup.innerHTML = '';

        columnWidths.forEach(function (width) {
            var col = document.createElement('col');
            col.style.width = width + 'px';
            colgroup.appendChild(col);
        });

        renderColumnHeaders();
    }

    /**
     * Excel-style column label: 0 => A, 1 => B, ... 25 => Z,
     * 26 => AA, 27 => AB, ...
     */
    function columnLetter(index) {
        var letter = '';

        index += 1;

        while (index > 0) {
            var remainder = (index - 1) % 26;

            letter = String.fromCharCode(65 + remainder) + letter;
            index = Math.floor((index - 1) / 26);
        }

        return letter;
    }

    function renderColumnHeaders() {
        headerRow.innerHTML = '';

        columnWidths.forEach(function (width, index) {
            var header = document.createElement('th');

            header.className = 'signatur-column-header';
            header.setAttribute('data-index', index);
            header.textContent = columnLetter(index);
            headerRow.appendChild(header);
        });
    }

    function renderResizeHandles() {
        resizeHandles.innerHTML = '';

        if (columnWidths.length < 2) {
            return;
        }

        var headerRect = headerRow.getBoundingClientRect();
        var offset = headerRect.left;

        for (var i = 0; i < columnWidths.length - 1; i++) {
            offset += columnWidths[i];

            var handle = document.createElement('div');

            handle.className = 'signatur-col-resize-handle';
            handle.setAttribute('data-index', i);
            handle.style.top = headerRect.top + 'px';
            handle.style.height = headerRect.height + 'px';
            handle.style.left = offset + 'px';
            resizeHandles.appendChild(handle);
        }
    }

    /**
     * Insert a new grid column at `index` (0..columnWidths.length) across
     * every row. A cell (real or a rowspan covering that boundary from
     * an earlier row) that already reaches across the insertion point
     * simply grows by one column; otherwise a plain new <td> is added
     * at that row's matching position.
     */
    function insertColumnAt(index, width) {
        var grid = buildGrid();

        columnWidths.splice(index, 0, width);

        var grown = [];

        Array.prototype.forEach.call(fieldTbody.children, function (row, r) {
            var rowSlots = grid[r] || [];
            var slot = rowSlots[index];

            if (slot && index > slot.start && index < slot.end) {
                if (grown.indexOf(slot.cell) === -1) {
                    setCellColSpan(slot.cell, getCellColSpan(slot.cell) + 1);
                    grown.push(slot.cell);
                }

                return;
            }

            var referenceCell = null;

            for (var col = index; col < rowSlots.length; col++) {
                if (rowSlots[col] && rowSlots[col].owner) {
                    referenceCell = rowSlots[col].cell;
                    break;
                }
            }

            var newCell = document.createElement('td');
            newCell.innerHTML = '&nbsp;';

            if (referenceCell) {
                row.insertBefore(newCell, referenceCell);
            } else {
                row.appendChild(newCell);
            }
        });

        renderColgroup();
        renderResizeHandles();
    }

    /**
     * Remove grid column `index` from every row. A cell that only
     * covers that one column is removed outright; a cell (real or
     * covering that column via rowspan) that spans more than one
     * column simply shrinks by one.
     */
    function removeColumnAt(index) {
        if (columnWidths.length <= 1) {
            return;
        }

        var grid = buildGrid();
        var removedWidth = columnWidths.splice(index, 1)[0];
        var neighborIndex = index < columnWidths.length ? index : index - 1;
        columnWidths[neighborIndex] += removedWidth;

        var shrunk = [];

        grid.forEach(function (rowSlots) {
            var slot = rowSlots[index];

            if (! slot || shrunk.indexOf(slot.cell) !== -1) {
                return;
            }

            shrunk.push(slot.cell);

            var span = getCellColSpan(slot.cell);

            if (span > 1) {
                setCellColSpan(slot.cell, span - 1);
            } else if (slot.cell.parentNode) {
                slot.cell.parentNode.removeChild(slot.cell);
            }
        });

        renderColgroup();
        renderResizeHandles();
    }

    /**
     * Undo a merge on `cell`: shrink it back to a single 1x1 cell and
     * recreate a plain <td> for every grid position it used to cover
     * (its own row for the extra columns, and every row below it was
     * spanning down into, which currently have no <td> there at all).
     */
    function splitCell(cell) {
        var colspan = getCellColSpan(cell);
        var rowspan = getCellRowSpan(cell);

        if (colspan <= 1 && rowspan <= 1) {
            return;
        }

        var grid = buildGrid();
        var pos = findCellPosition(grid, cell);

        if (! pos) {
            return;
        }

        var startRow = pos.row;
        var startCol = pos.col;
        var rows = Array.prototype.slice.call(fieldTbody.children);

        setCellColSpan(cell, 1);
        setCellRowSpan(cell, 1);

        for (var r = startRow; r < startRow + rowspan; r++) {
            var row = rows[r];
            var rowSlots = grid[r] || [];
            var referenceCell = null;

            for (var col = startCol + colspan; col < rowSlots.length; col++) {
                if (rowSlots[col] && rowSlots[col].owner) {
                    referenceCell = rowSlots[col].cell;
                    break;
                }
            }

            var firstCol = (r === startRow) ? startCol + 1 : startCol;

            for (var c = firstCol; c < startCol + colspan; c++) {
                var newCell = document.createElement('td');
                newCell.innerHTML = '&nbsp;';

                if (referenceCell) {
                    row.insertBefore(newCell, referenceCell);
                } else {
                    row.appendChild(newCell);
                }
            }
        }

        rebuildRowActionsPanel();
    }

    /**
     * Highlight the row a hovered edit/delete button belongs to.
     * Delegated on the container instead of attached per-group, so it
     * keeps working as groups are added/removed by
     * rebuildRowActionsPanel() without ever needing to be re-wired.
     */
    function findRowForActionGroup(group) {
        var groups = Array.prototype.slice.call(rowsActions.children).slice(1);
        var index = groups.indexOf(group);

        if (-1 === index) {
            return null;
        }

        var visibleRows = Array.prototype.filter.call(fieldTbody.children, function (row) {
            return row.children.length > 0;
        });

        return visibleRows[index] || null;
    }

    /**
     * Rebuild the row-actions sidebar so it has exactly one group per
     * row that currently has real cells of its own - used after any
     * change to the row count or to which rows are empty.
     */
    function rebuildRowActionsPanel() {
        var nonEmptyRowCount = Array.prototype.filter.call(fieldTbody.children, function (row) {
            return row.children.length > 0;
        }).length;
        var spacer = rowsActions.firstElementChild;

        rowsActions.innerHTML = '';
        rowsActions.appendChild(spacer);

        for (var i = 0; i < nonEmptyRowCount; i++) {
            rowsActions.appendChild(rowActionsTemplate.content.cloneNode(true));
        }

        updateRowDeleteButtons();
    }

    /**
     * A single remaining row can't be deleted (removeRow() refuses),
     * so every "Delete row" button is greyed out while that's true.
     */
    function updateRowDeleteButtons() {
        var disableDelete = fieldTbody.children.length <= 1;

        Array.prototype.forEach.call(rowsActions.querySelectorAll('.signatur-row-delete-button'), function (button) {
            button.disabled = disableDelete;
        });
    }

    /**
     * Delete `row` entirely. A cell it owns that only covers this one
     * row disappears with it; a cell it owns with a taller rowspan is
     * instead moved down into the next row with its rowspan reduced
     * by one, so the merge it was part of just gets one row shorter
     * instead of losing its reach entirely. A cell owned by an
     * earlier row that merely passes through this row also has its
     * rowspan reduced by one, for the same reason.
     */
    function removeRow(row) {
        if (fieldTbody.children.length <= 1) {
            return;
        }

        var rows = Array.prototype.slice.call(fieldTbody.children);
        var rowIndex = rows.indexOf(row);

        if (-1 === rowIndex) {
            return;
        }

        var grid = buildGrid();
        var rowSlots = grid[rowIndex] || [];
        var nextRow = rows[rowIndex + 1];
        var handled = [];

        for (var c = 0; c < rowSlots.length; c++) {
            var slot = rowSlots[c];

            if (! slot || handled.indexOf(slot.cell) !== -1) {
                continue;
            }

            handled.push(slot.cell);

            var rowspan = getCellRowSpan(slot.cell);

            if (rowspan <= 1) {
                continue;
            }

            if (slot.owner) {
                var nextRowSlots = grid[rowIndex + 1] || [];
                var referenceCell = null;

                for (var col = slot.end; col < nextRowSlots.length; col++) {
                    if (nextRowSlots[col] && nextRowSlots[col].owner) {
                        referenceCell = nextRowSlots[col].cell;
                        break;
                    }
                }

                setCellRowSpan(slot.cell, rowspan - 1);

                if (referenceCell) {
                    nextRow.insertBefore(slot.cell, referenceCell);
                } else {
                    nextRow.appendChild(slot.cell);
                }
            } else {
                setCellRowSpan(slot.cell, rowspan - 1);
            }
        }

        row.parentNode.removeChild(row);

        rebuildRowActionsPanel();
        rowHoverIndicator.style.display = 'none';
        clearSelection();
        hideColumnButtons();
        hideColumnCombineButton();
        renderResizeHandles();
    }

    rowsActions.addEventListener('mouseover', function (e) {
        var group = e.target.closest('.signatur-row-actions');
        var row = group && findRowForActionGroup(group);

        if (! row) {
            return;
        }

        var rowRect = row.getBoundingClientRect();
        var tableRect = fieldTable.getBoundingClientRect();

        rowHoverIndicator.style.top = rowRect.top + 'px';
        rowHoverIndicator.style.left = tableRect.left + 'px';
        rowHoverIndicator.style.width = tableRect.width + 'px';
        rowHoverIndicator.style.height = rowRect.height + 'px';
        rowHoverIndicator.style.display = 'block';
    });

    rowsActions.addEventListener('mouseout', function (e) {
        if (e.target.closest('.signatur-row-actions')) {
            rowHoverIndicator.style.display = 'none';
        }
    });

    rowsActions.addEventListener('click', function (e) {
        var deleteButton = e.target.closest('.signatur-row-delete-button');
        var group = deleteButton && deleteButton.closest('.signatur-row-actions');
        var row = group && findRowForActionGroup(group);

        if (! row || fieldTbody.children.length <= 1) {
            return;
        }

        pushUndoSnapshot();
        removeRow(row);
    });

    rowAddButton.addEventListener('click', function () {
        pushUndoSnapshot();

        var row = document.createElement('tr');

        columnWidths.forEach(function () {
            var cell = document.createElement('td');
            cell.innerHTML = '&nbsp;';
            row.appendChild(cell);
        });

        fieldTbody.appendChild(row);
        rowsActions.appendChild(rowActionsTemplate.content.cloneNode(true));
        updateRowDeleteButtons();
        renderResizeHandles();
    });

    renderColumnHeaders();
    updateRowDeleteButtons();
    renderResizeHandles();

    var resizing = null;

    resizeHandles.addEventListener('mousedown', function (e) {
        var handle = e.target.closest('.signatur-col-resize-handle');

        if (! handle) {
            return;
        }

        e.preventDefault();
        pushUndoSnapshot();

        var index = parseInt(handle.getAttribute('data-index'), 10);

        resizing = {
            index: index,
            startX: e.clientX,
            widthA: columnWidths[index],
            widthB: columnWidths[index + 1]
        };
    });

    document.addEventListener('mousemove', function (e) {
        if (! resizing) {
            return;
        }

        var delta = e.clientX - resizing.startX;
        var newA = resizing.widthA + delta;
        var newB = resizing.widthB - delta;

        if (newA < minColumnWidth) {
            newA = minColumnWidth;
            newB = resizing.widthA + resizing.widthB - newA;
        }

        if (newB < minColumnWidth) {
            newB = minColumnWidth;
            newA = resizing.widthA + resizing.widthB - newB;
        }

        columnWidths[resizing.index] = newA;
        columnWidths[resizing.index + 1] = newB;
        renderColgroup();
        renderResizeHandles();
    });

    function stopResizing() {
        resizing = null;
    }

    document.addEventListener('mouseup', stopResizing);
    document.documentElement.addEventListener('mouseleave', stopResizing);

    window.addEventListener('scroll', renderResizeHandles, true);
    window.addEventListener('resize', renderResizeHandles);

    var columnAddButton = document.getElementById('signatur_column_add');
    var columnCombineButton = document.getElementById('signatur_column_combine');
    var columnRemoveButton = document.getElementById('signatur_column_remove');
    var columnSplitButton = document.getElementById('signatur_column_split');
    var activeColumnCell = null;
    var selectedCells = [];
    var isSelecting = false;
    var selectionAnchor = null;

    function highlightSelection(cells) {
        selectedCells.forEach(function (cell) {
            cell.classList.remove('signatur-cell-selected');
        });

        selectedCells = cells;

        selectedCells.forEach(function (cell) {
            cell.classList.add('signatur-cell-selected');
        });
    }

    /**
     * A selection may only grow along one axis from the anchor cell -
     * straight across a row, or straight down a column, never both at
     * once. When the drag moves diagonally, whichever axis moved
     * further wins and the other is locked back to the anchor.
     *
     * Growing down a column also stops at the first row whose cell in
     * that column covers a different set of columns than the
     * anchor's (same colspan alone isn't enough - two cells can both
     * be 2 columns wide yet start at different columns, which is just
     * as invalid a pairing). Such a row, and anything past it, is
     * simply never part of the selection.
     */
    function selectRectangle(fromCell, toCell) {
        var grid = buildGrid();
        var fromPos = findCellPosition(grid, fromCell);
        var toPos = findCellPosition(grid, toCell);

        if (! fromPos || ! toPos) {
            return [];
        }

        var rowDelta = Math.abs(toPos.row - fromPos.row);
        var colDelta = Math.abs(toPos.col - fromPos.col);
        var minRow, maxRow, minCol, maxCol;

        if (rowDelta >= colDelta) {
            minCol = maxCol = fromPos.col;

            var anchorSlot = grid[fromPos.row] && grid[fromPos.row][minCol];
            var step = toPos.row >= fromPos.row ? 1 : -1;
            var boundaryRow = fromPos.row;

            for (var r = fromPos.row + step; r !== toPos.row + step; r += step) {
                var slot = grid[r] && grid[r][minCol];

                if (! anchorSlot || ! slot || slot.start !== anchorSlot.start || slot.end !== anchorSlot.end) {
                    break;
                }

                boundaryRow = r;
            }

            minRow = Math.min(fromPos.row, boundaryRow);
            maxRow = Math.max(fromPos.row, boundaryRow);
        } else {
            minCol = Math.min(fromPos.col, toPos.col);
            maxCol = Math.max(fromPos.col, toPos.col);
            minRow = maxRow = fromPos.row;
        }

        var cells = [];

        for (var r = minRow; r <= maxRow; r++) {
            for (var c = minCol; c <= maxCol; c++) {
                var slot = grid[r] && grid[r][c];

                if (slot && cells.indexOf(slot.cell) === -1) {
                    cells.push(slot.cell);
                }
            }
        }

        return cells;
    }

    /**
     * Cells spread across more than one row are never mergeable
     * unless every row in range has a cell covering that exact same
     * set of columns - matching colspan alone isn't enough, since two
     * cells can both be 2 columns wide yet start at different
     * columns (a "staircase" pairing that isn't a real rectangle).
     * This mirrors the rule selectRectangle() already enforces while
     * dragging, kept here too as a guard against the merge being
     * triggered any other way. A same-row (horizontal) merge has no
     * such constraint.
     */
    function hasVerticalRangeMismatch(cells) {
        var grid = buildGrid();
        var positions = cells.map(function (cell) {
            return findCellPosition(grid, cell);
        }).filter(function (pos) {
            return null !== pos;
        });

        if (positions.length < 2) {
            return false;
        }

        var minRow = Math.min.apply(null, positions.map(function (pos) { return pos.row; }));
        var maxRow = Math.max.apply(null, positions.map(function (pos) { return pos.row; }));

        if (minRow === maxRow) {
            return false;
        }

        var anchorSlot = grid[positions[0].row] && grid[positions[0].row][positions[0].col];

        for (var i = 0; i < positions.length; i++) {
            var slot = grid[positions[i].row] && grid[positions[i].row][positions[i].col];

            if (! anchorSlot || ! slot || slot.start !== anchorSlot.start || slot.end !== anchorSlot.end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mirrors hasVerticalRangeMismatch() for the other direction:
     * cells spread across more than one column are never mergeable
     * unless every one of them spans exactly the same set of rows -
     * matching rowspan alone isn't enough, since two cells can both
     * be 2 rows tall yet start at different rows. A single-row
     * (horizontal-only) merge has no such constraint.
     */
    function hasHorizontalRangeMismatch(cells) {
        var grid = buildGrid();
        var positions = cells.map(function (cell) {
            return findCellPosition(grid, cell);
        }).filter(function (pos) {
            return null !== pos;
        });

        if (positions.length < 2) {
            return false;
        }

        var minCol = Math.min.apply(null, positions.map(function (pos) { return pos.col; }));
        var maxCol = Math.max.apply(null, positions.map(function (pos) { return pos.col; }));

        if (minCol === maxCol) {
            return false;
        }

        var anchorRowStart = positions[0].row;
        var anchorRowEnd = anchorRowStart + getCellRowSpan(cells[0]);

        for (var i = 0; i < positions.length; i++) {
            var rowStart = positions[i].row;
            var rowEnd = rowStart + getCellRowSpan(cells[i]);

            if (rowStart !== anchorRowStart || rowEnd !== anchorRowEnd) {
                return true;
            }
        }

        return false;
    }

    function positionColumnButtons(cell) {
        var buttonSize = 32;
        var gap = 4;
        var showSplit = getCellColSpan(cell) > 1 || getCellRowSpan(cell) > 1;
        var showRemove = columnWidths.length > 1 && 1 === getCellColSpan(cell);
        var buttonCount = 1 + (showSplit ? 1 : 0) + (showRemove ? 1 : 0);
        var groupWidth = buttonSize * buttonCount + gap * (buttonCount - 1);
        var rect = cell.getBoundingClientRect();
        var top = rect.top + (rect.height - buttonSize) / 2;
        var left = rect.left + (rect.width - groupWidth) / 2;

        if (showSplit) {
            columnSplitButton.style.top = top + 'px';
            columnSplitButton.style.left = left + 'px';
            columnSplitButton.style.display = 'inline-flex';
            left += buttonSize + gap;
        } else {
            columnSplitButton.style.display = 'none';
        }

        columnAddButton.style.top = top + 'px';
        columnAddButton.style.left = left + 'px';
        columnAddButton.style.display = 'inline-flex';
        left += buttonSize + gap;

        if (showRemove) {
            columnRemoveButton.style.top = top + 'px';
            columnRemoveButton.style.left = left + 'px';
            columnRemoveButton.style.display = 'inline-flex';
        } else {
            columnRemoveButton.style.display = 'none';
        }
    }

    function positionColumnCombineButton(cells) {
        var buttonSize = 32;
        var top = Infinity;
        var left = Infinity;
        var bottom = -Infinity;
        var right = -Infinity;

        cells.forEach(function (cell) {
            var rect = cell.getBoundingClientRect();

            top = Math.min(top, rect.top);
            left = Math.min(left, rect.left);
            bottom = Math.max(bottom, rect.bottom);
            right = Math.max(right, rect.right);
        });

        columnCombineButton.style.top = (top + (bottom - top - buttonSize) / 2) + 'px';
        columnCombineButton.style.left = (left + (right - left - buttonSize) / 2) + 'px';
        columnCombineButton.style.display = 'inline-flex';
    }

    function hideColumnAddButton() {
        columnAddButton.style.display = 'none';
    }

    function hideColumnCombineButton() {
        columnCombineButton.style.display = 'none';
    }

    function hideColumnRemoveButton() {
        columnRemoveButton.style.display = 'none';
    }

    function hideColumnSplitButton() {
        columnSplitButton.style.display = 'none';
    }

    function hideColumnButtons() {
        activeColumnCell = null;
        hideColumnAddButton();
        hideColumnRemoveButton();
        hideColumnSplitButton();
    }

    function clearSelection() {
        highlightSelection([]);
        hideColumnCombineButton();
        hideColumnButtons();
    }

    fieldTbody.addEventListener('mousedown', function (e) {
        var cell = e.target.closest('td');

        if (! cell) {
            return;
        }

        e.preventDefault();
        isSelecting = true;
        selectionAnchor = cell;
        hideColumnButtons();
        hideColumnCombineButton();
        highlightSelection([cell]);
    });

    fieldTbody.addEventListener('mouseover', function (e) {
        if (! isSelecting) {
            return;
        }

        var cell = e.target.closest('td');

        if (! cell) {
            return;
        }

        var cells = selectRectangle(selectionAnchor, cell);

        if (hasVerticalRangeMismatch(cells) || hasHorizontalRangeMismatch(cells)) {
            return;
        }

        highlightSelection(cells);
    });

    columnRemoveButton.addEventListener('click', function () {
        if (! activeColumnCell || columnWidths.length <= 1) {
            hideColumnButtons();
            return;
        }

        pushUndoSnapshot();

        var start = findCellPosition(buildGrid(), activeColumnCell).col;
        var span = getCellColSpan(activeColumnCell);

        removeColumnAt(start + span - 1);
        hideColumnButtons();
    });

    columnSplitButton.addEventListener('click', function () {
        if (! activeColumnCell) {
            return;
        }

        pushUndoSnapshot();
        splitCell(activeColumnCell);
        clearSelection();
    });

    function finishSelection() {
        if (! isSelecting) {
            return;
        }

        isSelecting = false;

        if (1 === selectedCells.length) {
            activeColumnCell = selectedCells[0];
            positionColumnButtons(activeColumnCell);
        } else if (selectedCells.length >= 2) {
            if (hasVerticalRangeMismatch(selectedCells) || hasHorizontalRangeMismatch(selectedCells)) {
                clearSelection();
            } else {
                positionColumnCombineButton(selectedCells);
            }
        } else {
            clearSelection();
        }
    }

    document.addEventListener('mouseup', finishSelection);

    document.documentElement.addEventListener('mouseleave', finishSelection);

    columnAddButton.addEventListener('click', function () {
        if (! activeColumnCell) {
            return;
        }

        pushUndoSnapshot();

        var start = findCellPosition(buildGrid(), activeColumnCell).col;
        var span = getCellColSpan(activeColumnCell);
        var insertIndex = start + span;
        var neighborWidth = columnWidths[start];
        var newWidth = Math.max(minColumnWidth, Math.floor(neighborWidth / 2));

        columnWidths[start] = Math.max(minColumnWidth, neighborWidth - newWidth);
        insertColumnAt(insertIndex, newWidth);

        hideColumnButtons();
    });

    columnCombineButton.addEventListener('click', function () {
        if (selectedCells.length < 2 || hasVerticalRangeMismatch(selectedCells) || hasHorizontalRangeMismatch(selectedCells)) {
            return;
        }

        pushUndoSnapshot();

        var grid = buildGrid();
        var cellPositions = selectedCells.map(function (cell) {
            return { cell: cell, pos: findCellPosition(grid, cell) };
        }).filter(function (item) {
            return null !== item.pos;
        });

        if (cellPositions.length < 2) {
            return;
        }

        /*
         * The merged rectangle's true bounds come from each cell's
         * full extent (its grid slot's start/end for columns, its
         * own rowspan for rows) - not just its top-left grid
         * position. Using only the start position here previously
         * collapsed any cell already wider/taller than 1 back down
         * to 1 when it got re-set below.
         */
        var minRow = Infinity;
        var maxRow = -Infinity;
        var minCol = Infinity;
        var maxCol = -Infinity;

        cellPositions.forEach(function (item) {
            var slot = grid[item.pos.row] && grid[item.pos.row][item.pos.col];

            if (! slot) {
                return;
            }

            minRow = Math.min(minRow, item.pos.row);
            maxRow = Math.max(maxRow, item.pos.row + getCellRowSpan(item.cell) - 1);
            minCol = Math.min(minCol, slot.start);
            maxCol = Math.max(maxCol, slot.end - 1);
        });

        var rectCells = [];

        for (var r = minRow; r <= maxRow; r++) {
            for (var c = minCol; c <= maxCol; c++) {
                var rectSlot = grid[r] && grid[r][c];

                if (rectSlot && rectCells.indexOf(rectSlot.cell) === -1) {
                    rectCells.push(rectSlot.cell);
                }
            }
        }

        var topLeftSlot = grid[minRow][minCol];
        var topLeftCell = topLeftSlot.cell;

        setCellColSpan(topLeftCell, maxCol - minCol + 1);
        setCellRowSpan(topLeftCell, maxRow - minRow + 1);
        topLeftCell.classList.remove('signatur-cell-selected');

        rectCells.forEach(function (cell) {
            if (cell !== topLeftCell && cell.parentNode) {
                cell.parentNode.removeChild(cell);
            }
        });

        rebuildRowActionsPanel();

        selectedCells = [];
        clearSelection();
    });

    document.addEventListener('click', function (e) {
        if (! e.target.closest('#signatur_field_table')) {
            clearSelection();
        }
    });

    window.addEventListener('scroll', function () {
        hideColumnButtons();
        hideColumnCombineButton();
        rowAddIndicator.style.display = 'none';
        columnAddIndicator.style.display = 'none';
        rowHoverIndicator.style.display = 'none';
    }, true);

    modalCancel.addEventListener('click', closeModal);

    modalSave.addEventListener('click', function () {
        if ('width' === activeModalType) {
            pushUndoSnapshot();

            var widthInput = document.getElementById('signatur_width_input');
            var width = clampWidth(widthInput.value);

            rowTopWrap.style.width = width + 'px';
            rowAddWrap.style.width = width + 'px';
            rowGroup.style.width = (width + 200) + 'px';
            fieldTable.style.width = width + 'px';

            var oldWidth = columnWidths.reduce(function (sum, w) {
                return sum + w;
            }, 0);
            var ratio = width / oldWidth;
            var used = 0;

            columnWidths = columnWidths.map(function (w, i) {
                if (i === columnWidths.length - 1) {
                    return Math.max(minColumnWidth, width - used);
                }

                var scaled = Math.max(minColumnWidth, Math.round(w * ratio));
                used += scaled;

                return scaled;
            });

            renderColgroup();
            renderResizeHandles();

            paddingSides.forEach(function (side) {
                var paddingInput = document.getElementById('signatur_padding_' + side);
                var padding = clampPadding(paddingInput.value);
                var property = 'padding' + side.charAt(0).toUpperCase() + side.slice(1);

                fieldTable.style[property] = padding + 'px';
            });
        }

        closeModal();
    });

    modalOverlay.addEventListener('click', function (e) {
        if (e.target === modalOverlay) {
            closeModal();
        }
    });

    var form = document.getElementById('signatur_form');
    var settingsInput = document.getElementById('signatur_settings_input');

    /**
     * A full, independent snapshot of the builder's state - used both
     * for the form submission and for each undo/redo step.
     */
    function serializeSettings() {
        var padding = {};

        paddingSides.forEach(function (side) {
            var property = 'padding' + side.charAt(0).toUpperCase() + side.slice(1);
            padding[side] = parseInt(fieldTable.style[property], 10) || 0;
        });

        var rows = Array.prototype.map.call(fieldTbody.children, function (row) {
            return Array.prototype.map.call(row.children, function (cell) {
                return [getCellColSpan(cell), getCellRowSpan(cell)];
            });
        });

        return {
            width: parseInt(fieldTable.style.width, 10) || minWidth,
            padding: padding,
            columns: columnWidths.slice(),
            rows: rows
        };
    }

    /**
     * Rebuild the whole table (colgroup, header, rows) plus the
     * row-actions sidebar from a snapshot produced by
     * serializeSettings() - this is how undo/redo restores a step.
     */
    function applySettings(settings) {
        var width = settings.width;

        fieldTable.style.width = width + 'px';
        rowTopWrap.style.width = width + 'px';
        rowAddWrap.style.width = width + 'px';
        rowGroup.style.width = (width + 200) + 'px';

        paddingSides.forEach(function (side) {
            var property = 'padding' + side.charAt(0).toUpperCase() + side.slice(1);
            fieldTable.style[property] = settings.padding[side] + 'px';
        });

        columnWidths = settings.columns.slice();
        renderColgroup();

        fieldTbody.innerHTML = '';

        settings.rows.forEach(function (rowCells) {
            var row = document.createElement('tr');

            rowCells.forEach(function (cellSpan) {
                var cell = document.createElement('td');

                cell.innerHTML = '&nbsp;';
                setCellColSpan(cell, cellSpan[0]);
                setCellRowSpan(cell, cellSpan[1]);
                row.appendChild(cell);
            });

            fieldTbody.appendChild(row);
        });

        rebuildRowActionsPanel();

        clearSelection();
        hideColumnButtons();
        hideColumnCombineButton();
        renderResizeHandles();
    }

    var undoStack = [];
    var redoStack = [];
    var undoButton = document.getElementById('signatur_undo');
    var redoButton = document.getElementById('signatur_redo');

    function updateUndoRedoButtons() {
        undoButton.disabled = 0 === undoStack.length;
        redoButton.disabled = 0 === redoStack.length;
    }

    function pushUndoSnapshot() {
        undoStack.push(serializeSettings());
        redoStack = [];
        updateUndoRedoButtons();
    }

    undoButton.addEventListener('click', function () {
        if (0 === undoStack.length) {
            return;
        }

        redoStack.push(serializeSettings());
        applySettings(undoStack.pop());
        updateUndoRedoButtons();
    });

    redoButton.addEventListener('click', function () {
        if (0 === redoStack.length) {
            return;
        }

        undoStack.push(serializeSettings());
        applySettings(redoStack.pop());
        updateUndoRedoButtons();
    });

    updateUndoRedoButtons();

    form.addEventListener('submit', function () {
        settingsInput.value = JSON.stringify(serializeSettings());
    });
})();
