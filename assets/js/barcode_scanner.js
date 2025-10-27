// Arquivo: assets/js/barcode_scanner.js
// Lógica isolada para o scanner de código de barras Scandit.

// Importa as funções e classes necessárias da biblioteca Scandit
import { configure, DataCaptureContext, Camera, FrameSourceState, DataCaptureView } from "@scandit/web-datacapture-core";
import { BarcodeCapture, BarcodeCaptureSettings, Symbology, barcodeCaptureLoader } from "@scandit/web-datacapture-barcode";

document.addEventListener('DOMContentLoaded', function() {
    console.log("barcode_scanner.js: Script e Módulos Scandit iniciados.");

    // Seleciona apenas os elementos necessários para o scanner
    const scanBarcodeBtn = document.getElementById('scan-barcode-btn');
    if (!scanBarcodeBtn) {
        console.log("barcode_scanner.js: Botão de scanner não encontrado nesta página. Encerrando script do scanner.");
        return; 
    }

    const scannerModal = document.getElementById('barcode-scanner-modal');
    const closeScannerModalBtn = document.getElementById('close-scanner-modal-btn');
    const scanditPickerContainer = document.getElementById('scanner-container');
    const scannerStatus = document.getElementById('scanner-status');
    const resultConfirmationDiv = document.getElementById('scanner-result-confirmation');
    const scannedProductInfoDiv = document.getElementById('scanned-product-info');
    const saveScannedFoodForm = document.getElementById('save-scanned-food-form');
    const saveScannedFoodBtn = document.getElementById('save-scanned-food-btn');
    const rescanBtn = document.getElementById('rescan-barcode-btn');
    const baseAppUrlElement = document.getElementById('base_app_url_for_js');
    const baseAppUrl = baseAppUrlElement ? baseAppUrlElement.value : '';

    let dataCaptureContext;
    let camera;
    let barcodeCapture;
    let view;

    async function initializeScanner() {
        scannerModal.style.display = 'flex';
        scannerStatus.style.display = 'block';
        scannerStatus.textContent = 'Configurando scanner...';
        scanditPickerContainer.style.display = 'block';
        resultConfirmationDiv.style.display = 'none';
        rescanBtn.style.display = 'none';

        try {
            await configure({
                licenseKey: "An72iQOeJQErLLulFOoFCoQtpKRwQ7COOC045DAnsMjqfeRTNFhH551hyGDBb14BeEtQh3dM6g4OW7SmT1CcK7tvMRFBSSl+v3G+yGs4eWArNxvTIxSTbvs56vl+bZwO5wVF7AVj2XIAS/nOtVuCkQlVGdlqeu8ZEmuNuGNKccDCDUSSLGrTbrFMpTOYRDl3LlZxEF1fNOxUaO5nqXuQoYVbxK04Y2ufv3HmwIAQ4qcyYkj1jmMXRZJiuIBLW+AC0Hjy9bhJQAyjYrjVEE2n7JhDt4uvb+c4dmQ8GQhDdqF9eY7OGWqgdGQQf1GpUmbh1WTEfmVhc+NuRVJHI32WJwBIZlZsKopC33NPvlFEpvrXczzf2VGhbtdmHe2ifHyeGnpLaqJTz/JxTdTZC3S1yM9a16v7UaYHU03OdE9cRFbrWXx0wnI0/2hv3GW4TLq0UXFHnzd9zN3PA82TTHCxR94SSuEQN60xg2Um5MtFvcC9W1BJxl9OrJYqeqBpWuN9qEHj8h528aQgeZd2YUU8fNQUm+9m68P0jZaA7qIEEBgBU/DGQ/s71/k+JlwWeKz1yQetDorq26qLNX9+KATRGUPLg+lwDoeRMiardoSwkrNnm/Zm093oKG/Rgfe8e36k3RXzOah+wz+MVyVPtns+j++A8Ld4i+HKvKmgFa3KC6QzcgQ5P6ibPcFKSZvfAKmO+V0AJ9bQZk6gf5S9E9uadM+BeyWNG0emu1bEvVS2p3HP7c5VLnrCgFS0B3RoqgezGt1Gnwsc+KrWvfN0B0vywNTNuE3UdIqLnyflYWC2N6DTUp3k5i/L+MCUzyBrLDziIcmo0kGpVbcV3eoO+Nw5NnXycZ4Jq2QgOQd9Ha0N5hKRl4ZH+EQGK6ZN8f400bufMUXi5cDHgEgipURBh8AgL6IK39JgPOtlOZNx3ZEGflIWR9lZIy3QrNiWTq0GKNrLQBx0Ny9K9SxURJuWVX3glcPO/6pLceGGiZ7pofSEUMuPIdVBH6pW7kQn9f3nA+x4YW6a6uW+5o8kOqxmKY2gEsxpqvrxVqC8m1CB4XtTupwZQiGfkdWXgiZT33uO0sM7vPQbEXplJltNyc5ERaTxSYlQsK1NIVxbxl4+RGpyLhWJYkihTUE6m/L9dQqQ1Cgv90UAf791sHNig/cLZySbFHoniFr6+fi7uyZsIdXwjjACg7aq2rxToURPbazzizjaxnQ=",
                libraryLocation: "https://cdn.jsdelivr.net/npm/@scandit/web-datacapture-barcode@7.3.0/build/",
                moduleLoaders: [barcodeCaptureLoader()],
            });

            dataCaptureContext = await DataCaptureContext.create();
            view = new DataCaptureView();
            view.connectToElement(scanditPickerContainer);
            await view.setContext(dataCaptureContext);
            
            const settings = new BarcodeCaptureSettings();
            settings.enableSymbologies([Symbology.EAN13UPCA, Symbology.EAN8, Symbology.Code128, Symbology.QR]);
            barcodeCapture = await BarcodeCapture.forContext(dataCaptureContext, settings);
            
            barcodeCapture.addListener({
                didScan: (barcodeCaptureMode, session) => {
                    barcodeCaptureMode.setEnabled(false);
                    const barcode = session.newlyRecognizedBarcodes[0];
                    handleScan(barcode);
                }
            });

            camera = Camera.default;
            if (camera) {
                await dataCaptureContext.setFrameSource(camera);
                await camera.switchToDesiredState(FrameSourceState.On);
            }
            await barcodeCapture.setEnabled(true);
            
            scannerStatus.textContent = "Aponte para o código de barras";

        } catch (error) {
            console.error("Erro na inicialização da Scandit:", error);
            scannerStatus.textContent = 'Erro ao iniciar o scanner. Verifique as permissões.';
        }
    }

    function handleScan(barcode) {
        scannerStatus.textContent = `Código [${barcode.data}] lido! Buscando...`;
        scanditPickerContainer.style.display = 'none';
        
        fetch(`${baseAppUrl}/ajax_lookup_barcode.php?barcode=${barcode.data}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    scannerStatus.style.display = 'none';
                    displayProductForConfirmation(data.data);
                } else {
                    scannerStatus.innerHTML = `Falha: ${data.message || 'Produto não encontrado.'}<br>Tente novamente.`;
                    rescanBtn.style.display = 'inline-block';
                }
            });
    }

    function displayProductForConfirmation(product) {
        scannedProductInfoDiv.innerHTML = `<p><strong>Nome:</strong> ${product.name}</p><p><strong>Marca:</strong> ${product.brand || 'N/A'}</p><p><strong>Calorias (100g):</strong> ${product.kcal_100g || 'N/A'}</p>`;
        saveScannedFoodForm.innerHTML = `<input type="hidden" name="barcode" value="${product.id}"><input type="hidden" name="name_pt" value="${product.name}"><input type="hidden" name="brand" value="${product.brand || ''}"><input type="hidden" name="energy_kcal_100g" value="${product.kcal_100g || ''}"><input type="hidden" name="protein_g_100g" value="${product.protein_100g || ''}"><input type="hidden" name="carbohydrate_g_100g" value="${product.carbs_100g || ''}"><input type="hidden" name="fat_g_100g" value="${product.fat_100g || ''}">`;
        resultConfirmationDiv.style.display = 'block';
    }
    
    function closeAndDestroyScanner() {
        if (barcodeCapture) barcodeCapture.setEnabled(false);
        if (camera) camera.switchToDesiredState(FrameSourceState.Off);
        scannerModal.style.display = 'none';
    }

    // Listeners
    scanBarcodeBtn.addEventListener('click', initializeScanner);
    closeScannerModalBtn.addEventListener('click', closeAndDestroyScanner);
    rescanBtn.addEventListener('click', () => {
        scanditPickerContainer.style.display = 'block';
        resultConfirmationDiv.style.display = 'none';
        rescanBtn.style.display = 'none';
        scannerStatus.textContent = "Aponte para o código de barras";
        if (barcodeCapture) barcodeCapture.setEnabled(true);
        if (camera) camera.switchToDesiredState(FrameSourceState.On);
    });
    saveScannedFoodBtn.addEventListener('click', () => {
        const formData = new FormData(saveScannedFoodForm);
        saveScannedFoodBtn.disabled = true;
        saveScannedFoodBtn.textContent = 'Salvando...';
        fetch(`${baseAppUrl}/process_save_custom_food.php`, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Alimento adicionado com sucesso!');
                closeAndDestroyScanner();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => alert('Erro de comunicação ao salvar.'))
        .finally(() => {
            saveScannedFoodBtn.disabled = false;
            saveScannedFoodBtn.textContent = 'Sim, Adicionar';
        });
    });
});