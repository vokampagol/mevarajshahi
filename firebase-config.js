window.MEVA_ADMIN_EMAIL = 'sapaharitsolution@gmail.com';
window.mevaFirebaseReady = false;

const firebaseConfig = {
    apiKey: 'AIzaSyBR4H-DTZL5Wn_vRApZkRaBD_cQ8E2TXCE',
    authDomain: 'sapahar-xpress.firebaseapp.com',
    projectId: 'sapahar-xpress',
    storageBucket: 'sapahar-xpress.firebasestorage.app',
    messagingSenderId: '226161011930',
    appId: '1:226161011930:web:bce5464a9e2e6784e749e3',
    measurementId: 'G-SB7VNREL4M'
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
