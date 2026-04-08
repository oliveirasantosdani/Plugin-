import * as THREE from 'https://esm.sh/three@0.160.0';
import { OrbitControls } from 'https://esm.sh/three@0.160.0/examples/jsm/controls/OrbitControls.js';
import { GLTFLoader } from 'https://esm.sh/three@0.160.0/examples/jsm/loaders/GLTFLoader.js';
import { STLLoader } from 'https://esm.sh/three@0.160.0/examples/jsm/loaders/STLLoader.js';
import { OBJLoader } from 'https://esm.sh/three@0.160.0/examples/jsm/loaders/OBJLoader.js';

/**
 * Aperçu 3D pour OSDS3D Customizer Pro
 *
 * Version corrigée :
 * - support GLB / STL / OBJ
 * - chargement auto du modèle produit
 * - texture du design appliquée uniquement sur l’extérieur du mug
 * - fallback plan 2D
 * - resize fiable
 * - zoom/orbit stables
 * - logs console utiles
 */

(function () {
    'use strict';

    let scene = null;
    let camera = null;
    let renderer = null;
    let controls = null;

    let plane = null;
    let currentTexture = null;
    let currentModel = null;
    let currentModelBaseScale = 1;

    let backgroundMesh = null;
    let animationStarted = false;
    let initDone = false;
    let productModelLoaded = false;
    let lastDesignDataUrl = '';
    let lastRenderSize = { width: 800, height: 600 };

    function log(...args) {
        console.log('[OSDS3D 3D]', ...args);
    }

    function warn(...args) {
        console.warn('[OSDS3D 3D]', ...args);
    }

    function getParams() {
        return window.osds3d_customizer_params || {};
    }

    function getModelParams() {
        return getParams().model3d || {};
    }

    function normalizeMeshName(name) {
        return String(name || '').trim().toLowerCase();
    }

    function getConfiguredPrintableMeshName() {
        return normalizeMeshName(getModelParams().printable_mesh || '');
    }

    function getConfiguredExcludedMeshNames() {
        const excluded = getModelParams().excluded_meshes;
        if (!Array.isArray(excluded)) {
            return [];
        }

        return excluded
            .map((name) => normalizeMeshName(name))
            .filter(Boolean);
    }

    function getConfiguredDefaultRotation() {
        const rotation = getModelParams().default_rotation || {};

        return {
            x: Number.isFinite(Number(rotation.x)) ? Number(rotation.x) : 0,
            y: Number.isFinite(Number(rotation.y)) ? Number(rotation.y) : 0,
            z: Number.isFinite(Number(rotation.z)) ? Number(rotation.z) : 0
        };
    }

    function getConfiguredCameraDistance() {
        const distance = Number(getModelParams().camera_distance || 0);
        return Number.isFinite(distance) && distance > 0 ? distance : 0;
    }

    function getCanvasEl() {
        return document.getElementById('osds3d-three-canvas');
    }

    function getPreviewWrapper() {
        return document.getElementById('osds3d-preview3d');
    }

    function getRenderContainer() {
        const canvasEl = getCanvasEl();
        if (!canvasEl) {
            return null;
        }

        return canvasEl.closest('.osds3d-canvas-card') || canvasEl.parentElement || canvasEl;
    }

    function isPreviewVisible() {
        const preview = getPreviewWrapper();
        if (!preview) {
            return false;
        }

        if (preview.hasAttribute('hidden')) {
            return false;
        }

        const style = window.getComputedStyle(preview);
        return style.display !== 'none' && style.visibility !== 'hidden';
    }

    function getSafeRenderSize() {
        const container = getRenderContainer();
        const canvasEl = getCanvasEl();

        let width = lastRenderSize.width || 800;
        let height = lastRenderSize.height || 600;

        if (container) {
            width = container.clientWidth || width;
            height = container.clientHeight || height;
        } else if (canvasEl) {
            width = canvasEl.clientWidth || width;
            height = canvasEl.clientHeight || height;
        }

        if (!width || width < 50) {
            width = 800;
        }

        if (!height || height < 50) {
            height = Math.round(width * 0.75);
        }

        lastRenderSize = { width, height };
        return { width, height };
    }

    function resizeRenderer() {
        if (!renderer || !camera) {
            return;
        }

        const size = getSafeRenderSize();
        const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);

        renderer.setPixelRatio(pixelRatio);
        renderer.setSize(size.width, size.height, false);

        camera.aspect = size.width / size.height;
        camera.updateProjectionMatrix();

        if (currentModel) {
            frameModel(currentModel);
        } else {
            frameScene();
        }
    }

    function disposeTexture(texture) {
        if (texture && typeof texture.dispose === 'function') {
            texture.dispose();
        }
    }

    function disposeMaterial(material) {
        if (!material) {
            return;
        }

        if (Array.isArray(material)) {
            material.forEach(disposeMaterial);
            return;
        }

        if (material.map) disposeTexture(material.map);
        if (material.normalMap) disposeTexture(material.normalMap);
        if (material.roughnessMap) disposeTexture(material.roughnessMap);
        if (material.metalnessMap) disposeTexture(material.metalnessMap);
        if (material.emissiveMap) disposeTexture(material.emissiveMap);
        if (material.alphaMap) disposeTexture(material.alphaMap);
        if (material.bumpMap) disposeTexture(material.bumpMap);
        if (material.aoMap) disposeTexture(material.aoMap);

        if (typeof material.dispose === 'function') {
            material.dispose();
        }
    }

    function disposeObject3D(object) {
        if (!object) {
            return;
        }

        object.traverse((child) => {
            if (child.isMesh) {
                if (child.geometry && typeof child.geometry.dispose === 'function') {
                    child.geometry.dispose();
                }

                if (child.material) {
                    disposeMaterial(child.material);
                }
            }
        });
    }

    function createPreviewPlane() {
        if (!scene) {
            return;
        }

        if (plane) {
            scene.remove(plane);

            if (plane.geometry) {
                plane.geometry.dispose();
            }

            if (plane.material) {
                disposeMaterial(plane.material);
            }

            plane = null;
        }

        const geometry = new THREE.PlaneGeometry(2, 2);
        const material = new THREE.MeshBasicMaterial({
            color: 0xffffff,
            side: THREE.DoubleSide,
            transparent: true
        });

        plane = new THREE.Mesh(geometry, material);
        plane.position.set(0, 0, 0);
        plane.visible = true;

        scene.add(plane);
    }

    function setPlaneVisible(visible) {
        if (plane) {
            plane.visible = !!visible;
        }
    }

    function startAnimation() {
        if (animationStarted) {
            return;
        }

        animationStarted = true;
        animate();
    }

    function animate() {
        if (!animationStarted) {
            return;
        }

        requestAnimationFrame(animate);

        if (controls) {
            controls.update();
        }

        if (renderer && scene && camera) {
            renderer.render(scene, camera);
        }
    }

    function frameScene() {
        if (!camera || !controls) {
            return;
        }

        controls.target.set(0, 0, 0);
        camera.position.set(0.9, 0.45, 2.8);
        camera.lookAt(0, 0, 0);
        controls.minDistance = 0.7;
        controls.maxDistance = 8;
        controls.update();
    }

    function fitPlaneToTexture(texture) {
        if (!plane || !texture || !texture.image) {
            return;
        }

        const img = texture.image;
        const imgWidth = img.naturalWidth || img.videoWidth || img.width || 1;
        const imgHeight = img.naturalHeight || img.videoHeight || img.height || 1;
        const aspect = imgWidth / imgHeight;

        if (aspect >= 1) {
            plane.scale.set(1.8, 1.8 / aspect, 1);
        } else {
            plane.scale.set(1.8 * aspect, 1.8, 1);
        }
    }

    function updateTexture(dataURL) {
        if (!plane || !dataURL) {
            return;
        }

        lastDesignDataUrl = dataURL;

        const loader = new THREE.TextureLoader();

        loader.load(
            dataURL,
            (texture) => {
                if (currentTexture) {
                    disposeTexture(currentTexture);
                    currentTexture = null;
                }

                texture.colorSpace = THREE.SRGBColorSpace;
                texture.minFilter = THREE.LinearFilter;
                texture.magFilter = THREE.LinearFilter;
                texture.wrapS = THREE.ClampToEdgeWrapping;
                texture.wrapT = THREE.ClampToEdgeWrapping;
                texture.flipY = false;
                texture.needsUpdate = true;

                currentTexture = texture;

                if (plane && plane.material) {
                    plane.material.map = texture;
                    plane.material.needsUpdate = true;
                }

                fitPlaneToTexture(texture);

                if (currentModel) {
                    applyTextureToCurrentModel(texture);
                } else {
                    setPlaneVisible(true);
                    frameScene();
                }

                resizeRenderer();
                log('Texture design mise à jour');
            },
            undefined,
            (err) => {
                warn('Erreur chargement texture 3D :', err);
            }
        );
    }

    function removeCurrentModel() {
        if (currentModel && scene) {
            scene.remove(currentModel);
            disposeObject3D(currentModel);
            currentModel = null;
            currentModelBaseScale = 1;
        }
    }

    function removeBackgroundMesh() {
        if (!backgroundMesh || !scene) {
            return;
        }

        scene.remove(backgroundMesh);

        if (backgroundMesh.geometry) {
            backgroundMesh.geometry.dispose();
        }

        if (backgroundMesh.material) {
            disposeMaterial(backgroundMesh.material);
        }

        backgroundMesh = null;
    }

    function frameModel(object) {
        if (!object || !camera || !controls) {
            return;
        }

        const box = new THREE.Box3().setFromObject(object);
        const center = new THREE.Vector3();
        const sphere = new THREE.Sphere();

        box.getCenter(center);
        box.getBoundingSphere(sphere);

        let radius = sphere.radius;
        if (!radius || !isFinite(radius)) {
            radius = 1;
        }

        const fov = camera.fov * (Math.PI / 180);
        let distance = radius / Math.sin(fov / 2);
        distance *= 1.35;

        const configuredDistance = getConfiguredCameraDistance();
        if (configuredDistance > 0) {
            distance = Math.max(configuredDistance, radius * 0.9);
        }

        const direction = new THREE.Vector3(1, 0.45, 1.35).normalize();
        camera.position.copy(center.clone().add(direction.multiplyScalar(distance)));
        controls.target.copy(center);
        controls.minDistance = Math.max(0.35, radius * 0.8);
        controls.maxDistance = Math.max(4, radius * 8);
        camera.near = Math.max(0.01, distance / 100);
        camera.far = Math.max(1000, distance * 20);
        camera.updateProjectionMatrix();
        controls.update();
    }

    function applyTextureToMaterial(material, texture) {
        if (!material) {
            return;
        }

        if (Array.isArray(material)) {
            material.forEach((mat) => applyTextureToMaterial(mat, texture));
            return;
        }

        material.map = texture || null;
        material.needsUpdate = true;

        if (material.color && typeof material.color.set === 'function') {
            material.color.set('#ffffff');
        }

        material.side = THREE.FrontSide;
    }

    function hasKeyword(name, keywords) {
        if (!name) {
            return false;
        }

        return keywords.some((keyword) => {
            return name === keyword ||
                name.startsWith(keyword + '_') ||
                name.startsWith(keyword + '-') ||
                name.endsWith('_' + keyword) ||
                name.endsWith('-' + keyword) ||
                name.includes('_' + keyword + '_') ||
                name.includes('-' + keyword + '-') ||
                name.includes('_' + keyword + '-') ||
                name.includes('-' + keyword + '_');
        });
    }

    function getMeshName(child) {
        return normalizeMeshName(child && child.name ? child.name : '');
    }

    function isExcludedMeshName(name) {
        const normalizedName = normalizeMeshName(name);
        if (!normalizedName) {
            return false;
        }

        return getConfiguredExcludedMeshNames().includes(normalizedName);
    }

    function isLikelyInnerMesh(name) {
        return (
            hasKeyword(name, ['inside', 'inner', 'interior', 'dentro', 'intern'])
        );
    }

    function isLikelyHandleMesh(name) {
        return (
            hasKeyword(name, ['handle', 'anse', 'ear'])
        );
    }

    function isLikelyBottomMesh(name) {
        return (
            hasKeyword(name, ['bottom', 'base', 'foot', 'socle'])
        );
    }

    function isLikelyExteriorMesh(name) {
        return (
            hasKeyword(name, ['cup', 'mug', 'body', 'outside', 'outer', 'exterior', 'wrap'])
        );
    }

    function getRootPrintMetrics(root) {
        const box = new THREE.Box3().setFromObject(root);
        const size = new THREE.Vector3();
        const center = new THREE.Vector3();

        box.getSize(size);
        box.getCenter(center);

        return {
            box,
            size,
            center,
            radiusLike: Math.max(size.x, size.z) || 1,
            heightLike: size.y || 1
        };
    }

    function scoreMeshForPrint(child, rootMetrics) {
        if (!child || !child.isMesh || !child.geometry) {
            return -999999;
        }

        const name = getMeshName(child);
        if (isExcludedMeshName(name)) {
            return -999999;
        }

        const box = new THREE.Box3().setFromObject(child);
        const size = new THREE.Vector3();
        const center = new THREE.Vector3();
        box.getSize(size);
        box.getCenter(center);

        let score = 0;

        const radiusLike = Math.max(size.x, size.z);
        const heightLike = size.y;
        const volumeHint = size.x * size.y * size.z;
        const radiusCoverage = rootMetrics ? (radiusLike / Math.max(rootMetrics.radiusLike, 0.0001)) : 0;
        const heightCoverage = rootMetrics ? (heightLike / Math.max(rootMetrics.heightLike, 0.0001)) : 0;
        const horizontalOffset = rootMetrics
            ? Math.hypot(center.x - rootMetrics.center.x, center.z - rootMetrics.center.z)
            : 0;
        const insetX = rootMetrics ? Math.max(0, (rootMetrics.size.x - size.x) / 2) : 0;
        const insetZ = rootMetrics ? Math.max(0, (rootMetrics.size.z - size.z) / 2) : 0;
        const radialInset = insetX + insetZ;

        score += volumeHint;
        score += radiusCoverage * 4000;
        score += heightCoverage * 1500;

        if (heightLike > 0) {
            score += heightLike * 3;
        }

        if (radiusLike > 0) {
            score += radiusLike * 2;
        }

        if (isLikelyExteriorMesh(name)) {
            score += 5000;
        }

        if (isLikelyInnerMesh(name)) {
            score -= 12000;
        }

        if (isLikelyHandleMesh(name)) {
            score -= 9000;
        }

        if (isLikelyBottomMesh(name)) {
            score -= 7000;
        }

        if (radiusCoverage < 0.82) {
            score -= 3500;
        }

        if (heightCoverage < 0.88) {
            score -= 1800;
        }

        if (radialInset > 0) {
            score -= radialInset * 2500;
        }

        if (horizontalOffset > 0) {
            score -= horizontalOffset * 1800;
        }

        if (!name) {
            score += 50;
        }

        return score;
    }

    function findConfiguredPrintableMesh(root) {
        if (!root) {
            return null;
        }

        const printableMeshName = getConfiguredPrintableMeshName();
        if (!printableMeshName) {
            return null;
        }

        let matchedMesh = null;

        root.traverse((child) => {
            if (!child.isMesh || matchedMesh) {
                return;
            }

            const meshName = getMeshName(child);
            if (!meshName || isExcludedMeshName(meshName)) {
                return;
            }

            if (meshName === printableMeshName) {
                matchedMesh = child;
            }
        });

        if (matchedMesh) {
            log('Mesh imprimable configuré utilisé :', matchedMesh.name || '(sans nom)');
        } else {
            warn('Mesh imprimable configuré introuvable :', printableMeshName);
        }

        return matchedMesh;
    }

    function findBestPrintableMesh(root) {
        if (!root) {
            return null;
        }

        const configuredMesh = findConfiguredPrintableMesh(root);
        if (configuredMesh) {
            return configuredMesh;
        }

        const meshes = [];
        const rootMetrics = getRootPrintMetrics(root);

        root.traverse((child) => {
            if (child.isMesh) {
                meshes.push(child);
            }
        });

        if (!meshes.length) {
            return null;
        }

        meshes.forEach((mesh) => {
            log('Mesh détecté :', mesh.name || '(sans nom)', mesh);
        });

        let bestMesh = null;
        let bestScore = -999999;

        meshes.forEach((mesh) => {
            const score = scoreMeshForPrint(mesh, rootMetrics);
            if (score > bestScore) {
                bestScore = score;
                bestMesh = mesh;
            }
        });

        log('Mesh choisi pour impression :', bestMesh ? (bestMesh.name || '(sans nom)') : 'aucun', 'score =', bestScore);

        return bestMesh;
    }

    function applyTextureToCurrentModel(texture) {
        if (!currentModel || !texture) {
            return;
        }

        const bestMesh = findBestPrintableMesh(currentModel);

        if (!bestMesh) {
            warn('Aucun mesh valable trouvé pour la texture');
            return;
        }

        let appliedCount = 0;

        currentModel.traverse((child) => {
            if (!child.isMesh || !child.material) {
                return;
            }

            if (child === bestMesh) {
                applyTextureToMaterial(child.material, texture);
                appliedCount++;
            } else {
                if (Array.isArray(child.material)) {
                    child.material.forEach((mat) => {
                        if (mat && mat.map) {
                            mat.map = null;
                            mat.needsUpdate = true;
                        }
                    });
                } else if (child.material.map) {
                    child.material.map = null;
                    child.material.needsUpdate = true;
                }
            }
        });

        setPlaneVisible(false);
        log('Texture appliquée sur', appliedCount, 'mesh(s)');
    }

    function ensureMeshMaterial(child, fallbackMaterial) {
        if (!child.isMesh) {
            return;
        }

        if (!child.material) {
            child.material = fallbackMaterial.clone();
            return;
        }

        if (Array.isArray(child.material)) {
            child.material = child.material.map((mat) => {
                return mat && typeof mat.clone === 'function' ? mat.clone() : fallbackMaterial.clone();
            });
        } else {
            child.material = child.material.clone ? child.material.clone() : fallbackMaterial.clone();
        }
    }

    function orientModelForMug(root) {
        if (!root) {
            return;
        }

        const configuredRotation = getConfiguredDefaultRotation();
        const hasConfiguredRotation = configuredRotation.x !== 0 || configuredRotation.y !== 0 || configuredRotation.z !== 0;

        root.rotation.x = configuredRotation.x;
        root.rotation.y = configuredRotation.y;
        root.rotation.z = configuredRotation.z;

        if (hasConfiguredRotation) {
            return;
        }

        const box = new THREE.Box3().setFromObject(root);
        const size = new THREE.Vector3();
        box.getSize(size);

        if (size.z > size.y * 1.15) {
            root.rotateX(-Math.PI / 2);
        }
    }

    function attachLoadedModel(object, material) {
        if (!object || !scene) {
            return;
        }

        removeCurrentModel();

        if (object.isBufferGeometry) {
            currentModel = new THREE.Mesh(object, material);
        } else {
            object.traverse((child) => {
                if (child.isMesh) {
                    ensureMeshMaterial(child, material);
                    child.castShadow = false;
                    child.receiveShadow = false;
                }
            });

            currentModel = object;
        }

        orientModelForMug(currentModel);

        let box = new THREE.Box3().setFromObject(currentModel);
        const size = new THREE.Vector3();
        const center = new THREE.Vector3();

        box.getSize(size);
        box.getCenter(center);

        const maxDim = Math.max(size.x, size.y, size.z);
        const scaleFactor = maxDim > 0 ? (1 / maxDim) : 1;

        currentModelBaseScale = scaleFactor;
        currentModel.scale.set(scaleFactor, scaleFactor, scaleFactor);

        box = new THREE.Box3().setFromObject(currentModel);
        box.getCenter(center);
        currentModel.position.sub(center);

        scene.add(currentModel);

        if (currentTexture) {
            applyTextureToCurrentModel(currentTexture);
        } else {
            setPlaneVisible(false);
        }

        frameModel(currentModel);
        log('Modèle attaché à la scène');
    }

    function loadBackgroundImage(url) {
        if (!url || !scene) {
            return;
        }

        removeBackgroundMesh();

        const loader = new THREE.TextureLoader();

        loader.load(
            url,
            (texture) => {
                texture.colorSpace = THREE.SRGBColorSpace;

                const geometry = new THREE.PlaneGeometry(12, 8);
                const material = new THREE.MeshBasicMaterial({
                    map: texture,
                    transparent: true,
                    depthWrite: false
                });

                backgroundMesh = new THREE.Mesh(geometry, material);
                backgroundMesh.position.set(0, 0, -4);
                scene.add(backgroundMesh);
            },
            undefined,
            (err) => {
                warn('Erreur chargement background 3D :', err);
            }
        );
    }

    function loadGLBFromUrl(url, onDone) {
        if (!url) {
            warn('URL GLB vide');
            if (typeof onDone === 'function') {
                onDone(false);
            }
            return;
        }

        log('Chargement GLB :', url);

        const loader = new GLTFLoader();

        loader.load(
            url,
            (gltf) => {
                const object = gltf.scene || null;

                if (!object) {
                    warn('GLB chargé mais gltf.scene vide');
                    if (typeof onDone === 'function') {
                        onDone(false);
                    }
                    return;
                }

                attachLoadedModel(
                    object,
                    new THREE.MeshStandardMaterial({ color: 0xffffff })
                );

                log('GLB chargé avec succès');
                if (typeof onDone === 'function') {
                    onDone(true);
                }
            },
            undefined,
            (err) => {
                warn('Erreur chargement GLB :', err);
                if (typeof onDone === 'function') {
                    onDone(false);
                }
            }
        );
    }

    function maybeLoadProductModel() {
        if (productModelLoaded) {
            return;
        }

        const model3d = getModelParams();
        log('Paramètres model3d :', model3d);

        if (!model3d || !model3d.url) {
            warn('Aucune URL modèle 3D trouvée');
            return;
        }

        productModelLoaded = true;

        if (model3d.background_url) {
            loadBackgroundImage(model3d.background_url);
        }

        if ((model3d.format || '').toLowerCase() === 'glb') {
            loadGLBFromUrl(model3d.url, (loaded) => {
                if (!loaded) {
                    setPlaneVisible(true);
                    if (currentTexture) {
                        fitPlaneToTexture(currentTexture);
                    }
                }
            });
        } else {
            warn('Format modèle non géré automatiquement :', model3d.format);
            setPlaneVisible(true);
        }
    }

    function init() {
        if (initDone) {
            maybeLoadProductModel();
            return true;
        }

        const canvasEl = getCanvasEl();

        if (!canvasEl) {
            warn('Canvas 3D introuvable');
            return false;
        }

        renderer = new THREE.WebGLRenderer({
            canvas: canvasEl,
            alpha: true,
            antialias: true
        });

        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.setClearColor(0x000000, 0);

        scene = new THREE.Scene();

        const size = getSafeRenderSize();

        camera = new THREE.PerspectiveCamera(45, size.width / size.height, 0.1, 1000);
        camera.position.set(0, 0, 3);

        controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.08;
        controls.enablePan = false;
        controls.enableZoom = true;
        controls.minPolarAngle = 0.15;
        controls.maxPolarAngle = Math.PI - 0.15;
        controls.target.set(0, 0, 0);

        const dirLight = new THREE.DirectionalLight(0xffffff, 1.15);
        dirLight.position.set(2, 2, 3);
        scene.add(dirLight);

        const dirLight2 = new THREE.DirectionalLight(0xffffff, 0.7);
        dirLight2.position.set(-2, -1, 2);
        scene.add(dirLight2);

        const ambientLight = new THREE.AmbientLight(0xffffff, 1.0);
        scene.add(ambientLight);

        createPreviewPlane();
        resizeRenderer();
        frameScene();
        startAnimation();

        initDone = true;
        log('Scène 3D initialisée');

        maybeLoadProductModel();

        return true;
    }

    window.osds3dUpdate3DPreview = function (dataURL) {
        const ok = init();
        if (!ok) {
            return;
        }

        resizeRenderer();
        updateTexture(dataURL);

        setTimeout(() => {
            resizeRenderer();
        }, 120);
    };

    window.osds3dLoad3DModel = function (file, color) {
        if (!file) {
            return;
        }

        const ok = init();
        if (!ok) {
            return;
        }

        const ext = file.name.split('.').pop().toLowerCase();
        const url = URL.createObjectURL(file);
        const matColor = color || '#cccccc';

        const material = new THREE.MeshPhongMaterial({
            color: new THREE.Color(matColor)
        });

        function finalize() {
            URL.revokeObjectURL(url);
            resizeRenderer();
        }

        if (ext === 'stl') {
            const stlLoader = new STLLoader();

            stlLoader.load(
                url,
                (geometry) => {
                    geometry.computeVertexNormals();
                    attachLoadedModel(geometry, material);
                    finalize();
                },
                undefined,
                (err) => {
                    warn('Erreur chargement STL :', err);
                    finalize();
                }
            );
        } else if (ext === 'obj') {
            const objLoader = new OBJLoader();

            objLoader.load(
                url,
                (object) => {
                    attachLoadedModel(object, material);
                    finalize();
                },
                undefined,
                (err) => {
                    warn('Erreur chargement OBJ :', err);
                    finalize();
                }
            );
        } else if (ext === 'glb') {
            const gltfLoader = new GLTFLoader();

            gltfLoader.load(
                url,
                (gltf) => {
                    const object = gltf.scene || null;
                    if (object) {
                        attachLoadedModel(object, material);
                    }
                    finalize();
                },
                undefined,
                (err) => {
                    warn('Erreur chargement GLB :', err);
                    finalize();
                }
            );
        } else {
            warn('Format non supporté :', ext);
            URL.revokeObjectURL(url);
        }
    };

    window.osds3dUpdateModelColor = function (color) {
        if (!currentModel) {
            return;
        }

        currentModel.traverse((child) => {
            if (!child.isMesh || !child.material) {
                return;
            }

            if (Array.isArray(child.material)) {
                child.material.forEach((mat) => {
                    if (mat.color) {
                        mat.color.set(color);
                    }
                });
            } else if (child.material.color) {
                child.material.color.set(color);
            }
        });
    };

    window.osds3dUpdateModelScale = function (scale) {
        if (!currentModel) {
            return;
        }

        const s = parseFloat(scale);
        if (!s || s <= 0) {
            return;
        }

        const finalScale = currentModelBaseScale * s;
        currentModel.scale.set(finalScale, finalScale, finalScale);
        frameModel(currentModel);
    };

    window.osds3dRemoveModel = function () {
        removeCurrentModel();
        setPlaneVisible(true);

        if (currentTexture) {
            fitPlaneToTexture(currentTexture);
        }

        frameScene();
    };

    function handleResize() {
        if (!renderer || !camera) {
            return;
        }

        resizeRenderer();
    }

    window.osds3dRefresh3DLayout = function () {
        if (!renderer) {
            return;
        }

        requestAnimationFrame(() => {
            handleResize();
        });

        setTimeout(() => {
            handleResize();
        }, 120);
    };

    window.addEventListener('resize', handleResize);

    document.addEventListener('click', (e) => {
        const target = e.target;
        if (!target || !target.id) {
            return;
        }

        if (target.id === 'osds3d-show-preview3d' || target.id === 'osds3d-hide-preview3d') {
            setTimeout(() => {
                if (renderer && isPreviewVisible()) {
                    handleResize();
                }
            }, 120);
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        const model3d = getModelParams();

        if (model3d && model3d.enabled && model3d.url) {
            setTimeout(() => {
                init();

                if (lastDesignDataUrl) {
                    updateTexture(lastDesignDataUrl);
                }
            }, 50);
        }
    });
})();
