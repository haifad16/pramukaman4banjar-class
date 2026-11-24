// Konfigurasi Resmi Firebase Project: pramuka-man4banjar-class
import { initializeApp } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-app.js";
import { 
  getFirestore, 
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
} from "https://www.gstatic.com/firebasejs/11.0.1/firebase-firestore.js";

export const firebaseConfig = {
  apiKey: "AIzaSyAUrh-ji5gO7tqru0Zjd5ALQNs44r-Rpnk",
  authDomain: "pramuka-man4banjar-class.firebaseapp.com",
  projectId: "pramuka-man4banjar-class",
  storageBucket: "pramuka-man4banjar-class.firebasestorage.app",
  messagingSenderId: "341085579442",
  appId: "1:341085579442:web:0d19262d399582031801ab",
  measurementId: "G-QFTJ1WJTTE"
};

// Inisialisasi Firebase App & Firestore
export const app = initializeApp(firebaseConfig);
export const db = getFirestore(app);

export { 
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
};

