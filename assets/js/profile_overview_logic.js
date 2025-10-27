document.addEventListener('DOMContentLoaded', function () {

    function initializeGage() {
        const imcGaugeElement = document.getElementById('imc-gauge');
        
        // Verifica se o elemento e a biblioteca JustGage estão prontos
        if (imcGaugeElement && typeof JustGage !== 'undefined') {
            
            const imcValue = parseFloat(imcGaugeElement.dataset.value) || 0;
            
            // Destrói qualquer instância anterior para evitar duplicatas
            if (imcGaugeElement.childNodes.length > 0) {
                imcGaugeElement.innerHTML = '';
            }
            
            new JustGage({
                id: "imc-gauge",
                value: imcValue.toFixed(2),
                min: 15,
                max: 40,
                title: " ",
                label: " ",
                symbol: '',
                pointer: true,
                pointerOptions: {
                    toplength: 8,
                    bottomlength: 8,
                    bottomwidth: 2,
                    color: '#E0E0E0'
                  },
                gaugeWidthScale: 0.6,
                customSectors: {
                    percents: false,
                    ranges: [{
                        color: "#3498db", // Azul - Abaixo
                        lo: 15, hi: 18.49
                    }, {
                        color: "#2ecc71", // Verde - Ideal
                        lo: 18.5, hi: 24.99
                    }, {
                        color: "#f1c40f", // Amarelo - Sobrepeso
                        lo: 25, hi: 29.99
                    }, {
                        color: "#e67e22", // Laranja - Obesidade I
                        lo: 30, hi: 34.99
                    },{
                        color: "#e74c3c", // Vermelho - Obesidade II+
                        lo: 35, hi: 40
                    }]
                },
                counter: true,
                relativeGaugeSize: true,
                donut: true,
                valueFontColor: "#E0E0E0",
                valueFontFamily: "Poppins, sans-serif",
                titleFontFamily: "Poppins, sans-serif",
                labelFontFamily: "Poppins, sans-serif"
            });
        } else {
             // Se JustGage ainda não estiver pronto, tenta de novo em um instante.
             // Isso resolve problemas de ordem de carregamento de scripts.
            setTimeout(initializeGage, 50);
        }
    }
    
    // Inicia a primeira tentativa de carregar o medidor.
    initializeGage();

});