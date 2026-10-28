/**
 * CLIFFORD & JESSA — INTERACTIVE WEDDING INVITATION
 * Plain HTML, CSS, and JS implementation featuring:
 * 1. Interactive Photo Booth Dispenser with mechanical print animation & shutter audio
 * 2. Same-Document View Transitions (View Transitions API + focus routing)
 * 3. Live Wedding Movie Postcard Cinemagraph (60fps romantic golden-hour film canvas)
 * 4. Interactive Spinning Vinyl Record Player (Web Audio API acoustic wedding serenade + custom MP3)
 * 5. Botanical Color Palette Inspector (from reference photos) & Wax-Seal RSVP Modal
 */

(() => {
  'use strict';

  // --- DOM Elements ---
  const boothView = document.getElementById('boothView');
  const invitationView = document.getElementById('invitationView');
  const printButtonPlaque = document.getElementById('printButtonPlaque');
  const marriedHeader = document.getElementById('marriedHeader');
  const boothHeading = document.getElementById('boothHeading');
  const venueHeading = document.getElementById('venueHeading');
  const printerLed = document.getElementById('printerLed');
  const chuteEngraving = document.getElementById('chuteEngraving');
  const boothPhotoStrip = document.getElementById('boothPhotoStrip');
  const collagePhotoStrip = document.getElementById('collagePhotoStrip');
  const boothDateDisplay = document.getElementById('boothDateDisplay');
  const openInvitationBtn = document.getElementById('openInvitationBtn');
  const cameraFlash = document.getElementById('cameraFlash');

  // Navigation
  const replayBoothFooterBtn = document.getElementById('replayBoothFooterBtn');

  // Collage Interactive Elements
  const moviePostcard = document.getElementById('moviePostcard');
  const postcardCanvas = document.getElementById('postcardCinemaCanvas');
  const vinylRecordBtn = document.getElementById('vinylRecordBtn');
  const vinylDisc = document.getElementById('vinylDisc');
  const vinylDiscLarge = document.getElementById('vinylDiscLarge');
  const vinylNotes = document.getElementById('vinylNotes');
  const vinylArcText = document.getElementById('vinylArcText');
  const modalVinylArcText = document.getElementById('modalVinylArcText');
  const vinylStatusText = document.getElementById('vinylStatusText');
  const zoomVinylBtn = document.getElementById('zoomVinylBtn');

  // Modals
  const vinylModal = document.getElementById('vinylModal');
  const modalPlayToggleBtn = document.getElementById('modalPlayToggleBtn');
  const tonearm = document.getElementById('tonearm');
  const customAudioInput = document.getElementById('customAudioInput');
  const customAudioElement = document.getElementById('customAudioElement');
  const currentSongTitle = document.getElementById('currentSongTitle');

  const rsvpModal = document.getElementById('rsvpModal');
  const waxSealRsvpBtn = document.getElementById('waxSealRsvpBtn');
  const rsvpForm = document.getElementById('rsvpForm');
  const rsvpSavedBadge = document.getElementById('rsvpSavedBadge');

  // State
  let isPrinting = false;
  let hasPrinted = false;
  let isMusicPlaying = false;
  let useCustomAudio = false;
  let audioCtx = null;
  let melodyTimer = null;
  let customPhotoImages = [];

  // Load reference botanical photos to blend into the romantic frames & cinemagraph
  const loadedAssets = {};
  const assetSources = {
    arch: 'assets/ceremony-arch.png',
    bouquet: 'assets/bouquet-bride.png',
    tablescape: 'assets/tablescape.png',
    suite: 'assets/stationery-suite.png',
  };

  Object.entries(assetSources).forEach(([key, src]) => {
    const img = new Image();
    img.src = src;
    img.onload = () => {
      loadedAssets[key] = img;
      renderAllPhotoStripFrames();
    };
  });

  // =========================================================================
  // 1. RENDER ROMANTIC PHOTO BOOTH STRIP FRAMES (4 Poses + Custom Photo Support)
  // =========================================================================
  function drawRomanticCouplePose(ctx, width, height, poseIndex, customImg) {
    ctx.save();

    if (customImg) {
      // Draw user-uploaded photo with warm vintage terracotta/sepia film grading
      const imgRatio = customImg.width / customImg.height;
      const targetRatio = width / height;
      let sx = 0, sy = 0, sw = customImg.width, sh = customImg.height;
      if (imgRatio > targetRatio) {
        sw = customImg.height * targetRatio;
        sx = (customImg.width - sw) / 2;
      } else {
        sh = customImg.width / targetRatio;
        sy = (customImg.height - sh) / 2;
      }
      ctx.drawImage(customImg, sx, sy, sw, sh, 0, 0, width, height);
      ctx.fillStyle = 'rgba(176, 83, 52, 0.14)';
      ctx.fillRect(0, 0, width, height);
    } else {
      // Warm vintage editorial backdrop using our uploaded moodboard textures + romantic couple silhouettes
      const bgGrad = ctx.createLinearGradient(0, 0, width, height);
      bgGrad.addColorStop(0, '#DFCBB5');
      bgGrad.addColorStop(0.5, '#C89F7E');
      bgGrad.addColorStop(1, '#7D4E38');
      ctx.fillStyle = bgGrad;
      ctx.fillRect(0, 0, width, height);

      // Subtle background texture from cropped moodboard assets if loaded
      const assetKeys = ['arch', 'bouquet', 'tablescape', 'suite'];
      const bgAsset = loadedAssets[assetKeys[(poseIndex - 1) % assetKeys.length]];
      if (bgAsset) {
        ctx.save();
        ctx.globalAlpha = 0.42;
        ctx.filter = 'sepia(38%) contrast(108%) brightness(92%)';
        ctx.drawImage(bgAsset, 0, 0, width, height);
        ctx.restore();
      }

      // Golden hour sun glow
      const sunGrad = ctx.createRadialGradient(
        width * (0.35 + poseIndex * 0.08),
        height * 0.32,
        8,
        width * 0.5,
        height * 0.5,
        width * 0.75
      );
      sunGrad.addColorStop(0, 'rgba(255, 244, 224, 0.85)');
      sunGrad.addColorStop(0.45, 'rgba(216, 128, 70, 0.32)');
      sunGrad.addColorStop(1, 'rgba(42, 24, 16, 0.65)');
      ctx.fillStyle = sunGrad;
      ctx.fillRect(0, 0, width, height);

      // Draw intimate photo-booth couple portrait silhouettes in warm espresso & sepia tones
      ctx.fillStyle = '#2B1B14';
      const cx = width * 0.5;
      const cy = height * 0.58;

      // Pose variations (1: Forehead touch, 2: Cheek kiss, 3: Laughing together, 4: Ring & bouquet hold)
      const tiltGroom = poseIndex === 2 ? 0.14 : poseIndex === 3 ? -0.06 : 0.09;
      const tiltBride = poseIndex === 2 ? 0.04 : poseIndex === 3 ? 0.12 : -0.08;
      const offsetX = poseIndex === 2 ? 16 : 20;

      // Groom shoulders & head
      ctx.save();
      ctx.translate(cx - offsetX, cy);
      ctx.rotate(tiltGroom);
      ctx.beginPath();
      ctx.ellipse(0, 58, 46, 52, 0, Math.PI, 0);
      ctx.fill();
      ctx.beginPath();
      ctx.arc(4, -8, 25, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();

      // Bride shoulders, veil & head
      ctx.save();
      ctx.translate(cx + offsetX, cy + 6);
      ctx.rotate(tiltBride);
      // Soft veil highlight
      ctx.fillStyle = 'rgba(247, 241, 232, 0.55)';
      ctx.beginPath();
      ctx.moveTo(10, -24);
      ctx.quadraticCurveTo(54, 18, 48, 80);
      ctx.lineTo(-10, 80);
      ctx.closePath();
      ctx.fill();

      ctx.fillStyle = '#332018';
      ctx.beginPath();
      ctx.ellipse(0, 56, 40, 48, 0, Math.PI, 0);
      ctx.fill();
      ctx.beginPath();
      ctx.arc(-4, -8, 22.5, 0, Math.PI * 2);
      ctx.fill();
      // Hair bun
      ctx.beginPath();
      ctx.arc(15, -14, 11, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();

      // Terracotta & Cream Rose Bouquet accent at bottom of frame
      if (poseIndex === 1 || poseIndex === 4) {
        const flowers = [
          { x: cx, y: height - 18, r: 12, c: '#B05334' },
          { x: cx - 16, y: height - 14, r: 10, c: '#F2E4CE' },
          { x: cx + 15, y: height - 15, r: 11, c: '#D88046' },
          { x: cx + 5, y: height - 26, r: 9, c: '#F7F1E8' },
        ];
        flowers.forEach((f) => {
          ctx.fillStyle = f.c;
          ctx.beginPath();
          ctx.arc(f.x, f.y, f.r, 0, Math.PI * 2);
          ctx.fill();
        });
      }
    }

    // Classic Photo Booth Vignette & Warm Film Frame Border
    const vig = ctx.createRadialGradient(width / 2, height / 2, width * 0.25, width / 2, height / 2, width * 0.72);
    vig.addColorStop(0, 'transparent');
    vig.addColorStop(1, 'rgba(30, 16, 10, 0.52)');
    ctx.fillStyle = vig;
    ctx.fillRect(0, 0, width, height);

    // Subtle frame number stamp in corner
    ctx.fillStyle = 'rgba(242, 228, 206, 0.55)';
    ctx.font = '500 9px Montserrat, sans-serif';
    ctx.fillText(`0${poseIndex}`, 8, height - 8);

    ctx.restore();
  }

  function renderAllPhotoStripFrames() {
    const canvases = document.querySelectorAll('.couple-frame-canvas');
    canvases.forEach((canvas) => {
      const pose = parseInt(canvas.dataset.pose || '1', 10);
      const ctx = canvas.getContext('2d');
      const customImg = customPhotoImages.length > 0
        ? customPhotoImages[(pose - 1) % customPhotoImages.length]
        : null;
      drawRomanticCouplePose(ctx, canvas.width, canvas.height, pose, customImg);
    });
  }

  renderAllPhotoStripFrames();

  // =========================================================================
  // 2. SOUND EFFECTS & ROMANTIC WEDDING SONG SYNTHESIZER (Web Audio API)
  // =========================================================================
  function ensureAudioContext() {
    if (!audioCtx) {
      const AudioContextClass = window.AudioContext || window.webkitAudioContext;
      if (AudioContextClass) {
        audioCtx = new AudioContextClass();
      }
    }
    if (audioCtx && audioCtx.state === 'suspended') {
      audioCtx.resume();
    }
    return audioCtx;
  }

  // Camera Shutter + Photo Printer Mechanical Feed Sound
  function playPhotoBoothPrintSound() {
    const ctx = ensureAudioContext();
    if (!ctx) return;

    const now = ctx.currentTime;
    // 1. Crisp camera shutter click
    const clickOsc = ctx.createOscillator();
    const clickGain = ctx.createGain();
    clickOsc.type = 'triangle';
    clickOsc.frequency.setValueAtTime(920, now);
    clickOsc.frequency.exponentialRampToValueAtTime(140, now + 0.06);
    clickGain.gain.setValueAtTime(0.18, now);
    clickGain.gain.exponentialRampToValueAtTime(0.001, now + 0.065);
    clickOsc.connect(clickGain).connect(ctx.destination);
    clickOsc.start(now);
    clickOsc.stop(now + 0.07);

    // 2. 4 soft printer stepper motor pulses as the strip feeds down
    [0.18, 0.75, 1.35, 1.95].forEach((offset, idx) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(220 + idx * 25, now + offset);
      osc.frequency.linearRampToValueAtTime(185, now + offset + 0.28);
      gain.gain.setValueAtTime(0.04, now + offset);
      gain.gain.exponentialRampToValueAtTime(0.001, now + offset + 0.3);
      osc.connect(gain).connect(ctx.destination);
      osc.start(now + offset);
      osc.stop(now + offset + 0.32);
    });

    // 3. Warm completion chime when strip finishes printing
    const chimeNotes = [523.25, 659.25, 783.99, 1046.5]; // C5, E5, G5, C6
    chimeNotes.forEach((freq, i) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      const t = now + 2.45 + i * 0.09;
      osc.type = 'sine';
      osc.frequency.setValueAtTime(freq, t);
      gain.gain.setValueAtTime(0.07, t);
      gain.gain.exponentialRampToValueAtTime(0.0008, t + 0.9);
      osc.connect(gain).connect(ctx.destination);
      osc.start(t);
      osc.stop(t + 0.95);
    });
  }

  // Romantic Acoustic Piano & Warm Pad Serenade for the Vinyl Player
  const romanticProgression = [
    // Warm romantic arpeggiated chords (Fmaj7 -> G6 -> Em7 -> Am7 -> Dm7 -> G7 -> Cmaj9)
    { bass: 174.61, notes: [349.23, 440.0, 523.25, 659.25] },
    { bass: 196.0, notes: [392.0, 493.88, 587.33, 659.25] },
    { bass: 164.81, notes: [329.63, 392.0, 493.88, 587.33] },
    { bass: 220.0, notes: [329.63, 440.0, 523.25, 659.25] },
    { bass: 146.83, notes: [293.66, 440.0, 523.25, 698.46] },
    { bass: 196.0, notes: [293.66, 392.0, 493.88, 659.25] },
    { bass: 130.81, notes: [261.63, 329.63, 392.0, 587.33] },
    { bass: 130.81, notes: [329.63, 392.0, 493.88, 523.25] },
  ];
  let chordStep = 0;

  function playSynthChordStep() {
    if (!isMusicPlaying || useCustomAudio) return;
    const ctx = ensureAudioContext();
    if (!ctx) return;

    const chord = romanticProgression[chordStep % romanticProgression.length];
    chordStep++;
    const now = ctx.currentTime;

    // Warm cello/bass foundation note
    const bassOsc = ctx.createOscillator();
    const bassGain = ctx.createGain();
    bassOsc.type = 'sine';
    bassOsc.frequency.setValueAtTime(chord.bass, now);
    bassGain.gain.setValueAtTime(0.001, now);
    bassGain.gain.linearRampToValueAtTime(0.08, now + 0.15);
    bassGain.gain.exponentialRampToValueAtTime(0.0008, now + 2.3);
    bassOsc.connect(bassGain).connect(ctx.destination);
    bassOsc.start(now);
    bassOsc.stop(now + 2.35);

    // Delicate felted piano arpeggio
    chord.notes.forEach((freq, idx) => {
      const noteTime = now + idx * 0.42;
      const osc = ctx.createOscillator();
      const overtone = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.type = 'sine';
      overtone.type = 'triangle';
      osc.frequency.setValueAtTime(freq, noteTime);
      overtone.frequency.setValueAtTime(freq * 2, noteTime);

      gain.gain.setValueAtTime(0.001, noteTime);
      gain.gain.linearRampToValueAtTime(0.055, noteTime + 0.03);
      gain.gain.exponentialRampToValueAtTime(0.0008, noteTime + 1.55);

      const overtoneGain = ctx.createGain();
      overtoneGain.gain.value = 0.15;

      osc.connect(gain);
      overtone.connect(overtoneGain).connect(gain);
      gain.connect(ctx.destination);

      osc.start(noteTime);
      overtone.start(noteTime);
      osc.stop(noteTime + 1.6);
      overtone.stop(noteTime + 1.6);
    });
  }

  function syncVinylUI() {
    vinylDisc?.classList.toggle('is-spinning', isMusicPlaying);
    vinylDiscLarge?.classList.toggle('is-spinning', isMusicPlaying);
    vinylNotes?.classList.toggle('active', isMusicPlaying);
    tonearm?.classList.toggle('playing', isMusicPlaying);

    vinylRecordBtn?.setAttribute('aria-pressed', String(isMusicPlaying));

    const arcLabel = isMusicPlaying ? 'NOW PLAYING ♫' : 'TAP TO PLAY';
    if (vinylArcText) {
      const tp = vinylArcText.querySelector('textPath');
      if (tp) tp.textContent = arcLabel;
    }
    if (modalVinylArcText) {
      modalVinylArcText.textContent = arcLabel;
    }

    if (vinylStatusText) {
      vinylStatusText.textContent = isMusicPlaying ? 'Playing Our Song' : 'Tap Vinyl to Play Song';
    }
  }

  function toggleWeddingMusic() {
    isMusicPlaying = !isMusicPlaying;

    if (isMusicPlaying) {
      if (useCustomAudio && customAudioElement.src) {
        customAudioElement.play().catch(() => {});
      } else {
        playSynthChordStep();
        melodyTimer = window.setInterval(playSynthChordStep, 1850);
      }
    } else {
      if (melodyTimer) {
        clearInterval(melodyTimer);
        melodyTimer = null;
      }
      if (!customAudioElement.paused) {
        customAudioElement.pause();
      }
    }

    syncVinylUI();
  }

  // =========================================================================
  // 3. SCREEN 1: PHOTO BOOTH PRINT INTERACTION (00:00 - 00:06 in Video)
  // =========================================================================
  function triggerPhotoBoothPrint() {
    if (isPrinting) return;
    isPrinting = true;

    // 1. Tactile button press & camera shutter flash
    printButtonPlaque.classList.add('pressed');
    cameraFlash.classList.add('flash-active');
    setTimeout(() => cameraFlash.classList.remove('flash-active'), 140);

    playPhotoBoothPrintSound();

    // 2. Swap metallic plaque for "WE ARE GETTING MARRIED! Clifford & Jessa"
    setTimeout(() => {
      printButtonPlaque.classList.add('plaque-hidden');
      marriedHeader.classList.add('visible');
    }, 260);

    // 3. Start LED indicator & slide photo strip down out of top dispenser slot
    printerLed.classList.add('printing');
    boothDateDisplay.classList.add('date-active');

    boothPhotoStrip.classList.remove('printed-done', 'printing-active');
    // Force reflow so re-printing replays smoothly
    void boothPhotoStrip.offsetWidth;
    boothPhotoStrip.classList.add('printing-active');

    const finishPrinting = () => {
      if (!isPrinting) return;
      isPrinting = false;
      hasPrinted = true;
      printerLed.classList.remove('printing');
      boothPhotoStrip.classList.remove('printing-active');
      boothPhotoStrip.classList.add('printed-done');
      openInvitationBtn.classList.add('btn-visible');
    };

    // Listen to animationend using event.animation (per modern-web-guidance `motion`)
    const onAnimEnd = (event) => {
      const anim = 'animation' in AnimationEvent.prototype ? event.animation : null;
      if (!anim || anim.animationName === 'printPhotoStrip' || event.animationName === 'printPhotoStrip') {
        boothPhotoStrip.removeEventListener('animationend', onAnimEnd);
        finishPrinting();
      }
    };
    boothPhotoStrip.addEventListener('animationend', onAnimEnd);

    // Fallback timer for reduced-motion or immediate completion
    setTimeout(finishPrinting, 2850);
  }

  printButtonPlaque?.addEventListener('click', triggerPhotoBoothPrint);

  // =========================================================================
  // 4. VIEW TRANSITION: SCREEN 1 (BOOTH) <-> SCREEN 2 (INVITATION COLLAGE)
  // Follows `same-document-transitions` guide from modern-web-guidance
  // =========================================================================
  function openFullInvitation() {
    const applyDOMSwitch = () => {
      if (boothPhotoStrip) boothPhotoStrip.style.viewTransitionName = '';
      if (collagePhotoStrip) collagePhotoStrip.style.viewTransitionName = 'shared-photo-strip';
      if (boothView) {
        boothView.hidden = true;
        boothView.classList.remove('active');
      }
      if (invitationView) {
        invitationView.hidden = false;
        invitationView.classList.add('active');
      }
      window.scrollTo({ top: 0, behavior: 'instant' });
    };

    if (!document.startViewTransition) {
      applyDOMSwitch();
      venueHeading?.focus();
      return;
    }

    // Prepare shared view-transition-name between booth photo strip and collage photo strip
    if (boothPhotoStrip) boothPhotoStrip.style.viewTransitionName = 'shared-photo-strip';
    if (collagePhotoStrip) collagePhotoStrip.style.viewTransitionName = '';

    try {
      const transition = document.startViewTransition(() => {
        applyDOMSwitch();
      });

      transition.finished.finally(() => {
        // Clean up temporary view-transition-name and route focus for accessibility
        if (collagePhotoStrip) collagePhotoStrip.style.viewTransitionName = '';
        venueHeading?.focus({ preventScroll: true });
      });
    } catch {
      applyDOMSwitch();
      venueHeading?.focus({ preventScroll: true });
    }
  }

  function returnToPhotoBooth() {
    const applyDOMReturn = () => {
      if (collagePhotoStrip) collagePhotoStrip.style.viewTransitionName = '';
      if (boothPhotoStrip) boothPhotoStrip.style.viewTransitionName = 'shared-photo-strip';
      if (invitationView) {
        invitationView.hidden = true;
        invitationView.classList.remove('active');
      }
      if (boothView) {
        boothView.hidden = false;
        boothView.classList.add('active');
      }
      window.scrollTo({ top: 0, behavior: 'instant' });
    };

    if (!document.startViewTransition) {
      applyDOMReturn();
      boothHeading?.focus();
      return;
    }

    if (collagePhotoStrip) collagePhotoStrip.style.viewTransitionName = 'shared-photo-strip';
    if (boothPhotoStrip) boothPhotoStrip.style.viewTransitionName = '';

    try {
      const transition = document.startViewTransition(() => {
        applyDOMReturn();
      });

      transition.finished.finally(() => {
        if (boothPhotoStrip) boothPhotoStrip.style.viewTransitionName = '';
        boothHeading?.focus({ preventScroll: true });
      });
    } catch {
      applyDOMReturn();
      boothHeading?.focus({ preventScroll: true });
    }
  }

  openInvitationBtn?.addEventListener('click', openFullInvitation);
  replayBoothFooterBtn?.addEventListener('click', returnToPhotoBooth);

  // =========================================================================
  // 5. LIVE WEDDING MOVIE POSTCARD CINEMAGRAPH (00:18 "wedding movie postcard")
  // =========================================================================
  const cinemaDust = Array.from({ length: 28 }, () => ({
    x: Math.random(),
    y: Math.random(),
    r: 1 + Math.random() * 2.8,
    vx: (Math.random() - 0.5) * 0.0006,
    vy: -0.0004 - Math.random() * 0.0007,
    alpha: 0.25 + Math.random() * 0.55,
  }));

  function renderPostcardCinemagraph(timestamp) {
    if ( postcardCanvas && !invitationView.hidden ) {
      const ctx = postcardCanvas.getContext('2d');
      const w = postcardCanvas.width;
      const h = postcardCanvas.height;
      const t = timestamp * 0.001;

      // 1. Slow cinematic Ken Burns pan over our golden-hour ceremony arch / sunset backdrop
      const skyGrad = ctx.createLinearGradient(0, 0, w, h);
      skyGrad.addColorStop(0, '#4A2B1D');
      skyGrad.addColorStop(0.45, '#B05334');
      skyGrad.addColorStop(0.8, '#D88046');
      skyGrad.addColorStop(1, '#2D1810');
      ctx.fillStyle = skyGrad;
      ctx.fillRect(0, 0, w, h);

      if (loadedAssets.arch) {
        ctx.save();
        const zoom = 1.08 + Math.sin(t * 0.45) * 0.04;
        const panX = Math.cos(t * 0.35) * 14;
        ctx.translate(w / 2 + panX, h / 2);
        ctx.scale(zoom, zoom);
        ctx.globalAlpha = 0.52;
        ctx.filter = 'sepia(32%) contrast(115%) brightness(82%)';
        ctx.drawImage(loadedAssets.arch, -w / 2, -h / 2, w, h);
        ctx.restore();
      }

      // 2. Breathing golden-hour sunflare on the right
      const flareX = w * 0.72 + Math.sin(t * 0.7) * 18;
      const flareY = h * 0.36 + Math.cos(t * 0.5) * 10;
      const flareGrad = ctx.createRadialGradient(flareX, flareY, 10, flareX, flareY, w * 0.65);
      flareGrad.addColorStop(0, 'rgba(255, 245, 225, 0.78)');
      flareGrad.addColorStop(0.35, 'rgba(216, 128, 70, 0.38)');
      flareGrad.addColorStop(1, 'rgba(28, 14, 9, 0.55)');
      ctx.fillStyle = flareGrad;
      ctx.fillRect(0, 0, w, h);

      // 3. Romantic slow-swaying couple silhouette on the right half (matching 00:18 postcard)
      const sway = Math.sin(t * 1.1) * 4;
      const cx = w * 0.67 + sway;
      const cy = h * 0.56;

      ctx.save();
      ctx.fillStyle = 'rgba(32, 18, 12, 0.88)';
      // Groom silhouette
      ctx.beginPath();
      ctx.arc(cx - 22, cy - 42, 30, 0, Math.PI * 2);
      ctx.fill();
      ctx.beginPath();
      ctx.ellipse(cx - 28, cy + 48, 58, 78, 0.08, Math.PI, 0);
      ctx.fill();

      // Bride silhouette & flowing veil in the breeze
      ctx.fillStyle = 'rgba(242, 228, 206, 0.36)';
      ctx.beginPath();
      ctx.moveTo(cx + 34, cy - 50);
      ctx.quadraticCurveTo(cx + 110 + Math.sin(t * 2) * 12, cy + 10, cx + 95, cy + 120);
      ctx.lineTo(cx + 10, cy + 120);
      ctx.closePath();
      ctx.fill();

      ctx.fillStyle = 'rgba(42, 24, 16, 0.9)';
      ctx.beginPath();
      ctx.arc(cx + 22, cy - 32, 27, 0, Math.PI * 2);
      ctx.fill();
      ctx.beginPath();
      ctx.ellipse(cx + 24, cy + 54, 50, 72, -0.1, Math.PI, 0);
      ctx.fill();
      ctx.restore();

      // 4. Floating golden bokeh dust motes
      cinemaDust.forEach((p) => {
        p.x += p.vx;
        p.y += p.vy;
        if (p.y < -0.05) p.y = 1.05;
        if (p.x < -0.05) p.x = 1.05;
        if (p.x > 1.05) p.x = -0.05;

        ctx.fillStyle = `rgba(242, 228, 206, ${p.alpha * (0.6 + 0.4 * Math.sin(t * 2 + p.r))})`;
        ctx.beginPath();
        ctx.arc(p.x * w, p.y * h, p.r, 0, Math.PI * 2);
        ctx.fill();
      });
    }
    requestAnimationFrame(renderPostcardCinemagraph);
  }
  requestAnimationFrame(renderPostcardCinemagraph);

  moviePostcard?.addEventListener('click', () => {
    moviePostcard.classList.toggle('cinema-expanded');
  });
  moviePostcard?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      moviePostcard.classList.toggle('cinema-expanded');
    }
  });

  // =========================================================================
  // 6. VINYL TURNTABLE CONTROLS & CUSTOM MP3 UPLOAD
  // =========================================================================
  vinylRecordBtn?.addEventListener('click', toggleWeddingMusic);
  modalPlayToggleBtn?.addEventListener('click', toggleWeddingMusic);
  vinylDiscLarge?.addEventListener('click', toggleWeddingMusic);

  zoomVinylBtn?.addEventListener('click', () => {
    vinylModal?.showModal();
  });

  customAudioInput?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    customAudioElement.src = url;
    useCustomAudio = true;
    currentSongTitle.textContent = `“${file.name.replace(/\.[^/.]+$/, '')}”`;
    if (melodyTimer) {
      clearInterval(melodyTimer);
      melodyTimer = null;
    }
    isMusicPlaying = false;
    toggleWeddingMusic();
  });

  // =========================================================================
  // 7. LIVE WEDDING COUNTDOWN TIMER (Synced to Event Date: December 17, 2026)
  // Follows `calculate-event-differentials` guide from modern-web-guidance
  // =========================================================================
  const eventDateEl = document.getElementById('eventDateDisplay') || document.querySelector('.card-serif-emphasis');
  const eventDateStr = eventDateEl?.dataset.eventDate || '2026-12-17';
  const eventTimeStr = eventDateEl?.dataset.eventTime || '15:30:00';
  const eventTz = eventDateEl?.dataset.eventTz || 'Asia/Manila';

  const cdDays = document.getElementById('cdDays');
  const cdHours = document.getElementById('cdHours');
  const cdMinutes = document.getElementById('cdMinutes');
  const cdSeconds = document.getElementById('cdSeconds');

  function updateCountdown() {
    if (typeof Temporal !== 'undefined') {
      const now = Temporal.Now.zonedDateTimeISO(eventTz);
      // Combine separate date and time strings directly via Temporal methods (no template literal interpolation)
      const end = Temporal.PlainDate.from(eventDateStr)
        .toPlainDateTime(eventTimeStr)
        .toZonedDateTime(eventTz);

      const timeRemaining = now.until(end, { largestUnit: 'day', smallestUnit: 'second' });
      const isExpired = timeRemaining.sign < 0;

      const days = isExpired ? 0 : timeRemaining.days;
      const hours = isExpired ? 0 : timeRemaining.hours;
      const mins = isExpired ? 0 : timeRemaining.minutes;
      const secs = isExpired ? 0 : timeRemaining.seconds;

      if (cdDays) cdDays.textContent = String(days).padStart(2, '0');
      if (cdHours) cdHours.textContent = String(hours).padStart(2, '0');
      if (cdMinutes) cdMinutes.textContent = String(mins).padStart(2, '0');
      if (cdSeconds) cdSeconds.textContent = String(secs).padStart(2, '0');
    }
  }

  function startCountdownLoop() {
    updateCountdown();
    setInterval(updateCountdown, 1000);
  }

  // Check if Temporal is supported natively; conditionally load polyfill if needed
  (async () => {
    if (typeof Temporal === 'undefined') {
      try {
        const module = await import('https://esm.sh/@js-temporal/polyfill');
        globalThis.Temporal = module.Temporal;
        Date.prototype.toTemporalInstant = module.toTemporalInstant;
      } catch {
        // Offline environment without native Temporal
      }
    }
    startCountdownLoop();
  })();

  // =========================================================================
  // 8. INTERACTIVE BOTANICAL SWATCH INSPECTOR (From Photo 1)
  // =========================================================================
  const swatchButtons = document.querySelectorAll('.swatch-item');
  const inspectorDot = document.getElementById('inspectorDot');
  const inspectorTitle = document.getElementById('inspectorTitle');
  const inspectorDesc = document.getElementById('inspectorDesc');

  swatchButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      swatchButtons.forEach((b) => {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
      });
      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');

      const hex = btn.dataset.color;
      const name = btn.dataset.name;
      const desc = btn.dataset.desc;

      inspectorDot.style.background = hex;
      inspectorTitle.textContent = `${name} (${hex})`;
      inspectorDesc.textContent = desc;
    });
  });

  // =========================================================================
  // 9. WAX-SEAL RSVP MODAL & LIGHT-DISMISS FALLBACK
  // Follows `light-dismiss-a-dialog` guide from modern-web-guidance
  // =========================================================================
  waxSealRsvpBtn?.addEventListener('click', () => rsvpModal?.showModal());

  document.querySelectorAll('[data-close-dialog]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-close-dialog');
      document.getElementById(targetId)?.close();
    });
  });

  // Mandatory Fallback for browsers without <dialog closedby="any"> support
  if (!('closedBy' in HTMLDialogElement.prototype)) {
    document.querySelectorAll('dialog[closedby="any"]').forEach((dialog) => {
      dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        const isDialogContent =
          rect.top <= event.clientY &&
          event.clientY <= rect.top + rect.height &&
          rect.left <= event.clientX &&
          event.clientX <= rect.left + rect.width;
        if (isDialogContent) return;
        dialog.close();
      });
    });
  }

  rsvpForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(rsvpForm);
    const guestName = formData.get('guestName') || 'Honored Guest';
    const songReq = formData.get('songRequest');
    if (songReq && songReq.trim()) {
      currentSongTitle.textContent = `Requested by ${guestName}: “${songReq.trim()}”`;
    }
    rsvpSavedBadge.hidden = false;
    rsvpSavedBadge.textContent = `✓ Thank you, ${guestName}! Your wax-sealed RSVP has been recorded.`;
    rsvpModal.close();
  });

  // =========================================================================
  // 10. SCROLL-DRIVEN ANIMATIONS FALLBACK (IntersectionObserver)
  // Follows `scroll-entry-exit-effects` guide from modern-web-guidance
  // =========================================================================
  if (!CSS.supports('(animation-timeline: view()) and (animation-range: entry)')) {
    const revealEls = document.querySelectorAll('.scroll-reveal');
    revealEls.forEach((el) => el.classList.add('scroll-reveal-fallback'));

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.classList.add('in-view');
          }
        }
      },
      { threshold: 0.15 }
    );

    revealEls.forEach((el) => observer.observe(el));
  }

  // =========================================================================
  // 11. AMBIENT FLOATING DRIED BOTANICAL PETALS CANVAS
  // =========================================================================
  const petalCanvas = document.getElementById('petalCanvas');
  if (petalCanvas) {
    const pCtx = petalCanvas.getContext('2d');
    const resizePetals = () => {
      petalCanvas.width = window.innerWidth;
      petalCanvas.height = window.innerHeight;
    };
    resizePetals();
    window.addEventListener('resize', resizePetals);

    const petalColors = [
      'rgba(242, 228, 206, 0.45)', // Cream Daffodil
      'rgba(201, 140, 80, 0.24)',  // Amber Banyan
      'rgba(216, 128, 70, 0.22)',  // Sunset Vendela
      'rgba(176, 83, 52, 0.18)',   // Terracotta Anthurium
    ];

    const petals = Array.from({ length: 18 }, (_, i) => ({
      x: Math.random() * window.innerWidth,
      y: Math.random() * window.innerHeight,
      rx: 4 + Math.random() * 6,
      ry: 2 + Math.random() * 3.5,
      rot: Math.random() * Math.PI * 2,
      vRot: (Math.random() - 0.5) * 0.012,
      vx: (Math.random() - 0.4) * 0.35,
      vy: 0.25 + Math.random() * 0.45,
      color: petalColors[i % petalColors.length],
    }));

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function animatePetals() {
      if (!prefersReducedMotion.matches) {
        pCtx.clearRect(0, 0, petalCanvas.width, petalCanvas.height);
        petals.forEach((p) => {
          p.x += p.vx;
          p.y += p.vy;
          p.rot += p.vRot;
          if (p.y > petalCanvas.height + 20) {
            p.y = -20;
            p.x = Math.random() * petalCanvas.width;
          }
          if (p.x > petalCanvas.width + 20) p.x = -20;
          if (p.x < -20) p.x = petalCanvas.width + 20;

          pCtx.save();
          pCtx.translate(p.x, p.y);
          pCtx.rotate(p.rot);
          pCtx.fillStyle = p.color;
          pCtx.beginPath();
          pCtx.ellipse(0, 0, p.rx, p.ry, 0, 0, Math.PI * 2);
          pCtx.fill();
          pCtx.restore();
        });
      }
      requestAnimationFrame(animatePetals);
    }
    requestAnimationFrame(animatePetals);
  }
})();

