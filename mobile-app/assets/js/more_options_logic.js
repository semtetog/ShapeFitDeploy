document.addEventListener('DOMContentLoaded', function () {
    
    // Ativa o efeito de inclinação 3D nos cards da grade.
    // A biblioteca VanillaTilt.js já deve ter sido carregada antes deste script.
    const tiltCards = document.querySelectorAll('.option-card');
    
    if (typeof VanillaTilt !== 'undefined' && tiltCards.length > 0) {
        VanillaTilt.init(tiltCards, {
            max: 8,         // Inclinação máxima em graus (mais sutil)
            speed: 600,     // Velocidade da transição
            perspective: 1500, // Perspectiva mais distante para um efeito mais suave
            glare: true,    // Ativa o efeito de brilho que segue o mouse
            "max-glare": 0.15 // Intensidade do brilho (0 a 1)
        });
    } else if (tiltCards.length > 0) {
        console.error("VanillaTilt.js não foi encontrado. Certifique-se de que ele está sendo carregado antes deste script.");
    }
});