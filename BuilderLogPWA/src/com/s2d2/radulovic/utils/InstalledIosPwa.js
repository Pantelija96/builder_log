// Installed iOS apps use the old PWA login flow; browser tabs keep token restoration.
export default function isInstalledIosPwa() {
  const { navigator } = window;
  // iPadOS can identify itself as a Mac when requesting desktop websites.
  const ios = /iPhone|iPad|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  return ios && (navigator.standalone === true
    || window.matchMedia('(display-mode: standalone)').matches);
}
