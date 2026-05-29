/**
 * Content Protection System
 * Protects website content, source code, and assets from casual theft.
 * Channel Partner: Digital8x
 */
(function () {
  'use strict';

  // 1. Inject Protection CSS dynamically to prevent text selection and image dragging
  const style = document.createElement('style');
  style.textContent = `
    /* Prevent text selection across the entire page */
    * {
      -webkit-user-select: none !important;
      -moz-user-select: none !important;
      -ms-user-select: none !important;
      user-select: none !important;
      -webkit-touch-callout: none !important; /* iOS Safari */
    }

    /* Allow text selection in form inputs and textareas so users can type details */
    input, textarea, [contenteditable="true"] {
      -webkit-user-select: text !important;
      -moz-user-select: text !important;
      -ms-user-select: text !important;
      user-select: text !important;
    }

    /* Prevent image dragging and pointer events to make downloads harder */
    img {
      -webkit-user-drag: none !important;
      user-drag: none !important;
      pointer-events: none !important;
    }
  `;
  document.head.appendChild(style);

  // 2. Disable Right-Click Context Menu
  document.addEventListener('contextmenu', function (e) {
    e.preventDefault();
  }, false);

  // 3. Disable Keyboard Shortcuts (DevTools, View Source, Saving)
  document.addEventListener('keydown', function (e) {
    // Check modifier keys depending on OS (Mac uses Meta key, others use Control)
    const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
    const modifier = isMac ? e.metaKey : e.ctrlKey;
    const shift = e.shiftKey;
    const alt = e.altKey;

    // F12 key (DevTools)
    if (e.key === 'F12' || e.keyCode === 123) {
      e.preventDefault();
      return false;
    }

    // Ctrl + Shift + I or Cmd + Option + I (Inspect Element / DevTools)
    if (modifier && (shift || alt) && (e.key === 'i' || e.key === 'I' || e.keyCode === 73)) {
      e.preventDefault();
      return false;
    }

    // Ctrl + Shift + J or Cmd + Option + J (Console / DevTools)
    if (modifier && (shift || alt) && (e.key === 'j' || e.key === 'J' || e.keyCode === 74)) {
      e.preventDefault();
      return false;
    }

    // Ctrl + Shift + C or Cmd + Option + C (Element Inspector)
    if (modifier && (shift || alt) && (e.key === 'c' || e.key === 'C' || e.keyCode === 67)) {
      e.preventDefault();
      return false;
    }

    // Ctrl + U or Cmd + Option + U (View Source)
    if (modifier && (e.key === 'u' || e.key === 'U' || e.keyCode === 85)) {
      e.preventDefault();
      return false;
    }

    // Ctrl + S or Cmd + S (Save Page)
    if (modifier && (e.key === 's' || e.key === 'S' || e.keyCode === 83)) {
      e.preventDefault();
      return false;
    }

    // Ctrl + C or Cmd + C (Copy)
    if (modifier && (e.key === 'c' || e.key === 'C' || e.keyCode === 67)) {
      e.preventDefault();
      return false;
    }
  }, false);

  // 4. Extra Layer: Block Copy Event
  document.addEventListener('copy', function (e) {
    e.preventDefault();
  }, false);

  // 5. Extra Layer: Block Cut Event
  document.addEventListener('cut', function (e) {
    e.preventDefault();
  }, false);

  // 6. Prevent drag and drop of elements
  document.addEventListener('dragstart', function (e) {
    if (e.target.nodeName === 'IMG') {
      e.preventDefault();
    }
  }, false);

})();
