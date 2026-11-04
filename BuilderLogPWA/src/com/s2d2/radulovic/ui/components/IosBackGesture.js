// iOS can skip pushState entries created by a notification (no page activation).
// Handle the left-edge Back gesture while such a destination is open.
export function installIosBackGesture(target, getBackAction) {
  let gesture = null;
  function start(event) {
    gesture = null;
    if (!event.cancelable || event.touches.length !== 1) return;
    const touch = event.touches[0];
    if (touch.clientX < 0 || touch.clientX > 24) return;
    if (event.target.closest?.('input,textarea,select,button,a,[contenteditable]:not([contenteditable="false"])')) return;
    const back = getBackAction();
    if (!back) return;
    // Cancel on touchstart: waiting until touchmove lets Safari claim its edge swipe.
    event.preventDefault();
    gesture = { id: touch.identifier, x: touch.clientX, y: touch.clientY, back };
  }
  function move(event) {
    if (!gesture) return;
    if (event.touches.length !== 1) { gesture = null; return; }
    if (event.cancelable) event.preventDefault();
  }
  function end(event) {
    const previous = gesture;
    gesture = null;
    if (!previous) return;
    const touch = [...event.changedTouches].find(touch => touch.identifier === previous.id);
    if (!touch) return;
    const dx = touch.clientX - previous.x;
    const dy = Math.abs(touch.clientY - previous.y);
    if (dx < 60 || dx < dy * 1.5) return;
    if (event.cancelable) event.preventDefault();
    previous.back();
  }
  function cancel() { gesture = null; }
  const options = { capture: true, passive: false };
  target.addEventListener('touchstart', start, options);
  target.addEventListener('touchmove', move, options);
  target.addEventListener('touchend', end, options);
  target.addEventListener('touchcancel', cancel, options);
  return () => {
    cancel();
    target.removeEventListener('touchstart', start, options);
    target.removeEventListener('touchmove', move, options);
    target.removeEventListener('touchend', end, options);
    target.removeEventListener('touchcancel', cancel, options);
  };
}
