(function () {
  'use strict';

  const BANNER_DURATION_MS = 5000;
  let deferredInstallPrompt = null;

  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    deferredInstallPrompt = event;
  });

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(error => {
        console.warn('Groupe Dangote app installation is unavailable:', error);
      });
    });
  }

  function isAlreadyInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches
      || window.navigator.standalone === true;
  }

  function translate(text) {
    return window.GDLanguage?.translate(text) || text;
  }

  function getInstallInstructions() {
    const userAgent = window.navigator.userAgent || '';
    const isAppleMobile = /iphone|ipad|ipod/i.test(userAgent)
      || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);

    if (isAppleMobile) {
      return 'Sur iPhone ou iPad : ouvrez Partager, puis choisissez « Sur l’écran d’accueil ».';
    }
    if (/android/i.test(userAgent)) {
      return 'Sur Android : ouvrez le menu ⋮, puis choisissez « Installer l’application » ou « Ajouter à l’écran d’accueil ».';
    }
    return 'Ouvrez le menu de votre navigateur, puis choisissez « Installer l’application ».';
  }

  document.addEventListener('DOMContentLoaded', () => {
    const banner = document.querySelector('[data-pwa-install-banner]');
    if (!banner || isAlreadyInstalled()) return;

    const installButton = banner.querySelector('[data-pwa-install-button]');
    const closeButton = banner.querySelector('[data-pwa-install-close]');
    const helpDialog = document.querySelector('[data-pwa-install-help]');
    const helpCloseButton = helpDialog?.querySelector('[data-pwa-install-help-close]');
    const helpText = helpDialog?.querySelector('[data-pwa-install-steps]');
    let hideTimer;

    function closeHelp() {
      if (!helpDialog) return;
      helpDialog.hidden = true;
      if (!banner.hidden) installButton?.focus();
    }

    function hideBanner() {
      window.clearTimeout(hideTimer);
      banner.hidden = true;
    }

    function showHelp() {
      if (!helpDialog || !helpText) return;
      helpText.textContent = translate(getInstallInstructions());
      helpDialog.hidden = false;
      helpCloseButton?.focus();
    }

    if (installButton) {
      installButton.addEventListener('click', async () => {
        if (!deferredInstallPrompt) {
          hideBanner();
          showHelp();
          return;
        }

        const installPrompt = deferredInstallPrompt;
        deferredInstallPrompt = null;
        try {
          await installPrompt.prompt();
          await installPrompt.userChoice;
          hideBanner();
        } catch (error) {
          console.warn('Could not open the app installation prompt:', error);
          hideBanner();
          showHelp();
        }
      });
    }

    closeButton?.addEventListener('click', hideBanner);
    helpCloseButton?.addEventListener('click', closeHelp);
    helpDialog?.addEventListener('click', event => {
      if (event.target === helpDialog) closeHelp();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && helpDialog && !helpDialog.hidden) closeHelp();
    });
    window.addEventListener('appinstalled', hideBanner, { once: true });
    window.addEventListener('gd-language-change', () => {
      if (helpText && helpDialog && !helpDialog.hidden) {
        helpText.textContent = translate(getInstallInstructions());
      }
    });

    banner.hidden = false;
    hideTimer = window.setTimeout(hideBanner, BANNER_DURATION_MS);
  });
})();