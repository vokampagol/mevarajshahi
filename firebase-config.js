window.MEVA_ADMIN_EMAIL = 'sapaharitsolution@gmail.com';
window.mevaFirebaseReady = false;

const firebaseConfig = {
    apiKey: 'AIzaSyC2VzAmh3wxYlkQdZYcyrPN5TlyI66GvdU',
    authDomain: 'meva-rajshahi.firebaseapp.com',
    projectId: 'meva-rajshahi',
    storageBucket: 'meva-rajshahi.firebasestorage.app',
    messagingSenderId: '82469055752',
    appId: '1:82469055752:web:bd7933d2a3a1e6092f90bd',
    measurementId: 'G-12LPZ9QNE8'
};

const hasFirebaseConfig = Object.values(firebaseConfig).every(
    (value) => value && !value.startsWith('PASTE_')
);

if (hasFirebaseConfig) {
    firebase.initializeApp(firebaseConfig);
    window.mevaFirebaseReady = true;
} else {
    console.error('Firebase is not configured. Add the Web app config in firebase-config.js.');
}
