const MODEL_URLS = [
  'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model',
  'https://cdn.jsdelivr.net/gh/vladmandic/face-api/model'
];
const state = { lat:null, lng:null, accuracy:null, descriptor:null, locationOK:false, faceOK:false, hasSignature:false, stream:null, models:false, detecting:false };
const faceStatus = document.getElementById('faceStatus');
const locationStatus = document.getElementById('locationStatus');
const result = document.getElementById('result');
const startCamera = document.getElementById('startCamera');
const submit = document.getElementById('submitAttendance');
const video = document.getElementById('video');

// Signature Pad Elements
const sigCanvas = document.getElementById('signatureCanvas');
const sigCtx = sigCanvas ? sigCanvas.getContext('2d') : null;
const sigWatermark = document.getElementById('signatureWatermark');
const sigStatusText = document.getElementById('signatureStatusText');
const clearSigBtn = document.getElementById('clearSignatureBtn');
const stepSignature = document.getElementById('step-signature');

let isDrawing = false;
let lastX = 0;
let lastY = 0;

function checkReadyToSubmit(){
  if (state.locationOK && state.faceOK && state.hasSignature) {
    submit.disabled = false;
    setResult('success', 'Lokasi, Wajah & Tanda tangan lengkap! Silakan tekan Konfirmasi Presensi.');
  } else if (!state.hasSignature) {
    submit.disabled = true;
    if (state.faceOK) setResult('info', 'Wajah terverifikasi. Silakan goreskan tanda tangan perorangan Anda pada kotak.');
  }
}

if (sigCanvas && sigCtx) {
  sigCtx.strokeStyle = '#0f172a';
  sigCtx.lineWidth = 2.8;
  sigCtx.lineCap = 'round';
  sigCtx.lineJoin = 'round';

  function getCanvasPos(e) {
    const rect = sigCanvas.getBoundingClientRect();
    const scaleX = sigCanvas.width / rect.width;
    const scaleY = sigCanvas.height / rect.height;
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return {
      x: (clientX - rect.left) * scaleX,
      y: (clientY - rect.top) * scaleY
    };
  }

  function startDraw(e) {
    isDrawing = true;
    const pos = getCanvasPos(e);
    lastX = pos.x;
    lastY = pos.y;
    sigCtx.beginPath();
    sigCtx.moveTo(lastX, lastY);
    if (sigWatermark) sigWatermark.style.display = 'none';
  }

  function draw(e) {
    if (!isDrawing) return;
    if (e.cancelable) e.preventDefault();
    const pos = getCanvasPos(e);
    sigCtx.lineTo(pos.x, pos.y);
    sigCtx.stroke();
    lastX = pos.x;
    lastY = pos.y;
    if (!state.hasSignature) {
      state.hasSignature = true;
      if (sigStatusText) {
        sigStatusText.style.color = '#15803d';
        sigStatusText.textContent = '✓ Tanda tangan tergores';
      }
      if (stepSignature) stepSignature.classList.remove('locked');
      checkReadyToSubmit();
    }
  }

  function stopDraw() {
    isDrawing = false;
  }

  sigCanvas.addEventListener('mousedown', startDraw);
  sigCanvas.addEventListener('mousemove', draw);
  window.addEventListener('mouseup', stopDraw);

  sigCanvas.addEventListener('touchstart', startDraw, { passive: false });
  sigCanvas.addEventListener('touchmove', draw, { passive: false });
  window.addEventListener('touchend', stopDraw);
  window.addEventListener('touchcancel', stopDraw);

  if (clearSigBtn) {
    clearSigBtn.onclick = () => {
      sigCtx.clearRect(0, 0, sigCanvas.width, sigCanvas.height);
      state.hasSignature = false;
      if (sigWatermark) sigWatermark.style.display = 'flex';
      if (sigStatusText) {
        sigStatusText.style.color = '#e11d48';
        sigStatusText.textContent = '● Belum ditandatangani';
      }
      if (stepSignature) stepSignature.classList.add('locked');
      checkReadyToSubmit();
    };
  }
}

function setResult(type,msg){ result.className='result-box '+type; result.textContent=msg; }
function dist(a,b,c,d){const R=6371000,p1=a*Math.PI/180,p2=c*Math.PI/180,dp=(c-a)*Math.PI/180,dl=(d-b)*Math.PI/180;const x=Math.sin(dp/2)**2+Math.cos(p1)*Math.cos(p2)*Math.sin(dl/2)**2;return 2*R*Math.asin(Math.min(1,Math.sqrt(x)));}

async function loadModels(){
  for (const base of MODEL_URLS) {
    try {
      setResult('info','Memuat model wajah...');
      await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(base),
        faceapi.nets.faceLandmark68TinyNet.loadFromUri(base),
        faceapi.nets.faceRecognitionNet.loadFromUri(base)
      ]);
      state.models=true;
      return;
    } catch(e) {
      console.warn('Model gagal dari', base, e);
    }
  }
  setResult('error','Model wajah gagal dimuat. Pastikan internet aktif lalu muat ulang halaman.');
  faceStatus.textContent='Model wajah belum siap.';
}

function checkLocation(pos){
  state.lat=pos.coords.latitude; state.lng=pos.coords.longitude; state.accuracy=pos.coords.accuracy;
  const d=dist(state.lat,state.lng, Number(window.OFFICE_LAT), Number(window.OFFICE_LNG));
  if(d<=Number(window.OFFICE_RADIUS)){
    state.locationOK=true;
    locationStatus.textContent=`Lokasi valid • jarak ±${Math.round(d)} m • akurasi ±${Math.round(state.accuracy)} m`;
    document.getElementById('step-face').classList.remove('locked');
    faceStatus.textContent='Lokasi valid. Silakan buka kamera.';
    startCamera.disabled=false;
    if(state.accuracy>100){
      setResult('info',`Anda berada di area kantor. Akurasi GPS ±${Math.round(state.accuracy)} m; usahakan berada dekat jendela/area terbuka bila hasil kurang stabil.`);
    }else{
      setResult('success','Lokasi memenuhi syarat. Lanjutkan verifikasi wajah.');
    }
  }else{
    state.locationOK=false;
    locationStatus.textContent=`Lokasi ditolak • jarak ±${Math.round(d)} m • akurasi ±${Math.round(state.accuracy)} m`;
    startCamera.disabled=true;
    document.getElementById('step-face').classList.add('locked');
    faceStatus.textContent='Menunggu lokasi valid.';
    setResult('error',`Absensi ditolak karena Anda terdeteksi sekitar ${Math.round(d)} meter dari titik kantor. Periksa kembali koordinat kantor di Admin > Pengaturan Kantor.`);
  }
}

function requestLocation(){
  if(!navigator.geolocation){ setResult('error','Browser tidak mendukung GPS.'); return; }
  locationStatus.textContent='Membaca lokasi perangkat...';
  navigator.geolocation.getCurrentPosition(checkLocation, err=>{
    locationStatus.textContent='Lokasi gagal dibaca: '+err.message;
    setResult('error','Izinkan akses lokasi lalu muat ulang halaman.');
  }, {enableHighAccuracy:true,timeout:20000,maximumAge:0});
}

async function openCamera(){
  if(!state.locationOK || !state.models){ setResult('error','Lokasi/model belum siap.'); return; }
  try {
    state.stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'user'},width:{ideal:720,min:320},height:{ideal:720,min:240}},audio:false});
    video.srcObject=state.stream;
    await video.play();
    startCamera.disabled=true;
    faceStatus.textContent='Kamera aktif. Posisikan wajah di dalam lingkaran.';
    setResult('info','Kamera aktif. Mencari wajah...');
    detectLoop();
  } catch(e){ setResult('error','Kamera tidak dapat digunakan: '+e.message); }
}

async function detectLoop(){
  if(!state.stream || state.detecting) return;
  state.detecting=true;
  try {
    const det=await faceapi.detectSingleFace(video,new faceapi.TinyFaceDetectorOptions({inputSize:416,scoreThreshold:0.35}))
      .withFaceLandmarks(true).withFaceDescriptor();
    if(det){
      const w=det.detection.box.width;
      const h=det.detection.box.height;
      if(w>=80 && h>=80){
        state.descriptor=Array.from(det.descriptor);
        state.faceOK=true;
        faceStatus.textContent=`Wajah terdeteksi • ukuran ${Math.round(w)}×${Math.round(h)} px`;
        checkReadyToSubmit();
      }else{
        state.faceOK=false; submit.disabled=true;
        faceStatus.textContent='Wajah terlalu kecil. Dekatkan wajah ke kamera.';
      }
    } else {
      state.faceOK=false; submit.disabled=true;
      faceStatus.textContent='Wajah belum terdeteksi. Pastikan wajah terang dan terlihat penuh.';
    }
  } catch(e){ console.error(e); }
  state.detecting=false;
  if(state.stream) setTimeout(detectLoop,250);
}

startCamera.onclick=openCamera;
requestLocation();
loadModels();

submit.onclick=async()=>{
  if(!state.locationOK||!state.faceOK||!state.descriptor) {
    setResult('error', 'Pastikan verifikasi lokasi dan wajah telah berhasil.');
    return;
  }
  if (!state.hasSignature) {
    setResult('error', 'Wajib membubuhkan tanda tangan perorangan Anda pada kotak tanda tangan di atas.');
    if (sigStatusText) sigStatusText.scrollIntoView({ behavior: 'smooth' });
    return;
  }
  submit.disabled=true; setResult('info','Menyimpan absensi & tanda tangan perorangan...');
  const fd=new FormData();
  fd.append('csrf',document.getElementById('csrf').value);
  fd.append('type',document.getElementById('attendanceType').value);
  fd.append('latitude',state.lat); fd.append('longitude',state.lng); fd.append('accuracy',state.accuracy);
  fd.append('descriptor',JSON.stringify(state.descriptor));
  if (sigCanvas) {
    fd.append('signature_image', sigCanvas.toDataURL('image/png'));
  }
  try {
    const r=await fetch('api/submit_attendance.php',{method:'POST',body:fd});
    const j=await r.json();
    if(!j.ok) throw new Error(j.message);
    setResult('success',j.message+' Jarak '+j.distance+' m.');
    if(state.stream)state.stream.getTracks().forEach(t=>t.stop());
    setTimeout(()=>location.href='dashboard.php',1200);
  } catch(e){ setResult('error',e.message); submit.disabled=false; }
};
