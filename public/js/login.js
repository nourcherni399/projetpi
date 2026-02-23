(() => {
    const emailInput = document.getElementById('email');
    const csrfInput = document.getElementById('face_recognition_csrf');
    const startBtn = document.getElementById('face-start-camera');
    const verifyBtn = document.getElementById('face-verify-btn');
    const video = document.getElementById('face-login-video');
    const canvas = document.getElementById('face-login-canvas');
    const statusEl = document.getElementById('face-login-status');

    if (!emailInput || !csrfInput || !startBtn || !verifyBtn || !video || !canvas || !statusEl) {
        return;
    }

    const MODEL_URL = '/models';
    let modelsLoaded = false;
    let stream = null;
    const DETECTOR_OPTIONS = { inputSize: 416, scoreThreshold: 0.35 };

    const setStatus = (message, isError = false) => {
        statusEl.textContent = message;
        statusEl.classList.toggle('text-red-600', isError);
        statusEl.classList.toggle('text-green-600', !isError && message.toLowerCase().includes('validé'));
        statusEl.classList.toggle('text-[#6B7280]', !isError && !message.toLowerCase().includes('validé'));
    };

    const ensureModels = async () => {
        if (modelsLoaded) return;
        if (!window.faceapi) {
            throw new Error('face-api.js indisponible');
        }
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
        modelsLoaded = true;
    };

    const startCamera = async () => {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Webcam non supportée');
        }
        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'user',
                width: { ideal: 640 },
                height: { ideal: 480 },
            },
            audio: false,
        });
        video.srcObject = stream;
        await waitForVideoReady();
    };

    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    const waitForVideoReady = async () => {
        if (video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0) {
            return;
        }

        await new Promise((resolve) => {
            const onReady = () => {
                video.removeEventListener('loadedmetadata', onReady);
                video.removeEventListener('canplay', onReady);
                resolve();
            };
            video.addEventListener('loadedmetadata', onReady, { once: true });
            video.addEventListener('canplay', onReady, { once: true });
        });

        if (video.paused) {
            await video.play().catch(() => {});
        }
    };

    const extractDescriptorFromVideo = async () => {
        await waitForVideoReady();

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            throw new Error('Canvas indisponible');
        }

        let detection = null;
        for (let attempt = 0; attempt < 5; attempt += 1) {
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            detection = await faceapi
                .detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions(DETECTOR_OPTIONS))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (detection && detection.descriptor) {
                return Array.from(detection.descriptor);
            }

            await sleep(220);
        }

        throw new Error('Aucun visage détecté en live. Rapprochez-vous, regardez la caméra et augmentez la lumière.');
    };

    const extractDescriptorBatch = async () => {
        const batch = [];
        for (let i = 0; i < 3; i += 1) {
            const descriptor = await extractDescriptorFromVideo();
            batch.push(descriptor);
            await sleep(180);
        }
        return batch;
    };

    startBtn.addEventListener('click', async () => {
        try {
            await ensureModels();
            await startCamera();
            await sleep(500);
            setStatus('Webcam active. Positionnez votre visage.');
        } catch (e) {
            setStatus('Erreur webcam/modèles: ' + (e.message || 'inconnue'), true);
        }
    });

    verifyBtn.addEventListener('click', async () => {
        const email = (emailInput.value || '').trim();
        if (!email) {
            setStatus('Saisissez votre e-mail avant la vérification faciale.', true);
            return;
        }

        if (!stream) {
            setStatus('Activez d abord la webcam.', true);
            return;
        }

        try {
            await ensureModels();
            setStatus('Analyse du visage en cours (3 captures)...');
            const descriptors = await extractDescriptorBatch();

            const response = await fetch('/face-recognition', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    _csrf_token: csrfInput.value,
                    email: email,
                    descriptors: descriptors,
                }),
            });

            const data = await response.json();
            if (!response.ok || data.ok !== true) {
                setStatus(data.message || 'Visage non validé.', true);
                return;
            }

            setStatus('Visage validé. Redirection...');
            const redirectUrl = typeof data.redirectUrl === 'string' && data.redirectUrl !== '' ? data.redirectUrl : '/';
            window.location.href = redirectUrl;
        } catch (e) {
            setStatus('Erreur vérification faciale: ' + (e.message || 'inconnue'), true);
        }
    });
})();

