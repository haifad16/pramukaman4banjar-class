const MODEL_URLS=['https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model','https://cdn.jsdelivr.net/gh/vladmandic/face-api/model'];
let selectedId=null, stream=null, ready=false;
const modal=document.getElementById('faceModal'); const video=document.getElementById('enrollVideo'); const status=document.getElementById('enrollStatus'); const btn=document.getElementById('captureFace');
async function boot(){
  for(const base of MODEL_URLS){
    try{
      await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(base),
        faceapi.nets.faceLandmark68TinyNet.loadFromUri(base),
        faceapi.nets.faceRecognitionNet.loadFromUri(base)
      ]);
      ready=true; status.textContent='Model siap. Arahkan wajah ke kamera.'; return;
    }catch(e){ console.warn('Model gagal dari',base,e); }
  }
  status.className='result-box error'; status.textContent='Model gagal dimuat. Pastikan internet aktif.';
}
boot();
document.querySelectorAll('.enroll').forEach(b=>b.onclick=async()=>{
  selectedId=b.dataset.id; document.getElementById('enrollName').textContent=b.dataset.name; modal.classList.add('show');
  try{
    stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'user'},width:{ideal:720},height:{ideal:720}},audio:false});
    video.srcObject=stream; await video.play();
  }catch(e){status.className='result-box error';status.textContent='Kamera gagal: '+e.message;}
});
btn.onclick=async()=>{
  if(!ready||!selectedId)return;
  btn.disabled=true; status.className='result-box info'; status.textContent='Menganalisis wajah...';
  try{
    const d=await faceapi.detectSingleFace(video,new faceapi.TinyFaceDetectorOptions({inputSize:416,scoreThreshold:.35})).withFaceLandmarks(true).withFaceDescriptor();
    if(!d){status.className='result-box error';status.textContent='Wajah belum terdeteksi. Pastikan wajah terang dan terlihat penuh.';btn.disabled=false;return;}
    if(d.detection.box.width<80||d.detection.box.height<80){status.className='result-box error';status.textContent='Wajah terlalu kecil. Dekatkan wajah ke kamera.';btn.disabled=false;return;}
    const csrfEl = document.querySelector('input[name=csrf]') || document.getElementById('csrf');
    const csrfVal = csrfEl ? csrfEl.value : '';
    const fd = new FormData(); 
    fd.append('csrf', csrfVal); 
    fd.append('id', selectedId); 
    fd.append('descriptor', JSON.stringify(Array.from(d.descriptor)));
    const apiUrl = window.SAVE_FACE_API_URL || (location.pathname.includes('/admin/') ? '../api/save_employee_face.php' : './api/save_employee_face.php');
    const r = await fetch(apiUrl, {method:'POST', body:fd}); 
    const j = await r.json(); 
    if(!j.ok) throw new Error(j.message);
    status.className='result-box success'; status.textContent=j.message; setTimeout(()=>location.reload(),800);
  }catch(e){status.className='result-box error';status.textContent=e.message;btn.disabled=false;}
};
function closeEnroll(){modal.classList.remove('show');if(stream)stream.getTracks().forEach(t=>t.stop());stream=null;}
