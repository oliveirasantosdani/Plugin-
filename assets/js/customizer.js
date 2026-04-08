document.addEventListener("DOMContentLoaded", function () {

    const canvasEl = document.getElementById('osds3d-canvas');
    if (!canvasEl) return;

    // =========================
    // INIT CANVAS
    // =========================
    const canvas = new fabric.Canvas('osds3d-canvas', {
        preserveObjectStacking: true,
        selection: true
    });

    // =========================
    // RESPONSIVE CANVAS
    // =========================
    function resizeCanvas() {
        const container = document.querySelector('.osds3d-center');

        if (!container) return;

        const width = container.clientWidth;
        const height = container.clientHeight;

        canvas.setWidth(width);
        canvas.setHeight(height);

        canvas.renderAll();
    }

    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    // =========================
    // AJOUT TEXTE
    // =========================
    document.getElementById('add-text')?.addEventListener('click', function () {

        const text = new fabric.IText('Ton texte', {
            left: 100,
            top: 100,
            fill: '#000',
            fontSize: 30,
            fontFamily: 'Arial'
        });

        canvas.add(text);
        canvas.setActiveObject(text);
    });

    // =========================
    // SUPPRIMER OBJET
    // =========================
    document.getElementById('delete-object')?.addEventListener('click', function () {

        const active = canvas.getActiveObject();

        if (active) {
            canvas.remove(active);
        }
    });

    // =========================
    // DRAG & DROP IMAGE
    // =========================
    const dropZone = document.querySelector('.osds3d-center');

    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
    });

    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();

        const file = e.dataTransfer.files[0];

        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();

        reader.onload = function (f) {

            fabric.Image.fromURL(f.target.result, function (img) {

                img.set({
                    left: 100,
                    top: 100,
                    scaleX: 0.5,
                    scaleY: 0.5
                });

                canvas.add(img);
            });
        };

        reader.readAsDataURL(file);
    });

    // =========================
    // COULEUR TEXTE
    // =========================
    document.getElementById('text-color')?.addEventListener('change', function (e) {

        const active = canvas.getActiveObject();

        if (active && active.type === 'i-text') {
            active.set('fill', e.target.value);
            canvas.renderAll();
        }
    });

    // =========================
    // EXPORT DESIGN
    // =========================
    function exportDesign() {

        const json = JSON.stringify(canvas.toJSON());
        const image = canvas.toDataURL({
            format: 'png',
            quality: 1
        });

        return {
            json: json,
            image: image
        };
    }

    // =========================
    // ADD TO CART HOOK
    // =========================
    const form = document.querySelector('form.cart');

    if (form) {
        form.addEventListener('submit', function () {

            const data = exportDesign();

            let inputJson = document.getElementById('osds3d_json');
            let inputImg = document.getElementById('osds3d_image');

            if (!inputJson) {
                inputJson = document.createElement('input');
                inputJson.type = 'hidden';
                inputJson.name = 'osds3d_json';
                inputJson.id = 'osds3d_json';
                form.appendChild(inputJson);
            }

            if (!inputImg) {
                inputImg = document.createElement('input');
                inputImg.type = 'hidden';
                inputImg.name = 'osds3d_image';
                inputImg.id = 'osds3d_image';
                form.appendChild(inputImg);
            }

            inputJson.value = data.json;
            inputImg.value = data.image;
        });
    }

});