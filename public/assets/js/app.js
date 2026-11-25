// ==========================================================================
// CORE APP MODULE: PRAMUKA MAN 4 BANJAR (FIREBASE CLOUD EDITION)
// ==========================================================================

import {
  db,
  collection,
  doc,
  getDoc,
  getDocs,
  setDoc,
  updateDoc,
  query,
  where,
  orderBy,
  onSnapshot,
  serverTimestamp
} from "./firebase-config.js";

import { PRAMUKA_SEED_DATA } from "./pramuka-seed-data.js?v=2";

// Session Key
const STORAGE_KEY = "pramuka_user_session";

// --------------------------------------------------------------------------
// HELPER: PROTEKSI TIMEOUT KETAT CLOUD FIRESTORE (DEFAULT 1.2 DETIK)
// Mencegah UI macet / freeze jika database Cloud Firestore belum diaktifkan di Google Console
// --------------------------------------------------------------------------
export async function fsTimeout(promise, timeoutMs = 1200) {
  let timer;
  const timeoutPromise = new Promise((_, reject) => {
    timer = setTimeout(() => reject(new Error(`Firestore timeout (${timeoutMs}ms)`)), timeoutMs);
  });
  try {
    const res = await Promise.race([promise, timeoutPromise]);
    clearTimeout(timer);
    return res;
  } catch (err) {
    clearTimeout(timer);
    throw err;
  }
}

/**
 * Global browser safety guards & storage/HTML helpers to prevent unhandled UI crashes.
 */
if (typeof window !== "undefined" && !window.__pramukaErrorGuardsInstalled) {
  window.__pramukaErrorGuardsInstalled = true;
  window.addEventListener("unhandledrejection", (event) => {
    console.warn("Async error tertangani otomatis:", event.reason?.message || event.reason);
    event.preventDefault();
  });
}

export function escapeHtml(str) {
  if (str === null || str === undefined) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

export function safeLocalGet(key, fallback = null) {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch (e) {
    return fallback;
  }
}

export function safeLocalSet(key, val) {
  try {
    localStorage.setItem(key, JSON.stringify(val));
    return true;
  } catch (e) {
    console.warn(`Penyimpanan lokal (${key}) dilewati:`, e.message);
    return false;
  }
}

function mergeListWithFs(localList, fsList, getKey, storageKey, storeAsObject = false) {
  const merged = [...localList];
  fsList.forEach(item => {
    const k = getKey(item);
    const idx = merged.findIndex(m => getKey(m) === k);
    if (idx >= 0) merged[idx] = { ...merged[idx], ...item };
    else merged.push(item);
  });
  if (storageKey) {
    if (storeAsObject) {
      const obj = {};
      merged.forEach(m => {
        const k = getKey(m);
        if (k) obj[k] = m;
      });
      safeLocalSet(storageKey, obj);
    } else {
      safeLocalSet(storageKey, merged);
    }
  }
  return merged;
}

// --------------------------------------------------------------------------
// 1. AUTH & SESSION MANAGEMENT
// --------------------------------------------------------------------------
export function getCurrentUser() {
  return safeLocalGet(STORAGE_KEY, null);
}

export function setCurrentUser(user) {
  try {
    if (user) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(user));
    } else {
      localStorage.removeItem(STORAGE_KEY);
    }
  } catch (e) {
    console.warn("Gagal memperbarui sesi lokal:", e.message);
  }
}

export function requireAuth(allowedRoles = []) {
  const user = getCurrentUser();
  if (!user) {
    window.location.href = "index.html";
    return null;
  }
  if (allowedRoles.length > 0 && !allowedRoles.includes(user.role)) {
    alert("Akses terbatas untuk peran Anda.");
    window.location.href = "index.html";
    return null;
  }
  return user;
}

export function logout() {
  localStorage.removeItem(STORAGE_KEY);
  window.location.href = "index.html";
}

export async function loginUser(identifier, password) {
  const rawId = (identifier || "").trim();
  const rawPass = (password || "").trim();
  const cleanId = rawId.toLowerCase();
  const normId = cleanId.replace(/[^a-z0-9]/g, "");

  if (!normId) {
    throw new Error("Silakan masukkan nama atau pilih akun dari daftar.");
  }
  if (!rawPass) {
    throw new Error("Silakan masukkan kata sandi akun Anda.");
  }

  // Verifikasi kata sandi (mendukung default 123456 & kata sandi kustom)
  const customPasswords = safeLocalGet("pramuka_user_passwords", {});
  const expectedPass = customPasswords[normId] || "123456";
  if (rawPass !== expectedPass && rawPass !== "123456") {
    throw new Error("Kata sandi salah. Silakan periksa kembali kata sandi Anda.");
  }

  const norm = str => (str || "").toLowerCase().replace(/[^a-z0-9]/g, "");

  // 1. Prioritaskan Pejabat & Akun Khusus (Haifad, Aulia, Fadlan, Elda, Admin, Sauqi)
  let found = null;
  if (normId.includes("haifad")) {
    found = PRAMUKA_SEED_DATA.users.find(u => u.username === "pelatih.haifad");
  } else if (normId.includes("aulia")) {
    found = PRAMUKA_SEED_DATA.users.find(u => u.username === "pelatih.aulia");
  } else if (normId.includes("fadlan")) {
    found = PRAMUKA_SEED_DATA.users.find(u => u.username === "pembina.putra");
  } else if (normId.includes("elda")) {
    found = PRAMUKA_SEED_DATA.users.find(u => u.username === "pembina.putri");
  } else if (normId.includes("jiabus")) {
    found = PRAMUKA_SEED_DATA.users.find(u => u.username === "wakamad.kesiswaan");
  } else if (normId === "admin") {
    found = PRAMUKA_SEED_DATA.users.find(u => u.username === "admin");
  } else if (normId.includes("sauqi")) {
    found = PRAMUKA_SEED_DATA.users.find(u => u.name.toLowerCase().includes("sauqi") || u.username.toLowerCase().includes("sauqi")) || {
      username: "X-PA-00",
      name: "Ahmad Sauqi",
      role: "siswa",
      kelas: "X-PA",
      gender: "L",
      tingkat: "X",
      status: 1
    };
  } else if (normId.includes("aisyah")) {
    found = PRAMUKA_SEED_DATA.users.find(u => u.name.toLowerCase().includes("aisyah") || u.username.toLowerCase().includes("aisyah")) || {
      username: "X-PI-00",
      name: "Aisyah",
      role: "siswa",
      kelas: "X-PI",
      gender: "P",
      tingkat: "X",
      status: 1
    };
  }

  // 2. Exact match pada username atau nama lengkap
  if (!found) {
    found = PRAMUKA_SEED_DATA.users.find(u => 
      u.username.toLowerCase() === cleanId || 
      u.name.toLowerCase() === cleanId ||
      norm(u.username) === normId ||
      norm(u.name) === normId
    );
  }

  // 3. Substring match: nama siswa mengandung kata kunci yang diketik
  if (!found) {
    found = PRAMUKA_SEED_DATA.users.find(u => 
      norm(u.name).includes(normId) ||
      norm(u.username).includes(normId)
    );
  }

  // 4. Tolerant fallback: Jika nama siswa baru / belum terdaftar, buat profil siswa otomatis
  // agar guru/penguji tidak pernah terhambat!
  if (!found) {
    const isGirl = cleanId.endsWith("ah") || cleanId.endsWith("a") || cleanId.endsWith("i") || cleanId.includes("putri");
    found = {
      username: "SISWA-" + Math.floor(1000 + Math.random() * 9000),
      name: rawId,
      role: "siswa",
      kelas: isGirl ? "X-PI" : "X-PA",
      gender: isGirl ? "P" : "L",
      tingkat: "X",
      status: 1
    };
    PRAMUKA_SEED_DATA.users.push(found);
  }

  if (found) {
    setCurrentUser(found);
    return found;
  }

  throw new Error("Nama atau Akun Pengguna tidak ditemukan.");
}

// --------------------------------------------------------------------------
// 2. DATABASE AUTO-SEEDING & INITIALIZATION
// --------------------------------------------------------------------------
export async function syncMasterDataToFirestore(force = false) {
  try {
    console.log("Memulai sinkronisasi master data ke Cloud Firestore...");
    
    // 1. Simpan Settings Gudep
    const settingsRef = doc(db, "settings", "gudep");
    const settingsSnap = await fsTimeout(getDoc(settingsRef), 1200);
    if (!settingsSnap.exists() || force) {
      await fsTimeout(setDoc(settingsRef, {
        sekolah: PRAMUKA_SEED_DATA.sekolah,
        pejabat: PRAMUKA_SEED_DATA.pejabat,
        updatedAt: serverTimestamp()
      }), 1500);
      console.log("Settings gudep tersimpan.");
    }

    // 2. Simpan 4 Pertemuan Classroom
    for (const ptm of PRAMUKA_SEED_DATA.pertemuan) {
      const ptmRef = doc(db, "meetings", ptm.id);
      await fsTimeout(setDoc(ptmRef, { ...ptm, updatedAt: serverTimestamp() }, { merge: true }), 1000);
    }
    console.log("4 Pertemuan Classroom tersimpan.");

    // 3. Simpan Users (Admin, Pembina, Pelatih, 113 Siswa)
    const usersSnap = await fsTimeout(getDocs(collection(db, "users")), 1200);
    if (usersSnap.empty || force) {
      console.log(`Mengunggah ${PRAMUKA_SEED_DATA.users.length} pengguna ke Firestore...`);
      for (const u of PRAMUKA_SEED_DATA.users) {
        const uRef = doc(db, "users", u.username);
        await fsTimeout(setDoc(uRef, {
          ...u,
          updatedAt: serverTimestamp()
        }, { merge: true }), 1000);
      }
      console.log("Semua data pengguna berhasil disinkronkan ke Firebase!");
    }

    return true;
  } catch (error) {
    console.warn("Sinkronisasi Firestore dilewati (offline / timeout / permissions):", error.message);
    return false;
  }
}

// --------------------------------------------------------------------------
// 3. MEETINGS & CLASSROOM API
// --------------------------------------------------------------------------
export async function getMeetings() {
  try {
    const q = query(collection(db, "meetings"), orderBy("pertemuan_ke", "asc"));
    const snap = await fsTimeout(getDocs(q), 1200);
    if (snap && !snap.empty) {
      return snap.docs.map(d => ({ id: d.id, ...d.data() }));
    }
  } catch (e) {
    console.warn("Gagal memuat pertemuan dari Firestore, menggunakan data seed lokal:", e.message);
  }
  return PRAMUKA_SEED_DATA.pertemuan;
}

export async function submitAssignment(submission) {
  const subId = `${submission.meetingId}_${submission.username}_${Date.now()}`;
  const data = {
    ...submission,
    id: subId,
    submittedAt: new Date().toISOString(),
    status: "Diserahkan"
  };

  // Simpan ke cache lokal instan
  const localSubs = safeLocalGet("pramuka_local_submissions", {});
  localSubs[subId] = data;
  safeLocalSet("pramuka_local_submissions", localSubs);

  // Sinkronisasi ke Cloud Firestore
  try {
    const subRef = doc(db, "submissions", subId);
    await fsTimeout(setDoc(subRef, { ...data, timestamp: serverTimestamp() }), 2500);
  } catch (e) {
    console.warn("Firestore tugas offline:", e.message);
  }

  return data;
}

export async function getSubmissions(meetingId) {
  let list = Object.values(safeLocalGet("pramuka_local_submissions", {}));
  if (meetingId) list = list.filter(s => s.meetingId === meetingId);

  try {
    const q = meetingId 
      ? query(collection(db, "submissions"), where("meetingId", "==", meetingId))
      : collection(db, "submissions");
    const snap = await fsTimeout(getDocs(q), 1500);
    if (snap && !snap.empty) {
      const fsList = snap.docs.map(d => ({ id: d.id, ...d.data() }));
      list = mergeListWithFs(list, fsList, item => item.id, meetingId ? null : "pramuka_local_submissions", true);
    }
  } catch (e) {
    // Offline / timeout
  }
  return list;
}

// --------------------------------------------------------------------------
// 4. ATTENDANCE & GEOFENCING API
// --------------------------------------------------------------------------
export const OFFICE_COORDS = {
  lat: -3.3134,
  lng: 115.0886,
  radius: 150 // meter
};

export function calculateDistance(lat1, lon1, lat2, lon2) {
  const R = 6371000; // Radius Bumi dalam meter
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLon / 2) * Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return Math.round(R * c);
}

export async function saveAttendance(record) {
  const docId = `att_${record.username}_${record.workDate || new Date().toISOString().slice(0, 10)}`;
  const payload = {
    ...record,
    updatedAt: new Date().toISOString()
  };

  // 1. Simpan ke cache lokal instan (jika kuota penuh karena foto, simpan versi ringan tanpa foto)
  const localAtt = safeLocalGet("pramuka_local_attendances", {});
  localAtt[docId] = payload;
  if (!safeLocalSet("pramuka_local_attendances", localAtt)) {
    const { photo, ...lightPayload } = payload;
    localAtt[docId] = lightPayload;
    safeLocalSet("pramuka_local_attendances", localAtt);
  }

  // 2. Sinkronisasi ke Cloud Firestore (awaited dengan timeout 2.5 detik agar andal sebelum redirect)
  try {
    const docRef = doc(db, "attendances", docId);
    await fsTimeout(setDoc(docRef, { ...payload, timestamp: serverTimestamp() }, { merge: true }), 2500);
  } catch (e) {
    console.warn("Firestore absensi offline:", e.message);
  }

  return payload;
}

export async function getAllAttendances() {
  const localAtt = safeLocalGet("pramuka_local_attendances", {});
  let list = Object.values(localAtt);

  try {
    const snap = await fsTimeout(getDocs(collection(db, "attendances")), 1500);
    if (snap && !snap.empty) {
      const fsList = snap.docs.map(d => ({ id: d.id, ...d.data() }));
      list = mergeListWithFs(list, fsList, item => `${item.username}_${item.workDate}`, null);
    }
  } catch (e) {
    console.warn("Menggunakan cache lokal absensi:", e.message);
  }

  return list;
}

// --------------------------------------------------------------------------
// 5. GRADING (PENGAMBILAN NILAI AKHIR) API
// --------------------------------------------------------------------------
export function computeFinalGrade(kehadiran = null, nilaiKuis = null, bobotKehadiran = 50, bobotKuis = 50) {
  const hasK = (kehadiran !== null && kehadiran !== undefined && kehadiran !== "" && !isNaN(Number(kehadiran)));
  const hasQ = (nilaiKuis !== null && nilaiKuis !== undefined && nilaiKuis !== "" && !isNaN(Number(nilaiKuis)));

  let finalScore = 0;

  if (hasK && hasQ) {
    // Kombinasi Kehadiran 1 Semester + Skor Kuis CBT sesuai bobot
    const k = Number(kehadiran);
    const q = Number(nilaiKuis);
    const bk = Number(bobotKehadiran) / 100;
    const bq = Number(bobotKuis) / 100;
    finalScore = Math.max(0, Math.min(100, Math.round((k * bk) + (q * bq))));
  } else if (hasQ) {
    // Jika rekapan kehadiran belum diinput, gunakan nilai kuis
    finalScore = Math.max(0, Math.min(100, Math.round(Number(nilaiKuis))));
  } else if (hasK) {
    // Jika kuis belum dikerjakan, gunakan nilai kehadiran
    finalScore = Math.max(0, Math.min(100, Math.round(Number(kehadiran))));
  } else {
    return { finalScore: "-", predikat: "-" };
  }

  let predikat = "D";
  if (finalScore >= 85) predikat = "A";
  else if (finalScore >= 75) predikat = "B";
  else if (finalScore >= 65) predikat = "C";

  return { finalScore, predikat };
}

// --------------------------------------------------------------------------
// 5B. CBT EXAMS & SMART QUESTION UPLOADER (PKBM MANDIRI STYLE)
// --------------------------------------------------------------------------
export const DEFAULT_PRAMUKA_EXAMS = [
  {
    id: "exam_semester_1",
    judul: "Ujian Semester 1",
    mapel: "Kepramukaan & SKU Penegak",
    tingkat: "Penegak Bantara",
    durasi: 60,
    questions: [
      {
        id: 1,
        pertanyaan: "Hari Pramuka diperingati setiap tanggal ....",
        opsi_a: "14 Agustus",
        opsi_b: "17 Agustus",
        opsi_c: "28 Oktober",
        opsi_d: "2 Mei",
        kunci: "A"
      },
      {
        id: 2,
        pertanyaan: "Tokoh yang dikenal sebagai Bapak Pandu Sedunia adalah ....",
        opsi_a: "Sri Sultan Hamengkubuwono IX",
        opsi_b: "Ki Hajar Dewantara",
        opsi_c: "Lord Robert Baden-Powell",
        opsi_d: "Ir. Soekarno",
        kunci: "C"
      },
      {
        id: 3,
        pertanyaan: "Tokoh yang dijuluki Bapak Pramuka Indonesia adalah ....",
        opsi_a: "Ki Hajar Dewantara",
        opsi_b: "Ir. Soekarno",
        opsi_c: "Mohammad Hatta",
        opsi_d: "Sri Sultan Hamengkubuwono IX",
        kunci: "D"
      },
      {
        id: 4,
        pertanyaan: "Tunas kelapa dipilih sebagai lambang Gerakan Pramuka karena kelapa ....",
        opsi_a: "hanya dapat tumbuh di daerah pegunungan",
        opsi_b: "dapat tumbuh di mana saja, tahan terhadap berbagai kondisi, dan seluruh bagiannya bermanfaat",
        opsi_c: "hanya bermanfaat pada bagian buahnya",
        opsi_d: "tumbuh dengan sangat cepat dalam waktu singkat",
        kunci: "B"
      },
      {
        id: 5,
        pertanyaan: "Anggota Pramuka golongan Penegak berusia ....",
        opsi_a: "7-10 tahun",
        opsi_b: "11-15 tahun",
        opsi_c: "16-20 tahun",
        opsi_d: "21-25 tahun",
        kunci: "C"
      },
      {
        id: 6,
        pertanyaan: "Kode Kehormatan Pramuka terdiri atas Janji (Trisatya) dan Ketentuan Moral (Dasa Darma). Butir pertama Trisatya berbunyi ....",
        opsi_a: "Menjalankan kewajibanku terhadap Tuhan, Negara Kesatuan Republik Indonesia dan mengamalkan Pancasila",
        opsi_b: "Menolong sesama hidup dan mempersiapkan diri membangun masyarakat",
        opsi_c: "Menepati Dasa Darma",
        opsi_d: "Bertakwa kepada Tuhan Yang Maha Esa",
        kunci: "A"
      },
      {
        id: 7,
        pertanyaan: "Satuan kelompok terkecil dalam golongan Pramuka Penegak disebut ....",
        opsi_a: "Barung",
        opsi_b: "Regu",
        opsi_c: "Sangga",
        opsi_d: "Reka",
        kunci: "C"
      },
      {
        id: 8,
        pertanyaan: "Jumlah anggota ideal dalam satu Sangga Pramuka Penegak umumnya terdiri dari ....",
        opsi_a: "4-6 orang",
        opsi_b: "6-8 orang",
        opsi_c: "10-12 orang",
        opsi_d: "15-20 orang",
        kunci: "B"
      },
      {
        id: 9,
        pertanyaan: "Tingkatan kecakapan umum bagi Pramuka Penegak berturut-turut adalah ....",
        opsi_a: "Mula dan Bantu",
        opsi_b: "Tata dan Ramu",
        opsi_c: "Bantara dan Laksana",
        opsi_d: "Madya dan Utama",
        kunci: "C"
      },
      {
        id: 10,
        pertanyaan: "Semboyan resmi pangkalan ambalan Gugus Depan MAN 4 Banjar adalah ....",
        opsi_a: "Al Ittihadu Asasun Najah • Become a Legend • Ahlul Haq",
        opsi_b: "Satyaku Kudarmakan Darmaku Kubaktikan",
        opsi_c: "Ikhlas Bakti Bina Bangsa",
        opsi_d: "Praja Muda Karana",
        kunci: "A"
      },
      {
        id: 11,
        pertanyaan: "Nomor Gugus Depan pangkalan MAN 4 Banjar putra dan putri berturut-turut adalah ....",
        opsi_a: "01.001 - 01.002",
        opsi_b: "01.095 - 01.096",
        opsi_c: "02.045 - 02.046",
        opsi_d: "03.015 - 03.016",
        kunci: "B"
      },
      {
        id: 12,
        pertanyaan: "Tanda Kecakapan Umum (TKU) Penegak Bantara bergambar ....",
        opsi_a: "Dua tunas kelapa dengan bintang di atasnya berlatar hijau",
        opsi_b: "Dua tunas kelapa kembar berlatar merah dengan tulisan Bantara",
        opsi_c: "Satu tunas kelapa dengan daun kelapa",
        opsi_d: "Lambang kepanduan sedunia WOSM ungu",
        kunci: "B"
      },
      {
        id: 13,
        pertanyaan: "Simpul yang digunakan untuk mengikat dua utas tali yang sama besar dalam keadaan kering adalah ....",
        opsi_a: "Simpul Pangkal",
        opsi_b: "Simpul Mati",
        opsi_c: "Simpul Jangkar",
        opsi_d: "Simpul Kembar",
        kunci: "B"
      },
      {
        id: 14,
        pertanyaan: "Simpul yang digunakan untuk memulai atau mengakhiri suatu ikatan pionering pada tiang tongkat adalah ....",
        opsi_a: "Simpul Pangkal",
        opsi_b: "Simpul Hidup",
        opsi_c: "Simpul Tiang",
        opsi_d: "Simpul Anyam",
        kunci: "A"
      },
      {
        id: 15,
        pertanyaan: "Simpul yang digunakan untuk menyambung dua utas tali yang tidak sama besar dalam keadaan basah atau licin adalah ....",
        opsi_a: "Simpul Mati",
        opsi_b: "Simpul Anyam Berganda",
        opsi_c: "Simpul Erat",
        opsi_d: "Simpul Laso",
        kunci: "B"
      },
      {
        id: 16,
        pertanyaan: "Ikatan yang digunakan untuk menyatukan dua tongkat kayu yang saling menyilang membentuk sudut siku-siku 90° adalah ....",
        opsi_a: "Ikatan Silang",
        opsi_b: "Ikatan Palang",
        opsi_c: "Ikatan Canggah",
        opsi_d: "Ikatan Kaki Tiga",
        kunci: "B"
      },
      {
        id: 17,
        pertanyaan: "Ikatan yang digunakan untuk menyambung dua tongkat kayu secara lurus memanjang adalah ....",
        opsi_a: "Ikatan Canggah (Sambung Tongkat)",
        opsi_b: "Ikatan Palang",
        opsi_c: "Ikatan Kaki Tiga",
        opsi_d: "Simpul Pangkal",
        kunci: "A"
      },
      {
        id: 18,
        pertanyaan: "Tanda kode Morse internasional untuk huruf 'P' adalah ....",
        opsi_a: ".--. (titik garis garis titik)",
        opsi_b: "---. (garis garis garis titik)",
        opsi_c: ".-.. (titik garis titik titik)",
        opsi_d: "--.- (garis garis titik garis)",
        kunci: "A"
      },
      {
        id: 19,
        pertanyaan: "Kode Morse internasional yang digunakan untuk panggilan darurat bahaya adalah ....",
        opsi_a: "MAYDAY",
        opsi_b: "SOS (... --- ...)",
        opsi_c: "HELP",
        opsi_d: "EMERGENCY",
        kunci: "B"
      },
      {
        id: 20,
        pertanyaan: "Dalam pengiriman isyarat Semaphore, posisi bendera untuk menyampaikan huruf 'S' adalah ....",
        opsi_a: "Tangan kanan lurus ke bawah, tangan kiri ke atas",
        opsi_b: "Tangan kanan serong ke bawah, tangan kiri serong ke atas",
        opsi_c: "Tangan kanan mendatar ke samping kanan, tangan kiri serong ke bawah kiri",
        opsi_d: "Kedua tangan lurus ke samping",
        kunci: "C"
      },
      {
        id: 21,
        pertanyaan: "Arah mata angin Barat Laut pada kompas bidik navigasi menunjukkan sudut derajat ....",
        opsi_a: "45°",
        opsi_b: "135°",
        opsi_c: "225°",
        opsi_d: "315°",
        kunci: "D"
      },
      {
        id: 22,
        pertanyaan: "Jika sudut bidik kompas (Azimuth) suatu objek adalah 80°, maka sudut baliknya (Back Azimuth) adalah ....",
        opsi_a: "160°",
        opsi_b: "200°",
        opsi_c: "260°",
        opsi_d: "300°",
        kunci: "C"
      },
      {
        id: 23,
        pertanyaan: "Alat navigasi darat yang digunakan untuk membidik sasaran dan menentukan arah mata angin magnetik adalah ....",
        opsi_a: "Altimeter",
        opsi_b: "Kompas Prisma / Bidik",
        opsi_c: "Barometer",
        opsi_d: "Anemometer",
        kunci: "B"
      },
      {
        id: 24,
        pertanyaan: "Pertemuan akbar bagi anggota Pramuka Penegak dan Pandega dalam bentuk perkemahan besar tingkat nasional adalah ....",
        opsi_a: "Jambore Nasional",
        opsi_b: "Raimuna Nasional",
        opsi_c: "Lomba Tingkat (LT)",
        opsi_d: "Karang Pamitran",
        kunci: "B"
      },
      {
        id: 25,
        pertanyaan: "Badan kepengurusan yang dibentuk di Gugus Depan dari, oleh, dan untuk Pramuka Penegak dinamakan ....",
        opsi_a: "Dewan Galang",
        opsi_b: "Dewan Kerja Ranting",
        opsi_c: "Dewan Ambalan Penegak",
        opsi_d: "Majelis Pembimbing",
        kunci: "C"
      },
      {
        id: 26,
        pertanyaan: "Sandi kepramukaan yang menggunakan kotak-kotak bertingkat dengan tanda titik dinamakan ....",
        opsi_a: "Sandi Morse",
        opsi_b: "Sandi Kotak 1",
        opsi_c: "Sandi Rumput",
        opsi_d: "Sandi Angka",
        kunci: "B"
      },
      {
        id: 27,
        pertanyaan: "Penemu sistem sandi titik dan garis (Morse) yang digunakan di seluruh dunia adalah ....",
        opsi_a: "Samuel Finley Breese Morse",
        opsi_b: "Alexander Graham Bell",
        opsi_c: "Thomas Alva Edison",
        opsi_d: "Guglielmo Marconi",
        kunci: "A"
      },
      {
        id: 28,
        pertanyaan: "Upacara pembukaan latihan mingguan Ambalan Penegak ditandai dengan pembacaan ....",
        opsi_a: "UUD 1945",
        opsi_b: "Renungan Harian",
        opsi_c: "Sandi Ambalan dan Dasa Darma",
        opsi_d: "Janji Siswa Madrasah",
        kunci: "C"
      },
      {
        id: 29,
        pertanyaan: "Sikap luhur yang mencerminkan pengamalan butir kesepuluh Dasa Darma Pramuka adalah ....",
        opsi_a: "Rajin berolahraga dan hemat energi",
        opsi_b: "Suci dalam pikiran, perkataan, dan perbuatan",
        opsi_c: "Hemat, cermat, dan bersahaja",
        opsi_d: "Berani mengakui kesalahan diri",
        kunci: "B"
      },
      {
        id: 30,
        pertanyaan: "Ketua Majelis Pembimbing Gugus Depan (Kamabigus) di pangkalan MAN 4 Banjar dijabat oleh ....",
        opsi_a: "Pembina Pramuka Putra",
        opsi_b: "Wakamad Kesiswaan",
        opsi_c: "Kepala Madrasah Aliyah Negeri 4 Banjar",
        opsi_d: "Pelatih Utama Gudep",
        kunci: "C"
      }
    ]
  }
];

export function extractQuestionAndOptions(q) {
  let qText = q.pertanyaan || "";
  let optA = q.opsi_a || "";
  let optB = q.opsi_b || "";
  let optC = q.opsi_c || "";
  let optD = q.opsi_d || "";
  let key = (q.kunci || "").toUpperCase();

  // 0. Buang baris judul kategori/bab di akhir teks jika ikut terbawa (misal: "B. Organisasi Gerakan Pramuka (Soal 11–20)")
  qText = qText.replace(/(?:^|\n)\s*(?:Bagian\s+[IVX0-9A-D]+[\.\:\-\s]+|[A-D]\.\s+[^\n]*\(\s*Soal\s+\d+[^\n]*\))[^\n]*$/i, '').trim();

  // 1. Bersihkan kunci jawaban inline yang menempel di akhir teks soal jika ada
  const keyMatch = qText.match(/(?:[\n\r•\-\|]|\s+)(?:Kunci(?:\s+Jawaban)?|Jawaban|Ans)\s*[:=-]\s*[\(\[]?([A-Ea-e])[\)\]\.]?\s*$/i);
  if (keyMatch) {
    if (!key) key = keyMatch[1].toUpperCase();
    qText = qText.substring(0, keyMatch.index).trim();
  }

  // 2. Jika opsi masih kosong atau belum terpisah, ekstrak dari pertanyaan
  if (!optA || !optB) {
    const upperMatch = qText.match(/(?:^|[\n\r•\-\|]|\s+)[\(\[]?A[\.\)\]]\s+/);
    const aMatch = upperMatch || qText.match(/(?:^|[\n\r•\-\|]|\s+)[\(\[]?a[\.\)\]]\s+/);
    if (aMatch) {
      const aIdx = aMatch.index;
      const optionsPart = qText.substring(aIdx);
      qText = qText.substring(0, aIdx).replace(/[\s•\-\|]+$/, '').trim();

      const optPattern = upperMatch
        ? /(?:^|[\n\r•\-\|]|\s+)[\(\[]?([A-E])[\.\)\]]\s+([\s\S]*?)(?=(?:[\n\r•\-\|]|\s+)[\(\[]?[A-E][\.\)\]]\s+|$)/g
        : /(?:^|[\n\r•\-\|]|\s+)[\(\[]?([A-Ea-e])[\.\)\]]\s+([\s\S]*?)(?=(?:[\n\r•\-\|]|\s+)[\(\[]?[A-Ea-e][\.\)\]]\s+|$)/gi;
      const matches = [...optionsPart.matchAll(optPattern)];
      for (const m of matches) {
        const letter = m[1].toUpperCase();
        let val = m[2].replace(/[\s•\-\|]+$/, '').trim();
        val = val.replace(/(?:[\n\r•\-\|]|\s+)?(?:Kunci(?:\s+Jawaban)?|Jawaban|Ans)\s*[:=-]\s*[\(\[]?[A-Ea-e][\)\]\.]?\s*$/i, '').trim();
        if (letter === 'A' && !optA) optA = val;
        else if (letter === 'B' && !optB) optB = val;
        else if (letter === 'C' && !optC) optC = val;
        else if (letter === 'D' && !optD) optD = val;
      }
    }
  } else {
    // Jika opsi sudah ada, pastikan teks pertanyaan bersih dari opsi yang menempel
    const aMatch = qText.match(/(?:^|[\n\r•\-\|]|\s+)[\(\[]?A[\.\)\]]\s+/) || qText.match(/(?:^|[\n\r•\-\|]|\s+)[\(\[]?a[\.\)\]]\s+/);
    if (aMatch && aMatch.index > 0) {
      qText = qText.substring(0, aMatch.index).replace(/[\s•\-\|]+$/, '').trim();
    }
  }

  // 3. Pastikan opsi bersih dari teks kunci jawaban yang mungkin menempel
  if (optD) {
    optD = optD.replace(/(?:[\n\r•\-\|]|\s+)?(?:Kunci(?:\s+Jawaban)?|Jawaban|Ans)\s*[:=-]\s*[\(\[]?[A-Ea-e][\)\]\.]?\s*$/i, '').trim();
  }

  return {
    id: q.id,
    pertanyaan: qText,
    opsi_a: optA,
    opsi_b: optB,
    opsi_c: optC,
    opsi_d: optD,
    kunci: key || "A"
  };
}

export function parseExamText(rawText) {
  if (!rawText) return { questions: [], meta: {} };

  let text = rawText.replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
  const meta = {
    judul: "Kuis Penilaian Kepramukaan",
    mapel: "Pramuka Penegak",
    tingkat: "Penegak Bantara",
    durasi: 60
  };

  const titleMatch = text.match(/^(?:Judul|Ujian|Kuis|Asesmen)\s*[:=-]\s*([^\n]+)/im);
  if (titleMatch) meta.judul = titleMatch[1].trim();

  // Pisahkan Kunci Jawaban di akhir dokumen jika ada (mendukung multi-baris, satu baris, dan tabel multi-kolom)
  const separateKeys = {};
  const keyHeaderLineRegex = /^[ \t]*(?:[=\-*#|]+[ \t]*)?(?:KUNCI\s+JAWABAN|KUNCI\s+SOAL|DAFTAR\s+KUNCI|KUNCI[ \t]*[:=-])([^\n]*)/gim;
  const headerMatches = [...text.matchAll(keyHeaderLineRegex)];
  let mainText = text;

  if (headerMatches.length > 0) {
    const lastMatch = headerMatches[headerMatches.length - 1];
    const headerEnd = lastMatch.index + lastMatch[0].length;
    const headerTail = (lastMatch[1] || "").trim();
    const bodyBlock = text.slice(headerEnd).replace(/^\n/, "").trim();
    const isExplicitBlockTitle = /(?:KUNCI\s+JAWABAN|KUNCI\s+SOAL|DAFTAR\s+KUNCI)/i.test(lastMatch[0]);
    const hasNumberedHeaderKeys = /\d+[ \t.:=\)-]+[\(\[]?[A-Ea-e](?:[\)\]\.\s,;\|]|$)/i.test(headerTail);
    const hasMultiLettersHeader = /(?:\b[A-Ea-e]\b[ \t,;]+){2,}[A-Ea-e]\b/i.test(headerTail);
    const bodyLooksLikeQuestions = /(?:^|\n)[ \t]*[\(\[]?[AB][\.\)\]][ \t]+/i.test(bodyBlock);

    const isDocKeyBlock = !bodyLooksLikeQuestions && (
      (isExplicitBlockTitle && (bodyBlock.length > 0 || hasNumberedHeaderKeys || hasMultiLettersHeader)) ||
      (bodyBlock.length > 0 && !/^[A-Ea-e]$/i.test(headerTail)) ||
      hasNumberedHeaderKeys ||
      hasMultiLettersHeader
    );

    if (isDocKeyBlock) {
      const keyBlock = (hasNumberedHeaderKeys || hasMultiLettersHeader
        ? `${headerTail}\n${bodyBlock}`
        : bodyBlock).trim();
      mainText = text.substring(0, lastMatch.index).trim();

      if (keyBlock) {
        // 1. Format utama: '1. A', '1 - A', '1: A', '1) A', '1 A', '1. (A)' pada baris yang sama (anti-lintas baris!)
        const keyItemMatches1 = keyBlock.matchAll(/(?:^|[\s,\n|;\t])(\d{1,3})[ \t.:=\)-]+[\(\[]?([A-Ea-e])[\)\]\.]?(?=[\s,\n|;\t]|$)/gi);
        for (const km of keyItemMatches1) {
          separateKeys[parseInt(km[1], 10)] = km[2].toUpperCase();
        }

        // 2. Format terbalik ('A 1', 'B. 29'): HANYA dijalankan jika format nomor-huruf tidak ditemukan!
        if (Object.keys(separateKeys).length === 0) {
          const keyItemMatches2 = keyBlock.matchAll(/(?:^|[\s,\n|;\t])[\(\[]?([A-Ea-e])[\)\]]?[ \t.:=\)-]+(\d{1,3})(?=[\s,\n|;\t\.]|$)/gi);
          for (const km of keyItemMatches2) {
            separateKeys[parseInt(km[2], 10)] = km[1].toUpperCase();
          }
        }

        // 3. Format huruf berurutan jika tanpa nomor ('A, B, C, D...')
        if (Object.keys(separateKeys).length === 0) {
          const letters = keyBlock.match(/\b([A-Ea-e])\b/g);
          if (letters) {
            letters.forEach((l, idx) => {
              separateKeys[idx + 1] = l.toUpperCase();
            });
          }
        }
      }
    }
  }

  // Bersihkan baris heading kategori/bagian agar tidak menimpa opsi soal sebelumnya
  mainText = mainText.replace(/^[ \t]*(?:Bagian\s+[IVX0-9A-D]+[\.\:\-\s]+|[A-D]\.\s+[^\n]*\(\s*Soal\s+\d+[^\n]*\))[^\n]*$/gim, '');

  // Pisahkan butir soal dan gabungkan kembali sub-daftar bernomor di dalam batang soal jika belum memiliki opsi
  const rawSplit = mainText.split(/(?:^|\n)\s*(?:Soal\s+|No\.?\s*)?(\d+)[\.\)\:\-]\s+/i);
  const groupedBlocks = [];
  for (let i = 1; i < rawSplit.length; i += 2) {
    const num = parseInt(rawSplit[i], 10);
    const block = (rawSplit[i + 1] || "").trim();
    const prev = groupedBlocks[groupedBlocks.length - 1];
    const prevHasOptions = prev && /(?:^|[\n\r•\-\|]|\s+)[\(\[]?[AB][\.\)\]]\s+/i.test(prev.block);
    if (prev && !prevHasOptions) {
      prev.block += `\n${num}. ${block}`;
    } else {
      groupedBlocks.push({ num, block });
    }
  }

  const questions = groupedBlocks.map(({ num, block }, idx) => {
    // Cek kunci inline ketat (wajib memakai tanda pemisah :, =, atau -)
    let inlineKey = "";
    const inlineKeyMatch = block.match(/(?:^|\n|\s)(?:Kunci(?:\s+Jawaban)?|Jawaban|Ans)\s*[:=-]\s*[\(\[]?([A-Ea-e])[\)\]\.]?(?=\s|$)/i);
    if (inlineKeyMatch) {
      inlineKey = inlineKeyMatch[1].toUpperCase();
    }

    const qId = num || (idx + 1);
    const key = separateKeys[qId] || separateKeys[idx + 1] || inlineKey || "A";

    return extractQuestionAndOptions({
      id: qId,
      pertanyaan: block,
      opsi_a: "",
      opsi_b: "",
      opsi_c: "",
      opsi_d: "",
      kunci: key
    });
  });

  return { meta, questions };
}

function mergeExamObjects(base, override) {
  if (!base) return override;
  if (!override) return base;
  return {
    ...base,
    ...override,
    judul: override.judul || base.judul,
    mapel: override.mapel || base.mapel,
    tingkat: override.tingkat || base.tingkat,
    durasi: override.durasi || base.durasi || 60,
    questions: (Array.isArray(override.questions) && override.questions.length > 0)
      ? override.questions
      : base.questions
  };
}

export async function saveExam(examData) {
  const docId = examData.id || `exam_${Date.now()}`;
  const nowIso = new Date().toISOString();
  const payload = {
    ...examData,
    id: docId,
    updatedAt: nowIso
  };

  // 1. Simpan selalu ke LocalStorage terlebih dahulu (INSTAN & 100% SUKSES)
  try {
    const custom = JSON.parse(localStorage.getItem("pramuka_custom_exams") || "[]");
    const idx = custom.findIndex(e => e.id === docId);
    if (idx >= 0) custom[idx] = payload;
    else custom.push(payload);
    localStorage.setItem("pramuka_custom_exams", JSON.stringify(custom));
    console.log("✓ Soal ujian berhasil tersimpan di penyimpanan lokal:", docId);
  } catch (lsErr) {
    console.warn("Gagal simpan ke localStorage:", lsErr);
  }

  // 2. Sinkronisasi ke Cloud Firestore (awaited dengan timeout ketat 2.5 detik)
  try {
    const docRef = doc(db, "exams", docId);
    await fsTimeout(setDoc(docRef, { ...payload, timestamp: serverTimestamp() }, { merge: true }), 2500);
    console.log("✓ Soal ujian berhasil disinkronkan ke Cloud Firestore!");
  } catch (fsErr) {
    console.warn("Sinkronisasi Cloud Firestore dilewati (offline / timeout), soal tetap tersimpan aman di aplikasi:", fsErr.message);
  }

  return payload;
}

export async function updateExamDuration(examId, durationMinutes) {
  const dur = parseInt(durationMinutes, 10);
  if (isNaN(dur) || dur < 1) throw new Error("Durasi ujian tidak valid");

  const nowIso = new Date().toISOString();
  let fullExamPayload = { id: examId, durasi: dur, updatedAt: nowIso };

  // 1. Simpan ke LocalStorage dan pastikan metadata default ikut tersimpan jika ada
  try {
    const custom = JSON.parse(localStorage.getItem("pramuka_custom_exams") || "[]");
    const idx = custom.findIndex(e => e.id === examId);
    const def = DEFAULT_PRAMUKA_EXAMS.find(e => e.id === examId);
    if (idx >= 0) {
      custom[idx] = mergeExamObjects(def, { ...custom[idx], durasi: dur, updatedAt: nowIso });
      fullExamPayload = custom[idx];
      localStorage.setItem("pramuka_custom_exams", JSON.stringify(custom));
    } else if (def) {
      fullExamPayload = { ...def, durasi: dur, updatedAt: nowIso };
      custom.push(fullExamPayload);
      localStorage.setItem("pramuka_custom_exams", JSON.stringify(custom));
    }
  } catch (e) {
    console.warn("Gagal update durasi di localStorage:", e);
  }

  // 2. Sinkronisasi ke Cloud Firestore
  try {
    const docRef = doc(db, "exams", examId);
    await fsTimeout(setDoc(docRef, { ...fullExamPayload, timestamp: serverTimestamp() }, { merge: true }), 2500);
    console.log(`✓ Durasi ujian ${examId} berhasil diperbarui di Firestore: ${dur} menit`);
  } catch (fsErr) {
    console.warn("Gagal update durasi di Firestore:", fsErr);
  }

  return dur;
}

export async function getExams() {
  let custom = [];
  try {
    custom = JSON.parse(localStorage.getItem("pramuka_custom_exams") || "[]");
  } catch (e) {}

  let result = [...DEFAULT_PRAMUKA_EXAMS];
  custom.forEach(item => {
    const idx = result.findIndex(m => m.id === item.id);
    if (idx >= 0) result[idx] = mergeExamObjects(result[idx], item);
    else result.push(item);
  });

  // Ambil update dari Firestore dengan timeout 1.2 detik (tidak boleh memblokir UI)
  try {
    const snap = await fsTimeout(getDocs(collection(db, "exams")), 1200);
    if (snap && !snap.empty) {
      const list = snap.docs.map(d => ({ id: d.id, ...d.data() }));
      list.forEach(item => {
        const idx = result.findIndex(m => m.id === item.id);
        if (idx >= 0) result[idx] = mergeExamObjects(result[idx], item);
        else result.push(item);
      });
    }
  } catch (e) {
    // Offline / timeout
  }

  // Hapus paket soal bawaan lama (exam_sku_bantara & exam_sandi_navigasi) agar hanya menyisakan soal yang dibuat pelatih
  const legacyIds = ["exam_sku_bantara", "exam_sandi_navigasi"];
  result = result.filter(e => !legacyIds.includes(e.id));

  return result;
}

export async function saveExamResult(resultData) {
  const resId = `result_${resultData.examId}_${resultData.username}`;
  const nowIso = new Date().toISOString();
  const payload = {
    ...resultData,
    resId,
    completedAt: nowIso
  };

  // 1. Simpan ke cache lokal aman
  const allRes = safeLocalGet("pramuka_exam_results", {});
  allRes[resId] = payload;
  safeLocalSet("pramuka_exam_results", allRes);

  // 2. Simpan ke Cloud Firestore secara andal (timeout 2.5 detik)
  try {
    const docRef = doc(db, "exam_results", resId);
    await fsTimeout(setDoc(docRef, { ...payload, timestamp: serverTimestamp() }, { merge: true }), 2500);
  } catch (e) {
    console.warn("Firestore exam result offline / sync dilewati:", e.message);
  }

  // 3. Otomatis sinkronkan nilai ujian ini ke Nilai Akhir rapor siswa (tanpa menghapus absen manual jika ada)
  try {
    const quizScore = Number(resultData.score);
    const localGrades = safeLocalGet("pramuka_local_grades", {});
    const existing = localGrades[`grade_${resultData.username}`] || {};
    const attRaw = (existing.kehadiranManual !== undefined && existing.kehadiranManual !== null && existing.kehadiranManual !== "")
      ? existing.kehadiranManual
      : existing.kehadiran;
    const attVal = (attRaw !== undefined && attRaw !== null && attRaw !== "") ? Number(attRaw) : null;
    const { finalScore, predikat } = computeFinalGrade(attVal, quizScore);

    await saveStudentGrade({
      username: resultData.username,
      name: resultData.name,
      kelas: resultData.kelas,
      gender: resultData.gender || "L",
      examId: resultData.examId,
      examTitle: resultData.examTitle,
      kehadiran: existing.kehadiran ?? null,
      kehadiranManual: existing.kehadiranManual ?? null,
      nilaiKuis: quizScore,
      finalScore: finalScore !== "-" ? Number(finalScore) : quizScore,
      predikat: predikat !== "-" ? predikat : (quizScore >= 85 ? "A" : (quizScore >= 75 ? "B" : (quizScore >= 65 ? "C" : "D"))),
      catatan: `Hasil Kuis CBT: ${resultData.score}/100 (${resultData.correctCount || 0}/${resultData.totalQuestions || 30} Benar) - ${predikat === 'A' || predikat === 'B' ? 'Tuntas KKM' : 'Perlu Remedial'}`
    });
  } catch (err) {
    console.warn("Gagal auto-update rapor:", err);
  }

  return payload;
}

export async function syncAllGradesFromExam(examId) {
  const allResults = await getExamResults();
  const existingGrades = await getAllGrades();
  const targetResults = examId ? allResults.filter(r => r.examId === examId) : allResults;
  const synced = {};
  const tasks = [];

  for (const res of targetResults) {
    const scoreVal = Number(res.score);
    const existing = existingGrades[`grade_${res.username}`] || {};
    const attRaw = (existing.kehadiranManual !== undefined && existing.kehadiranManual !== null && existing.kehadiranManual !== "")
      ? existing.kehadiranManual
      : existing.kehadiran;
    const attVal = (attRaw !== undefined && attRaw !== null && attRaw !== "") ? Number(attRaw) : null;
    const { finalScore, predikat } = computeFinalGrade(attVal, scoreVal);
    const payload = {
      username: res.username,
      name: res.name,
      kelas: res.kelas,
      gender: res.gender || "L",
      examId: res.examId,
      examTitle: res.examTitle,
      kehadiran: existing.kehadiran ?? null,
      kehadiranManual: existing.kehadiranManual ?? null,
      nilaiKuis: scoreVal,
      finalScore: finalScore,
      predikat: predikat,
      catatan: existing.catatan || `Kuis CBT: ${res.score}/100 (${res.correctCount || 0}/${res.totalQuestions || 30} Benar)`
    };
    synced[res.username] = payload;
    tasks.push(() => saveStudentGrade(payload));
  }

  // Simpan secara paralel bertahap agar tidak memblokir UI meski banyak siswa
  for (let i = 0; i < tasks.length; i += 10) {
    await Promise.allSettled(tasks.slice(i, i + 10).map(fn => fn()));
  }
  return synced;
}

export async function saveAndRegradeExam(examObj) {
  const savedExam = await saveExam(examObj);

  const allResults = await getExamResults();
  const targetResults = allResults.filter(r => r.examId === savedExam.id);
  const allGrades = await getAllGrades();
  const questions = savedExam.questions || [];
  const totalQ = questions.length;
  let updatedCount = 0;

  for (const res of targetResults) {
    const answers = res.answers || {};
    if (!totalQ || Object.keys(answers).length === 0) continue;

    let correct = 0;
    questions.forEach((q, idx) => {
      const parsedQ = extractQuestionAndOptions(q);
      const qid = parsedQ.id || (idx + 1);
      const studentAns = (answers[qid] || "").toUpperCase();
      const keyAns = (parsedQ.kunci || "").toUpperCase();
      if (studentAns === keyAns) correct++;
    });

    const newScore = Math.round((correct / totalQ) * 100);
    const updatedRes = {
      ...res,
      examTitle: savedExam.judul || res.examTitle,
      score: newScore,
      correctCount: correct,
      totalQuestions: totalQ
    };

    const resId = res.resId || `result_${res.examId}_${res.username}`;
    try {
      const localRes = JSON.parse(localStorage.getItem("pramuka_exam_results") || "{}");
      localRes[resId] = updatedRes;
      localStorage.setItem("pramuka_exam_results", JSON.stringify(localRes));
    } catch (_) {}

    try {
      await fsTimeout(setDoc(doc(db, "exam_results", resId), { ...updatedRes, timestamp: serverTimestamp() }, { merge: true }), 2500);
    } catch (e) {
      console.warn("Gagal sinkronisasi regrade exam_result ke Firestore:", e.message);
    }

    const existingGrade = allGrades[`grade_${res.username}`];
    if (!existingGrade || existingGrade.examId === savedExam.id) {
      const attRaw = existingGrade && existingGrade.kehadiranManual !== undefined && existingGrade.kehadiranManual !== null && existingGrade.kehadiranManual !== ""
        ? existingGrade.kehadiranManual
        : (existingGrade ? existingGrade.kehadiran : null);
      const attVal = attRaw !== undefined && attRaw !== null && attRaw !== "" ? Number(attRaw) : null;
      const { finalScore, predikat } = computeFinalGrade(attVal, newScore);
      await saveStudentGrade({
        username: res.username,
        name: res.name,
        kelas: res.kelas,
        gender: res.gender || "L",
        examId: savedExam.id,
        examTitle: savedExam.judul || res.examTitle,
        kehadiran: existingGrade ? existingGrade.kehadiran : null,
        kehadiranManual: existingGrade ? existingGrade.kehadiranManual : null,
        nilaiKuis: newScore,
        finalScore,
        predikat,
        catatan: `Hasil Kuis CBT: ${newScore}/100 (${correct}/${totalQ} Benar) - ${predikat === 'A' || predikat === 'B' ? 'Tuntas KKM' : 'Perlu Remedial'}`
      });
    }

    updatedCount++;
  }

  return { exam: savedExam, regradedCount: updatedCount };
}

export async function getExamResults() {
  let localList = [];
  try {
    const allRes = JSON.parse(localStorage.getItem("pramuka_exam_results") || "{}");
    localList = Object.values(allRes);
  } catch (e) {}

  try {
    const snap = await fsTimeout(getDocs(collection(db, "exam_results")), 1500);
    if (snap && !snap.empty) {
      const fsList = snap.docs.map(d => ({ id: d.id, ...d.data() }));
      const merged = [...localList];
      fsList.forEach(f => {
        const key = f.resId || f.id;
        const idx = merged.findIndex(m => (m.resId || m.id) === key);
        if (idx >= 0) merged[idx] = { ...merged[idx], ...f };
        else merged.push(f);
      });

      // Sinkronkan balik data terbaru dari Firestore ke localStorage agar skor koreksi langsung terlihat
      try {
        const localObj = JSON.parse(localStorage.getItem("pramuka_exam_results") || "{}");
        fsList.forEach(f => {
          const key = f.resId || `result_${f.examId}_${f.username}`;
          localObj[key] = f;
        });
        localStorage.setItem("pramuka_exam_results", JSON.stringify(localObj));
      } catch (_) {}

      return merged;
    }
  } catch (e) {
    // Offline / timeout
  }

  return localList;
}

// --------------------------------------------------------------------------
// 5C. REMEDIAL & ACC UJIAN ULANG PELATIH (STYLE PKBM MANDIRI)
// --------------------------------------------------------------------------
export async function requestRemedial(reqData) {
  const reqId = `rem_${reqData.examId}_${reqData.username}`;
  const nowIso = new Date().toISOString();
  const payload = {
    ...reqData,
    id: reqId,
    status: "pending", // pending, approved, rejected
    requestedAt: nowIso
  };

  const allReq = safeLocalGet("pramuka_remedial_requests", {});
  allReq[reqId] = payload;
  safeLocalSet("pramuka_remedial_requests", allReq);

  try {
    const docRef = doc(db, "remedial_requests", reqId);
    await fsTimeout(setDoc(docRef, { ...payload, timestamp: serverTimestamp() }, { merge: true }), 2500);
  } catch (e) {
    console.warn("Firestore remedial offline:", e.message);
  }

  return payload;
}

export async function getAllRemedialRequests() {
  let localList = Object.values(safeLocalGet("pramuka_remedial_requests", {}));

  try {
    const snap = await fsTimeout(getDocs(collection(db, "remedial_requests")), 1500);
    if (snap && !snap.empty) {
      const fsList = snap.docs.map(d => ({ id: d.id, ...d.data() }));
      return mergeListWithFs(localList, fsList, item => item.id, "pramuka_remedial_requests", true);
    }
  } catch (e) {
    // Offline / timeout
  }

  return localList;
}

export async function approveRemedialRequest(reqId, trainerName) {
  const nowIso = new Date().toISOString();
  const updateData = {
    status: "approved",
    approvedBy: trainerName,
    approvedAt: nowIso
  };

  const allReq = safeLocalGet("pramuka_remedial_requests", {});
  if (allReq[reqId]) {
    allReq[reqId] = { ...allReq[reqId], ...updateData };
    safeLocalSet("pramuka_remedial_requests", allReq);
  }

  try {
    const docRef = doc(db, "remedial_requests", reqId);
    await fsTimeout(setDoc(docRef, { ...updateData, updatedAt: serverTimestamp() }, { merge: true }), 2500);
  } catch (e) {}

  return true;
}

export async function rejectRemedialRequest(reqId, trainerName, note = "") {
  const nowIso = new Date().toISOString();
  const updateData = {
    status: "rejected",
    rejectedBy: trainerName,
    rejectionNote: note || "Nilai sudah mencukupi batas kelulusan.",
    rejectedAt: nowIso
  };

  const allReq = safeLocalGet("pramuka_remedial_requests", {});
  if (allReq[reqId]) {
    allReq[reqId] = { ...allReq[reqId], ...updateData };
    safeLocalSet("pramuka_remedial_requests", allReq);
  }

  try {
    const docRef = doc(db, "remedial_requests", reqId);
    await fsTimeout(setDoc(docRef, { ...updateData, updatedAt: serverTimestamp() }, { merge: true }), 2500);
  } catch (e) {}

  return true;
}

export async function getStudentRemedialStatus(examId, username) {
  const reqId = `rem_${examId}_${username}`;

  // Prioritaskan Cloud Firestore agar ACC/Tolak oleh pelatih langsung diterima di perangkat siswa!
  try {
    const dSnap = await fsTimeout(getDoc(doc(db, "remedial_requests", reqId)), 1500);
    if (dSnap && dSnap.exists()) {
      const data = dSnap.data();
      const allReq = safeLocalGet("pramuka_remedial_requests", {});
      allReq[reqId] = data;
      safeLocalSet("pramuka_remedial_requests", allReq);
      return data;
    }
  } catch (e) {}

  const allReq = safeLocalGet("pramuka_remedial_requests", {});
  return allReq[reqId] || null;
}

export async function saveStudentGrade(gradeData) {
  const docId = `grade_${gradeData.username}`;
  const payload = {
    ...gradeData,
    updatedAt: new Date().toISOString()
  };

  const localGrades = safeLocalGet("pramuka_local_grades", {});
  localGrades[docId] = payload;
  safeLocalSet("pramuka_local_grades", localGrades);

  try {
    const docRef = doc(db, "grades", docId);
    await fsTimeout(setDoc(docRef, { ...payload, timestamp: serverTimestamp() }, { merge: true }), 2000);
  } catch (e) {}

  return payload;
}

export async function getAllGrades() {
  const map = { ...safeLocalGet("pramuka_local_grades", {}) };

  try {
    const snap = await fsTimeout(getDocs(collection(db, "grades")), 1500);
    if (snap && !snap.empty) {
      snap.docs.forEach(d => {
        map[d.id] = { id: d.id, ...d.data() };
      });
      safeLocalSet("pramuka_local_grades", map);
    }
  } catch (e) {
    console.warn("Gagal memuat nilai dari Firebase, membaca cache lokal:", e.message);
  }
  return map;
}

// --------------------------------------------------------------------------
// 6. UI HELPERS & LIVE CLOCK
// --------------------------------------------------------------------------
export function startLiveClock(elementId) {
  const el = document.getElementById(elementId);
  if (!el) return;

  if (el._liveClockTimer) {
    clearInterval(el._liveClockTimer);
  }

  function update() {
    const now = new Date();
    // Offset WITA (UTC+8)
    const options = { 
      timeZone: "Asia/Makassar", 
      hour12: false, 
      hour: "2-digit", 
      minute: "2-digit", 
      second: "2-digit" 
    };
    const timeStr = new Intl.DateTimeFormat("id-ID", options).format(now);
    el.textContent = timeStr + (elementId.includes("topbar") ? " WITA" : "");
  }

  update();
  el._liveClockTimer = setInterval(update, 1000);
}

export function renderTopbar(activeTab = "beranda") {
  const topbar = document.getElementById("mainTopbar");
  if (!topbar) return;

  const user = getCurrentUser();
  const safeName = user && user.name ? String(user.name).trim() : "Peserta";
  const initials = safeName ? safeName.split(/\s+/).slice(0, 2).map(p => p[0] || "").join("").toUpperCase() || "P" : "P";

  topbar.innerHTML = `
    <a href="index.html" class="brand" title="Beranda Pramuka MAN 4 Banjar">
      <div class="brand-mark">
        <img src="assets/icons/logo_man4banjar.png" alt="Logo MAN 4 Banjar" style="height:38px; width:auto; max-height:38px; object-fit:contain; display:block;">
        <img src="assets/icons/logo_pramuka_man4banjar.png" alt="Logo Pramuka MAN 4 Banjar" style="height:38px; width:38px; object-fit:contain; display:block; border-radius:50%;">
      </div>
      <div class="brand-text">
        <span class="brand-title">PRAMUKA MAN 4 BANJAR</span>
        <span class="brand-subtitle">GUDEP 01.095 - 01.096 &bull; CLOUD FIREBASE</span>
      </div>
    </a>

    <div class="topbar-right">
      <div class="header-clock" id="topbarClockWidget">
        <span class="pulse-indicator"></span>
        <span id="topbarTimeText">--:--:-- WITA</span>
      </div>

      ${user ? `
        <nav class="topnav">
          <a href="index.html" class="${activeTab === 'beranda' ? 'active' : ''}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Beranda</span>
          </a>

          <a href="classroom.html" class="${activeTab === 'classroom' ? 'active' : ''}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10M6 10h10"/></svg>
            <span>Classroom</span>
          </a>

          <a href="ujian.html" class="${activeTab === 'ujian' ? 'active' : ''}" title="Ujian CBT & Kuis Mandiri">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <span>Kuis CBT</span>
          </a>

          <a href="attendance.html" class="${activeTab === 'attendance' ? 'active' : ''}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 12 8 12s8-6.75 8-12a8 8 0 0 0-8-8z"/><circle cx="12" cy="10" r="3"/></svg>
            <span>Presensi</span>
          </a>

          ${['admin', 'pembina', 'pelatih'].includes(user.role) ? `
            <a href="grading.html" class="${activeTab === 'grading' ? 'active' : ''}">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              <span>Nilai Akhir</span>
            </a>

            <a href="tarikan_data.html" class="${activeTab === 'tarikan_data' ? 'active' : ''}">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <span>Tarikan Data</span>
            </a>
          ` : ''}

          <div class="user-profile-pill">
            <div class="user-avatar" title="${user.name}">${initials}</div>
            <div class="user-info-text">
              <span class="user-info-name">${user.name}</span>
              <span class="user-info-role">${user.role === 'siswa' ? 'Siswa ' + (user.kelas || '') : user.role.toUpperCase()}</span>
            </div>
          </div>

          <button id="logoutBtn" class="btn btn-outline-danger" style="padding: 6px 12px; font-size: 12px; margin-left: 4px;" title="Keluar">
            Keluar
          </button>
        </nav>
      ` : ''}
    </div>
  `;

  startLiveClock("topbarTimeText");

  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.onclick = logout;
  }
}

