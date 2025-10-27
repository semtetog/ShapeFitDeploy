// banner-carousel.js (VERSÃO FINAL, ESTÁVEL E COM LOOP SIMPLES)

function initLottieCarousel() {
  console.log('[Banner Carousel] Inicializando com loop estável...');
  
  if (typeof lottie === 'undefined') {
    console.error('[Banner Carousel] Biblioteca lottie-web não foi carregada!');
    return;
  }
  
  const carousel = document.querySelector('.main-carousel');
  if (!carousel) {
    console.error('[Banner Carousel] Container .main-carousel não encontrado!');
    return;
  }
  
  const track = carousel.querySelector('.carousel-track');
  const slides = Array.from(carousel.querySelectorAll('.lottie-slide'));
  const paginationContainer = carousel.querySelector('.pagination-container');
  
  if (!track) {
    console.error('[Banner Carousel] Trilho (.carousel-track) não encontrado!');
    return;
  }
  
  if (slides.length <= 1) {
    if (slides.length === 1) {
        const container = slides[0].querySelector('.lottie-animation-container');
        if (container) {
          lottie.loadAnimation({ 
            container, 
            renderer: 'svg', 
            loop: true, 
            autoplay: true, 
            path: '/banner_receitas.json' 
          });
        }
    }
    console.log('[Banner Carousel] Apenas 1 slide ou menos. Carrossel desabilitado.');
    return;
  }

  let currentIndex = 0;
  let carouselInterval = null;
  const DURATION = 7000;
  const loadedAnimations = [];
  const animationPaths = [
    '/banner_receitas.json', 
    '/banner2.json', 
    '/banner3.json', 
    '/banner4.json'
  ];
  const slidesCount = slides.length;

  // =========================================================================
  //         FUNÇÕES DE CONTROLE (SIMPLIFICADAS E ROBUSTAS)
  // =========================================================================

  function goToSlide(index, withAnimation = true) {
    // CÉREBRO (JS): Calcula o índice correto usando o operador de módulo para criar o loop.
    // Este operador garante que o índice sempre esteja entre 0 e 3, criando o efeito de loop.
    currentIndex = ((index % slidesCount) + slidesCount) % slidesCount;

    if (!withAnimation) {
      track.classList.add('no-transition');
    }

    const slideWidth = slides[0].offsetWidth;
    // CÉREBRO (JS): Calcula a posição final e atualiza o estilo.
    track.style.transform = `translateX(-${currentIndex * slideWidth}px)`;
    
    // MÚSCULO (CSS): A propriedade 'transition' no seu CSS fará a animação suavemente até este ponto,
    // usando a GPU para garantir 60fps e fluidez máxima.
    
    if (!withAnimation) {
        track.offsetHeight; // Força reflow para aplicar a mudança
        track.classList.remove('no-transition');
    }

    // Controla animações Lottie
    loadedAnimations.forEach((anim, i) => {
        if (anim) {
          if (i === currentIndex) {
            anim.play();
          } else {
            anim.stop();
          }
        }
    });

    updatePagination();
    restartCarouselTimer();
  }
  
  function nextSlide() {
    // Timer automático: sempre faz loop
    goToSlide(currentIndex + 1); 
  }

  function prevSlide() {
    // Timer automático: sempre faz loop
    goToSlide(currentIndex - 1); 
  }

  function updatePagination() {
      progressFills.forEach((fill, i) => {
          fill.style.transition = 'none';
          fill.style.width = '0%';
          if (i === currentIndex) {
              requestAnimationFrame(() => {
                  fill.style.transition = `width ${DURATION}ms linear`;
                  fill.style.width = '100%';
              });
          }
      });
  }
  
  // =========================================================================
  //         SISTEMA DE SWIPE (LÓGICA ROBUSTA E SIMPLES)
  // =========================================================================
  let isDragging = false;
  let startX = 0;
  let startTranslate = 0;
  let currentTranslate = 0;

  function getPositionX(e) {
    return e.type.includes('mouse') ? e.pageX : e.touches[0].clientX;
  }

  function handleStart(e) {
    isDragging = true;
    startX = getPositionX(e);
    stopCarouselTimer();
    
    // CÉREBRO (JS): Diz ao CSS para desativar a animação durante o arraste.
    carousel.classList.add('is-dragging');
    
    // NOVO: Bloqueia o scroll da página durante o toque no carrossel
    document.body.style.overflow = 'hidden';
    document.body.style.touchAction = 'none';
    
    // Captura a posição atual do trilho
    const transformMatrix = new WebKitCSSMatrix(window.getComputedStyle(track).transform);
    startTranslate = transformMatrix.m41;
    currentTranslate = startTranslate;
  }

  function handleMove(e) {
    if (!isDragging) return;
    
    // NOVO: Previne o scroll da página durante o arraste no mobile
    e.preventDefault();
    
    const currentX = getPositionX(e);
    const diffX = currentX - startX;
    let newTranslate = startTranslate + diffX;
    
    // LIMITE: Impede arrastar além dos limites
    const slideWidth = slides[0].offsetWidth;
    const minTranslate = -(slidesCount - 1) * slideWidth; // Último slide
    const maxTranslate = 0; // Primeiro slide
    
    // Se está no primeiro slide (index 0) e tentando arrastar para direita
    if (currentIndex === 0 && newTranslate > maxTranslate) {
      newTranslate = maxTranslate;
    }
    
    // Se está no último slide (index 3) e tentando arrastar para esquerda
    if (currentIndex === slidesCount - 1 && newTranslate < minTranslate) {
      newTranslate = minTranslate;
    }
    
    currentTranslate = newTranslate;
    
    // CÉREBRO (JS): Atualiza a posição em tempo real enquanto o dedo se move.
    track.style.transform = `translateX(${currentTranslate}px)`;
  }

  function handleEnd() {
    if (!isDragging) return;
    isDragging = false;
    
    // CÉREBRO (JS): Diz ao CSS para reativar a animação.
    carousel.classList.remove('is-dragging');
    
    // NOVO: Reativa o scroll da página quando soltar o toque
    document.body.style.overflow = '';
    document.body.style.touchAction = '';

    const movedBy = currentTranslate - startTranslate;
    const threshold = slides[0].offsetWidth * 0.2; // 20% de arraste

    // A decisão de ir para o próximo/anterior é simples
    if (movedBy < -threshold) {
        nextSlide();
    } else if (movedBy > threshold) {
        prevSlide();
    } else {
        // Se não arrastou o suficiente, volta para o slide atual
        goToSlide(currentIndex);
    }
  }

  // =========================================================================
  //         TIMERS E GERENCIAMENTO
  // =========================================================================
  function startCarouselTimer() {
    stopCarouselTimer();
    // Timer automático sempre faz loop infinito
    carouselInterval = setInterval(nextSlide, DURATION);
  }
  
  function stopCarouselTimer() { 
    clearInterval(carouselInterval); 
  }
  
  function restartCarouselTimer() { 
    startCarouselTimer(); 
  }

  // =========================================================================
  //         CONFIGURAÇÃO INICIAL
  // =========================================================================
  const progressFills = [];
  slides.forEach(() => {
    const item = document.createElement('div');
    item.className = 'pagination-item';
    const fill = document.createElement('div');
    fill.className = 'pagination-fill';
    item.appendChild(fill);
    paginationContainer.appendChild(item);
    progressFills.push(fill);
  });
  
  // Event Listeners
  carousel.addEventListener('mousedown', handleStart);
  carousel.addEventListener('mousemove', handleMove);
  carousel.addEventListener('mouseup', handleEnd);
  carousel.addEventListener('mouseleave', handleEnd);
  
  // NOVO: { passive: false } permite preventDefault() para bloquear scroll
  carousel.addEventListener('touchstart', handleStart, { passive: false });
  carousel.addEventListener('touchmove', handleMove, { passive: false });
  carousel.addEventListener('touchend', handleEnd, { passive: false });
  
  // Ajusta a posição ao redimensionar a janela
  window.addEventListener('resize', () => goToSlide(currentIndex, false));
  
  // Click handler para navegação (adiciona ao carousel diretamente)
  carousel.addEventListener('click', (e) => {
    // Previne clique se o usuário acabou de arrastar
    const movedBy = Math.abs(currentTranslate - startTranslate);
    if (isDragging || movedBy > 10) return;

    const link = slides[currentIndex].dataset.link;
    if (link && link !== '#') {
      window.location.href = link;
    }
  });

  // =========================================================================
  //         CARREGAR ANIMAÇÕES E INICIAR
  // =========================================================================
  
  // Inicia o carrossel imediatamente (não espera carregar)
  goToSlide(0, false);
  
  // Carrega as animações em paralelo
  slides.forEach((slide, index) => {
    const container = slide.querySelector('.lottie-animation-container');
    if (!container) {
      console.warn(`[Banner Carousel] Container não encontrado no slide ${index}`);
      return;
    }
    
    const anim = lottie.loadAnimation({
        container, 
        renderer: 'svg', 
        loop: true, 
        autoplay: (index === 0), // Primeiro slide começa automaticamente
        path: animationPaths[index]
    });
    
    anim.addEventListener('DOMLoaded', () => {
        console.log(`[Banner Carousel] Animação ${index} carregada.`);
        loadedAnimations[index] = anim;
        
        // Se é o primeiro slide e ainda está visível, garante que está tocando
        if (index === 0 && currentIndex === 0) {
          anim.play();
        }
    });
    
    anim.addEventListener('data_failed', () => {
      console.error(`[Banner Carousel] Falha ao carregar animação ${index}`);
    });
  });
}

// Aguarda o window.load para garantir que todos os scripts carregaram
window.addEventListener('load', () => {
  console.log('[Banner Carousel] Window load event - iniciando...');
  if (typeof lottie !== 'undefined') {
    initLottieCarousel();
  } else {
    console.error('[Banner Carousel] Lottie.js não foi encontrado.');
  }
});