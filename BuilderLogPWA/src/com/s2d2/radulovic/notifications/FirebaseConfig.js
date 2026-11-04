// Public Firebase Web configuration, shared by the PWA and its future service worker.
// Importing this module does not initialize Firebase or enable notifications.
const firebaseConfig = Object.freeze({
  apiKey: 'AIzaSyDevSQp-UY2FaUwnHH-Ka2RI_hac-4fHVE',
  authDomain: 'builderlog-2ce47.firebaseapp.com',
  projectId: 'builderlog-2ce47',
  storageBucket: 'builderlog-2ce47.firebasestorage.app',
  messagingSenderId: '442522932049',
  appId: '1:442522932049:web:db4cedf5df844720bf3970',
  measurementId: 'G-4J4EQW3CC7',
});

export const VAPID_PUBLIC_KEY = 'BFHAz2MX2mxd5zGdXioG8R6UqnJPMXuGc0kwhivYikRVM-Q6og7428u95snjlnL-x8FQAiugCkRAVT2QQKmcKNM';

export default firebaseConfig;
