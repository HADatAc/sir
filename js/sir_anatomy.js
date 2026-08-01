(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.sirAnatomyPanel = {
    attach: function (context, settings) {
      var roots = once('sirAnatomyPanelRoot', 'body', context);
      if (!roots.length) {
        return;
      }

      var cfg = settings.sirAnatomy || {};
      if (!cfg.listUrl || !cfg.resolveUrl) {
        return;
      }

      var panel = document.getElementById('sir-anatomy-panel');
      if (!panel) {
        panel = document.createElement('div');
        panel.id = 'sir-anatomy-panel';
        panel.className = 'sir-anatomy-panel';
        panel.setAttribute('data-config-mode', cfg.configMode ? '1' : '0');

        var configLink = '';
        if (cfg.isAdmin && cfg.configToggleUrl) {
          configLink = '<a class="sir-anatomy-config-link" href="' + cfg.configToggleUrl + '">' + (cfg.configToggleLabel || 'Enter configuration mode') + '</a>';
        }

        var html = '';
        html += '<h4 class="sir-anatomy-title">Search Simulators by Anathomy</h4>' + configLink;
        html += '<p class="sir-anatomy-help">Click on the body map to resolve a coordinate to an UBERON anatomical class and search with it.</p>';
        html += '<button type="button" id="sir-anatomy-toggle-tooltips" class="btn btn-outline-secondary sir-anatomy-tooltips-toggle" aria-pressed="false">Show all tooltips</button>';
        html += '<div class="sir-anatomy-map-wrap"><img id="sir-anatomy-map" src="/modules/custom/sir/images/full_body.png" alt="Human body map" /></div>';
        html += '<div id="sir-anatomy-selection" class="sir-anatomy-selection">No anatomy selected yet.</div>';

        if (cfg.configMode) {
          html += '<div id="sir-anatomy-config" class="sir-anatomy-config">';
          html += '<h5>Mapping configuration</h5>';
          html += '<p class="sir-anatomy-capture-help">Use <strong>Capture box</strong> to set a region with two clicks on the image (first click: top-left, second click: bottom-right).</p>';
          html += '<div class="sir-anatomy-capture-actions">';
          html += '<button type="button" class="btn btn-outline-primary" id="sir-map-capture-toggle">Capture box</button>';
          html += '<button type="button" class="btn btn-outline-secondary" id="sir-map-capture-clear">Clear capture</button>';
          html += '</div>';
          html += '<div id="sir-map-capture-status" class="sir-anatomy-capture-status">Capture mode is off.</div>';
          html += '<input type="hidden" id="sir-map-id" value="" />';
          html += '<div class="sir-anatomy-grid">';
          html += '<label>X min <input type="number" id="sir-map-x-min" min="0" max="100" step="0.01" /></label>';
          html += '<label>X max <input type="number" id="sir-map-x-max" min="0" max="100" step="0.01" /></label>';
          html += '<label>Y min <input type="number" id="sir-map-y-min" min="0" max="100" step="0.01" /></label>';
          html += '<label>Y max <input type="number" id="sir-map-y-max" min="0" max="100" step="0.01" /></label>';
          html += '<label>Label <input type="text" id="sir-map-label" maxlength="255" /></label>';
          html += '<label>UBERON URI <input type="url" id="sir-map-uberon-uri" maxlength="512" /></label>';
          html += '<label>Description <input type="text" id="sir-map-description" maxlength="1024" /></label>';
          html += '<label>Status <select id="sir-map-status"><option value="1">Enabled</option><option value="0">Disabled</option></select></label>';
          html += '<label>Weight <input type="number" id="sir-map-weight" value="0" step="1" /></label>';
          html += '</div>';
          html += '<div class="sir-anatomy-config-actions">';
          html += '<button type="button" class="btn btn-primary" id="sir-map-save">Save Mapping</button>';
          html += '<button type="button" class="btn btn-secondary" id="sir-map-reset">Reset</button>';
          html += '</div>';
          html += '<div id="sir-anatomy-config-table"></div>';
          html += '</div>';
        }

        panel.innerHTML = html;

        var host = document.getElementById('sir-anatomy-panel-host');
        if (!host) {
          return;
        }
        host.appendChild(panel);
      }

      var mapImage = document.getElementById('sir-anatomy-map');
      var selectionBox = document.getElementById('sir-anatomy-selection');
      var mappingsBox = document.getElementById('sir-anatomy-mappings');
      var searchInput = document.getElementById('search_input');
      var mapWrap = mapImage ? mapImage.parentElement : null;
      var toggleTooltipsBtn = document.getElementById('sir-anatomy-toggle-tooltips');

      var hoverTip = null;
      var tipsLayer = null;
      if (mapWrap) {
        hoverTip = document.createElement('div');
        hoverTip.id = 'sir-anatomy-hover-tip';
        hoverTip.className = 'sir-anatomy-hover-tip';
        hoverTip.setAttribute('aria-hidden', 'true');
        mapWrap.appendChild(hoverTip);

        tipsLayer = document.createElement('div');
        tipsLayer.id = 'sir-anatomy-tips-layer';
        tipsLayer.className = 'sir-anatomy-tips-layer';
        tipsLayer.style.display = 'none';
        mapWrap.appendChild(tipsLayer);
      }

      var state = {
        mappings: [],
        captureMode: false,
        captureStart: null,
        showAllTips: !!cfg.configMode,
        draggingTip: null,
      };

      function escapeHtml(value) {
        return String(value || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function updateSelection(text, isError) {
        if (!selectionBox) {
          return;
        }
        selectionBox.style.borderColor = isError ? '#f3b3b3' : '#e5edf6';
        selectionBox.style.background = isError ? '#fff5f5' : '#f8fafc';
        selectionBox.innerHTML = text;
      }

      function toNumber(value) {
        var num = parseFloat(value);
        return Number.isFinite(num) ? num : null;
      }

      function isMappingEnabled(mapping) {
        return String(mapping && mapping.status) !== '0';
      }

      function mappingArea(mapping) {
        var xMin = toNumber(mapping.x_min);
        var xMax = toNumber(mapping.x_max);
        var yMin = toNumber(mapping.y_min);
        var yMax = toNumber(mapping.y_max);
        if (xMin === null || xMax === null || yMin === null || yMax === null) {
          return Number.POSITIVE_INFINITY;
        }
        return Math.abs((xMax - xMin) * (yMax - yMin));
      }

      function findMappingsAtCoordinate(x, y) {
        var matches = [];
        for (var i = 0; i < state.mappings.length; i++) {
          var row = state.mappings[i];
          if (!isMappingEnabled(row)) {
            continue;
          }
          var xMin = toNumber(row.x_min);
          var xMax = toNumber(row.x_max);
          var yMin = toNumber(row.y_min);
          var yMax = toNumber(row.y_max);
          if (xMin === null || xMax === null || yMin === null || yMax === null) {
            continue;
          }
          if (x >= xMin && x <= xMax && y >= yMin && y <= yMax) {
            matches.push(row);
          }
        }

        matches.sort(function (a, b) {
          var areaDiff = mappingArea(a) - mappingArea(b);
          if (areaDiff !== 0) {
            return areaDiff;
          }
          var aw = parseInt(a.weight, 10) || 0;
          var bw = parseInt(b.weight, 10) || 0;
          if (aw !== bw) {
            return aw - bw;
          }
          return (parseInt(a.id, 10) || 0) - (parseInt(b.id, 10) || 0);
        });

        return matches;
      }

      function findMappingAtCoordinate(x, y) {
        var matches = findMappingsAtCoordinate(x, y);
        return matches.length ? matches[0] : null;
      }

      function hideHoverTip() {
        if (!hoverTip) {
          return;
        }
        hoverTip.style.display = 'none';
        mapImage.style.cursor = state.captureMode ? 'cell' : 'crosshair';
      }

      function showHoverTip(mapping, event) {
        if (!hoverTip || !mapWrap || !mapImage || !mapping) {
          return;
        }
        var left = event.clientX - mapWrap.getBoundingClientRect().left + 10;
        var top = event.clientY - mapWrap.getBoundingClientRect().top + 10;
        hoverTip.innerHTML = '<strong>' + escapeHtml(mapping.label || 'Mapped region') + '</strong><br />' + escapeHtml(mapping.uberon_uri || '');
        hoverTip.style.left = left + 'px';
        hoverTip.style.top = top + 'px';
        hoverTip.style.display = 'block';
        mapImage.style.cursor = state.captureMode ? 'cell' : 'pointer';
      }

      function updateCaptureStatus(message) {
        var status = document.getElementById('sir-map-capture-status');
        if (!status) {
          return;
        }
        status.textContent = message;
      }

      function setCaptureMode(enabled) {
        state.captureMode = !!enabled;
        state.captureStart = null;
        if (mapImage) {
          mapImage.style.cursor = state.captureMode ? 'cell' : 'crosshair';
        }
        if (mapWrap) {
          mapWrap.classList.toggle('sir-anatomy-map-wrap--capture', state.captureMode);
        }
        updateCaptureStatus(state.captureMode
          ? 'Capture mode is on. Click the first corner of the region.'
          : 'Capture mode is off.');
      }

      function applyCapturedBox(firstX, firstY, secondX, secondY) {
        var xMin = Math.min(firstX, secondX);
        var xMax = Math.max(firstX, secondX);
        var yMin = Math.min(firstY, secondY);
        var yMax = Math.max(firstY, secondY);

        document.getElementById('sir-map-x-min').value = xMin.toFixed(2);
        document.getElementById('sir-map-x-max').value = xMax.toFixed(2);
        document.getElementById('sir-map-y-min').value = yMin.toFixed(2);
        document.getElementById('sir-map-y-max').value = yMax.toFixed(2);

        updateCaptureStatus('Box captured. Coordinates updated in form fields.');
        updateSelection('Captured region x:[' + xMin.toFixed(2) + ', ' + xMax.toFixed(2) + '] y:[' + yMin.toFixed(2) + ', ' + yMax.toFixed(2) + '].', false);
        setCaptureMode(false);
      }

      function findMappingById(id) {
        for (var i = 0; i < state.mappings.length; i++) {
          if (String(state.mappings[i].id) === String(id)) {
            return state.mappings[i];
          }
        }
        return null;
      }

      function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
      }

      function setFormFromMapping(row) {
        if (!cfg.configMode || !row) {
          return;
        }
        document.getElementById('sir-map-id').value = row.id || '';
        document.getElementById('sir-map-x-min').value = row.x_min || '';
        document.getElementById('sir-map-x-max').value = row.x_max || '';
        document.getElementById('sir-map-y-min').value = row.y_min || '';
        document.getElementById('sir-map-y-max').value = row.y_max || '';
        document.getElementById('sir-map-label').value = row.label || '';
        document.getElementById('sir-map-uberon-uri').value = row.uberon_uri || '';
        document.getElementById('sir-map-description').value = row.description || '';
        document.getElementById('sir-map-status').value = String(row.status || 1);
        document.getElementById('sir-map-weight').value = row.weight || 0;
      }

      function buildFormPayload() {
        return {
          id: document.getElementById('sir-map-id').value || null,
          x_min: document.getElementById('sir-map-x-min').value,
          x_max: document.getElementById('sir-map-x-max').value,
          y_min: document.getElementById('sir-map-y-min').value,
          y_max: document.getElementById('sir-map-y-max').value,
          label: document.getElementById('sir-map-label').value,
          uberon_uri: document.getElementById('sir-map-uberon-uri').value,
          description: document.getElementById('sir-map-description').value,
          status: document.getElementById('sir-map-status').value,
          weight: document.getElementById('sir-map-weight').value
        };
      }

      function persistCurrentMapping(keepFormValues, successMessage) {
        if (!cfg.configMode || !cfg.saveUrl) {
          return;
        }

        fetch(cfg.saveUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(buildFormPayload()),
          credentials: 'same-origin'
        }).then(function (response) {
          return response.json();
        }).then(function (data) {
          if (!data || !data.isSuccessful) {
            updateSelection((data && data.message) ? data.message : 'Failed to save mapping.', true);
            return;
          }

          if (!keepFormValues) {
            var resetBtn = document.getElementById('sir-map-reset');
            if (resetBtn) {
              resetBtn.click();
            }
          }

          loadMappings();
          updateSelection(successMessage || 'Mapping saved successfully.', false);
        }).catch(function () {
          updateSelection('Failed to save mapping.', true);
        });
      }

      function startTipDrag(event, row) {
        if (!cfg.configMode || !row || !mapImage) {
          return;
        }
        var rect = mapImage.getBoundingClientRect();
        if (!rect || rect.width === 0 || rect.height === 0) {
          return;
        }

        var xMin = parseFloat(row.x_min);
        var xMax = parseFloat(row.x_max);
        var yMin = parseFloat(row.y_min);
        var yMax = parseFloat(row.y_max);
        if (!Number.isFinite(xMin) || !Number.isFinite(xMax) || !Number.isFinite(yMin) || !Number.isFinite(yMax)) {
          return;
        }

        var width = xMax - xMin;
        var height = yMax - yMin;
        if (width < 0 || height < 0) {
          return;
        }

        var pointerX = ((event.clientX - rect.left) / rect.width) * 100;
        var pointerY = ((event.clientY - rect.top) / rect.height) * 100;

        state.draggingTip = {
          id: String(row.id),
          width: width,
          height: height,
          offsetX: pointerX - ((xMin + xMax) / 2),
          offsetY: pointerY - ((yMin + yMax) / 2)
        };

        if (mapWrap) {
          mapWrap.classList.add('sir-anatomy-map-wrap--dragging');
        }
      }

      function updateTipDrag(event) {
        if (!state.draggingTip || !mapImage) {
          return;
        }
        var rect = mapImage.getBoundingClientRect();
        if (!rect || rect.width === 0 || rect.height === 0) {
          return;
        }

        var row = findMappingById(state.draggingTip.id);
        if (!row) {
          return;
        }

        var pointerX = ((event.clientX - rect.left) / rect.width) * 100;
        var pointerY = ((event.clientY - rect.top) / rect.height) * 100;
        var centerX = clamp(pointerX - state.draggingTip.offsetX, state.draggingTip.width / 2, 100 - (state.draggingTip.width / 2));
        var centerY = clamp(pointerY - state.draggingTip.offsetY, state.draggingTip.height / 2, 100 - (state.draggingTip.height / 2));

        row.x_min = +(centerX - (state.draggingTip.width / 2)).toFixed(2);
        row.x_max = +(centerX + (state.draggingTip.width / 2)).toFixed(2);
        row.y_min = +(centerY - (state.draggingTip.height / 2)).toFixed(2);
        row.y_max = +(centerY + (state.draggingTip.height / 2)).toFixed(2);

        setFormFromMapping(row);
        renderAllTooltips();
      }

      function endTipDrag() {
        if (!state.draggingTip) {
          return;
        }
        var row = findMappingById(state.draggingTip.id);
        if (row) {
          updateSelection('Tip moved for ' + escapeHtml(row.label || ('mapping #' + row.id)) + '. Saving updated location...', false);
          persistCurrentMapping(true, 'Tip location saved for ' + escapeHtml(row.label || ('mapping #' + row.id)) + '.');
        }
        state.draggingTip = null;
        if (mapWrap) {
          mapWrap.classList.remove('sir-anatomy-map-wrap--dragging');
        }
      }

      function renderMappings() {
        if (!mappingsBox) {
          return;
        }

        if (!state.mappings.length) {
          mappingsBox.innerHTML = '<div class="sir-anatomy-mapping-item">No mappings configured.</div>';
          return;
        }

        var html = '<strong>Mapped regions</strong>';
        for (var i = 0; i < state.mappings.length; i++) {
          var row = state.mappings[i];
          html += '<div class="sir-anatomy-mapping-item">';
          html += '<div><strong>' + escapeHtml(row.label || 'Unnamed') + '</strong></div>';
          html += '<div>' + escapeHtml(row.uberon_uri || '') + '</div>';
          html += '<div>x:[' + escapeHtml(row.x_min) + ', ' + escapeHtml(row.x_max) + '] y:[' + escapeHtml(row.y_min) + ', ' + escapeHtml(row.y_max) + ']</div>';
          html += '</div>';
        }
        mappingsBox.innerHTML = html;
      }

      function setTooltipsButtonState() {
        if (!toggleTooltipsBtn) {
          return;
        }
        toggleTooltipsBtn.textContent = state.showAllTips ? 'Hide all tooltips' : 'Show all tooltips';
        toggleTooltipsBtn.setAttribute('aria-pressed', state.showAllTips ? 'true' : 'false');
      }

      function renderAllTooltips() {
        if (!tipsLayer) {
          return;
        }

        tipsLayer.classList.toggle('sir-anatomy-tips-layer--config', !!cfg.configMode);
        tipsLayer.innerHTML = '';

        if (!state.showAllTips) {
          tipsLayer.style.display = 'none';
          return;
        }

        var visibleCount = 0;
        for (var i = 0; i < state.mappings.length; i++) {
          var row = state.mappings[i];
          if (String(row.status) === '0') {
            continue;
          }

          var xMin = parseFloat(row.x_min);
          var xMax = parseFloat(row.x_max);
          var yMin = parseFloat(row.y_min);
          var yMax = parseFloat(row.y_max);
          if (!Number.isFinite(xMin) || !Number.isFinite(xMax) || !Number.isFinite(yMin) || !Number.isFinite(yMax)) {
            continue;
          }

          var tip = document.createElement('div');
          tip.className = 'sir-anatomy-region-tip';
          tip.style.left = ((xMin + xMax) / 2) + '%';
          tip.style.top = ((yMin + yMax) / 2) + '%';
          tip.textContent = row.label || 'Mapped region';
          tip.title = row.uberon_uri || '';
          tip.setAttribute('data-map-id', String(row.id || ''));
          if (cfg.configMode) {
            tip.classList.add('sir-anatomy-region-tip--draggable');
            tip.addEventListener('mousedown', function (event) {
              event.preventDefault();
              event.stopPropagation();
              var mapId = this.getAttribute('data-map-id');
              var mapping = findMappingById(mapId);
              if (!mapping) {
                return;
              }
              setFormFromMapping(mapping);
              startTipDrag(event, mapping);
            });
          }
          tipsLayer.appendChild(tip);
          visibleCount++;
        }

        tipsLayer.style.display = visibleCount ? 'block' : 'none';
      }

      function renderConfigTable() {
        var tableWrap = document.getElementById('sir-anatomy-config-table');
        if (!tableWrap) {
          return;
        }

        if (!state.mappings.length) {
          tableWrap.innerHTML = '<p>No mappings saved yet.</p>';
          return;
        }

        var html = '<table><thead><tr><th>ID</th><th>Label</th><th>URI</th><th>Range</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        for (var i = 0; i < state.mappings.length; i++) {
          var row = state.mappings[i];
          html += '<tr>';
          html += '<td>' + escapeHtml(row.id) + '</td>';
          html += '<td>' + escapeHtml(row.label || '') + '</td>';
          html += '<td>' + escapeHtml(row.uberon_uri || '') + '</td>';
          html += '<td>x:[' + escapeHtml(row.x_min) + ', ' + escapeHtml(row.x_max) + '] y:[' + escapeHtml(row.y_min) + ', ' + escapeHtml(row.y_max) + ']</td>';
          html += '<td>' + (String(row.status) === '1' ? 'Enabled' : 'Disabled') + '</td>';
          html += '<td><div class="sir-anatomy-row-actions">';
          html += '<button type="button" class="btn btn-sm btn-outline-primary" data-map-edit="' + escapeHtml(row.id) + '">Edit</button>';
          html += '<button type="button" class="btn btn-sm btn-outline-danger" data-map-delete="' + escapeHtml(row.id) + '">Delete</button>';
          html += '</div></td>';
          html += '</tr>';
        }
        html += '</tbody></table>';
        tableWrap.innerHTML = html;

        var editButtons = tableWrap.querySelectorAll('[data-map-edit]');
        editButtons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-map-edit');
            var row = findMappingById(id);
            if (!row) {
              return;
            }
            setFormFromMapping(row);
          });
        });

        var deleteButtons = tableWrap.querySelectorAll('[data-map-delete]');
        deleteButtons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-map-delete');
            if (!id || !cfg.deleteUrlTemplate) {
              return;
            }
            if (!window.confirm('Delete mapping #' + id + '?')) {
              return;
            }
            var url = cfg.deleteUrlTemplate.replace('__id__', encodeURIComponent(id));
            fetch(url, {
              method: 'DELETE',
              headers: {
                'Content-Type': 'application/json'
              },
              credentials: 'same-origin'
            }).then(function (response) {
              return response.json();
            }).then(function () {
              loadMappings();
            }).catch(function () {
              updateSelection('Failed to delete mapping.', true);
            });
          });
        });
      }

      function loadMappings() {
        var url = cfg.listUrl;
        if (cfg.configMode) {
          url += (url.indexOf('?') === -1 ? '?' : '&') + 'all=1';
        }

        fetch(url, {
          credentials: 'same-origin'
        }).then(function (response) {
          return response.json();
        }).then(function (payload) {
          state.mappings = (payload && payload.body) ? payload.body : [];
          renderMappings();
          renderConfigTable();
          renderAllTooltips();
        }).catch(function () {
          updateSelection('Unable to load anatomy mappings.', true);
        });
      }

      function updateSearchInput(uri) {
        if (!searchInput || !uri) {
          return;
        }
        searchInput.value = uri;
        searchInput.dispatchEvent(new Event('input', { bubbles: true }));
        searchInput.dispatchEvent(new Event('keyup', { bubbles: true }));
      }

      function resolveCoordinates(x, y) {
        function applyMappingSelection(row) {
          var text = '<strong>Selected:</strong> ' + escapeHtml(row.label || 'Unnamed') + '<br />'
            + '<strong>URI:</strong> ' + escapeHtml(row.uberon_uri || '') + '<br />'
            + '<strong>Coordinate:</strong> x=' + x.toFixed(2) + ', y=' + y.toFixed(2);
          updateSelection(text, false);
          updateSearchInput(row.uberon_uri || row.label || '');
        }

        function showOverlapChooser(matches) {
          var html = '<strong>Multiple matches at x=' + x.toFixed(2) + ', y=' + y.toFixed(2) + '</strong>';
          html += '<div class="sir-anatomy-overlap-list">';
          for (var i = 0; i < matches.length; i++) {
            var row = matches[i];
            html += '<button type="button" class="btn btn-sm btn-outline-primary sir-anatomy-overlap-choice" data-sir-match-index="' + i + '">';
            html += escapeHtml(row.label || 'Unnamed');
            if (row.uberon_uri) {
              html += ' <span class="sir-anatomy-overlap-uri">(' + escapeHtml(row.uberon_uri) + ')</span>';
            }
            html += '</button>';
          }
          html += '</div>';
          updateSelection(html, false);

          var choiceButtons = selectionBox ? selectionBox.querySelectorAll('[data-sir-match-index]') : [];
          choiceButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
              var idx = parseInt(btn.getAttribute('data-sir-match-index'), 10);
              if (!Number.isFinite(idx) || !matches[idx]) {
                return;
              }
              applyMappingSelection(matches[idx]);
            });
          });
        }

        var url = cfg.resolveUrl + '?x=' + encodeURIComponent(x) + '&y=' + encodeURIComponent(y);
        fetch(url, {
          credentials: 'same-origin'
        }).then(function (response) {
          return response.json();
        }).then(function (payload) {
          if (!payload || !payload.isSuccessful) {
            updateSelection('No anatomy mapping at x=' + x.toFixed(2) + ', y=' + y.toFixed(2) + '.', false);
            return;
          }

          var matches = [];
          if (payload.matches && Array.isArray(payload.matches) && payload.matches.length) {
            matches = payload.matches;
          }
          else if (payload.body) {
            matches = [payload.body];
          }

          if (!matches.length) {
            updateSelection('No anatomy mapping at x=' + x.toFixed(2) + ', y=' + y.toFixed(2) + '.', false);
            return;
          }

          if (matches.length === 1) {
            applyMappingSelection(matches[0]);
            return;
          }

          showOverlapChooser(matches);
        }).catch(function () {
          var localMatches = findMappingsAtCoordinate(x, y);
          if (localMatches.length === 1) {
            applyMappingSelection(localMatches[0]);
            return;
          }
          if (localMatches.length > 1) {
            showOverlapChooser(localMatches);
            return;
          }
          updateSelection('Failed to resolve anatomy for selected coordinate.', true);
        });
      }

      if (mapImage) {
        mapImage.addEventListener('mousemove', function (event) {
          var rect = mapImage.getBoundingClientRect();
          if (!rect || rect.width === 0 || rect.height === 0) {
            hideHoverTip();
            return;
          }
          var xPct = ((event.clientX - rect.left) / rect.width) * 100;
          var yPct = ((event.clientY - rect.top) / rect.height) * 100;
          var row = findMappingAtCoordinate(xPct, yPct);
          if (!row) {
            hideHoverTip();
            return;
          }
          showHoverTip(row, event);
        });

        mapImage.addEventListener('mouseleave', function () {
          hideHoverTip();
        });

        mapImage.addEventListener('click', function (event) {
          var rect = mapImage.getBoundingClientRect();
          if (!rect || rect.width === 0 || rect.height === 0) {
            return;
          }
          var xPct = ((event.clientX - rect.left) / rect.width) * 100;
          var yPct = ((event.clientY - rect.top) / rect.height) * 100;

          if (cfg.configMode && state.captureMode) {
            if (!state.captureStart) {
              state.captureStart = { x: xPct, y: yPct };
              updateCaptureStatus('First corner saved at x=' + xPct.toFixed(2) + ', y=' + yPct.toFixed(2) + '. Click the opposite corner.');
            }
            else {
              applyCapturedBox(state.captureStart.x, state.captureStart.y, xPct, yPct);
            }
            return;
          }

          resolveCoordinates(xPct, yPct);

          if (cfg.configMode) {
            document.getElementById('sir-map-x-min').value = xPct.toFixed(2);
            document.getElementById('sir-map-x-max').value = xPct.toFixed(2);
            document.getElementById('sir-map-y-min').value = yPct.toFixed(2);
            document.getElementById('sir-map-y-max').value = yPct.toFixed(2);
          }
        });
      }

      document.addEventListener('mousemove', function (event) {
        if (!state.draggingTip) {
          return;
        }
        event.preventDefault();
        updateTipDrag(event);
      });

      document.addEventListener('mouseup', function () {
        endTipDrag();
      });

      if (toggleTooltipsBtn) {
        toggleTooltipsBtn.addEventListener('click', function () {
          state.showAllTips = !state.showAllTips;
          setTooltipsButtonState();
          renderAllTooltips();
        });
      }

      setTooltipsButtonState();

      if (cfg.configMode) {
        var saveBtn = document.getElementById('sir-map-save');
        var resetBtn = document.getElementById('sir-map-reset');
        var captureToggleBtn = document.getElementById('sir-map-capture-toggle');
        var captureClearBtn = document.getElementById('sir-map-capture-clear');

        if (captureToggleBtn) {
          captureToggleBtn.addEventListener('click', function () {
            setCaptureMode(!state.captureMode);
          });
        }

        if (captureClearBtn) {
          captureClearBtn.addEventListener('click', function () {
            state.captureStart = null;
            setCaptureMode(false);
          });
        }

        if (saveBtn) {
          saveBtn.addEventListener('click', function () {
            persistCurrentMapping(false, 'Mapping saved successfully.');
          });
        }

        if (resetBtn) {
          resetBtn.addEventListener('click', function () {
            document.getElementById('sir-map-id').value = '';
            document.getElementById('sir-map-x-min').value = '';
            document.getElementById('sir-map-x-max').value = '';
            document.getElementById('sir-map-y-min').value = '';
            document.getElementById('sir-map-y-max').value = '';
            document.getElementById('sir-map-label').value = '';
            document.getElementById('sir-map-uberon-uri').value = '';
            document.getElementById('sir-map-description').value = '';
            document.getElementById('sir-map-status').value = '1';
            document.getElementById('sir-map-weight').value = '0';
          });
        }
      }

      loadMappings();
    }
  };
})(Drupal, once);
