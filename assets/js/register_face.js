const MODEL_URLS = [
  'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model',
  'https://cdn.jsdelivr.net/gh/vladmandic/face-api/model'
];

const video = document.getElementById('registerVideo');
const openBtn = document.getElementById('openRegisterCamera');
const captureBtn = document.getElementById('captureRegisterFace');
const statusBox = document.getElementById('registerFaceStatus');
const hint = document.getElementById('faceRegisterHint');
const badge = document.getElementById('faceReadyBadge');
const descriptorInput = document.getElementById('faceDescriptor');
const agreementCheck = document.getElementById('faceAgreementCheck');
const agreementHidden = document.getElementById('faceAgreement');
const createBtn = document.getElementById('createAccountBtn');
const form = document.getElementById('registerForm');

let stream = null;
let modelsReady = false;
let capturing = false;
let descriptorReady = false;
let faceApiLoading = false;

function setStatus(type, message) {
  statusBox.className = 'result-box ' + type;
  statusBox.textContent = message;
}

function loadFaceApiScript() {
  if (window.faceapi) return Promise.resolve(window.faceapi);
  if (faceApiLoading) {
    return new Promise((resolve, reject) => {
      const timer = setInterval(() => {
        if (window.faceapi) { clearInterval(timer); resolve(window.faceapi); }
      }, 100);
      setTimeout(() => { clearInterval(timer); reject(new Error('Waktu memuat modul wajah habis.')); }, 20000);
    });
  }
  faceApiLoading = true;
  return new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/dist/face-api.js';
    script.async = true;
    script.onload = () => window.faceapi ? resolve(window.faceapi) : reject(new Error('Modul wajah tidak tersedia.'));
    script.onerror = () => reject(new Error('Modul wajah gagal dimuat. Pastikan koneksi internet aktif.'));
    document.head.appendChild(script);
  });
}

async function loadModels() {
  setStatus('info', 'Modul wajah akan dimuat saat kamera dibuka.');
  hint.textContent = 'Isi data akun terlebih dahulu. Kamera dapat dibuka saat siap.';
}

async function ensureModelsReady() {
  if (modelsReady) return true;
  try {
    setStatus('info', 'Memuat modul dan model pengenalan wajah...');
    const faceapi = await loadFaceApiScript();
    for (const base of MODEL_URLS) {
      try {
        await Promise.all([
          faceapi.nets.tinyFaceDetector.loadFromUri(base),
          faceapi.nets.faceLandmark68TinyNet.loadFromUri(base),
          faceapi.nets.faceRecognitionNet.loadFromUri(base)
        ]);
        modelsReady = true;
        hint.textContent = 'Model wajah siap. Anda dapat membuka kamera.';
        setStatus('success', 'Model wajah siap. Buka kamera lalu posisikan wajah di dalam lingkaran.');
        return true;
      } catch (err) {
        console.warn('Model gagal dimuat dari', base, err);
      }
    }
    throw new Error('Model wajah gagal dimuat.');
  } catch (err) {
    setStatus('error', err.message + ' Pastikan koneksi internet aktif lalu coba lagi.');
    hint.textContent = 'Model belum siap.';
    return false;
  }
}

async function openCamera() {
  if (!(await ensureModelsReady())) return;
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    setStatus('error', 'Browser ini tidak mendukung kamera atau halaman belum menggunakan HTTPS/localhost.');
    return;
  }
  try {
    if (stream) stream.getTracks().forEach(track => track.stop());
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'user' }, width: { ideal: 720, min: 320 }, height: { ideal: 720, min: 240 } },
      audio: false
    });
    video.srcObject = stream;
    await video.play();
    openBtn.disabled = true;
    captureBtn.disabled = false;
    hint.textContent = 'Kamera aktif. Pastikan hanya satu wajah yang terlihat.';
    setStatus('info', 'Kamera aktif. Cek posisi wajah sebelum mendaftar.');
  } catch (err) {
    setStatus('error', 'Kamera tidak dapat digunakan: ' + err.message + '. Izinkan kamera pada browser.');
  }
}

async function captureFace() {
  if (!modelsReady || !stream || capturing || !window.faceapi) return;
  capturing = true;
  captureBtn.disabled = true;
  setStatus('info', 'Menganalisis wajah...');

  try {
    const faceapi = window.faceapi;
    const detections = await faceapi.detectAllFaces(
      video,
      new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.45 })
    ).withFaceLandmarks(true).withFaceDescriptors();

    if (detections.length === 0) {
      throw new Error('Wajah belum terdeteksi. Pastikan wajah terlihat penuh, terang, dan tidak memakai masker.');
    }
    if (detections.length > 1) {
      throw new Error('Terdapat lebih dari satu wajah. Pastikan hanya wajah Anda yang terlihat di kamera.');
    }

    const d = detections[0];
    const w = d.detection.box.width;
    const h = d.detection.box.height;
    const centerX = d.detection.box.x + w / 2;
    const videoCenterX = video.videoWidth / 2;
    const centerOffset = Math.abs(centerX - videoCenterX) / Math.max(video.videoWidth, 1);

    if (w < 120 || h < 120) {
      throw new Error('Wajah terlalu kecil. Dekatkan wajah ke kamera sekitar 30–60 cm.');
    }
    if (centerOffset > 0.22) {
      throw new Error('Posisikan wajah lebih ke tengah kamera.');
    }

    const descriptor = Array.from(d.descriptor);
    if (descriptor.length !== 128) {
      throw new Error('Template wajah tidak valid. Silakan coba lagi.');
    }

    descriptorInput.value = JSON.stringify(descriptor);
    descriptorReady = true;
    badge.textContent = 'Wajah siap';
    badge.classList.remove('neutral');
    badge.classList.add('success');
    setStatus('success', 'Wajah berhasil terbaca. Data wajah akan disimpan saat tombol "Buat Akun & Simpan" ditekan.');
    hint.textContent = 'Wajah berhasil direkam. Anda dapat membuat akun sekarang.';
    updateSubmitState();
  } catch (err) {
    descriptorReady = false;
    descriptorInput.value = '';
    setStatus('error', err.message);
    updateSubmitState();
  } finally {
    capturing = false;
    captureBtn.disabled = false;
  }
}

function updateSubmitState() {
  const agreed = agreementCheck.checked;
  agreementHidden.value = agreed ? '1' : '0';
  createBtn.disabled = !(descriptorReady && agreed);
}

agreementCheck.addEventListener('change', updateSubmitState);
openBtn.addEventListener('click', openCamera);
captureBtn.addEventListener('click', captureFace);

form.addEventListener('submit', (e) => {
  if (!descriptorReady || !agreementCheck.checked) {
    e.preventDefault();
    setStatus('error', 'Lengkapi pendaftaran wajah dan centang persetujuan biometrik terlebih dahulu.');
    return;
  }
  createBtn.disabled = true;
  createBtn.textContent = 'Menyimpan akun dan wajah...';
});

window.addEventListener('beforeunload', () => {
  if (stream) stream.getTracks().forEach(track => track.stop());
});

loadModels();
