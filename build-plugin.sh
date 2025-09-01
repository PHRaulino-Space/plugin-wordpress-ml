#!/bin/bash

# Configurações
PLUGIN_NAME="mercadolivre-integration"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
RANDOM_ID=$(openssl rand -hex 4)
BUILD_NAME="${PLUGIN_NAME}_${TIMESTAMP}_${RANDOM_ID}.zip"

echo "🔨 Iniciando build do plugin..."
echo "📦 Arquivo de saída: $BUILD_NAME"

# Verificar se arquivos principais existem
if [ ! -f "mercadolivre-integration.php" ]; then
    echo "❌ ERRO: Arquivo principal mercadolivre-integration.php não encontrado!"
    exit 1
fi

if [ ! -d "assets" ]; then
    echo "⚠️  AVISO: Diretório assets não encontrado"
fi

# Remover builds antigos (manter apenas os 5 mais recentes)
echo "🧹 Limpando builds antigos..."
ls -t ${PLUGIN_NAME}_*.zip 2>/dev/null | tail -n +6 | xargs -r rm
if [ $? -eq 0 ]; then
    echo "✅ Builds antigos removidos"
fi

# Criar lista de arquivos para incluir
FILES_TO_ZIP=""

# Arquivo principal
FILES_TO_ZIP="$FILES_TO_ZIP mercadolivre-integration.php"
echo "✅ Adicionado: mercadolivre-integration.php"

# Assets (se existir)
if [ -d "assets" ]; then
    FILES_TO_ZIP="$FILES_TO_ZIP assets/"
    echo "✅ Adicionado: assets/ ($(ls -1 assets/ | wc -l) arquivos)"
fi

# Widgets Elementor (se existir)
if [ -f "elementor-widget.php" ]; then
    FILES_TO_ZIP="$FILES_TO_ZIP elementor-widget.php"
    echo "✅ Adicionado: elementor-widget.php"
fi

# Verificar arquivos de debug (não incluir no build final)
DEBUG_FILES=(
    "mercadolivre-debug.php"
    "mercadolivre-db-test.php" 
    "mercadolivre-safe.php"
    "mercadolivre-integration-minimal.php"
    "mercadolivre-integration-no-assets.php"
)

for debug_file in "${DEBUG_FILES[@]}"; do
    if [ -f "$debug_file" ]; then
        echo "⚠️  AVISO: Arquivo de debug encontrado: $debug_file (não incluído no build)"
    fi
done

# Criar zip
echo "📦 Criando arquivo zip..."
zip -r "$BUILD_NAME" $FILES_TO_ZIP

# Verificar se zip foi criado com sucesso
if [ $? -eq 0 ] && [ -f "$BUILD_NAME" ]; then
    FILE_SIZE=$(stat -f%z "$BUILD_NAME" 2>/dev/null || stat -c%s "$BUILD_NAME" 2>/dev/null)
    FILE_SIZE_KB=$((FILE_SIZE / 1024))
    
    echo ""
    echo "🎉 BUILD CONCLUÍDO COM SUCESSO!"
    echo "📁 Arquivo: $BUILD_NAME"
    echo "📏 Tamanho: ${FILE_SIZE_KB}KB"
    echo ""
    
    # Listar conteúdo do zip para verificação
    echo "📋 Conteúdo do arquivo:"
    unzip -l "$BUILD_NAME"
    echo ""
    
    # Criar link simbólico para o build mais recente (opcional)
    if [ -L "latest-build.zip" ] || [ -f "latest-build.zip" ]; then
        rm "latest-build.zip"
    fi
    ln -s "$BUILD_NAME" "latest-build.zip"
    echo "🔗 Link simbólico criado: latest-build.zip -> $BUILD_NAME"
    
    # Teste básico do zip
    echo ""
    echo "🧪 Testando integridade do arquivo..."
    if zip -T "$BUILD_NAME" > /dev/null 2>&1; then
        echo "✅ Arquivo zip íntegro"
    else
        echo "❌ ERRO: Arquivo zip corrompido!"
        exit 1
    fi
    
else
    echo "❌ ERRO: Falha ao criar o arquivo zip"
    exit 1
fi

echo ""
echo "✨ Build finalizado: $BUILD_NAME"