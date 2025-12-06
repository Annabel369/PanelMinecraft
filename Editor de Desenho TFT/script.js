// === JAVASCRIPT CORRIGIDO COM PADRÃO DE COORDENADAS (offset 10) ===

document.addEventListener('DOMContentLoaded', () => {
    const creeperContainer = document.getElementById('creeper-container');
    const pixelGrid = document.getElementById('pixel-grid');
    const codeOutput = document.getElementById('codeOutput');
    const generateCodeBtn = document.getElementById('generateCodeBtn');
    const ajaxStatus = document.getElementById('ajaxStatus');

    // Configurações do Grid
    const GRID_SIZE = 12; // Grade visual (12x12)
    const CONTAINER_SIZE = 120; // Tamanho visual do container (120x120px)
    const PIXEL_DIMENSION = CONTAINER_SIZE / GRID_SIZE; // 10 pixels

    // O offset de 10 pixels é a primeira linha/coluna no grid (índice 1)
    const FIRST_OFFSET = 10; 

    // --- 1. Inicializa o Grid de Pixels ---
    function initializeGrid() {
        pixelGrid.innerHTML = '';
        for (let i = 0; i < GRID_SIZE * GRID_SIZE; i++) {
            const pixel = document.createElement('div');
            pixel.classList.add('pixel');
            pixel.dataset.index = i;
            pixelGrid.appendChild(pixel);
        }
        applyInitialDrawing(); // Aplica seu desenho específico
    }

    // Função para aplicar o desenho C++ fornecido (mapeado para a grade 12x12)
    function applyInitialDrawing() {
        const activeCoords = [
            // Linha Y=10
            {c:3, r:1}, {c:4, r:1}, {c:7, r:1}, {c:8, r:1}, 
            // Linha Y=20
            {c:2, r:2}, {c:3, r:2}, {c:4, r:2}, {c:7, r:2}, {c:8, r:2}, {c:9, r:2},
            // Linha Y=30
            {c:2, r:3}, {c:3, r:3}, {c:4, r:3}, {c:7, r:3}, {c:8, r:3}, {c:9, r:3},
            // Linha Y=50
            {c:5, r:5}, {c:6, r:5},
            // Linha Y=60
            {c:3, r:6}, {c:4, r:6}, {c:5, r:6}, {c:6, r:6}, {c:7, r:6}, {c:8, r:6},
            // Linha Y=70
            {c:3, r:7}, {c:4, r:7}, {c:5, r:7}, {c:6, r:7}, {c:7, r:7}, {c:8, r:7},
            // Linha Y=80
            {c:3, r:8}, {c:4, r:8}, {c:5, r:8}, {c:6, r:8}, {c:7, r:8}, {c:8, r:8},
            // Linha Y=90
            {c:3, r:9}, {c:4, r:9}, {c:7, r:9}, {c:8, r:9},
            // Linha Y=100
            {c:3, r:10}, {c:4, r:10}, {c:7, r:10}, {c:8, r:10}
            
            // Nota: Coluna 0 (x=0) e Coluna 11 (x=110) não são usadas no seu desenho
            // Linha 0 (y=0) e Linha 11 (y=110) não são usadas no seu desenho
        ];
        
        // Mapeamento: Colunas (c) e Linhas (r) para índice do grid (0 a 143)
        activeCoords.forEach(coord => {
            const index = coord.r * GRID_SIZE + coord.c;
            const pixel = pixelGrid.children[index];
            if (pixel) {
                pixel.classList.add('active');
            }
        });
    }

    // --- 2. Lógica de Desenho com o Mouse (Mantida a funcionalidade anterior) ---
    let isDrawing = false; 
    let drawMode = null; 

    creeperContainer.addEventListener('mousedown', (e) => {
        isDrawing = true;
        const pixel = getPixelFromEvent(e);
        if (pixel) {
            drawMode = pixel.classList.contains('active') ? 'remove' : 'add';
            togglePixel(pixel);
        }
        e.preventDefault();
    });

    document.addEventListener('mouseup', () => {
        isDrawing = false;
        drawMode = null;
    });

    creeperContainer.addEventListener('mousemove', (e) => {
        if (!isDrawing || drawMode === null) return;
        const pixel = getPixelFromEvent(e);
        if (pixel) {
            togglePixel(pixel);
        }
    });
    
    function getPixelFromEvent(e) {
        const rect = creeperContainer.getBoundingClientRect();
        const offsetX = e.clientX - rect.left;
        const offsetY = e.clientY - rect.top;

        const col = Math.floor(offsetX / PIXEL_DIMENSION);
        const row = Math.floor(offsetY / PIXEL_DIMENSION);

        const index = row * GRID_SIZE + col;
        return pixelGrid.children[index];
    }

    function togglePixel(pixel) {
        if (drawMode === 'add') {
            pixel.classList.add('active');
        } else if (drawMode === 'remove') {
            pixel.classList.remove('active');
        }
        generateTFTCode(); 
    }


    // --- 3. Geração do Código TFT (CORRIGIDO PARA O SEU PADRÃO) ---

    function generateTFTCode() {
        let code =
`void drawCustomCreeper(int x, int y, int tam) {
    // Calcula o tamanho do pixel. tam / 12 = 10 neste caso.
    int pixelSize = tam / ${GRID_SIZE}; 
    
    // Desenha o fundo verde (Base)
    tft.fillRect(x, y, tam, tam, TFT_GREEN); 
    
    // Pixels Pretos (Desenho personalizado)
`;
        
        const activePixels = Array.from(pixelGrid.children).filter(p => p.classList.contains('active'));
        
        activePixels.forEach(pixel => {
            const index = parseInt(pixel.dataset.index);
            const row = Math.floor(index / GRID_SIZE); // Linha (0 a 11)
            const col = index % GRID_SIZE; // Coluna (0 a 11)

            // O offset X/Y é (Índice da Coluna/Linha) * 10 pixels.
            // Ex: Coluna 3 -> 3 * 10 = 30.
            const offsetX = col * PIXEL_DIMENSION; 
            const offsetY = row * PIXEL_DIMENSION;

            // O código gerado usa os offsets calculados (30, 10, etc.) e a variável pixelSize
            code += `    tft.fillRect(x + ${offsetX}, y + ${offsetY}, pixelSize, pixelSize, TFT_BLACK);\n`;
        });

        code += `}\n`;
        
        codeOutput.textContent = code;
        return code;
    }


    // --- 4. Simulação AJAX (Mantida) ---
    function simulateAjax(code) {
        ajaxStatus.style.backgroundColor = '#ffc107'; 
        ajaxStatus.style.color = '#333';
        ajaxStatus.textContent = "💻 Enviando código para o servidor (Simulação AJAX)...";

        setTimeout(() => {
            ajaxStatus.style.backgroundColor = '#28a745'; 
            ajaxStatus.style.color = '#fff';
            ajaxStatus.textContent = "✅ Código TFT enviado com sucesso! (Simulação AJAX concluída)";
        }, 1500);
    }

    // --- 5. Evento Principal ---
    generateCodeBtn.addEventListener('click', () => {
        const generatedCode = generateTFTCode();
        simulateAjax(generatedCode);
    });

    // Inicia a aplicação
    initializeGrid();
    generateTFTCode(); 
});