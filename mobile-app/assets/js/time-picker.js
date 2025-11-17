/**
 * TimePicker - Componente nativo para mobile
 * Usa input type="time" para abrir seletor nativo do sistema
 */
if (typeof TimePicker === 'undefined') {
class TimePicker {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            value: '00:00',
            placeholder: 'Selecionar horário',
            ...options
        };
        
        this.value = this.options.value;
        this.init();
    }
    
    init() {
        this.createHTML();
        this.bindEvents();
        this.setValue(this.value);
    }
    
    createHTML() {
        this.container.innerHTML = `
            <div class="time-picker-wrapper">
                <input type="time" class="time-input-native" value="${this.value}">
            </div>
        `;
        
        this.input = this.container.querySelector('.time-input-native');
    }
    
    bindEvents() {
        this.input.addEventListener('change', (e) => {
            this.value = e.target.value;
            this.onChange && this.onChange(this.value);
        });
        
        this.input.addEventListener('input', (e) => {
            this.value = e.target.value;
            this.onInput && this.onInput(this.value);
        });
    }
    
    setValue(value) {
        if (value) {
            this.value = value;
            this.input.value = value;
        }
    }
    
    getValue() {
        return this.input.value;
    }
    
    setOnChange(callback) {
        this.onChange = callback;
    }
    
    setOnInput(callback) {
        this.onInput = callback;
    }
    
    focus() {
        this.input.focus();
    }
    
    blur() {
        this.input.blur();
    }
    
    disable() {
        this.input.disabled = true;
    }
    
    enable() {
        this.input.disabled = false;
    }
}

// CSS para o time picker nativo
const timePickerCSS = `
<style>
.time-picker-wrapper {
    width: 100%;
    position: relative;
}

.time-input-native {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary, #ffffff);
    font-size: 16px;
    font-weight: 500;
    transition: all 0.2s ease;
    outline: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.time-input-native:focus {
    border-color: var(--accent-orange, #f59e0b);
    background: rgba(255, 255, 255, 0.1);
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
}

.time-input-native:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Estilo para iOS */
@supports (-webkit-touch-callout: none) {
    .time-input-native {
        font-size: 16px; /* Previne zoom no iOS */
    }
}

/* Estilo para Android */
@media screen and (-webkit-min-device-pixel-ratio: 0) {
    .time-input-native {
        font-size: 16px;
    }
}
`;

// Adicionar CSS ao head se não existir
if (!document.querySelector('#time-picker-css')) {
    const style = document.createElement('style');
    style.id = 'time-picker-css';
    style.textContent = timePickerCSS.replace('<style>', '').replace('</style>', '');
    document.head.appendChild(style);
}
} // Fechar o if do TimePicker