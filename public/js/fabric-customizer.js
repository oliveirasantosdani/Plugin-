(function ($) {
    'use strict';

    var canvas = null;
    var designSaved = false;
    var currentColor = '#000000';

    var history = [];
    var historyIndex = -1;
    var isSavingHistory = true;

    var gridActive = false;
    var safeZoneVisible = false;

    var baseCanvasWidth = (window.osds3d_customizer_params && osds3d_customizer_params.canvas_width)
        ? parseInt(osds3d_customizer_params.canvas_width, 10)
        : 800;

    var baseCanvasHeight = (window.osds3d_customizer_params && osds3d_customizer_params.canvas_height)
        ? parseInt(osds3d_customizer_params.canvas_height, 10)
        : 600;

    var responsiveZoom = 1;
    var userZoom = 1;

    var snapThreshold = 10;
    var autosaveKey = 'osds3d_autosave_v3';

    var copiedObject = null;

    var faces = {
        front: null,
        back: null
    };

    var currentFace = 'front';

    function getParams() {
        return window.osds3d_customizer_params || {};
    }

    function getI18n(key, fallback) {
        var params = getParams();
        if (params.i18n && typeof params.i18n[key] !== 'undefined') {
            return params.i18n[key];
        }
        return fallback || '';
    }

    function getModel3DParams() {
        var params = getParams();
        return params.model3d || {};
    }

    function hasProduct3DModel() {
        var model3d = getModel3DParams();
        return !!(model3d && model3d.url);
    }

    function getFinalZoom() {
        return responsiveZoom * userZoom;
    }

    function deepClone(obj) {
        return JSON.parse(JSON.stringify(obj));
    }

    function getCanvasJSON() {
        if (!canvas) {
            return null;
        }

        return canvas.toJSON([
            'selectable',
            'evented',
            'lockMovementX',
            'lockMovementY',
            'lockScalingX',
            'lockScalingY',
            'lockRotation',
            'opacity'
        ]);
    }

    function serializeCanvas() {
        if (!canvas) {
            return '';
        }

        return JSON.stringify(getCanvasJSON());
    }

    function setCanvasLogicalSize(width, height) {
        baseCanvasWidth = parseInt(width, 10) || 800;
        baseCanvasHeight = parseInt(height, 10) || 600;

        if (!canvas) {
            return;
        }

        canvas.setWidth(baseCanvasWidth);
        canvas.setHeight(baseCanvasHeight);

        $(canvas.getElement()).attr('width', baseCanvasWidth);
        $(canvas.getElement()).attr('height', baseCanvasHeight);
    }

    function applyCanvasZoom() {
        if (!canvas) {
            return;
        }

        var finalZoom = getFinalZoom();

        canvas.setViewportTransform([finalZoom, 0, 0, finalZoom, 0, 0]);

        canvas.setDimensions({
            width: Math.round(baseCanvasWidth * finalZoom),
            height: Math.round(baseCanvasHeight * finalZoom)
        }, { cssOnly: true });

        canvas.renderAll();
    }

    function fitCanvasToContainer() {
        if (!canvas) {
            return;
        }

        var container = document.querySelector('.osds3d-canvas-card');
        if (!container) {
            return;
        }

        var padding = 24;
        var availableWidth = container.clientWidth - padding;
        var availableHeight = container.clientHeight - padding;

        if (availableWidth < 150 || availableHeight < 150) {
            return;
        }

        var zoomX = availableWidth / baseCanvasWidth;
        var zoomY = availableHeight / baseCanvasHeight;

        responsiveZoom = Math.min(zoomX, zoomY, 1);
        applyCanvasZoom();
    }

    function resetUserZoom() {
        userZoom = 1;
        applyCanvasZoom();
    }

    function saveHistory(label) {
        if (!canvas || !isSavingHistory) {
            return;
        }

        var maxHistory = 50;
        var state = serializeCanvas();

        if (!state) {
            return;
        }

        if (historyIndex < history.length - 1) {
            history = history.slice(0, historyIndex + 1);
        }

        history.push({
            label: label || 'Action',
            state: state
        });

        if (history.length > maxHistory) {
            history.shift();
        } else {
            historyIndex++;
        }

        if (historyIndex >= history.length) {
            historyIndex = history.length - 1;
        }

        renderHistoryPanel();
        autosaveDraft();
    }

    function normalizeColor(value) {
        if (!value || typeof value !== 'string') {
            return '#000000';
        }

        if (value.indexOf('#') === 0) {
            return value;
        }

        var match = value.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/i);
        if (match) {
            return '#' + [match[1], match[2], match[3]].map(function (x) {
                return ('0' + parseInt(x, 10).toString(16)).slice(-2);
            }).join('');
        }

        return '#000000';
    }

    function centerObject(obj) {
        if (!canvas || !obj) {
            return;
        }

        obj.set({
            left: baseCanvasWidth / 2,
            top: baseCanvasHeight / 2,
            originX: 'center',
            originY: 'center'
        });
    }

    function updateInspector() {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();

        if (obj && obj.type === 'i-text') {
            $('#osds3d-text-content').val(obj.text || '');
            $('#osds3d-font-size').val(obj.fontSize || 32);
            $('#osds3d-text-color').val(normalizeColor(obj.fill || '#000000'));
            $('#osds3d-font-family').val(obj.fontFamily || 'Arial');
            $('#osds3d-stroke-color').val(normalizeColor(obj.stroke || '#000000'));
            $('#osds3d-stroke-width').val(obj.strokeWidth || 0);

            if (obj.shadow) {
                $('#osds3d-shadow-color').val(normalizeColor(obj.shadow.color || '#000000'));
                $('#osds3d-shadow-offset-x').val(obj.shadow.offsetX || 0);
                $('#osds3d-shadow-offset-y').val(obj.shadow.offsetY || 0);
                $('#osds3d-shadow-blur').val(obj.shadow.blur || 0);
            }
        }

        if (obj) {
            var opacity = typeof obj.opacity === 'number' ? Math.round(obj.opacity * 100) : 100;
            $('#osds3d-opacity').val(opacity);
        }
    }

    function withHistoryDisabled(callback, done) {
        isSavingHistory = false;

        callback(function () {
            isSavingHistory = true;

            if (typeof done === 'function') {
                done();
            }
        });
    }

    function initCanvas() {
        var el = document.getElementById('osds3d-canvas');
        if (!el || typeof fabric === 'undefined') {
            return;
        }

        canvas = new fabric.Canvas(el, {
            selection: true,
            preserveObjectStacking: true
        });

        setCanvasLogicalSize(baseCanvasWidth, baseCanvasHeight);
        canvas.setBackgroundColor('#ffffff', canvas.renderAll.bind(canvas));

        canvas.selectionColor = 'rgba(37,99,235,0.10)';
        canvas.selectionBorderColor = '#2563eb';
        canvas.selectionLineWidth = 1;

        canvas.on('selection:created', function () {
            updateInspector();
            renderLayersPanel();
        });

        canvas.on('selection:updated', function () {
            updateInspector();
            renderLayersPanel();
        });

        canvas.on('selection:cleared', function () {
            $('#osds3d-text-content').val('');
            renderLayersPanel();
        });

        canvas.on('object:added', function () {
            if (isSavingHistory) {
                saveHistory('Ajout');
            }
            renderLayersPanel();
        });

        canvas.on('object:modified', function () {
            if (isSavingHistory) {
                saveHistory('Modification');
            }
            renderLayersPanel();
        });

        canvas.on('object:removed', function () {
            if (isSavingHistory) {
                saveHistory('Suppression');
            }
            renderLayersPanel();
        });

        canvas.on('path:created', function () {
            if (isSavingHistory) {
                saveHistory('Dessin');
            }
            renderLayersPanel();
        });

        enableSmartGuides();
        fitCanvasToContainer();
        initFacesStore();
        applySafeZoneFromSettings();
        saveHistory('État initial');
    }

    function initFacesStore() {
        faces.front = getCanvasJSON();
        faces.back = null;
        currentFace = 'front';
        updateFaceButtons();
    }

    function saveCurrentFaceState() {
        if (!canvas) {
            return;
        }

        faces[currentFace] = deepClone(getCanvasJSON());
    }

    function loadFace(faceName) {
        if (!canvas || !faces.hasOwnProperty(faceName)) {
            return;
        }

        saveCurrentFaceState();
        currentFace = faceName;

        var state = faces[faceName];

        withHistoryDisabled(function (done) {
            if (state) {
                canvas.loadFromJSON(state, function () {
                    canvas.renderAll();
                    done();
                });
            } else {
                canvas.clear();
                setCanvasLogicalSize(baseCanvasWidth, baseCanvasHeight);
                canvas.setBackgroundColor('#ffffff', canvas.renderAll.bind(canvas));
                canvas.renderAll();
                done();
            }
        }, function () {
            updateFaceButtons();
            renderLayersPanel();
            fitCanvasToContainer();
            applySafeZoneFromSettings();
        });
    }

    function updateFaceButtons() {
        $('#osds3d-face-front')
            .toggleClass('osds3d-ui-btn-primary', currentFace === 'front')
            .toggleClass('osds3d-ui-btn-ghost', currentFace !== 'front');

        $('#osds3d-face-back')
            .toggleClass('osds3d-ui-btn-primary', currentFace === 'back')
            .toggleClass('osds3d-ui-btn-ghost', currentFace !== 'back');
    }

    function applySafeZoneFromSettings() {
        var params = getParams();
        var safe = params.safe_zone || {};
        var $zone = $('#osds3d-safe-zone');

        if (!$zone.length) {
            return;
        }

        var top = (typeof safe.top_percent !== 'undefined') ? safe.top_percent : 10;
        var right = (typeof safe.right_percent !== 'undefined') ? safe.right_percent : 12;
        var bottom = (typeof safe.bottom_percent !== 'undefined') ? safe.bottom_percent : 10;
        var left = (typeof safe.left_percent !== 'undefined') ? safe.left_percent : 12;

        $zone.css({
            top: top + '%',
            right: right + '%',
            bottom: bottom + '%',
            left: left + '%'
        });
    }

    function enableSmartGuides() {
        if (!canvas) {
            return;
        }

        canvas.on('object:moving', function (e) {
            var obj = e.target;
            if (!obj) {
                return;
            }

            var objects = canvas.getObjects();

            objects.forEach(function (o) {
                if (!o || o === obj) {
                    return;
                }

                if (Math.abs((o.left || 0) - (obj.left || 0)) < snapThreshold) {
                    obj.left = o.left;
                }

                if (Math.abs((o.top || 0) - (obj.top || 0)) < snapThreshold) {
                    obj.top = o.top;
                }
            });

            var centerX = baseCanvasWidth / 2;
            var centerY = baseCanvasHeight / 2;

            if (Math.abs((obj.left || 0) - centerX) < snapThreshold) {
                obj.left = centerX;
            }

            if (Math.abs((obj.top || 0) - centerY) < snapThreshold) {
                obj.top = centerY;
            }
        });
    }

    function startDrawing() {
        if (!canvas) {
            return;
        }

        canvas.isDrawingMode = true;

        var color = $('#osds3d-free-color').val() || '#000000';
        var size = parseInt($('#osds3d-free-size').val(), 10) || 5;

        canvas.freeDrawingBrush.color = color;
        canvas.freeDrawingBrush.width = size;
    }

    function stopDrawing() {
        if (!canvas) {
            return;
        }

        canvas.isDrawingMode = false;
    }

    function addText() {
        var value = prompt(getI18n('add_text', 'Ajouter du texte'), '');
        if (!value) {
            return;
        }

        addCustomText(value);
    }

    function addCustomText(value) {
        if (!value || !canvas) {
            return;
        }

        var text = new fabric.IText(value, {
            left: 100,
            top: 100,
            fill: $('#osds3d-text-color').val() || currentColor,
            fontSize: parseInt($('#osds3d-font-size').val(), 10) || 32,
            fontFamily: $('#osds3d-font-family').val() || 'Arial'
        });

        centerObject(text);
        canvas.add(text);
        canvas.setActiveObject(text);
        renderLayersPanel();
    }

    function addImageFromDataUrl(dataUrl) {
        if (!canvas || !dataUrl) {
            return;
        }

        fabric.Image.fromURL(dataUrl, function (img) {
            img.set({
                left: 100,
                top: 100,
                scaleX: 0.5,
                scaleY: 0.5
            });

            centerObject(img);
            canvas.add(img);
            canvas.setActiveObject(img);
            renderLayersPanel();
        }, { crossOrigin: 'anonymous' });
    }

    function addImage() {
        if (!canvas) {
            return;
        }

        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';

        input.onchange = function (e) {
            var file = e.target.files[0];
            if (!file) {
                return;
            }

            var reader = new FileReader();
            reader.onload = function (event) {
                addImageFromDataUrl(event.target.result);
            };
            reader.readAsDataURL(file);
        };

        input.click();
    }

    function enableDragDrop() {
        var stageDropZone = document.querySelector('.osds3d-canvas-card');
        var imageDropZone = document.getElementById('osds3d-image-dropzone');

        [stageDropZone, imageDropZone].forEach(function (zone) {
            if (!zone) {
                return;
            }

            zone.addEventListener('dragover', function (e) {
                e.preventDefault();
                zone.classList.add('is-dragover');
            });

            zone.addEventListener('dragleave', function () {
                zone.classList.remove('is-dragover');
            });

            zone.addEventListener('drop', function (e) {
                e.preventDefault();
                zone.classList.remove('is-dragover');

                if (!canvas) {
                    return;
                }

                var file = e.dataTransfer.files[0];
                if (!file || !file.type.startsWith('image/')) {
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (event) {
                    addImageFromDataUrl(event.target.result);
                };
                reader.readAsDataURL(file);
            });
        });
    }

    function createStarShape(opts) {
        var points = [];
        var spikes = opts.spikes || 5;
        var step = Math.PI / spikes;

        for (var i = 0; i < spikes * 2; i++) {
            var radius = (i % 2 === 0) ? opts.outerRadius : opts.innerRadius;
            var angle = i * step;

            points.push({
                x: radius * Math.sin(angle),
                y: -radius * Math.cos(angle)
            });
        }

        return new fabric.Polygon(points, {
            left: opts.left,
            top: opts.top,
            fill: opts.fill,
            originX: 'center',
            originY: 'center'
        });
    }

    function addShape(type) {
        if (!canvas) {
            return;
        }

        var shape = null;

        switch (type) {
            case 'rect':
                shape = new fabric.Rect({
                    left: 150,
                    top: 150,
                    width: 120,
                    height: 80,
                    fill: currentColor
                });
                break;

            case 'circle':
                shape = new fabric.Circle({
                    left: 150,
                    top: 150,
                    radius: 60,
                    fill: currentColor
                });
                break;

            case 'triangle':
                shape = new fabric.Triangle({
                    left: 150,
                    top: 150,
                    width: 120,
                    height: 100,
                    fill: currentColor
                });
                break;

            case 'star':
                shape = createStarShape({
                    left: 150,
                    top: 150,
                    outerRadius: 60,
                    innerRadius: 30,
                    fill: currentColor
                });
                break;

            case 'star6':
                shape = createStarShape({
                    left: 150,
                    top: 150,
                    outerRadius: 60,
                    innerRadius: 30,
                    fill: currentColor,
                    spikes: 6
                });
                break;

            case 'star8':
                shape = createStarShape({
                    left: 150,
                    top: 150,
                    outerRadius: 60,
                    innerRadius: 30,
                    fill: currentColor,
                    spikes: 8
                });
                break;

            default:
                return;
        }

        centerObject(shape);
        canvas.add(shape);
        canvas.setActiveObject(shape);
        renderLayersPanel();
    }

    function addPolygon() {
        if (!canvas) {
            return;
        }

        var sides = parseInt(prompt('Nombre de côtés (3-12):', '5'), 10);
        if (isNaN(sides) || sides < 3 || sides > 12) {
            return;
        }

        var radius = 60;
        var points = [];

        for (var i = 0; i < sides; i++) {
            var angle = (2 * Math.PI * i) / sides;
            points.push({
                x: radius * Math.cos(angle),
                y: radius * Math.sin(angle)
            });
        }

        var poly = new fabric.Polygon(points, {
            left: 150,
            top: 150,
            fill: currentColor,
            originX: 'center',
            originY: 'center'
        });

        centerObject(poly);
        canvas.add(poly);
        canvas.setActiveObject(poly);
        renderLayersPanel();
    }

    function applyTemplate() {
        if (!canvas) {
            return;
        }

        var selectedId = $('#osds3d-template-select').val();
        if (!selectedId) {
            return;
        }

        var tpl = null;
        var templates = getParams().templates || [];

        for (var i = 0; i < templates.length; i++) {
            if (String(templates[i].id) === String(selectedId)) {
                tpl = templates[i];
                break;
            }
        }

        if (!tpl) {
            return;
        }

        var json = null;

        try {
            json = JSON.parse(tpl.data);
        } catch (e) {
            alert('Template invalide');
            return;
        }

        withHistoryDisabled(function (done) {
            if (json.canvasWidth && json.canvasHeight) {
                setCanvasLogicalSize(json.canvasWidth, json.canvasHeight);
                resetUserZoom();
            }

            canvas.clear();

            canvas.loadFromJSON(json, function () {
                canvas.renderAll();

                if (!canvas.backgroundColor) {
                    canvas.setBackgroundColor('#ffffff', canvas.renderAll.bind(canvas));
                }

                done();
            });
        }, function () {
            fitCanvasToContainer();
            renderLayersPanel();
            saveHistory('Template');
            saveCurrentFaceState();
            applySafeZoneFromSettings();
        });
    }

    function groupSelected() {
        if (!canvas) {
            return;
        }

        var active = canvas.getActiveObjects();
        if (!active || active.length <= 1) {
            return;
        }

        var group = new fabric.Group(active);
        canvas.discardActiveObject();

        active.forEach(function (obj) {
            canvas.remove(obj);
        });

        canvas.add(group);
        canvas.setActiveObject(group);
        canvas.renderAll();
        saveHistory('Grouper');
    }

    function ungroupSelected() {
        if (!canvas) {
            return;
        }

        var active = canvas.getActiveObject();

        if (active && active.type === 'group') {
            var items = active._objects;

            canvas.discardActiveObject();
            canvas.remove(active);

            items.forEach(function (obj) {
                canvas.add(obj);
            });

            canvas.renderAll();
            saveHistory('Dégrouper');
        }
    }

    function undo() {
        if (!canvas || historyIndex <= 0) {
            return;
        }

        historyIndex--;
        var state = history[historyIndex].state;

        withHistoryDisabled(function (done) {
            canvas.loadFromJSON(state, function () {
                canvas.renderAll();
                done();
            });
        }, function () {
            fitCanvasToContainer();
            renderLayersPanel();
            renderHistoryPanel();
            saveCurrentFaceState();
            applySafeZoneFromSettings();
        });
    }

    function redo() {
        if (!canvas || historyIndex >= history.length - 1) {
            return;
        }

        historyIndex++;
        var state = history[historyIndex].state;

        withHistoryDisabled(function (done) {
            canvas.loadFromJSON(state, function () {
                canvas.renderAll();
                done();
            });
        }, function () {
            fitCanvasToContainer();
            renderLayersPanel();
            renderHistoryPanel();
            saveCurrentFaceState();
            applySafeZoneFromSettings();
        });
    }

    function toggleGrid() {
        if (!canvas) {
            return;
        }

        gridActive = !gridActive;

        if (gridActive) {
            var patternCanvas = document.createElement('canvas');
            patternCanvas.width = 40;
            patternCanvas.height = 40;

            var ctx = patternCanvas.getContext('2d');
            ctx.strokeStyle = '#e0e0e0';
            ctx.lineWidth = 1;

            ctx.beginPath();
            ctx.moveTo(0, 0); ctx.lineTo(40, 0);
            ctx.moveTo(0, 10); ctx.lineTo(40, 10);
            ctx.moveTo(0, 20); ctx.lineTo(40, 20);
            ctx.moveTo(0, 30); ctx.lineTo(40, 30);
            ctx.moveTo(0, 40); ctx.lineTo(40, 40);
            ctx.moveTo(0, 0); ctx.lineTo(0, 40);
            ctx.moveTo(10, 0); ctx.lineTo(10, 40);
            ctx.moveTo(20, 0); ctx.lineTo(20, 40);
            ctx.moveTo(30, 0); ctx.lineTo(30, 40);
            ctx.moveTo(40, 0); ctx.lineTo(40, 40);
            ctx.stroke();

            var dataUrl = patternCanvas.toDataURL('image/png');

            fabric.Image.fromURL(dataUrl, function (img) {
                canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), {
                    repeat: 'repeat',
                    opacity: 0.4
                });
            });
        } else {
            canvas.setBackgroundImage(null, canvas.renderAll.bind(canvas));
        }
    }

    function zoomIn() {
        userZoom = Math.min(userZoom * 1.1, 5);
        applyCanvasZoom();
    }

    function zoomOut() {
        userZoom = Math.max(userZoom / 1.1, 0.2);
        applyCanvasZoom();
    }

    function duplicateObject() {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();
        if (!obj) {
            return;
        }

        obj.clone(function (cloned) {
            cloned.set({
                left: (obj.left || 0) + 20,
                top: (obj.top || 0) + 20
            });

            canvas.add(cloned);
            canvas.setActiveObject(cloned);
            saveHistory('Dupliquer');
        });
    }

    function bringForward() {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();
        if (!obj) {
            return;
        }

        canvas.bringForward(obj);
        canvas.renderAll();
        saveHistory('Avancer');
    }

    function sendBackward() {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();
        if (!obj) {
            return;
        }

        canvas.sendBackwards(obj);
        canvas.renderAll();
        saveHistory('Reculer');
    }

    function lockObject() {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();
        if (!obj) {
            return;
        }

        obj.lockMovementX = true;
        obj.lockMovementY = true;
        obj.lockScalingX = true;
        obj.lockScalingY = true;
        obj.lockRotation = true;
        obj.selectable = false;
        obj.evented = false;

        canvas.discardActiveObject();
        canvas.renderAll();
        saveHistory('Verrouiller');
    }

    function unlockAllObjects() {
        if (!canvas) {
            return;
        }

        canvas.getObjects().forEach(function (obj) {
            obj.lockMovementX = false;
            obj.lockMovementY = false;
            obj.lockScalingX = false;
            obj.lockScalingY = false;
            obj.lockRotation = false;
            obj.selectable = true;
            obj.evented = true;
        });

        canvas.renderAll();
        saveHistory('Déverrouiller');
    }

    function applyTextColor(value) {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();

        if (
            obj &&
            (
                obj.type === 'i-text' ||
                obj.type === 'rect' ||
                obj.type === 'circle' ||
                obj.type === 'triangle' ||
                obj.type === 'polygon' ||
                obj.type === 'path'
            )
        ) {
            obj.set('fill', value);
            canvas.renderAll();
            saveHistory('Couleur');
        }

        currentColor = value;
    }

    function applyFontSize(value) {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();

        if (obj && obj.type === 'i-text') {
            obj.set('fontSize', parseInt(value, 10));
            canvas.renderAll();
            saveHistory('Taille texte');
        }
    }

    function toggleBold() {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();

        if (obj && obj.type === 'i-text') {
            obj.set('fontWeight', obj.fontWeight === 'bold' ? 'normal' : 'bold');
            canvas.renderAll();
            saveHistory('Gras');
        }
    }

    function toggleItalic() {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();

        if (obj && obj.type === 'i-text') {
            obj.set('fontStyle', obj.fontStyle === 'italic' ? 'normal' : 'italic');
            canvas.renderAll();
            saveHistory('Italique');
        }
    }

    function toggleUnderline() {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();

        if (obj && obj.type === 'i-text') {
            obj.set('underline', !obj.underline);
            canvas.renderAll();
            saveHistory('Souligné');
        }
    }

    function applyStroke(color, width) {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();
        if (!obj) {
            return;
        }

        obj.set('stroke', color || null);
        obj.set('strokeWidth', parseFloat(width) || 0);
        canvas.renderAll();
        saveHistory('Contour');
    }

    function applyShadow(color, offsetX, offsetY, blur) {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();
        if (!obj) {
            return;
        }

        if (!color) {
            obj.set('shadow', null);
        } else {
            obj.set('shadow', new fabric.Shadow({
                color: color,
                blur: parseInt(blur, 10) || 0,
                offsetX: parseInt(offsetX, 10) || 0,
                offsetY: parseInt(offsetY, 10) || 0
            }));
        }

        canvas.renderAll();
        saveHistory('Ombre');
    }

    function applyGradientFill() {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();
        if (!obj) {
            return;
        }

        var startColor = $('#osds3d-gradient-start').val() || '#ff0000';
        var endColor = $('#osds3d-gradient-end').val() || '#0000ff';
        var type = $('#osds3d-gradient-type').val() || 'linear';
        var gradient = null;

        if (type === 'radial') {
            gradient = new fabric.Gradient({
                type: 'radial',
                coords: { x1: 0.5, y1: 0.5, r1: 0, x2: 0.5, y2: 0.5, r2: 0.7 },
                colorStops: [
                    { offset: 0, color: startColor },
                    { offset: 1, color: endColor }
                ]
            });
        } else {
            var width = obj.width || obj.getScaledWidth() || 100;

            gradient = new fabric.Gradient({
                type: 'linear',
                coords: { x1: -width / 2, y1: 0, x2: width / 2, y2: 0 },
                colorStops: [
                    { offset: 0, color: startColor },
                    { offset: 1, color: endColor }
                ]
            });
        }

        obj.set('fill', gradient);
        canvas.renderAll();
        saveHistory('Dégradé');
    }

    function applyFilter(type) {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();
        if (!obj || obj.type !== 'image') {
            return;
        }

        var filters = [];

        switch (type) {
            case 'grayscale':
                filters.push(new fabric.Image.filters.Grayscale());
                break;
            case 'sepia':
                filters.push(new fabric.Image.filters.Sepia());
                break;
            case 'invert':
                filters.push(new fabric.Image.filters.Invert());
                break;
            case 'brightness':
                filters.push(new fabric.Image.filters.Brightness({ brightness: 0.05 }));
                break;
            case 'contrast':
                filters.push(new fabric.Image.filters.Contrast({ contrast: 0.1 }));
                break;
            case 'saturation':
                filters.push(new fabric.Image.filters.Saturation({ saturation: 0.5 }));
                break;
            case 'blur':
                filters.push(new fabric.Image.filters.Blur({ blur: 0.5 }));
                break;
            default:
                filters = [];
        }

        obj.filters = filters;
        obj.applyFilters();
        canvas.renderAll();
        saveHistory('Filtre');
    }

    function applyOpacity(percent) {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();
        if (!obj) {
            return;
        }

        obj.set('opacity', Math.max(0, Math.min(1, parseInt(percent, 10) / 100)));
        canvas.renderAll();
        saveHistory('Opacité');
    }

    function generateQR(content, colorDark, colorLight, logoFile) {
        if (!canvas || !content || typeof QRCode === 'undefined') {
            return;
        }

        var tmpDiv = document.createElement('div');
        var size = parseInt($('#osds3d-qr-size').val(), 10) || 256;
        var level = $('#osds3d-qr-level').val() || 'H';
        var correction = QRCode.CorrectLevel.M;

        switch (level) {
            case 'L':
                correction = QRCode.CorrectLevel.L;
                break;
            case 'M':
                correction = QRCode.CorrectLevel.M;
                break;
            case 'Q':
                correction = QRCode.CorrectLevel.Q;
                break;
            case 'H':
                correction = QRCode.CorrectLevel.H;
                break;
        }

        new QRCode(tmpDiv, {
            text: content,
            width: size,
            height: size,
            colorDark: colorDark || '#000000',
            colorLight: colorLight || '#ffffff',
            correctLevel: correction
        });

        setTimeout(function () {
            var img = tmpDiv.querySelector('img');
            if (!img) {
                return;
            }

            var addQrOnly = function () {
                fabric.Image.fromURL(img.src, function (fimg) {
                    fimg.set({
                        left: 150,
                        top: 150,
                        scaleX: 0.5,
                        scaleY: 0.5
                    });

                    centerObject(fimg);
                    canvas.add(fimg);
                    canvas.setActiveObject(fimg);
                    saveHistory('QR');
                }, { crossOrigin: 'anonymous' });
            };

            if (logoFile) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    fabric.Image.fromURL(img.src, function (qrImg) {
                        fabric.Image.fromURL(e.target.result, function (logoImg) {
                            var qrSize = Math.max(qrImg.width, qrImg.height);
                            var targetWidth = qrSize * 0.3;
                            var scaleLogo = targetWidth / logoImg.width;

                            logoImg.set({
                                originX: 'center',
                                originY: 'center'
                            });

                            logoImg.scale(scaleLogo);

                            qrImg.set({
                                originX: 'center',
                                originY: 'center'
                            });

                            var group = new fabric.Group([qrImg, logoImg], {
                                left: 150,
                                top: 150,
                                scaleX: 0.5,
                                scaleY: 0.5
                            });

                            centerObject(group);
                            canvas.add(group);
                            canvas.setActiveObject(group);
                            saveHistory('QR logo');
                        }, { crossOrigin: 'anonymous' });
                    }, { crossOrigin: 'anonymous' });
                };

                reader.readAsDataURL(logoFile);
            } else {
                addQrOnly();
            }
        }, 60);
    }

    function downloadPNG(multiplier) {
        if (!canvas) {
            return;
        }

        var dataURL = canvas.toDataURL({
            format: 'png',
            multiplier: multiplier || 1
        });

        var link = document.createElement('a');
        link.href = dataURL;
        link.download = multiplier && multiplier > 1 ? 'design-hd.png' : 'design.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function downloadSVG() {
        if (!canvas) {
            return;
        }

        var svg = canvas.toSVG();
        var blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
        var url = URL.createObjectURL(blob);

        var link = document.createElement('a');
        link.href = url;
        link.download = 'design.svg';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);
    }

    function downloadPDF() {
        if (!canvas) {
            return;
        }

        var dataURL = canvas.toDataURL({
            format: 'png',
            multiplier: 2
        });

        var w = window.open('', '_blank');
        if (!w) {
            return;
        }

        w.document.write('<html><head><title>PDF</title></head><body style="margin:0;display:flex;align-items:center;justify-content:center;"><img src="' + dataURL + '" style="max-width:100%;height:auto;"></body></html>');
        w.document.close();
        w.focus();
        w.print();
    }

    function ensureProduct3DModelLoaded() {
        if (typeof window.osds3dUpdate3DPreview === 'function' && canvas) {
            var dataURL = canvas.toDataURL({ format: 'png' });
            window.osds3dUpdate3DPreview(dataURL);
        }
    }

    function showPreview3D() {
        if (!canvas) {
            return;
        }

        saveCurrentFaceState();
        $('#osds3d-preview3d').prop('hidden', false).show();

        ensureProduct3DModelLoaded();

        if (typeof window.osds3dRefresh3DLayout === 'function') {
            window.osds3dRefresh3DLayout();
        }
    }

    function hidePreview3D() {
        $('#osds3d-preview3d').prop('hidden', true).hide();

        if (typeof window.osds3dRefresh3DLayout === 'function') {
            window.osds3dRefresh3DLayout();
        }
    }

    function toggleSafeZone() {
        safeZoneVisible = !safeZoneVisible;
        $('#osds3d-safe-zone').prop('hidden', !safeZoneVisible);
    }

    function getPreview3DImage() {
        var canvas3D = document.getElementById('osds3d-three-canvas');

        if (!canvas3D) {
            return '';
        }

        try {
            return canvas3D.toDataURL('image/png');
        } catch (e) {
            return '';
        }
    }

    function saveDesign() {
        if (!canvas) {
            return;
        }

        saveCurrentFaceState();

        var params = getParams();
        var productId = params.product_id || 0;

        if (!productId) {
            $('#osds3d-message').html('<div class="error"><p>Produit manquant</p></div>');
            return;
        }

        var payload = {
            front: faces.front,
            back: faces.back
        };

        var designData = JSON.stringify(payload);
        var previewImage = canvas.toDataURL({ format: 'png' });

        if ($('#osds3d-preview3d').is(':visible')) {
            ensureProduct3DModelLoaded();
        }

        var preview3DImage = getPreview3DImage();

        $('#osds3d-message').html('<p>' + getI18n('saving', 'Enregistrement en cours') + '...</p>');

        $.ajax({
            url: params.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'osds3d_save_design',
                nonce: params.nonce,
                product_id: productId,
                design_data: designData,
                preview_image: previewImage,
                preview_3d_image: preview3DImage
            }
        }).done(function (response) {
            if (response && response.success) {
                designSaved = true;

                var designId = response.data.design_id || 0;
                $('#osds3d_design_id').val(designId);

                $('#osds3d-message').html('<div class="updated"><p>Design sauvegardé (#' + designId + ')</p></div>');
                $('#osds3d-add-to-cart-form').show();
                $('#osds3d-save-design').prop('disabled', true);
                clearAutosaveDraft(true);
            } else {
                $('#osds3d-message').html(
                    '<div class="error"><p>' +
                    (response && response.data && response.data.message ? response.data.message : 'Erreur') +
                    '</p></div>'
                );
            }
        }).fail(function () {
            $('#osds3d-message').html('<div class="error"><p>Erreur AJAX</p></div>');
        });
    }

    function renderLayersPanel() {
        var $panel = $('#osds3d-layers-panel');
        if (!$panel.length || !canvas) {
            return;
        }

        var objects = canvas.getObjects();
        $panel.empty();

        if (!objects.length) {
            $panel.html('<div class="osds3d-empty-note">Aucun calque pour le moment.</div>');
            return;
        }

        var active = canvas.getActiveObject();

        objects.slice().reverse().forEach(function (obj, reverseIndex) {
            var realIndex = objects.length - 1 - reverseIndex;
            var type = obj.type || 'objet';
            var label = type;

            if (type === 'i-text') label = 'Texte';
            if (type === 'image') label = 'Image';
            if (type === 'group') label = 'Groupe';
            if (type === 'rect') label = 'Rectangle';
            if (type === 'circle') label = 'Cercle';
            if (type === 'triangle') label = 'Triangle';
            if (type === 'polygon') label = 'Polygone';
            if (type === 'path') label = 'Dessin';

            var isActive = active === obj ? ' is-active' : '';

            var $item = $('<div class="osds3d-layer-item' + isActive + '"></div>');
            var $name = $('<div class="osds3d-layer-name"></div>').text(label + ' #' + (realIndex + 1));
            var $actions = $('<div class="osds3d-layer-actions"></div>');

            var $select = $('<button type="button" class="osds3d-ui-btn">Sélect.</button>').on('click', function () {
                canvas.setActiveObject(obj);
                canvas.renderAll();
                renderLayersPanel();
            });

            var $hide = $('<button type="button" class="osds3d-ui-btn">Masq.</button>').on('click', function () {
                obj.visible = !obj.visible;
                canvas.renderAll();
                saveHistory('Visibilité');
            });

            var $up = $('<button type="button" class="osds3d-ui-btn">↑</button>').on('click', function () {
                canvas.bringForward(obj);
                canvas.renderAll();
                saveHistory('Calque +');
            });

            var $down = $('<button type="button" class="osds3d-ui-btn">↓</button>').on('click', function () {
                canvas.sendBackwards(obj);
                canvas.renderAll();
                saveHistory('Calque -');
            });

            $actions.append($select, $hide, $up, $down);
            $item.append($name, $actions);
            $panel.append($item);
        });
    }

    function renderHistoryPanel() {
        var $panel = $('#osds3d-history-panel');
        if (!$panel.length) {
            return;
        }

        $panel.empty();

        if (!history.length) {
            $panel.html('<div class="osds3d-empty-note">Historique vide.</div>');
            return;
        }

        history.slice().reverse().forEach(function (item, reverseIndex) {
            var realIndex = history.length - 1 - reverseIndex;
            var isActive = realIndex === historyIndex ? ' is-active' : '';
            var $item = $('<div class="osds3d-history-item' + isActive + '"></div>');
            var $label = $('<div class="osds3d-layer-name"></div>').text(item.label || ('Étape ' + (realIndex + 1)));

            var $go = $('<button type="button" class="osds3d-ui-btn">Revenir</button>').on('click', function () {
                historyIndex = realIndex;

                withHistoryDisabled(function (done) {
                    canvas.loadFromJSON(history[realIndex].state, function () {
                        canvas.renderAll();
                        done();
                    });
                }, function () {
                    fitCanvasToContainer();
                    renderLayersPanel();
                    renderHistoryPanel();
                    saveCurrentFaceState();
                    applySafeZoneFromSettings();
                });
            });

            $item.append($label, $go);
            $panel.append($item);
        });
    }

    function autosaveDraft() {
        if (!canvas) {
            return;
        }

        saveCurrentFaceState();

        var data = {
            front: faces.front,
            back: faces.back,
            currentFace: currentFace,
            savedAt: Date.now(),
            canvasWidth: baseCanvasWidth,
            canvasHeight: baseCanvasHeight
        };

        try {
            localStorage.setItem(autosaveKey, JSON.stringify(data));
        } catch (e) {
            console.warn('Autosave impossible', e);
        }
    }

    function restoreAutosaveDraft() {
        try {
            var raw = localStorage.getItem(autosaveKey);
            if (!raw) {
                return;
            }

            var data = JSON.parse(raw);
            if (!data) {
                return;
            }

            faces.front = data.front || null;
            faces.back = data.back || null;
            currentFace = data.currentFace || 'front';

            if (data.canvasWidth && data.canvasHeight) {
                setCanvasLogicalSize(data.canvasWidth, data.canvasHeight);
            }

            withHistoryDisabled(function (done) {
                if (faces[currentFace]) {
                    canvas.loadFromJSON(faces[currentFace], function () {
                        canvas.renderAll();
                        done();
                    });
                } else {
                    canvas.clear();
                    canvas.setBackgroundColor('#ffffff', canvas.renderAll.bind(canvas));
                    canvas.renderAll();
                    done();
                }
            }, function () {
                updateFaceButtons();
                renderLayersPanel();
                fitCanvasToContainer();
                applySafeZoneFromSettings();
                saveHistory('Restauration');
                $('#osds3d-message').text('Brouillon restauré.');
            });
        } catch (e) {
            console.warn('Restauration impossible', e);
        }
    }

    function clearAutosaveDraft(silent) {
        try {
            localStorage.removeItem(autosaveKey);

            if (!silent) {
                $('#osds3d-message').text('Brouillon supprimé.');
            }
        } catch (e) {
            console.warn('Suppression brouillon impossible', e);
        }
    }

    function getObjectBounds(obj) {
        return obj.getBoundingRect(true, true);
    }

    function alignObject(mode) {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();
        if (!obj) {
            return;
        }

        var bounds = getObjectBounds(obj);

        switch (mode) {
            case 'left':
                obj.set({ left: (obj.left || 0) - bounds.left });
                break;
            case 'centerX':
                obj.set({ left: (obj.left || 0) + (baseCanvasWidth / 2 - (bounds.left + bounds.width / 2)) });
                break;
            case 'right':
                obj.set({ left: (obj.left || 0) + (baseCanvasWidth - (bounds.left + bounds.width)) });
                break;
            case 'top':
                obj.set({ top: (obj.top || 0) - bounds.top });
                break;
            case 'centerY':
                obj.set({ top: (obj.top || 0) + (baseCanvasHeight / 2 - (bounds.top + bounds.height / 2)) });
                break;
            case 'bottom':
                obj.set({ top: (obj.top || 0) + (baseCanvasHeight - (bounds.top + bounds.height)) });
                break;
        }

        obj.setCoords();
        canvas.renderAll();
        saveHistory('Alignement');
    }

    function distributeObjects(axis) {
        if (!canvas) {
            return;
        }

        var selected = canvas.getActiveObjects();
        if (!selected || selected.length < 3) {
            return;
        }

        var sorted = selected.slice().sort(function (a, b) {
            var aVal = axis === 'x' ? getObjectBounds(a).left : getObjectBounds(a).top;
            var bVal = axis === 'x' ? getObjectBounds(b).left : getObjectBounds(b).top;
            return aVal - bVal;
        });

        var firstBounds = getObjectBounds(sorted[0]);
        var lastBounds = getObjectBounds(sorted[sorted.length - 1]);

        var first = axis === 'x' ? firstBounds.left : firstBounds.top;
        var last = axis === 'x' ? lastBounds.left : lastBounds.top;
        var step = (last - first) / (sorted.length - 1);

        sorted.forEach(function (obj, i) {
            var bounds = getObjectBounds(obj);

            if (axis === 'x') {
                obj.set({ left: (obj.left || 0) + (first + step * i - bounds.left) });
            } else {
                obj.set({ top: (obj.top || 0) + (first + step * i - bounds.top) });
            }

            obj.setCoords();
        });

        canvas.renderAll();
        saveHistory('Distribution');
    }

    function copyObject() {
        if (!canvas) {
            return;
        }

        var obj = canvas.getActiveObject();
        if (!obj) {
            return;
        }

        obj.clone(function (cloned) {
            copiedObject = cloned;
        });
    }

    function pasteObject() {
        if (!canvas || !copiedObject) {
            return;
        }

        copiedObject.clone(function (cloned) {
            cloned.set({
                left: (cloned.left || 0) + 20,
                top: (cloned.top || 0) + 20
            });

            canvas.add(cloned);
            canvas.setActiveObject(cloned);
            saveHistory('Coller');
        });
    }

    function applyGuidedFields() {
        var name = $('#osds3d-guided-name').val();
        var date = $('#osds3d-guided-date').val();
        var message = $('#osds3d-guided-message').val();

        if (name) addCustomText(name);
        if (date) addCustomText(date);
        if (message) addCustomText(message);
    }

    function initProfessionalUI() {
        $('.osds3d-tab').off('click.osds3dUI').on('click.osds3dUI', function () {
            var target = $(this).data('target');

            $('.osds3d-tab').removeClass('active is-active');
            $(this).addClass('active is-active');

            $('.osds3d-panel').removeClass('is-active').attr('hidden', true);
            $('#' + target).addClass('is-active').removeAttr('hidden');
        });

        var $modal = $('#osds3d-help-modal');

        function openHelpModal() {
            $modal.addClass('is-open').prop('hidden', false);
            $('body').css('overflow', 'hidden');
        }

        function closeHelpModal() {
            $modal.removeClass('is-open').prop('hidden', true);
            $('body').css('overflow', '');
        }

        $('#osds3d-help-btn').off('click.osds3dHelp').on('click.osds3dHelp', function (e) {
            e.preventDefault();
            openHelpModal();
        });

        $('#osds3d-help-close').off('click.osds3dHelp').on('click.osds3dHelp', function (e) {
            e.preventDefault();
            closeHelpModal();
        });

        $modal.off('click.osds3dHelp').on('click.osds3dHelp', function (e) {
            if (e.target === this) {
                closeHelpModal();
            }
        });

        $(document).off('keydown.osds3dHelp').on('keydown.osds3dHelp', function (e) {
            if (e.key === 'Escape') {
                closeHelpModal();
            }
        });
    }

    function enableShortcuts() {
        $(document).off('keydown.osds3dShortcuts').on('keydown.osds3dShortcuts', function (e) {
            if (!canvas) {
                return;
            }

            if (e.ctrlKey && e.key.toLowerCase() === 'z') {
                e.preventDefault();
                undo();
                return;
            }

            if (e.ctrlKey && e.key.toLowerCase() === 'y') {
                e.preventDefault();
                redo();
                return;
            }

            if (e.ctrlKey && e.key.toLowerCase() === 'd') {
                e.preventDefault();
                duplicateObject();
                return;
            }

            if (e.ctrlKey && e.key.toLowerCase() === 'c') {
                e.preventDefault();
                copyObject();
                return;
            }

            if (e.ctrlKey && e.key.toLowerCase() === 'v') {
                e.preventDefault();
                pasteObject();
                return;
            }

            if (e.key === 'Delete') {
                var obj = canvas.getActiveObject();

                if (obj) {
                    canvas.remove(obj);
                    saveHistory('Supprimer');
                }
            }
        });
    }

    function initControls() {
        var params = getParams();
        var $select = $('#osds3d-template-select');

        if ($select.length && params.templates && params.templates.length) {
            $select.find('option:not([value=""])').remove();

            params.templates.forEach(function (tpl) {
                var label = tpl.name;

                if (tpl.product_id && tpl.product_id !== 0) {
                    label += ' (Produit)';
                }

                $('<option></option>').val(tpl.id).text(label).appendTo($select);
            });
        }

        $('#osds3d-apply-template').off('click').on('click', applyTemplate);

        $('#osds3d-search').off('input').on('input', function () {
            var keyword = $(this).val().toLowerCase();

            $('.osds3d-tab').each(function () {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(keyword) !== -1);
            });
        });

        $('#osds3d-apply-background').off('click').on('click', function () {
            var color = $('#osds3d-background-color').val() || '#ffffff';
            canvas.setBackgroundColor(color, canvas.renderAll.bind(canvas));
            saveHistory('Fond');
        });

        $('#osds3d-add-text').off('click').on('click', function (e) {
            e.preventDefault();
            addText();
        });

        $('#osds3d-add-image').off('click').on('click', function (e) {
            e.preventDefault();
            addImage();
        });

        $('#osds3d-add-rect').off('click').on('click', function (e) {
            e.preventDefault();
            addShape('rect');
        });

        $('#osds3d-add-circle').off('click').on('click', function (e) {
            e.preventDefault();
            addShape('circle');
        });

        $('#osds3d-add-triangle').off('click').on('click', function (e) {
            e.preventDefault();
            addShape('triangle');
        });

        $('#osds3d-add-star').off('click').on('click', function (e) {
            e.preventDefault();
            addShape('star');
        });

        $('#osds3d-add-star6').off('click').on('click', function (e) {
            e.preventDefault();
            addShape('star6');
        });

        $('#osds3d-add-star8').off('click').on('click', function (e) {
            e.preventDefault();
            addShape('star8');
        });

        $('#osds3d-add-polygon').off('click').on('click', function (e) {
            e.preventDefault();
            addPolygon();
        });

        $('#osds3d-text-color').off('change').on('change', function () {
            applyTextColor($(this).val());
        });

        $('#osds3d-font-size').off('input').on('input', function () {
            applyFontSize($(this).val());
        });

        $('#osds3d-font-family').off('change').on('change', function () {
            var font = $(this).val();
            var obj = canvas ? canvas.getActiveObject() : null;

            if (obj && obj.type === 'i-text') {
                obj.set('fontFamily', font);
                canvas.renderAll();
                saveHistory('Police');
            }
        });

        $('#osds3d-update-text').off('click').on('click', function () {
            var content = $('#osds3d-text-content').val();
            var obj = canvas ? canvas.getActiveObject() : null;

            if (obj && obj.type === 'i-text') {
                obj.text = content;
                canvas.renderAll();
                saveHistory('Texte');
            } else {
                addCustomText(content);
            }
        });

        $('#osds3d-bold').off('click').on('click', toggleBold);
        $('#osds3d-italic').off('click').on('click', toggleItalic);
        $('#osds3d-underline').off('click').on('click', toggleUnderline);

        $('#osds3d-stroke-color, #osds3d-stroke-width').off('change input').on('change input', function () {
            applyStroke($('#osds3d-stroke-color').val(), $('#osds3d-stroke-width').val());
        });

        $('#osds3d-apply-shadow').off('click').on('click', function () {
            applyShadow(
                $('#osds3d-shadow-color').val(),
                $('#osds3d-shadow-offset-x').val(),
                $('#osds3d-shadow-offset-y').val(),
                $('#osds3d-shadow-blur').val()
            );
        });

        $('#osds3d-apply-filter').off('click').on('click', function () {
            applyFilter($('#osds3d-image-filter').val());
        });

        $('#osds3d-apply-global-color').off('click').on('click', function () {
            applyTextColor($('#osds3d-global-color').val());
        });

        $('#osds3d-global-color').off('change').on('change', function () {
            $('#osds3d-global-hex').val($(this).val());
        });

        $('#osds3d-global-hex').off('change').on('change', function () {
            var val = $(this).val();

            if (/^#([0-9A-F]{3}){1,2}$/i.test(val)) {
                $('#osds3d-global-color').val(val);
            }
        });

        $('.osds3d-color-chip').off('click').on('click', function () {
            var color = $(this).data('color');
            $('#osds3d-global-color').val(color);
            $('#osds3d-global-hex').val(color);
        });

        $('#osds3d-opacity').off('input change').on('input change', function () {
            applyOpacity($(this).val());
        });

        $('#osds3d-generate-qr').off('click').on('click', function () {
            var content = $('#osds3d-qr-content').val();
            var colorDark = $('#osds3d-qr-color').val();
            var colorLight = $('#osds3d-qr-bg-color').val();
            var logoInput = document.getElementById('osds3d-qr-logo');
            var logoFile = logoInput && logoInput.files && logoInput.files[0] ? logoInput.files[0] : null;

            generateQR(content, colorDark, colorLight, logoFile);
        });

        $('#osds3d-undo').off('click').on('click', undo);
        $('#osds3d-redo').off('click').on('click', redo);
        $('#osds3d-toggle-grid').off('click').on('click', toggleGrid);
        $('#osds3d-zoom-in').off('click').on('click', zoomIn);
        $('#osds3d-zoom-out').off('click').on('click', zoomOut);
        $('#osds3d-duplicate').off('click').on('click', duplicateObject);
        $('#osds3d-bring-forward').off('click').on('click', bringForward);
        $('#osds3d-send-backward').off('click').on('click', sendBackward);
        $('#osds3d-lock').off('click').on('click', lockObject);
        $('#osds3d-unlock').off('click').on('click', unlockAllObjects);

        $('#osds3d-delete-object').off('click').on('click', function () {
            var obj = canvas ? canvas.getActiveObject() : null;

            if (obj) {
                canvas.remove(obj);
                saveHistory('Supprimer');
            }
        });

        $('#osds3d-clear-canvas').off('click').on('click', function () {
            if (confirm(getI18n('confirm_clear', 'Êtes-vous sûr de vouloir tout effacer ?'))) {
                withHistoryDisabled(function (done) {
                    canvas.clear();
                    setCanvasLogicalSize(baseCanvasWidth, baseCanvasHeight);
                    canvas.setBackgroundColor('#ffffff', canvas.renderAll.bind(canvas));
                    canvas.renderAll();
                    done();
                }, function () {
                    $('#osds3d-save-design').prop('disabled', false);
                    designSaved = false;
                    saveHistory('Effacer');
                    fitCanvasToContainer();
                    saveCurrentFaceState();
                    renderLayersPanel();
                    applySafeZoneFromSettings();
                });
            }
        });

        $('#osds3d-save-design').off('click').on('click', function (e) {
            e.preventDefault();
            saveDesign();
        });

        $('#osds3d-download-png').off('click').on('click', function () {
            downloadPNG(1);
        });

        $('#osds3d-download-png-hd').off('click').on('click', function () {
            downloadPNG(3);
        });

        $('#osds3d-download-svg').off('click').on('click', downloadSVG);
        $('#osds3d-download-pdf').off('click').on('click', downloadPDF);

        $('#osds3d-show-preview3d').off('click').on('click', showPreview3D);
        $('#osds3d-hide-preview3d').off('click').on('click', hidePreview3D);

        $('#osds3d-add-model').off('click').on('click', function () {
            if (hasProduct3DModel()) {
                $('#osds3d-message').text('Un modèle 3D produit est déjà lié depuis l’administration.');
                return;
            }

            var fileInput = document.getElementById('osds3d-model-file');
            var file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            var color = $('#osds3d-model-color').val() || '#cccccc';

            if (file && typeof window.osds3dLoad3DModel === 'function') {
                window.osds3dLoad3DModel(file, color);
            }
        });

        $('#osds3d-model-color').off('change').on('change', function () {
            if (typeof window.osds3dUpdateModelColor === 'function') {
                window.osds3dUpdateModelColor($(this).val());
            }
        });

        $('#osds3d-model-scale').off('input change').on('input change', function () {
            if (typeof window.osds3dUpdateModelScale === 'function') {
                window.osds3dUpdateModelScale($(this).val());
            }
        });

        $('#osds3d-remove-model').off('click').on('click', function () {
            if (hasProduct3DModel()) {
                $('#osds3d-message').text('Le modèle 3D lié au produit ne se supprime pas ici. Il se gère depuis l’administration.');
                return;
            }

            if (typeof window.osds3dRemoveModel === 'function') {
                window.osds3dRemoveModel();
            }
        });

        $('#osds3d-free-color').off('change').on('change', function () {
            if (canvas && canvas.isDrawingMode && canvas.freeDrawingBrush) {
                canvas.freeDrawingBrush.color = $(this).val();
            }
        });

        $('#osds3d-free-size').off('input change').on('input change', function () {
            if (canvas && canvas.isDrawingMode && canvas.freeDrawingBrush) {
                canvas.freeDrawingBrush.width = parseInt($(this).val(), 10) || 1;
            }
        });

        $('#osds3d-start-draw').off('click').on('click', startDrawing);
        $('#osds3d-stop-draw').off('click').on('click', stopDrawing);

        $('#osds3d-apply-gradient').off('click').on('click', applyGradientFill);
        $('#osds3d-group').off('click').on('click', groupSelected);
        $('#osds3d-ungroup').off('click').on('click', ungroupSelected);

        $('#osds3d-align-left').off('click').on('click', function () { alignObject('left'); });
        $('#osds3d-align-center-x').off('click').on('click', function () { alignObject('centerX'); });
        $('#osds3d-align-right').off('click').on('click', function () { alignObject('right'); });
        $('#osds3d-align-top').off('click').on('click', function () { alignObject('top'); });
        $('#osds3d-align-center-y').off('click').on('click', function () { alignObject('centerY'); });
        $('#osds3d-align-bottom').off('click').on('click', function () { alignObject('bottom'); });

        $('#osds3d-distribute-x').off('click').on('click', function () { distributeObjects('x'); });
        $('#osds3d-distribute-y').off('click').on('click', function () { distributeObjects('y'); });

        $('#osds3d-face-front').off('click').on('click', function () { loadFace('front'); });
        $('#osds3d-face-back').off('click').on('click', function () { loadFace('back'); });

        $('#osds3d-toggle-safe-zone').off('click').on('click', toggleSafeZone);

        $('#osds3d-restore-autosave').off('click').on('click', restoreAutosaveDraft);
        $('#osds3d-clear-autosave').off('click').on('click', function () { clearAutosaveDraft(false); });

        $('#osds3d-apply-guided-fields').off('click').on('click', applyGuidedFields);
    }

    function init3DProductUIState() {
        if (!hasProduct3DModel()) {
            return;
        }

        $('#osds3d-model-file').prop('disabled', true);
        $('#osds3d-add-model').prop('disabled', true);
    }

    $(function () {
        initCanvas();
        initControls();
        initProfessionalUI();
        enableDragDrop();
        enableShortcuts();
        init3DProductUIState();
        renderLayersPanel();
        renderHistoryPanel();

        $(window).on('resize', function () {
            fitCanvasToContainer();
        });

        setTimeout(function () {
            fitCanvasToContainer();
            applySafeZoneFromSettings();
        }, 100);
    });

})(jQuery);
