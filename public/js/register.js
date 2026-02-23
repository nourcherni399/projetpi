(() => {
    const photoInput = document.getElementById('face-photo-upload');
    const statusEl = document.getElementById('face-register-status');
    const descriptorInput = document.getElementById('inscription_dataFaceApi');

    if (!photoInput || !statusEl || !descriptorInput) {
        return;
    }

    const MODEL_URL = '/models';
    let modelsLoaded = false;

    const setStatus = (message, isError = false) => {
        statusEl.textContent = message;
        statusEl.classList.toggle('text-red-600', isError);
        statusEl.classList.toggle('text-[#6B7280]', !isError);
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

    const fileToImage = (file) => new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error('Image invalide'));
            img.src = reader.result;
        };
        reader.onerror = () => reject(new Error('Lecture image impossible'));
        reader.readAsDataURL(file);
    });

    photoInput.addEventListener('change', async () => {
        descriptorInput.value = '';
        const file = photoInput.files && photoInput.files[0] ? photoInput.files[0] : null;
        if (!file) {
            setStatus('Aucune image sélectionnée.');
            return;
        }

        try {
            setStatus('Chargement des modèles faciaux...');
            await ensureModels();
            const img = await fileToImage(file);
            const detection = await faceapi
                .detectSingleFace(img, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection || !detection.descriptor) {
                setStatus('Aucun visage détecté sur cette photo.', true);
                return;
            }

            descriptorInput.value = JSON.stringify(Array.from(detection.descriptor));
            setStatus('Descripteur facial prêt. Vous pouvez finaliser l’inscription.');
        } catch (e) {
            setStatus('Erreur biométrie: ' + (e.message || 'inconnue'), true);
        }
    });
})();

